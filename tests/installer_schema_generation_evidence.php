<?php

declare(strict_types=1);

use Copot\Core\CommittedLifecycleStateStore;
use Copot\Core\CanonicalSchemaBaselineVerifier;
use Copot\Core\Config;
use Copot\Core\CoreMigrationRegistry;
use Copot\Core\CoreSchemaGenerationStore;
use Copot\Core\CoreSchemaMaterializer;
use Copot\Core\DatabaseTableNames;
use Copot\Core\InstallationIdentityStore;
use Copot\Core\InstallationMutex;
use Copot\Core\InstallationState;
use Copot\Core\InstallerDatabaseProbe;
use Copot\Core\InstallerEnvironmentWriter;
use Copot\Core\InstallerInstallationCommitter;
use Copot\Core\InstallerOwnershipProofAssembler;
use Copot\Core\InstallerSchemaRunner;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

final class InstallerForeignObjectSchemaRunner extends InstallerSchemaRunner
{
    private string $root;

    public function __construct(string $schemaPath, private string $foreignTable, private bool $createBeforeSchema = false)
    {
        parent::__construct($schemaPath);
        $this->root = dirname(dirname($schemaPath));
    }

    public function install(array $configuration): int
    {
        if ($this->createBeforeSchema) {
            $this->createForeignTable($configuration);
        }
        $count = parent::install($configuration);
        if (!$this->createBeforeSchema) {
            $this->createForeignTable($configuration);
            mkdir($this->root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'committed-state.json', 0700, true);
        }

        return $count;
    }

    private function createForeignTable(array $configuration): void
    {
        $connection = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $configuration['host'], $configuration['port'], $configuration['database']),
            $configuration['username'],
            $configuration['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $connection->exec('CREATE TABLE `' . str_replace('`', '``', $this->foreignTable) . '` (id INT NOT NULL)');
    }
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$host = (string) (getenv('D4_DB_HOST') ?: '127.0.0.1');
$port = (int) (getenv('D4_DB_PORT') ?: '3306');
$username = (string) (getenv('D4_DB_USERNAME') ?: 'root');
$password = (string) (getenv('D4_DB_PASSWORD') ?: '');
$server = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port), $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$roots = [];
$databases = [];
$remove = static function (string $path) use (&$remove): void {
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            $remove($path . DIRECTORY_SEPARATOR . $entry);
        }
    }
    @rmdir($path);
};
$copy = static function (string $source, string $target) use (&$copy): void {
    if (is_dir($source)) {
        if (!is_dir($target)) {
            mkdir($target, 0700, true);
        }
        foreach (scandir($source) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $copy($source . DIRECTORY_SEPARATOR . $entry, $target . DIRECTORY_SEPARATOR . $entry);
            }
        }
        return;
    }
    if (!is_file($source) || !copy($source, $target)) {
        throw new RuntimeException('Disposable installer fixture copy failed.');
    }
};

$administrator = static function (string $suffix): array {
    return [
        'admin_name' => 'Schema Evidence Administrator',
        'admin_email' => 'schema-' . $suffix . '@example.test',
        'admin_password' => 'Schema-evidence-pass-123',
        'admin_password_confirmation' => 'Schema-evidence-pass-123',
        'site_name' => 'Schema Evidence Site',
        'site_tagline' => 'Generation evidence fixture',
        'timezone' => 'UTC',
        'locale' => 'en_US',
    ];
};

$makeRoot = static function (string $label) use (&$roots, $base, $copy): array {
    $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-schema-evidence-' . $label . '-' . bin2hex(random_bytes(5));
    mkdir($root . DIRECTORY_SEPARATOR . 'storage', 0700, true);
    mkdir($root . DIRECTORY_SEPARATOR . 'config', 0700, true);
    mkdir($root . DIRECTORY_SEPARATOR . 'database', 0700, true);
    $copy($base . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql', $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql');
    $copy($base . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php', $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php');
    $copy($base . DIRECTORY_SEPARATOR . 'themes', $root . DIRECTORY_SEPARATOR . 'themes');
    $copy($base . DIRECTORY_SEPARATOR . 'modules', $root . DIRECTORY_SEPARATOR . 'modules');
    $roots[] = $root;
    return [$root, $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql'];
};

$makeCommitter = static function (string $root, string $schemaPath, string $databaseName, string $namespace, string $host, int $port, string $username, string $password, ?InstallerSchemaRunner $schemaRunner = null): InstallerInstallationCommitter {
    $configuration = [
        'host' => $host,
        'port' => $port,
        'database' => $databaseName,
        'username' => $username,
        'password' => $password,
        'namespace' => $namespace,
    ];
    return new InstallerInstallationCommitter(
        $root,
        new InstallationState($root . DIRECTORY_SEPARATOR . 'storage'),
        new InstallerDatabaseProbe(5, new InstallerOwnershipProofAssembler($root . DIRECTORY_SEPARATOR . 'storage', new CoreMigrationRegistry('copot-core-current', []))),
        $schemaRunner ?? new InstallerSchemaRunner($schemaPath),
        new InstallerEnvironmentWriter($root . DIRECTORY_SEPARATOR . '.env'),
        new InstallationMutex($root . DIRECTORY_SEPARATOR . 'storage')
    );
};

try {
    foreach (['empty', 'named', 'failed', 'foreign-empty', 'foreign-named', 'foreign-allowed-collision'] as $label) {
        $databaseName = 'copot_schema_evidence_' . $label . '_' . bin2hex(random_bytes(5));
        $databases[$label] = $databaseName;
        $server->exec('CREATE DATABASE `' . $databaseName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    $materializer = new CoreSchemaMaterializer();
    $expectedGeneration = null;
    foreach (['empty' => '', 'named' => 'r3a'] as $label => $namespace) {
        [$root, $schemaPath] = $makeRoot($label);
        $configuration = [
            'host' => $host,
            'port' => $port,
            'database' => $databases[$label],
            'username' => $username,
            'password' => $password,
            'namespace' => $namespace,
        ];
        $committer = $makeCommitter($root, $schemaPath, $databases[$label], $namespace, $host, $port, $username, $password);
        $result = $committer->commit($configuration, $administrator($label), true, Copot\Core\InstallerIntent::FRESH);
        $assert(($result['finalization']['version'] ?? null) === Copot\Core\Version::CURRENT, $label . ' installation did not finalize.');

        $connection = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $databases[$label]), $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $tables = new DatabaseTableNames($namespace);
        $generation = $materializer->generationIdentity($schemaPath);
        $row = (new CoreSchemaGenerationStore($tables))->read($connection, $namespace);
        $assert(is_array($row), $label . ' installation did not persist schema-generation evidence.');
        $assert(($row['namespace'] ?? null) === $namespace, $label . ' schema-generation namespace is incorrect.');
        $assert(($row['schema_generation'] ?? null) === $generation, $label . ' schema-generation identity is not canonical.');
        $assert(($row['compatibility_state'] ?? null) === 'compatible', $label . ' schema-generation compatibility state is not authoritative.');
        $assert($expectedGeneration === null || $expectedGeneration === $generation, 'Empty and namespaced installations derived different generation identities.');
        $expectedGeneration ??= $generation;

        $objects = array_map('strval', $connection->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
        $proofs = (new InstallerOwnershipProofAssembler($root . DIRECTORY_SEPARATOR . 'storage', new CoreMigrationRegistry('copot-core-current', [])))->assemble($connection, $objects);
        $assert(count($proofs) === 1, $label . ' authoritative ownership proof was not assembled from persisted evidence.');
        $assert($proofs[0]->namespace() === $namespace, $label . ' ownership proof namespace is incorrect.');

        $throws = false;
        try {
            (new CoreSchemaGenerationStore($tables))->record($connection, 'not-a-core-generation', 'compatible', $namespace);
        } catch (InvalidArgumentException) {
            $throws = true;
        }
        $assert($throws, $label . ' invalid schema-generation identity was accepted.');
        $assert((new CoreSchemaGenerationStore($tables))->read($connection, $namespace)['schema_generation'] === $generation, $label . ' invalid generation attempt altered authoritative evidence.');

        if ($label === 'empty') {
            $connection->exec('ALTER TABLE core_schema_generation DROP COLUMN compatibility_state');
            $invalidMetadata = (new CanonicalSchemaBaselineVerifier())->verify($connection, $schemaPath, true);
            $assert(!$invalidMetadata->passed(), 'Invalid core_schema_generation metadata columns were accepted.');
        }
    }

    [$failedRoot, $failedSchemaPath] = $makeRoot('failed');
    file_put_contents($failedRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php', "<?php return [\n");
    $failedCommitter = $makeCommitter($failedRoot, $failedSchemaPath, $databases['failed'], '', $host, $port, $username, $password);
    try {
        $failedCommitter->commit(
            ['host' => $host, 'port' => $port, 'database' => $databases['failed'], 'username' => $username, 'password' => $password, 'namespace' => ''],
            $administrator('failed'),
            true,
            Copot\Core\InstallerIntent::FRESH
        );
        throw new RuntimeException('Post-schema installation failure unexpectedly completed.');
    } catch (Copot\Core\InstallerValidationException|Copot\Core\InstallationException) {
        $connection = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $databases['failed']), $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $tableCount = (int) $connection->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'")->fetchColumn();
        $assert($tableCount === 0, 'Failed installation left authoritative database tables behind.');
        $assert(!is_file($failedRoot . DIRECTORY_SEPARATOR . '.env'), 'Failed installation left environment authority behind.');
        $assert(!(new InstallationIdentityStore($failedRoot . DIRECTORY_SEPARATOR . 'storage'))->read() instanceof Copot\Core\InstallationIdentity, 'Failed installation left installation identity behind.');
        $assert(!(new CommittedLifecycleStateStore($failedRoot . DIRECTORY_SEPARATOR . 'storage'))->read() instanceof Copot\Core\CommittedLifecycleState, 'Failed installation left committed lifecycle authority behind.');
    }

    foreach (['foreign-empty' => ['', 'foreign_empty_table'], 'foreign-named' => ['r3a', 'foreign_users']] as $label => [$namespace, $foreignTable]) {
        [$foreignRoot, $foreignSchemaPath] = $makeRoot($label);
        $foreignCommitter = $makeCommitter(
            $foreignRoot,
            $foreignSchemaPath,
            $databases[$label],
            $namespace,
            $host,
            $port,
            $username,
            $password,
            new InstallerForeignObjectSchemaRunner($foreignSchemaPath, $foreignTable)
        );
        $incompleteRollback = false;
        $failureMessage = '';
        try {
            $foreignCommitter->commit(
                ['host' => $host, 'port' => $port, 'database' => $databases[$label], 'username' => $username, 'password' => $password, 'namespace' => $namespace],
                $administrator($label),
                true,
                Copot\Core\InstallerIntent::FRESH
            );
        } catch (Copot\Core\InstallationException $exception) {
            $failureMessage = $exception->getMessage();
            $incompleteRollback = str_contains($exception->getMessage(), 'rollback is incomplete');
        }
        $assert($incompleteRollback, $label . ' foreign-object cleanup ambiguity was not reported as incomplete: ' . $failureMessage);
        $connection = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $databases[$label]), $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $foreignExists = (int) $connection->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '" . str_replace("'", "''", $foreignTable) . "'")->fetchColumn();
        $assert($foreignExists === 1, $label . ' foreign table was removed during rollback.');
        $canonicalTables = (int) $connection->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name <> '" . str_replace("'", "''", $foreignTable) . "'")->fetchColumn();
        $assert($canonicalTables === 0, $label . ' canonical installer tables were not cleaned after foreign-object preservation.');
    }

    [$collisionRoot, $collisionSchemaPath] = $makeRoot('foreign-allowed-collision');
    $collisionCommitter = $makeCommitter(
        $collisionRoot,
        $collisionSchemaPath,
        $databases['foreign-allowed-collision'],
        '',
        $host,
        $port,
        $username,
        $password,
        new InstallerForeignObjectSchemaRunner($collisionSchemaPath, 'users', true)
    );
    $collisionIncomplete = false;
    try {
        $collisionCommitter->commit(
            ['host' => $host, 'port' => $port, 'database' => $databases['foreign-allowed-collision'], 'username' => $username, 'password' => $password, 'namespace' => ''],
            $administrator('foreign-allowed-collision'),
            true,
            Copot\Core\InstallerIntent::FRESH
        );
    } catch (Copot\Core\InstallationException $exception) {
        $collisionIncomplete = str_contains($exception->getMessage(), 'rollback is incomplete');
    }
    $assert($collisionIncomplete, 'Same-name foreign table collision was not classified as incomplete cleanup.');
    $connection = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $databases['foreign-allowed-collision']), $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $assert((int) $connection->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'")->fetchColumn() === 1, 'Same-name foreign table was removed despite missing creation provenance.');

    echo "Installer schema-generation evidence tests passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    foreach ($databases as $databaseName) {
        try { $server->exec('DROP DATABASE IF EXISTS `' . $databaseName . '`'); } catch (Throwable) { }
    }
    foreach ($roots as $root) {
        $remove($root);
    }
}
