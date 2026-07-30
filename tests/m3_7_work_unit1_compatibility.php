<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\ModuleDiscovery;
use Copot\Core\ModuleManager;
use Copot\Core\ModuleRepository;
use Copot\Core\ThemeDiscovery;

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

$executeScript = static function (PDO $connection, string $sql): void {
    $delimiter = ';';
    $buffer = '';

    foreach (preg_split('/\R/', $sql) ?: [] as $line) {
        $trimmed = trim($line);

        if (preg_match('/^DELIMITER\s+(\S+)$/i', $trimmed, $matches) === 1) {
            $delimiter = $matches[1];
            continue;
        }

        $buffer .= $line . PHP_EOL;

        while (($position = strpos($buffer, $delimiter)) !== false) {
            $statement = trim(substr($buffer, 0, $position));
            $buffer = substr($buffer, $position + strlen($delimiter));

            if ($statement !== '') {
                $connection->exec($statement);
            }
        }
    }

    if (trim($buffer) !== '') {
        $connection->exec(trim($buffer));
    }
};

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_m37_wu1_' . bin2hex(random_bytes(6));
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

$themeRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-m37-wu1-theme-' . bin2hex(random_bytes(4));
mkdir($themeRoot . DIRECTORY_SEPARATOR . 'healthy' . DIRECTORY_SEPARATOR . 'layouts', 0777, true);
mkdir($themeRoot . DIRECTORY_SEPARATOR . 'broken', 0777, true);
file_put_contents($themeRoot . '/healthy/theme.json', json_encode([
    'id' => 'healthy',
    'name' => 'Healthy Theme',
    'version' => '1.0.0',
    'type' => 'frontend',
    'entry' => ['layout' => 'layouts/app.php'],
], JSON_THROW_ON_ERROR));
file_put_contents($themeRoot . '/healthy/layouts/app.php', '<?php echo "healthy";');
file_put_contents($themeRoot . '/broken/theme.json', '{"id":"broken","type":"frontend"');

try {
    $catalog = (new ThemeDiscovery($themeRoot))->discoverCatalog();
    $assert(array_map(static fn ($theme): string => $theme->id(), $catalog['themes']) === ['healthy'], 'Healthy themes must remain discoverable beside malformed themes.');
    $assert($catalog['errors'] === [[
        'theme' => 'broken',
        'status' => 'invalid',
        'code' => 'invalid_definition',
        'message' => 'Theme definition is invalid.',
    ]], 'Malformed theme diagnostics must be bounded and structured.');
    $diagnostics = json_encode($catalog['errors'], JSON_THROW_ON_ERROR);
    $assert(!str_contains($diagnostics, $themeRoot) && !str_contains($diagnostics, 'JsonException'), 'Theme diagnostics must not expose paths or exception details.');

    $missingCatalog = (new ThemeDiscovery($themeRoot . '-missing'))->discoverCatalog();
    $assert($missingCatalog['themes'] === [], 'Unavailable Theme roots must not produce themes.');
    $assert(($missingCatalog['errors'][0]['code'] ?? null) === 'themes_path_unavailable', 'Unavailable Theme roots must produce a bounded diagnostic.');
    $assert(!str_contains(json_encode($missingCatalog['errors'], JSON_THROW_ON_ERROR), $themeRoot), 'Unavailable Theme diagnostics must not expose configured paths.');

    $freshStatements = (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install($configuration);
    $assert($freshStatements > 0, 'Fresh schema must execute.');

    $connection = new PDO(
        "mysql:host={$host};port={$port};dbname={$databaseName};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $themesBefore = (int) $connection->query('SELECT COUNT(*) FROM themes')->fetchColumn();
    (new ThemeDiscovery($basePath . '/themes'))->discoverCatalog();
    $themesAfter = (int) $connection->query('SELECT COUNT(*) FROM themes')->fetchColumn();
    $assert($themesBefore === $themesAfter, 'Catalog inspection must not mutate the Theme registry.');

    $permissionCount = (int) $connection->query("SELECT COUNT(*) FROM permissions WHERE slug = 'themes.manage'")->fetchColumn();
    $adminMappingCount = (int) $connection->query("SELECT COUNT(*) FROM role_permissions INNER JOIN roles ON roles.id = role_permissions.role_id INNER JOIN permissions ON permissions.id = role_permissions.permission_id WHERE roles.slug = 'admin' AND permissions.slug = 'themes.manage'")->fetchColumn();
    $assert($permissionCount === 1 && $adminMappingCount === 1, 'Fresh schema must provision themes.manage and its admin mapping.');

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
    $moduleManager = new ModuleManager(new ModuleDiscovery($basePath . '/modules'), new ModuleRepository(new Database($config)));
    $discovered = $moduleManager->discover();
    $themeModule = array_values(array_filter($discovered, static fn ($module): bool => $module->name() === 'theme-manager'))[0] ?? null;
    $assert($themeModule !== null && $themeModule->routes() === null, 'Theme Manager baseline must discover without Admin routes.');
    $moduleManager->install('theme-manager');
    $moduleManager->enable('theme-manager');
    $assert((string) $connection->query("SELECT status FROM modules WHERE name = 'theme-manager'")->fetchColumn() === 'enabled', 'Theme Manager baseline must install and enable.');
    $assert((int) $connection->query("SELECT COUNT(*) FROM module_permissions WHERE module_name = 'theme-manager' AND permission_slug = 'themes.manage'")->fetchColumn() === 1, 'Theme Manager manifest permission must be registered.');

    $connection->exec("DELETE role_permissions FROM role_permissions INNER JOIN roles ON roles.id = role_permissions.role_id INNER JOIN permissions ON permissions.id = role_permissions.permission_id WHERE roles.slug = 'admin' AND permissions.slug = 'themes.manage'");
    $connection->exec("DELETE FROM permissions WHERE slug = 'themes.manage'");
    $upgrade = (string) file_get_contents($basePath . '/database/upgrades/m3_7_theme_manager.sql');
    $executeScript($connection, $upgrade);
    $executeScript($connection, $upgrade);
    $assert((int) $connection->query("SELECT COUNT(*) FROM permissions WHERE slug = 'themes.manage'")->fetchColumn() === 1, 'Theme upgrade must provision themes.manage exactly once.');
    $assert((int) $connection->query("SELECT COUNT(*) FROM role_permissions INNER JOIN roles ON roles.id = role_permissions.role_id INNER JOIN permissions ON permissions.id = role_permissions.permission_id WHERE roles.slug = 'admin' AND permissions.slug = 'themes.manage'")->fetchColumn() === 1, 'Theme upgrade must provision the admin mapping exactly once.');

    $manifest = require $basePath . '/build/package_manifest.php';
    $assert(in_array('modules/theme-manager', $manifest['include'] ?? [], true), 'Package manifest must include Theme Manager.');
    $assert((int) $connection->query("SELECT COUNT(*) FROM themes WHERE is_active = 1")->fetchColumn() === 0, 'WU1 compatibility setup must not activate a theme during catalog inspection.');

    echo "M3.7 Work Unit 1 compatibility passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase);
    foreach ([$themeRoot . '/healthy/layouts/app.php', $themeRoot . '/healthy/theme.json', $themeRoot . '/broken/theme.json'] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    foreach ([$themeRoot . '/healthy/layouts', $themeRoot . '/healthy', $themeRoot . '/broken', $themeRoot] as $directory) {
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
}
