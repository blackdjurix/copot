<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\ModuleDiscovery;
use Copot\Core\ModuleManager;
use Copot\Core\ModuleRepository;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');
require_once $basePath . '/modules/navigation/Services/NavigationRenderItem.php';
require_once $basePath . '/modules/navigation/Services/NavigationTargetResolver.php';
require_once $basePath . '/modules/navigation/Services/NavigationTargetResolverRegistry.php';

final class M36TestNavigationResolver implements NavigationTargetResolver
{
    public function kind(): string
    {
        return 'test';
    }

    public function resolve(string $reference): ?NavigationRenderItem
    {
        return $reference === 'home'
            ? new NavigationRenderItem('test', 'home', 'Home', '/home')
            : null;
    }
}

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

$tableExists = static function (PDO $connection, string $table): bool {
    $statement = $connection->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :table'
    );
    $statement->execute(['table' => $table]);

    return (int) $statement->fetchColumn() === 1;
};

$columnNames = static function (PDO $connection, string $table): array {
    $statement = $connection->prepare(
        'SELECT column_name
         FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = :table
         ORDER BY ordinal_position'
    );
    $statement->execute(['table' => $table]);

    return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
};

$indexColumns = static function (PDO $connection, string $table, string $index): array {
    $statement = $connection->prepare(
        'SELECT column_name
         FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index
         ORDER BY seq_in_index'
    );
    $statement->execute(['table' => $table, 'index' => $index]);

    return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
};

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$suffix = bin2hex(random_bytes(6));
$freshDatabase = 'copot_m36_wu1_fresh_' . $suffix;
$existingDatabase = 'copot_m36_wu1_existing_' . $suffix;
$quoted = static fn (string $name): string => '`' . str_replace('`', '``', $name) . '`';

$server = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $username,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);

foreach ([$freshDatabase, $existingDatabase] as $databaseName) {
    $server->exec('CREATE DATABASE ' . $quoted($databaseName) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
}

try {
    $configuration = [
        'host' => $host,
        'port' => $port,
        'database' => $freshDatabase,
        'username' => $username,
        'password' => $password,
    ];
    $installed = (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install($configuration);
    $assert($installed > 0, 'Fresh schema did not execute.');

    $fresh = new PDO(
        "mysql:host={$host};port={$port};dbname={$freshDatabase};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    foreach (['navigation_menus', 'navigation_items', 'navigation_menu_assignments'] as $table) {
        $assert($tableExists($fresh, $table), "Fresh schema omitted {$table}.");
    }
    $assert($columnNames($fresh, 'navigation_items') === [
        'id', 'menu_id', 'parent_id', 'label', 'target_kind', 'target_reference',
        'custom_url', 'sort_order', 'is_visible', 'created_at', 'updated_at',
    ], 'Navigation item schema contains an unexpected column set.');
    $assert($indexColumns($fresh, 'navigation_menu_assignments', 'uq_navigation_assignment_theme_location') === ['theme_id', 'location_key'], 'Navigation assignment identity index is incorrect.');
    $assert($indexColumns($fresh, 'navigation_items', 'idx_navigation_items_menu_parent_order') === ['menu_id', 'parent_id', 'sort_order'], 'Navigation hierarchy/order index is incorrect.');
    $assert((int) $fresh->query("SELECT COUNT(*) FROM permissions WHERE slug = 'navigation.manage'")->fetchColumn() === 1, 'Fresh schema omitted navigation.manage.');
    $assert((int) $fresh->query(
        "SELECT COUNT(*)
         FROM role_permissions
         INNER JOIN roles ON roles.id = role_permissions.role_id
         INNER JOIN permissions ON permissions.id = role_permissions.permission_id
         WHERE roles.slug = 'admin' AND permissions.slug = 'navigation.manage'"
    )->fetchColumn() === 1, 'Fresh schema omitted the seeded admin navigation.manage mapping.');

    $freshConfig = new Config($basePath . '/config');
    $configItems = (new ReflectionClass($freshConfig))->getProperty('items');
    $configuredItems = $configItems->getValue($freshConfig);
    $configuredItems['database']['connections']['mysql']['host'] = $host;
    $configuredItems['database']['connections']['mysql']['port'] = $port;
    $configuredItems['database']['connections']['mysql']['database'] = $freshDatabase;
    $configuredItems['database']['connections']['mysql']['username'] = $username;
    $configuredItems['database']['connections']['mysql']['password'] = $password;
    $configItems->setValue($freshConfig, $configuredItems);
    $moduleManager = new ModuleManager(
        new ModuleDiscovery($basePath . '/modules'),
        new ModuleRepository(new Database($freshConfig))
    );
    $moduleManager->install('navigation');
    $moduleManager->enable('navigation');
    $assert((string) $fresh->query("SELECT status FROM modules WHERE name = 'navigation'")->fetchColumn() === 'enabled', 'Navigation module did not install and enable.');
    $assert((int) $fresh->query("SELECT COUNT(*) FROM module_permissions WHERE module_name = 'navigation' AND permission_slug = 'navigation.manage'")->fetchColumn() === 1, 'Navigation manifest permission was not registered.');

    $existing = new PDO(
        "mysql:host={$host};port={$port};dbname={$existingDatabase};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $executeScript($existing, (string) file_get_contents($basePath . '/database/schema.sql'));
    $existing->exec("DROP TABLE navigation_menu_assignments, navigation_items, navigation_menus");
    $existing->exec("DELETE FROM role_permissions WHERE permission_id NOT IN (SELECT id FROM permissions)");
    $existing->exec("DELETE FROM permissions WHERE slug = 'navigation.manage'");
    $unrelatedPermissionCount = (int) $existing->query(
        "SELECT COUNT(*) FROM permissions WHERE slug = 'content.read'"
    )->fetchColumn();
    $unrelatedMappingCount = (int) $existing->query(
        "SELECT COUNT(*)
         FROM role_permissions
         INNER JOIN roles ON roles.id = role_permissions.role_id
         INNER JOIN permissions ON permissions.id = role_permissions.permission_id
         WHERE roles.slug = 'admin' AND permissions.slug = 'content.read'"
    )->fetchColumn();
    $upgrade = (string) file_get_contents($basePath . '/database/upgrades/m3_6_navigation_manager.sql');
    $executeScript($existing, $upgrade);
    $executeScript($existing, $upgrade);
    foreach (['navigation_menus', 'navigation_items', 'navigation_menu_assignments'] as $table) {
        $assert($tableExists($existing, $table), "Upgrade omitted {$table}.");
    }
    $assert((int) $existing->query("SELECT COUNT(*) FROM permissions WHERE slug = 'navigation.manage'")->fetchColumn() === 1, 'Upgrade was not idempotent for navigation.manage.');
    $assert((int) $existing->query(
        "SELECT COUNT(*)
         FROM role_permissions
         INNER JOIN roles ON roles.id = role_permissions.role_id
         INNER JOIN permissions ON permissions.id = role_permissions.permission_id
         WHERE roles.slug = 'admin' AND permissions.slug = 'navigation.manage'"
    )->fetchColumn() === 1, 'Upgrade was not idempotent for the seeded admin mapping.');
    $assert((int) $existing->query("SELECT COUNT(*) FROM permissions WHERE slug = 'content.read'")->fetchColumn() === $unrelatedPermissionCount, 'Upgrade changed unrelated permission data.');
    $assert((int) $existing->query(
        "SELECT COUNT(*)
         FROM role_permissions
         INNER JOIN roles ON roles.id = role_permissions.role_id
         INNER JOIN permissions ON permissions.id = role_permissions.permission_id
         WHERE roles.slug = 'admin' AND permissions.slug = 'content.read'"
    )->fetchColumn() === $unrelatedMappingCount, 'Upgrade changed unrelated permission mappings.');

    $manifest = json_decode((string) file_get_contents($basePath . '/modules/navigation/module.json'), true, 512, JSON_THROW_ON_ERROR);
    $assert(($manifest['requires']['modules'] ?? null) === [], 'Navigation must not require optional provider modules.');
    $assert(array_column($manifest['permissions'] ?? [], 'slug') === ['navigation.manage'], 'Navigation manifest permission contract changed.');
    $assert(str_contains((string) file_get_contents($basePath . '/build/package_manifest.php'), "'modules/navigation'"), 'Package manifest omitted Navigation.');

    $registry = new NavigationTargetResolverRegistry();
    $assert($registry->resolve('missing', '1') === null, 'Missing optional target providers must fail closed.');
    $registry->register(new M36TestNavigationResolver());
    $resolved = $registry->resolve('test', 'home');
    $assert($resolved instanceof NavigationRenderItem && $resolved->toArray() === [
        'kind' => 'test',
        'reference' => 'home',
        'label' => 'Home',
        'url' => '/home',
        'is_visible' => true,
    ], 'Provider-neutral resolver contract did not produce the expected render item.');

    echo "M3.6 Work Unit 1 Navigation compatibility passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $quoted($freshDatabase));
    $server->exec('DROP DATABASE IF EXISTS ' . $quoted($existingDatabase));
}
