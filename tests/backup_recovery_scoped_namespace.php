<?php

use Copot\Core\BackupRecovery\DatabaseCaptureContext;
use Copot\Core\BackupRecovery\DatabaseRestoreAttemptContext;
use Copot\Core\BackupRecovery\DatabaseRestoreContext;
use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\BackupRecovery\MySqlRecoveryProvider;
use Copot\Core\BackupRecovery\ScopedDatabaseConsistencyEvidence;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\DatabaseTableOwnershipCatalog;
use Copot\Core\CoreMigrationStateIdentity;
use Copot\Core\BackupRecovery\MySqlDatabaseArtifactCodec;
use Copot\Core\Env;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';
Env::load($base . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) { throw new RuntimeException($message); }
};

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', 3306);
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$database = 'copot_scoped_' . bin2hex(random_bytes(6));
$quoted = '`' . $database . '`';
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);

$legacyCodec = new MySqlDatabaseArtifactCodec();
$legacyBundle = [
    'format_version' => 1,
    'domain_identifier' => 'database.webcore',
    'database_identity' => 'legacy_fixture',
    'provider' => ['product' => 'fixture', 'version' => '1', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'sql_mode' => ''],
    'schema' => ['tables' => []],
    'data' => ['tables' => []],
    'migration_ledger' => [],
    'identities' => ['schema' => hash('sha256', '{"tables":[]}'), 'data' => hash('sha256', '{"tables":[]}'), 'migration_ledger' => CoreMigrationStateIdentity::fromRecords([])],
];
$assert($legacyCodec->decode($legacyCodec->encode($legacyBundle))['format_version'] === 1, 'Legacy v1 database artifact compatibility was lost.');

try {
    $server->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $make = static fn (): PDO => new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $db = $make();
    $catalog = DatabaseTableOwnershipCatalog::current();
    $alpha = DatabaseScope::fromOwnershipCatalog('installation-alpha', $database, 'alpha', $catalog);
    $beta = DatabaseScope::fromOwnershipCatalog('installation-beta', $database, 'beta', $catalog);
    $assert($alpha->identity() !== $beta->identity(), 'Different installations must have distinct scope identities.');
    $assert($alpha->tablesetFingerprint() !== $beta->tablesetFingerprint(), 'Different namespaces must have distinct tableset fingerprints.');
    $alpha->assertPhysicalTables(array_merge($alpha->physicalTables(), $beta->physicalTables()));
    try { $alpha->assertPhysicalTables(array_merge($alpha->physicalTables(), ['alpha_unknown'])); $assert(false, 'Unknown target namespace table was accepted.'); } catch (Throwable) { $assert(true, 'Unknown target namespace table failed closed.'); }

    foreach ([$alpha, $beta] as $scope) {
        foreach ($scope->physicalTables() as $table) {
            if (str_ends_with($table, '_core_migration_history')) {
                $db->exec("CREATE TABLE `{$table}` (migration_id VARCHAR(191) NOT NULL PRIMARY KEY, sequence_number INT UNSIGNED NOT NULL UNIQUE, target_webcore_version VARCHAR(64) NOT NULL, target_schema_identity VARCHAR(191) NOT NULL, migration_checksum CHAR(64) NOT NULL, applied_at DATETIME NOT NULL) ENGINE=InnoDB");
            } else {
                $db->exec("CREATE TABLE `{$table}` (id INT UNSIGNED NOT NULL AUTO_INCREMENT, label VARCHAR(64) NOT NULL, PRIMARY KEY (id)) ENGINE=InnoDB");
            }
        }
    }
    $db->exec("INSERT INTO `alpha_users` (label) VALUES ('alpha-before')");
    $db->exec("INSERT INTO `beta_users` (label) VALUES ('beta-stable')");
    $operationA = new RecoveryIdentity('scoped-recovery-alpha');
    $operationB = new RecoveryIdentity('scoped-recovery-beta');
    $now = time();
    $evidenceA = new ScopedDatabaseConsistencyEvidence($alpha, $operationA, hash('sha256', 'alpha-writer-drain'), hash('sha256', 'alpha-schema-stable'), $now, $now + 300);
    $evidenceB = new ScopedDatabaseConsistencyEvidence($beta, $operationB, hash('sha256', 'beta-writer-drain'), hash('sha256', 'beta-schema-stable'), $now, $now + 300);
    $provider = new MySqlRecoveryProvider();
    $capture = static function (DatabaseScope $scope, ScopedDatabaseConsistencyEvidence $evidence) use ($provider, $make, $database): \Copot\Core\BackupRecovery\DatabaseRecoveryArtifact {
        return $provider->capture(new DatabaseCaptureContext($make(), $make(), $database, 67108864, $scope, $evidence));
    };
    $artifactA = $capture($alpha, $evidenceA);
    $artifactB = $capture($beta, $evidenceB);
    $assert($artifactA->scope()?->identity() === $alpha->identity(), 'Alpha artifact did not retain scoped evidence.');
    $assert($artifactB->scope()?->identity() === $beta->identity(), 'Beta artifact did not retain scoped evidence.');
    $assert($provider->verifyCaptured($artifactA)->isValid() && $provider->verifyCaptured($artifactB)->isValid(), 'Scoped artifacts did not self-verify.');

    $db->exec("UPDATE `alpha_users` SET label='alpha-mutated' WHERE id=1");
    $betaBefore = (string) $db->query("SELECT label FROM `beta_users` WHERE id=1")->fetchColumn();
    $expectedState = $provider->stateIdentity($db, $database, $alpha);
    $expectedTables = $provider->tableSetIdentity($db, $database, $alpha);
    $attempt = new DatabaseRestoreAttemptContext(new RecoveryIdentity('scoped-recovery-test'), 'alpha-attempt', $expectedState, $expectedTables, DatabaseRestoreAttemptContext::PREPARED, $artifactA->scope()?->physicalTables() ?? [], static function (string $stage): void {});
    $provider->restore($artifactA, new DatabaseRestoreContext($make(), $make(), $database, $attempt, $alpha, $evidenceA));
    $assert((string) $db->query("SELECT label FROM `alpha_users` WHERE id=1")->fetchColumn() === 'alpha-before', 'Scoped restore did not restore installation A.');
    $assert((string) $db->query("SELECT label FROM `beta_users` WHERE id=1")->fetchColumn() === $betaBefore, 'Scoped restore changed installation B.');
    $assert($provider->verifyRestored($artifactA, new DatabaseRestoreContext($make(), $make(), $database, null, $alpha, $evidenceA))->isValid(), 'Scoped restored artifact did not verify.');
    echo "Scoped Backup Recovery assertions: {$assertions}" . PHP_EOL;
} finally {
    try { $server->exec("DROP DATABASE IF EXISTS {$quoted}"); } catch (Throwable) {}
}
