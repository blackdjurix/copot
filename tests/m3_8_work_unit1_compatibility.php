<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\ModuleDiscovery;
use Copot\Core\ModuleManager;
use Copot\Core\ModuleRepository;
use Copot\Core\SiteAssetStorage;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_m38_wu1_' . bin2hex(random_bytes(6));
$quotedDatabase = '`' . str_replace('`', '``', $databaseName) . '`';
$configuration = [
    'host' => $host,
    'port' => $port,
    'database' => $databaseName,
    'username' => $username,
    'password' => $password,
];

$server = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $username,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);
$server->exec('CREATE DATABASE ' . $quotedDatabase . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

try {
    $installedStatements = (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install($configuration);
    $assert($installedStatements > 0, 'Current schema must install into a clean database.');

    $connection = new PDO(
        "mysql:host={$host};port={$port};dbname={$databaseName};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $tables = array_map(
        static fn (array $row): string => (string) array_values($row)[0],
        $connection->query('SHOW TABLES')->fetchAll()
    );
    foreach (['users', 'roles', 'permissions', 'role_permissions', 'settings', 'modules', 'module_permissions'] as $table) {
        $assert(in_array($table, $tables, true), "Current schema must retain {$table}.");
    }
    foreach (['media', 'media_variants', 'media_usages'] as $table) {
        $assert(in_array($table, $tables, true), "WU2 must provision Media table {$table}.");
    }

    $config = new Config($basePath . '/config');
    $configReflection = new ReflectionClass($config);
    $configItems = $configReflection->getProperty('items');
    $configItems->setAccessible(true);
    $configuredItems = $configItems->getValue($config);
    $configuredItems['database']['connections']['mysql']['host'] = $host;
    $configuredItems['database']['connections']['mysql']['port'] = $port;
    $configuredItems['database']['connections']['mysql']['database'] = $databaseName;
    $configuredItems['database']['connections']['mysql']['username'] = $username;
    $configuredItems['database']['connections']['mysql']['password'] = $password;
    $configItems->setValue($config, $configuredItems);

    $moduleManager = new ModuleManager(
        new ModuleDiscovery($basePath . '/modules'),
        new ModuleRepository(new Database($config))
    );
    $discoveredNames = array_map(static fn ($module): string => $module->name(), $moduleManager->discover());
    sort($discoveredNames);
    $assert($discoveredNames === ['content', 'example', 'media', 'module-manager', 'navigation', 'settings-manager', 'taxonomy', 'theme-manager', 'users-access'], 'Current module discovery baseline changed unexpectedly.');

    $baselineModules = ['content', 'settings-manager', 'taxonomy', 'module-manager', 'navigation', 'theme-manager', 'media'];
    foreach ($baselineModules as $moduleName) {
        $moduleManager->install($moduleName);
        $moduleManager->enable($moduleName);
    }

    foreach ($baselineModules as $moduleName) {
        $status = (string) $connection->query("SELECT status FROM modules WHERE name = " . $connection->quote($moduleName))->fetchColumn();
        $assert($status === 'enabled', "Existing baseline module {$moduleName} must remain enabled through the normal lifecycle.");

        $manifest = json_decode((string) file_get_contents($basePath . '/modules/' . $moduleName . '/module.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($manifest['permissions'] ?? [] as $permission) {
            $slug = (string) ($permission['slug'] ?? '');
            $permissionCount = (int) $connection->query("SELECT COUNT(*) FROM permissions WHERE slug = " . $connection->quote($slug))->fetchColumn();
            $modulePermissionCount = (int) $connection->query(
                "SELECT COUNT(*) FROM module_permissions WHERE module_name = " . $connection->quote($moduleName)
                . " AND permission_slug = " . $connection->quote($slug)
            )->fetchColumn();
            $assert($permissionCount === 1 && $modulePermissionCount === 1, "Current permission baseline for {$moduleName}.{$slug} must remain intact.");
        }
    }

    $reconnected = new PDO(
        "mysql:host={$host};port={$port};dbname={$databaseName};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $assert((int) $reconnected->query('SELECT COUNT(*) FROM modules WHERE status = \'enabled\'')->fetchColumn() === count($baselineModules), 'Existing-install module state must survive reconnect.');
    $assert((int) $reconnected->query('SELECT COUNT(*) FROM module_permissions')->fetchColumn() > 0, 'Existing-install module permission state must survive reconnect.');

    $siteAssetSource = (string) file_get_contents($basePath . '/app/Core/SiteAssetStorage.php');
    $applicationSource = (string) file_get_contents($basePath . '/app/Core/Application.php');
    $siteAssetReflection = new ReflectionClass(SiteAssetStorage::class);
    $publicMethods = array_map(static fn (ReflectionMethod $method): string => $method->getName(), $siteAssetReflection->getMethods(ReflectionMethod::IS_PUBLIC));
    foreach (['remove', 'serve', 'store', 'url'] as $method) {
        $assert(in_array($method, $publicMethods, true), "SiteAssetStorage must retain the {$method} branding operation.");
    }
    $assert(count(array_filter($publicMethods, static fn (string $method): bool => preg_match('/media/i', $method) === 1)) === 0, 'SiteAssetStorage must not expose a Media-named public operation.');
    $assert(str_contains($siteAssetSource, "'logo'") && str_contains($siteAssetSource, "'favicon'"), 'SiteAssetStorage must retain only the existing branding slots.');
    $assert(!preg_match('/MediaStorage|MediaRepository|mediaAssets|media_variants|media_usages/i', $siteAssetSource), 'SiteAssetStorage must not expose a generic Media API.');
    $assert(str_contains($applicationSource, 'new SiteAssetStorage') && str_contains($applicationSource, 'function siteAssets'), 'Application must retain the existing site-branding storage boundary.');

    $assert(is_dir($basePath . '/modules/media'), 'WU2 must register the Media module.');
    $assert(glob($basePath . '/database/upgrades/*media*') !== [], 'WU2 must add a Media upgrade artifact.');
    $manifest = require $basePath . '/build/package_manifest.php';
    $assert(in_array('modules/media', $manifest['include'] ?? [], true), 'WU2 Media module must be present in the installable package.');
    $coreFiles = glob($basePath . '/app/Core/*Media*.php') ?: [];
    $assert($coreFiles === [], 'WU1 must not add a Core Media implementation.');

    echo "M3.8 Work Unit 1 compatibility passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase);
}
