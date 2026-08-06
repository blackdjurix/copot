<?php

use Copot\Core\BackupRecovery\DatabaseCaptureContext;
use Copot\Core\BackupRecovery\DatabaseRestoreContext;
use Copot\Core\BackupRecovery\DatabaseRestoreAttemptContext;
use Copot\Core\BackupRecovery\MySqlRecoveryProvider;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\RecoveryDomainIdentity;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryManifest;
use Copot\Core\BackupRecovery\RecoveryRootResolver;
use Copot\Core\BackupRecovery\RecoveryStorageRoot;
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
$name = 'copot_wu4_' . bin2hex(random_bytes(6));
$quoted = '`' . $name . '`';
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu4-storage-' . bin2hex(random_bytes(6));

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path) || is_link($path)) { return; }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') { continue; }
        $child = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($child) && !is_link($child)) { $removeTree($child); } else { @unlink($child); }
    }
    @rmdir($path);
};

try {
    $server->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $make = static fn (): PDO => new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $db = $make();
    $db->exec('CREATE TABLE parent (id INT UNSIGNED NOT NULL AUTO_INCREMENT, label VARCHAR(64) NOT NULL, amount DECIMAL(20,6) NOT NULL, payload BLOB NULL, note TEXT NULL, created_at DATETIME(6) NOT NULL, PRIMARY KEY (id), UNIQUE KEY uq_parent_label (label), KEY ix_parent_amount (amount)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $db->exec('CREATE TABLE child (parent_id INT UNSIGNED NOT NULL, child_id INT UNSIGNED NOT NULL, value_text VARCHAR(120) NULL, PRIMARY KEY (parent_id, child_id), CONSTRAINT fk_child_parent FOREIGN KEY (parent_id) REFERENCES parent(id) ON DELETE CASCADE ON UPDATE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $db->exec('CREATE TABLE core_migration_history (migration_id VARCHAR(191) NOT NULL PRIMARY KEY, sequence_number INT UNSIGNED NOT NULL UNIQUE, target_webcore_version VARCHAR(64) NOT NULL, target_schema_identity VARCHAR(191) NOT NULL, migration_checksum CHAR(64) NOT NULL, applied_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $db->exec("INSERT INTO parent(label,amount,payload,note,created_at) VALUES ('α', '12345678901234.123456', X'0001FF', NULL, '2026-08-06 12:34:56.123456'), ('empty', '0.000001', X'', '', '2026-08-06 12:34:57.000000')");
    $db->exec("INSERT INTO child(parent_id,child_id,value_text) VALUES (1,7,'child-value')");
    $db->exec("INSERT INTO core_migration_history VALUES ('m-wu4-fixture',1,'0.10.0','schema-fixture',REPEAT('a',64),'2026-08-06 12:35:00')");

    $provider = new MySqlRecoveryProvider();
    $artifact = $provider->capture(new DatabaseCaptureContext($make(), $make(), $name));
    $assert($provider->verifyCaptured($artifact)->isValid(), 'Captured database artifact must self-verify.');
    $assert($artifact->record()->domainIdentifier() === 'database.webcore', 'Database artifact must use the physical database domain.');

    mkdir($root, 0700, true);
    $storage = new RecoveryArtifactStore(new RecoveryStorageRoot($root, $root, hash('sha256', RecoveryRootResolver::identityPath(realpath($root)))));
    $recoveryIdentity = new RecoveryIdentity('wu4-fixture-' . bin2hex(random_bytes(4)));
    $domain = new RecoveryDomainIdentity('database.webcore', 'database.webcore', hash('sha256', $name), $artifact->record()->artifactIdentity());
    $manifest = new RecoveryManifest($recoveryIdentity, 'wu4-operation', 'package-fixture', '0.10.0', str_repeat('b', 64), str_repeat('c', 64), [$domain], 'lifecycle-fixture', $artifact->migrationLedgerIdentity());
    $storage->publish($manifest, [['record' => $artifact->record(), 'bytes' => $artifact->bytes()]]);
    $storedBytes = $storage->readArtifact($recoveryIdentity, $artifact->record());
    $assert($storedBytes === $artifact->bytes(), 'WU2 artifact readback must preserve exact database bytes.');

    $db->exec('SET FOREIGN_KEY_CHECKS=0');
    $db->exec('DROP TABLE child');
    $db->exec('SET FOREIGN_KEY_CHECKS=1');
    try { $provider->restoreFromStore($recoveryIdentity, $artifact->record(), $storage, new DatabaseRestoreContext($make(), $make(), $name)); $assert(false, 'Missing target table must be rejected.'); } catch (Throwable) { $assert(true, 'Missing target table rejected.'); }
    $db->exec('CREATE TABLE child (parent_id INT UNSIGNED NOT NULL, child_id INT UNSIGNED NOT NULL, value_text VARCHAR(120) NULL, PRIMARY KEY (parent_id, child_id), CONSTRAINT fk_child_parent FOREIGN KEY (parent_id) REFERENCES parent(id) ON DELETE CASCADE ON UPDATE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $db->exec('DELETE FROM child'); $db->exec('DELETE FROM parent'); $db->exec('DELETE FROM core_migration_history');
    $db->exec('ALTER TABLE parent MODIFY label VARCHAR(80) NOT NULL');
    $db->exec('ALTER TABLE parent AUTO_INCREMENT=99');
    $expectedStateIdentity = $provider->stateIdentity($db, $name);
    $expectedTableSetIdentity = $provider->tableSetIdentity($db, $name);
    $stages = [];
    $attempt = new DatabaseRestoreAttemptContext($recoveryIdentity, 'attempt-1', $expectedStateIdentity, $expectedTableSetIdentity, DatabaseRestoreAttemptContext::PREPARED, ['child', 'core_migration_history', 'parent'], static function (string $stage) use (&$stages): void { $stages[] = $stage; });
    $restoreContext = static fn (): DatabaseRestoreContext => new DatabaseRestoreContext($make(), $make(), $name, $attempt);
    $lockCheck = $restoreContext();
    $provider->restoreFromStore($recoveryIdentity, $artifact->record(), $storage, $lockCheck);
    $lockProbe = $make();
    $lockProbe->exec('SET SESSION lock_wait_timeout=1');
    $lockProbe->exec('LOCK TABLES parent WRITE');
    $lockProbe->exec('UNLOCK TABLES');
    $provider->restoreFromStore($recoveryIdentity, $artifact->record(), $storage, $restoreContext());
    $assert(in_array(DatabaseRestoreAttemptContext::DROPPING, $stages, true) && in_array(DatabaseRestoreAttemptContext::COMPLETED, $stages, true), 'Restore did not expose durable destructive stages.');
    $provider->restoreFromStore($recoveryIdentity, $artifact->record(), $storage, $restoreContext());
    $verified = $provider->verifyRestored($artifact, new DatabaseRestoreContext($make(), $make(), $name));
    $assert($verified->isValid(), 'Restored database must semantically match the captured artifact.');
    $assert((string) $db->query("SELECT label FROM parent WHERE id=1")->fetchColumn() === 'α', 'UTF-8 row data must round-trip.');
    $assert((string) $db->query('SELECT payload FROM parent WHERE id=1')->fetchColumn() === "\x00\x01\xFF", 'Binary row data must round-trip.');
    $assert((string) $db->query('SELECT amount FROM parent WHERE id=1')->fetchColumn() === '12345678901234.123456', 'Decimal precision must round-trip.');
    $db->exec("INSERT INTO parent(label,amount,created_at) VALUES ('next', '1.000000', '2026-08-06 12:36:00.000000')");
    $assert((int) $db->lastInsertId() === 3, 'Material auto-increment state must round-trip.');
    $assert((int) $db->query('SELECT COUNT(*) FROM core_migration_history')->fetchColumn() === 1, 'Migration ledger must be restored exactly once.');
    $db->exec("UPDATE parent SET label='unexpected-drift' WHERE id=1");
    $drift = $provider->verifyRestored($artifact, new DatabaseRestoreContext($make(), $make(), $name));
    $assert(!$drift->isValid(), 'Post-restore data drift must be detected.');
    try { $provider->restoreFromStore($recoveryIdentity, $artifact->record(), $storage, $restoreContext()); $assert(false, 'Unexpected database drift was accepted for restore.'); } catch (Throwable) { $assert(true, 'Unexpected database drift rejected for restore.'); }

    $resetExpectedTarget = static function () use ($db): void {
        $db->exec('SET FOREIGN_KEY_CHECKS=0');
        $db->exec('DELETE FROM child');
        $db->exec('DELETE FROM parent');
        $db->exec('DELETE FROM core_migration_history');
        $db->exec('ALTER TABLE parent MODIFY label VARCHAR(80) NOT NULL');
        $db->exec('ALTER TABLE parent AUTO_INCREMENT=99');
        $db->exec('SET FOREIGN_KEY_CHECKS=1');
    };
    foreach ([
        DatabaseRestoreAttemptContext::DROPPING,
        DatabaseRestoreAttemptContext::CREATING,
        DatabaseRestoreAttemptContext::LOADING,
        DatabaseRestoreAttemptContext::RESTORING_METADATA,
        DatabaseRestoreAttemptContext::VERIFYING,
    ] as $faultStage) {
        $resetExpectedTarget();
        $faultState = false;
        $expectedState = $provider->stateIdentity($db, $name);
        $expectedTables = $provider->tableSetIdentity($db, $name);
        $faultAttemptId = 'fault-' . strtolower($faultStage);
        $faultAttempt = new DatabaseRestoreAttemptContext($recoveryIdentity, $faultAttemptId, $expectedState, $expectedTables, DatabaseRestoreAttemptContext::PREPARED, ['child', 'core_migration_history', 'parent'], static function (string $stage) use (&$faultState, $faultStage): void {
            if (!$faultState && $stage === $faultStage) { $faultState = true; throw new RuntimeException('Injected restore-stage interruption.'); }
        });
        $faultContext = new DatabaseRestoreContext($make(), $make(), $name, $faultAttempt);
        try { $provider->restoreFromStore($recoveryIdentity, $artifact->record(), $storage, $faultContext); $assert(false, 'Injected failure at ' . $faultStage . ' was accepted.'); } catch (Throwable) { $assert(true, 'Injected failure at ' . $faultStage . ' was surfaced.'); }
        if ($faultStage !== DatabaseRestoreAttemptContext::DROPPING) {
            try { $provider->restoreFromStore($recoveryIdentity, $artifact->record(), $storage, new DatabaseRestoreContext($make(), $make(), $name)); $assert(false, 'Partial restore without attempt context was accepted.'); } catch (Throwable) { $assert(true, 'Partial restore without attempt context rejected.'); }
        }
        $badAttempt = new DatabaseRestoreAttemptContext(new RecoveryIdentity('wrong-recovery'), 'wrong-attempt', $expectedState, $expectedTables, $faultStage, ['child', 'core_migration_history', 'parent'], static function (string $stage): void {});
        try { $provider->restoreFromStore($recoveryIdentity, $artifact->record(), $storage, new DatabaseRestoreContext($make(), $make(), $name, $badAttempt)); $assert(false, 'Mismatched restore lineage was accepted.'); } catch (Throwable) { $assert(true, 'Mismatched restore lineage rejected.'); }
        $retryAttempt = new DatabaseRestoreAttemptContext($recoveryIdentity, $faultAttemptId, $expectedState, $expectedTables, $faultStage, ['child', 'core_migration_history', 'parent'], static function (string $stage): void {});
        $provider->restoreFromStore($recoveryIdentity, $artifact->record(), $storage, new DatabaseRestoreContext($make(), $make(), $name, $retryAttempt));
        $assert($provider->verifyRestored($artifact, new DatabaseRestoreContext($make(), $make(), $name))->isValid(), 'Retry after ' . $faultStage . ' did not restore the artifact.');
    }

    $tampered = $storedBytes;
    $tampered = substr($tampered, 0, -1) . ($tampered[-1] === '}' ? ']' : '}');
    $assert($tampered !== $storedBytes, 'Tamper fixture must differ.');
    try { (new \Copot\Core\BackupRecovery\MySqlDatabaseArtifactCodec())->decode($tampered); $assert(false, 'Tampered artifact must be rejected.'); } catch (Throwable) { $assert(true, 'Tampered artifact rejected.'); }

    $lock = $make(); $writer = $make();
    $lock->exec('FLUSH TABLES WITH READ LOCK');
    $writer->exec('SET SESSION innodb_lock_wait_timeout=1'); $writer->exec('SET SESSION lock_wait_timeout=1');
    try { $writer->exec("CREATE TABLE blocked_ddl (id INT PRIMARY KEY) ENGINE=InnoDB"); $assert(false, 'External DDL must be blocked by the provider lock primitive.'); } catch (Throwable) { $assert(true, 'External DDL blocked by the provider lock primitive.'); }
    try { $writer->exec("INSERT INTO parent(label,amount,created_at) VALUES ('blocked', '1.000000', NOW())"); $assert(false, 'External writes must be blocked by the provider lock primitive.'); } catch (Throwable) { $assert(true, 'External write blocked by the provider lock primitive.'); }
    $lock->exec('UNLOCK TABLES');
    $db->exec('CREATE TABLE unsupported_non_transactional (id INT PRIMARY KEY) ENGINE=MyISAM');
    try { $provider->capture(new DatabaseCaptureContext($make(), $make(), $name)); $assert(false, 'Non-transactional tables must be rejected.'); } catch (Throwable) { $assert(true, 'Non-transactional table rejected.'); }
    $db->exec('DROP TABLE unsupported_non_transactional');

    echo "Backup Recovery WU4 assertions: {$assertions}" . PHP_EOL;
} finally {
    try { if (isset($lock)) { @$lock->exec('UNLOCK TABLES'); } } catch (Throwable) {}
    try { $server->exec("DROP DATABASE IF EXISTS {$quoted}"); } catch (Throwable) {}
    $removeTree($root);
}
