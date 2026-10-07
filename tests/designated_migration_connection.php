<?php
declare(strict_types=1);

use Copot\Core\{CoreMigrationDescriptor, CoreMigrationPlan, Env, PackageLifecycleFactory};

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) { throw new RuntimeException($message); }
};
$adminUser = (string) (getenv('DESIGNATED_TEST_ADMIN_USERNAME') ?: 'root');
$adminPassword = (string) (getenv('DESIGNATED_TEST_ADMIN_PASSWORD') ?: '');
$databaseName = 'copot_designated_' . bin2hex(random_bytes(4));
$runtimeUser = 'copot_runtime_' . bin2hex(random_bytes(4));
$runtimePassword = bin2hex(random_bytes(12));
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-designated-migration-' . bin2hex(random_bytes(4));
$project = $tmp . DIRECTORY_SEPARATOR . 'project';
$admin = null;
$remove = function (string $path) use (&$remove): void {
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) { return; }
    foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry);
    @rmdir($path);
};
$copy = function (string $source, string $target) use (&$copy): void {
    if (!is_dir($target)) mkdir($target, 0700, true);
    foreach (scandir($source) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === '.git' || $entry === 'storage') continue;
        $from = $source . DIRECTORY_SEPARATOR . $entry;
        $to = $target . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($from) && !is_link($from)) $copy($from, $to); elseif (is_file($from) && !is_link($from)) copy($from, $to);
    }
};

try {
    $copy($base, $project);
    mkdir($project . DIRECTORY_SEPARATOR . 'storage', 0700, true);
    mkdir($tmp . DIRECTORY_SEPARATOR . 'recovery', 0700, true);
    file_put_contents($project . DIRECTORY_SEPARATOR . '.env', implode(PHP_EOL, [
        'DB_HOST="127.0.0.1"', 'DB_PORT="3307"', 'DB_DATABASE="' . $databaseName . '"',
        'DB_USERNAME="' . $runtimeUser . '"', 'DB_PASSWORD="' . $runtimePassword . '"',
        'DB_NAMESPACE=""', 'COPOT_RECOVERY_ROOT="' . ($tmp . DIRECTORY_SEPARATOR . 'recovery') . '"',
        'COPOT_MARIADB_ADMIN_USERNAME="' . $adminUser . '"', 'COPOT_MARIADB_ADMIN_PASSWORD="' . $adminPassword . '"',
        'COPOT_MARIADB_QUIESCENCE_CONFIRMED=true',
    ]) . PHP_EOL);
    Env::load($project . DIRECTORY_SEPARATOR . '.env');

    $admin = new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4', $adminUser, $adminPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $quote = static fn (string $value): string => '`' . str_replace('`', '``', $value) . '`';
    $admin->exec('CREATE DATABASE ' . $quote($databaseName));
    $admin->exec("CREATE USER '{$runtimeUser}'@'127.0.0.1' IDENTIFIED BY '{$runtimePassword}'");
    $admin->exec("GRANT ALL PRIVILEGES ON {$quote($databaseName)}.* TO '{$runtimeUser}'@'127.0.0.1'");
    $admin->exec('FLUSH PRIVILEGES');
    $runtime = new PDO("mysql:host=127.0.0.1;port=3307;dbname={$databaseName};charset=utf8mb4", $runtimeUser, $runtimePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $runtime->exec('CREATE TABLE core_migration_history (migration_id VARCHAR(190) NOT NULL PRIMARY KEY, sequence_number INT NOT NULL, target_webcore_version VARCHAR(40) NOT NULL, target_schema_identity VARCHAR(190) NOT NULL, migration_checksum CHAR(64) NOT NULL, applied_at DATETIME NOT NULL)');

    $service = PackageLifecycleFactory::forProject($project);
    $serviceProperty = new ReflectionProperty($service, 'applyCoordinator');
    $coordinator = $serviceProperty->getValue($service);
    $runnerProperty = new ReflectionProperty($coordinator, 'migrationRunner');
    $runner = $runnerProperty->getValue($coordinator);
    $capturedConnectionId = null;
    $descriptor = new CoreMigrationDescriptor(
        'test.designated', 10, '0.13.0', null, '0.13.0', 'test-schema', CoreMigrationDescriptor::TRANSACTIONAL,
        'test.designated:v1',
        static function (\Copot\Core\AuthorizedMigrationContext $context) use (&$capturedConnectionId): void {
            $connection = (new ReflectionProperty($context, 'connection'))->getValue($context);
            $capturedConnectionId = (int) $connection->query('SELECT CONNECTION_ID()')->fetchColumn();
        },
        null,
        static fn (): bool => true,
        true,
        new \Copot\Core\MigrationSchemaSurface(['core_migration_history'])
    );
    $plan = CoreMigrationPlan::allow('0.13.0', '0.13.0', 'test-schema', 'test-schema', [$descriptor]);

    $admin->exec('SET GLOBAL read_only = ON');
    $ordinaryRejected = false;
    try { $runtime->exec("INSERT INTO core_migration_history VALUES ('ordinary', 1, '0.13.0', 'test-schema', REPEAT('a', 64), NOW())"); }
    catch (Throwable) { $ordinaryRejected = true; }
    $assert($ordinaryRejected, 'Ordinary runtime PDO was able to write while read_only was enabled.');

    $result = $runner($plan, 'designated-test-operation', 'database_update');
    $assert($result->status() === \Copot\Core\MigrationRunResult::COMPLETED, 'Designated migration connection did not complete the migration.');
    $adminConnectionId = (int) $admin->query('SELECT CONNECTION_ID()')->fetchColumn();
    $assert($capturedConnectionId !== null && $capturedConnectionId !== $runtime->query('SELECT CONNECTION_ID()')->fetchColumn(), 'Migration did not use a distinct designated connection.');
    $assert($capturedConnectionId !== $adminConnectionId, 'Migration unexpectedly reused the factory admin connection object instead of a designated connection instance.');
    $assert((int) $runtime->query('SELECT COUNT(*) FROM core_migration_history')->fetchColumn() === 1, 'Designated migration ledger write was not persisted.');
    $admin->exec('SET GLOBAL read_only = OFF');
    echo "designated_migration_connection: {$assertions} assertions passed" . PHP_EOL;
} finally {
    if ($admin instanceof PDO) {
        try { $admin->exec('SET GLOBAL read_only = OFF'); } catch (Throwable) {}
        try { $admin->exec('DROP DATABASE IF EXISTS `' . $databaseName . '`'); } catch (Throwable) {}
        try { $admin->exec("DROP USER IF EXISTS '{$runtimeUser}'@'127.0.0.1'"); } catch (Throwable) {}
    }
    $remove($tmp);
}
