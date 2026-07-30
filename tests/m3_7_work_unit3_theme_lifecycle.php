<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\ThemeDiscovery;
use Copot\Core\ThemeLifecycle;
use Copot\Core\ThemeManager;
use Copot\Core\ThemeRepository;
use Copot\Core\ThemeException;

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
$removeDirectory = static function (string $path) use (&$removeDirectory): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $child = $path . DIRECTORY_SEPARATOR . $entry;
        is_dir($child) ? $removeDirectory($child) : unlink($child);
    }

    rmdir($path);
};
$makeTheme = static function (string $root, string $id, string $name, string $version = '1.0.0', string $type = 'frontend'): string {
    $path = $root . DIRECTORY_SEPARATOR . $id;
    mkdir($path . DIRECTORY_SEPARATOR . 'layouts', 0777, true);
    file_put_contents($path . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.php', '<?php echo "theme";');
    file_put_contents($path . DIRECTORY_SEPARATOR . 'theme.json', json_encode([
        'id' => $id,
        'name' => $name,
        'version' => $version,
        'type' => $type,
        'entry' => ['layout' => 'layouts/app.php'],
    ], JSON_THROW_ON_ERROR));

    return $path;
};
$registryInsert = static function (PDO $connection, string $id, string $name, string $version, string $type, string $path, bool $active, string $metadata = '{}'): void {
    $statement = $connection->prepare(
        'INSERT INTO themes (theme_id,name,version,type,path,is_active,metadata,created_at,updated_at)
         VALUES (:id,:name,:version,:type,:path,:active,:metadata,NOW(),NOW())'
    );
    $statement->execute([
        'id' => $id,
        'name' => $name,
        'version' => $version,
        'type' => $type,
        'path' => $path,
        'active' => $active ? 1 : 0,
        'metadata' => $metadata,
    ]);
};

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_m37_wu3_' . bin2hex(random_bytes(6));
$quotedDatabase = '`' . str_replace('`', '``', $databaseName) . '`';
$server = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $username,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$server->exec('CREATE DATABASE ' . $quotedDatabase . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$projectRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-m37-wu3-project-' . bin2hex(random_bytes(4));
$themeRoot = $projectRoot . DIRECTORY_SEPARATOR . 'themes';
mkdir($themeRoot, 0777, true);

try {
    $configuration = [
        'host' => $host,
        'port' => $port,
        'database' => $databaseName,
        'username' => $username,
        'password' => $password,
    ];
    (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install($configuration);

    $config = new Config($basePath . '/config');
    $reflection = new ReflectionClass($config);
    $items = $reflection->getProperty('items');
    $items->setAccessible(true);
    $configured = $items->getValue($config);
    foreach (['host', 'port', 'database', 'username', 'password'] as $key) {
        $configured['database']['connections']['mysql'][$key] = $configuration[$key];
    }
    $items->setValue($config, $configured);

    $database = new Database($config);
    $connection = $database->connection();
    $repository = new ThemeRepository($database);
    $manager = new ThemeManager($repository, $database, $projectRoot);
    $discovery = new ThemeDiscovery($themeRoot);
    $lifecycle = new ThemeLifecycle($discovery, $manager, $repository, $database);

    $makeTheme($themeRoot, 'alpha', 'Alpha', '1.0.0');
    $makeTheme($themeRoot, 'beta', 'Beta', '1.0.0');
    $makeTheme($themeRoot, 'nonfrontend', 'Non Frontend', '1.0.0', 'admin');
    mkdir($themeRoot . DIRECTORY_SEPARATOR . 'invalid', 0777, true);
    file_put_contents($themeRoot . DIRECTORY_SEPARATOR . 'invalid' . DIRECTORY_SEPARATOR . 'theme.json', '{');

    $alpha = array_values(array_filter(
        $discovery->discoverCatalog()['themes'],
        static fn (\Copot\Core\ThemeDefinition $definition): bool => $definition->id() === 'alpha'
    ))[0] ?? null;
    if ($alpha === null) {
        throw new RuntimeException('Healthy alpha fixture was not discovered.');
    }
    $manager->register($alpha);
    $manager->activate('alpha');
    $registryInsert($connection, 'stale', 'Stale', '0.1.0', 'frontend', 'themes/stale', false);

    $beforeCount = (int) $connection->query('SELECT COUNT(*) FROM themes')->fetchColumn();
    $beforeActive = (string) $repository->activeFrontend()['theme_id'];
    $inventory = $lifecycle->inventory();
    $afterCount = (int) $connection->query('SELECT COUNT(*) FROM themes')->fetchColumn();
    $assert($beforeCount === $afterCount && (string) $repository->activeFrontend()['theme_id'] === $beforeActive, 'Inventory read mutated registry or active state.');
    $assert(array_column($inventory['themes'], 'theme_id') === ['alpha', 'beta', 'invalid', 'nonfrontend', 'stale'], 'Inventory ordering is not deterministic.');
    $byId = array_column($inventory['themes'], null, 'theme_id');
    $assert($byId['alpha']['lifecycle_state'] === 'active' && $byId['alpha']['registration_status'] === 'registered', 'Active registered inventory state is incorrect.');
    $assert($byId['beta']['lifecycle_state'] === 'discovered' && $byId['beta']['registration_status'] === 'unregistered', 'Discovered unregistered inventory state is incorrect.');
    $assert($byId['invalid']['lifecycle_state'] === 'invalid' && $byId['invalid']['definition'] === null, 'Invalid inventory state is unsafe.');
    $assert($byId['nonfrontend']['lifecycle_state'] === 'invalid', 'Non-frontend inventory state is incorrect.');
    $assert($byId['stale']['lifecycle_state'] === 'stale' && $byId['stale']['discovery_status'] === 'missing', 'Stale inventory state is incorrect.');
    $assert($inventory['diagnostics'] === [], 'Unexpected catalog-level diagnostics were produced.');

    $lifecycle->activate('beta');
    $active = $repository->activeFrontendRows();
    $assert(count($active) === 1 && $active[0]['theme_id'] === 'beta', 'Discovered unregistered activation did not leave one active frontend Theme.');
    $assert($repository->findByThemeId('beta') !== null, 'Fresh activation did not register the target.');

    $betaPath = $themeRoot . DIRECTORY_SEPARATOR . 'beta' . DIRECTORY_SEPARATOR . 'theme.json';
    $betaManifest = json_decode((string) file_get_contents($betaPath), true, 512, JSON_THROW_ON_ERROR);
    $betaManifest['name'] = 'Beta Refreshed';
    $betaManifest['version'] = '2.0.0';
    file_put_contents($betaPath, json_encode($betaManifest, JSON_THROW_ON_ERROR));
    $lifecycle->activate('beta');
    $refreshed = $repository->findByThemeId('beta');
    $assert($refreshed['name'] === 'Beta Refreshed' && $refreshed['version'] === '2.0.0' && (int) $refreshed['is_active'] === 1, 'Already-active activation did not refresh its normalized registry snapshot idempotently.');

    $makeTheme($themeRoot, 'stale', 'Recovered Stale', '1.0.0');
    $lifecycle->activate('stale');
    $assert($repository->activeFrontend()['theme_id'] === 'stale', 'Fresh recovery of a stale registered Theme failed.');
    $makeTheme($themeRoot, 'gamma', 'Gamma', '1.0.0');

    foreach (['invalid', 'nonfrontend', 'missing'] as $target) {
        try {
            $lifecycle->activate($target);
            $assert(false, "Rejected target [{$target}] activated.");
        } catch (ThemeException) {
            $assert(true, "Rejected target [{$target}] was not contained.");
        }
    }
    $missingRootLifecycle = new ThemeLifecycle(new ThemeDiscovery($themeRoot . '-missing'), $manager, $repository, $database);
    try {
        $missingRootLifecycle->activate('stale');
        $assert(false, 'Unavailable Theme root allowed activation.');
    } catch (ThemeException) {
        $assert(true, 'Unavailable Theme root was rejected.');
    }

    $beforeFailure = $repository->findByThemeId('stale');
    $connection->exec("CREATE TRIGGER m37_wu3_fail_switch BEFORE UPDATE ON themes FOR EACH ROW BEGIN IF @m37_wu3_fail_switch = 1 AND OLD.is_active <> NEW.is_active THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'WU3 switch failure'; END IF; END");
    $connection->exec('SET @m37_wu3_fail_switch = 1');
    try {
        $lifecycle->activate('alpha');
        $assert(false, 'Database switch failure was not raised.');
    } catch (ThemeException) {
        $assert(true, 'Database switch failure was contained.');
    }
    $afterFailure = $repository->findByThemeId('stale');
    $assert($repository->activeFrontend()['theme_id'] === 'stale', 'Switch rollback did not preserve the previous active Theme.');
    $assert($afterFailure['name'] === $beforeFailure['name'] && $afterFailure['version'] === $beforeFailure['version'] && (int) $afterFailure['is_active'] === 1, 'Switch rollback did not preserve the previous registry state.');
    $connection->exec('SET @m37_wu3_fail_switch = 0');
    $connection->exec('DROP TRIGGER m37_wu3_fail_switch');

    $connection->exec("CREATE TRIGGER m37_wu3_fail_refresh BEFORE INSERT ON themes FOR EACH ROW BEGIN IF @m37_wu3_fail_refresh = 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'WU3 refresh failure'; END IF; END");
    $connection->exec('SET @m37_wu3_fail_refresh = 1');
    try {
        $lifecycle->activate('gamma');
        $assert(false, 'Database registry refresh failure was not raised.');
    } catch (ThemeException) {
        $assert(true, 'Database registry refresh failure was contained.');
    }
    $assert($repository->activeFrontend()['theme_id'] === 'stale' && $repository->findByThemeId('gamma') === null, 'Registry refresh rollback did not preserve prior active state.');
    $connection->exec('SET @m37_wu3_fail_refresh = 0');
    $connection->exec('DROP TRIGGER m37_wu3_fail_refresh');

    $unavailableInventory = $missingRootLifecycle->inventory();
    $assert($unavailableInventory['themes'] !== [] && array_values(array_unique(array_column($unavailableInventory['themes'], 'lifecycle_state'))) === ['unavailable'], 'Unavailable root did not mark registered rows unavailable.');
    $diagnosticsJson = json_encode($unavailableInventory, JSON_THROW_ON_ERROR);
    $assert(!str_contains($diagnosticsJson, $projectRoot) && !str_contains($diagnosticsJson, 'SQLSTATE') && !str_contains($diagnosticsJson, 'WU3'), 'Lifecycle diagnostics leaked paths or database details.');

    echo "M3.7 Work Unit 3 theme lifecycle passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase);
    $removeDirectory($projectRoot);
}
