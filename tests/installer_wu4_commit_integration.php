<?php

declare(strict_types=1);

use Copot\Core\CommittedLifecycleStateStore;
use Copot\Core\Config;
use Copot\Core\CoreMigrationRegistry;
use Copot\Core\InstallationException;
use Copot\Core\InstallationMutex;
use Copot\Core\InstallationState;
use Copot\Core\InstallerDatabaseProbe;
use Copot\Core\InstallerInstallationCommitter;
use Copot\Core\InstallerOwnershipProofAssembler;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\InstallerValidationException;
use Copot\Core\InstallerEnvironmentWriter;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$host = (string) (getenv('D4_DB_HOST') ?: '127.0.0.1');
$port = (int) (getenv('D4_DB_PORT') ?: '3306');
$username = (string) (getenv('D4_DB_USERNAME') ?: 'root');
$password = (string) (getenv('D4_DB_PASSWORD') ?: '');
$server = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port), $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu4-commit-' . bin2hex(random_bytes(6));
$storage = $root . DIRECTORY_SEPARATOR . 'storage';
$config = $root . DIRECTORY_SEPARATOR . 'config';
$databaseDirectory = $root . DIRECTORY_SEPARATOR . 'database';
$databaseName = 'copot_wu4_' . bin2hex(random_bytes(5));
$quotedDatabase = '`' . str_replace('`', '``', $databaseName) . '`';

$remove = static function (string $path) use (&$remove): void {
    if (is_file($path) || is_link($path)) { @unlink($path); return; }
    if (!is_dir($path)) { return; }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') { $remove($path . DIRECTORY_SEPARATOR . $entry); }
    }
    @rmdir($path);
};
$copy = static function (string $source, string $target) use (&$copy): void {
    if (is_dir($source)) {
        if (!is_dir($target)) { mkdir($target, 0700, true); }
        foreach (scandir($source) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') { $copy($source . DIRECTORY_SEPARATOR . $entry, $target . DIRECTORY_SEPARATOR . $entry); }
        }
        return;
    }
    if (!is_file($source) || !copy($source, $target)) { throw new RuntimeException('Disposable WU4 fixture copy failed.'); }
};

try {
    mkdir($storage, 0700, true);
    mkdir($config, 0700, true);
    mkdir($databaseDirectory, 0700, true);
    $copy($base . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql', $databaseDirectory . DIRECTORY_SEPARATOR . 'schema.sql');
    $copy($base . DIRECTORY_SEPARATOR . 'themes', $root . DIRECTORY_SEPARATOR . 'themes');
    $copy($base . DIRECTORY_SEPARATOR . 'modules', $root . DIRECTORY_SEPARATOR . 'modules');
    $copy($base . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php', $config . DIRECTORY_SEPARATOR . 'database.php');
    $server->exec('CREATE DATABASE ' . $quotedDatabase . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    $configuration = ['host' => $host, 'port' => $port, 'database' => $databaseName, 'username' => $username, 'password' => $password, 'namespace' => 'w4demo'];
    $installationState = new InstallationState($storage);
    $committer = new InstallerInstallationCommitter(
        $root,
        $installationState,
        new InstallerDatabaseProbe(5, new InstallerOwnershipProofAssembler($storage, new CoreMigrationRegistry('copot-core-current', []))),
        new InstallerSchemaRunner($databaseDirectory . DIRECTORY_SEPARATOR . 'schema.sql'),
        new InstallerEnvironmentWriter($root . DIRECTORY_SEPARATOR . '.env'),
        new InstallationMutex($storage)
    );
    $administrator = [
        'admin_name' => 'WU4 Disposable Administrator',
        'admin_email' => 'wu4-' . bin2hex(random_bytes(3)) . '@example.test',
        'admin_password' => 'WU4-disposable-pass-123',
        'admin_password_confirmation' => 'WU4-disposable-pass-123',
        'site_name' => 'WU4 Disposable Site',
        'site_tagline' => 'Review and result fixture',
        'timezone' => 'UTC',
        'locale' => 'en_US',
    ];

    $result = $committer->commit($configuration, $administrator, true, Copot\Core\InstallerIntent::FRESH);
    $database = new Copot\Core\Database(new Config($config));
    $tables = $database->connection()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $tableNames = array_map('strval', is_array($tables) ? $tables : []);
    if (!in_array('w4demo_users', $tableNames, true) || !in_array('w4demo_settings', $tableNames, true)) {
        throw new RuntimeException('Namespaced schema was not materialized by Review & Install.');
    }
    if (!$installationState->isInstalled() || !(new CommittedLifecycleStateStore($storage))->read() instanceof Copot\Core\CommittedLifecycleState) {
        throw new RuntimeException('Committed lifecycle/runtime evidence was not written.');
    }
    $environment = (string) file_get_contents($root . DIRECTORY_SEPARATOR . '.env');
    if (!str_contains($environment, 'DB_NAMESPACE="w4demo"') || str_contains($environment, $administrator['admin_password'])) {
        throw new RuntimeException('Environment persistence or secret handling failed.');
    }

    try {
        $committer->commit($configuration, $administrator, true, Copot\Core\InstallerIntent::FRESH);
        throw new RuntimeException('Repeated installation was not rejected.');
    } catch (InstallationException) {
        // Expected: the committed marker makes a second installation fail closed.
    }

    $failedRoot = $root . '-failed';
    mkdir($failedRoot . DIRECTORY_SEPARATOR . 'storage', 0700, true);
    mkdir($failedRoot . DIRECTORY_SEPARATOR . 'config', 0700, true);
    mkdir($failedRoot . DIRECTORY_SEPARATOR . 'database', 0700, true);
    $copy($databaseDirectory . DIRECTORY_SEPARATOR . 'schema.sql', $failedRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql');
    $copy($base . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php', $failedRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php');
    $failedCommitter = new InstallerInstallationCommitter(
        $failedRoot,
        new InstallationState($failedRoot . DIRECTORY_SEPARATOR . 'storage'),
        new InstallerDatabaseProbe(5, new InstallerOwnershipProofAssembler($failedRoot . DIRECTORY_SEPARATOR . 'storage', new CoreMigrationRegistry('copot-core-current', []))),
        new InstallerSchemaRunner($failedRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql'),
        new InstallerEnvironmentWriter($failedRoot . DIRECTORY_SEPARATOR . '.env'),
        new InstallationMutex($failedRoot . DIRECTORY_SEPARATOR . 'storage')
    );
    try {
        $failedCommitter->commit($configuration, ['admin_name' => '', 'admin_email' => 'bad', 'admin_password' => 'x', 'admin_password_confirmation' => 'y', 'site_name' => '', 'site_tagline' => '', 'timezone' => 'UTC', 'locale' => 'en_US'], true, Copot\Core\InstallerIntent::FRESH);
        throw new RuntimeException('Failed revalidation unexpectedly mutated the installation.');
    } catch (InstallerValidationException|InstallationException) {
        if (is_file($failedRoot . DIRECTORY_SEPARATOR . '.env') || (new InstallationState($failedRoot . DIRECTORY_SEPARATOR . 'storage'))->isInstalled()) {
            throw new RuntimeException('Failed revalidation left installation-owned state behind.');
        }
    }

    fwrite(STDOUT, "WU4 disposable commit integration: PASS\n");
} finally {
    try { $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase); } catch (Throwable) { }
    $remove($root . '-failed');
    $remove($root);
}
