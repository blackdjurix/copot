<?php

declare(strict_types=1);

use Copot\Core\Application;
use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\ModuleDiscovery;
use Copot\Core\ModuleManager;
use Copot\Core\ModuleRepository;
use Copot\Core\Request;
use Copot\Core\Response;
use Copot\Core\Redirect\RedirectContract;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');
foreach (['Redirect.php', 'RedirectExceptions.php', 'RedirectRepository.php', 'RedirectService.php', 'RedirectResolver.php'] as $file) {
    require_once $basePath . '/modules/redirects/Services/' . $file;
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$responseValue = static function (Response $response, string $property): mixed {
    return (new ReflectionProperty(Response::class, $property))->getValue($response);
};
$status = static fn (Response $response): int => (int) $responseValue($response, 'status');
$location = static fn (Response $response): ?string => $responseValue($response, 'headers')['Location'] ?? null;
$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$names = [];
$createDatabase = static function (string $prefix) use (&$names, $server): string {
    $name = $prefix . bin2hex(random_bytes(6));
    $names[] = $name;
    $server->exec('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    return $name;
};
$install = static function (string $name) use ($basePath, $host, $port, $username, $password): Database {
    (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install(['host' => $host, 'port' => $port, 'database' => $name, 'username' => $username, 'password' => $password]);
    $_ENV['DB_DATABASE'] = $name;
    putenv('DB_DATABASE=' . $name);
    return new Database(new Config($basePath . '/config'));
};
$buildApp = static function (Database $database) use ($basePath): Application {
    $app = new Application($basePath);
    $app->session()->start();
    require $basePath . '/routes/web.php';
    require $basePath . '/routes/auth.php';
    require $basePath . '/routes/admin.php';
    $app->moduleLoader()->loadResolvers($app);
    return $app;
};

try {
    $fresh = $install($createDatabase('copot_m310_wu4_fresh_'));
    $connection = $fresh->connection();
    $assert((int) $connection->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'redirects'")->fetchColumn() === 1, 'Fresh schema omitted redirects table.');
    $assert((string) $connection->query("SELECT COLLATION_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'redirects' AND column_name = 'source_path'")->fetchColumn() === 'utf8mb4_bin', 'Fresh schema did not preserve binary source identity.');
    $assert((int) $connection->query("SELECT COUNT(*) FROM permissions WHERE slug = 'redirects.manage'")->fetchColumn() === 1, 'Fresh schema omitted redirects.manage.');
    $assert((int) $connection->query("SELECT COUNT(*) FROM role_permissions rp INNER JOIN roles r ON r.id = rp.role_id INNER JOIN permissions p ON p.id = rp.permission_id WHERE r.slug = 'admin' AND p.slug = 'redirects.manage'")->fetchColumn() === 1, 'Fresh schema omitted Administrator redirect permission.');
    $connection->exec("INSERT INTO modules (name,title,version,path,status,installed_at,created_at,updated_at) VALUES ('redirects','Redirect Manager','0.1.0','modules/redirects','enabled',NOW(),NOW(),NOW())");
    $repository = new RedirectRepository($fresh);
    $service = new RedirectService($fresh, $repository);
    $permanent = $service->create(['source_path' => '/legacy-301', 'target' => '/destination', 'status_code' => 301]);
    $temporary = $service->create(['source_path' => '/legacy-302', 'target' => 'https://example.test/final', 'status_code' => 302]);
    $upper = $service->create(['source_path' => '/Case', 'target' => '/case-upper']);
    $lower = $service->create(['source_path' => '/case', 'target' => '/case-lower']);
    $assert($upper->id() !== $lower->id(), 'Case-sensitive source identity regressed.');
    $rejected = false;
    try { $service->create(['source_path' => '/chain', 'target' => '/legacy-301']); } catch (InvalidArgumentException) { $rejected = true; }
    $assert($rejected, 'Internal redirect chain was accepted.');
    $app = $buildApp($fresh);
    $app->router()->get('/exact', static fn (): Response => Response::html('exact route'));
    $app->router()->get('/content/{slug}', static fn (): Response => Response::html('owned 404', 404));
    $assert($status($app->run(new Request('GET', '/exact'))) === 200, 'Exact route did not win over redirect resolution.');
    $handler404 = $app->run(new Request('GET', '/content/missing'));
    $assert($status($handler404) === 404 && $responseValue($handler404, 'content') === 'owned 404', 'Handler-generated 404 fell through to redirects.');
    $resolved301 = $app->run(new Request('GET', '/legacy-301'));
    $assert($status($resolved301) === 301 && $location($resolved301) === '/destination', 'Persisted internal 301 did not resolve end to end.');
    $resolved302 = $app->run(new Request('GET', '/legacy-302'));
    $assert($status($resolved302) === 302 && $location($resolved302) === 'https://example.test/final', 'Persisted external 302 did not resolve end to end.');
    $assert($status($app->run(new Request('GET', '/unknown-source'))) === 404, 'Unknown unmatched GET did not retain normal 404.');
    $assert($status($app->run(new Request('POST', '/legacy-301'))) === 404, 'Unmatched non-GET resolved through Redirect Manager.');
    $connection->exec("INSERT INTO redirects (source_path,target,status_code,created_at,updated_at) VALUES ('/dapur/stale','/blocked',302,NOW(),NOW())");
    $configured = new RedirectResolver($repository, '/dapur');
    $assert($configured->resolve(new Request('GET', '/dapur/stale')) === null, 'Configured Admin namespace was intercepted from persisted data.');
    foreach (['/install/stale', '/admin-assets/stale', '/content/stale'] as $reserved) {
        $assert($configured->resolve(new Request('GET', $reserved)) === null, 'Reserved namespace was intercepted: ' . $reserved);
    }
    $manager = new ModuleManager(new ModuleDiscovery($basePath . '/modules'), new ModuleRepository($fresh));
    $manager->disable('redirects');
    $disabledApp = $buildApp($fresh);
    $assert($status($disabledApp->run(new Request('GET', '/legacy-301'))) === 404, 'Disabled Redirect module contributed a resolver.');
    $manager->enable('redirects');

    $legacy = $install($createDatabase('copot_m310_wu4_upgrade_'));
    $legacyConnection = $legacy->connection();
    $legacyConnection->exec("INSERT INTO roles (name,slug,created_at,updated_at) VALUES ('Retained legacy role','retained-legacy',NOW(),NOW())");
    $legacyConnection->exec("DELETE rp FROM role_permissions rp INNER JOIN permissions p ON p.id = rp.permission_id WHERE p.slug = 'redirects.manage'");
    $legacyConnection->exec("DELETE FROM permissions WHERE slug = 'redirects.manage'");
    $legacyConnection->exec('DROP TABLE redirects');
    $upgrade = (string) file_get_contents($basePath . '/database/upgrades/m3_10_redirect_manager.sql');
    foreach (array_filter(array_map('trim', explode(';', $upgrade))) as $statement) {
        $legacyConnection->exec($statement);
        $legacyConnection->exec($statement);
    }
    $assert((int) $legacyConnection->query("SELECT COUNT(*) FROM roles WHERE slug = 'retained-legacy'")->fetchColumn() === 1, 'Existing unrelated data was not retained by upgrade.');
    $assert((int) $legacyConnection->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'redirects'")->fetchColumn() === 1, 'Existing-install upgrade did not create redirects table.');
    $assert((int) $legacyConnection->query("SELECT COUNT(*) FROM permissions WHERE slug = 'redirects.manage'")->fetchColumn() === 1, 'Existing-install upgrade did not converge permission.');
    $assert((int) $legacyConnection->query("SELECT COUNT(*) FROM role_permissions rp INNER JOIN roles r ON r.id = rp.role_id INNER JOIN permissions p ON p.id = rp.permission_id WHERE r.slug = 'admin' AND p.slug = 'redirects.manage'")->fetchColumn() === 1, 'Existing-install upgrade did not converge Administrator mapping.');
    $legacyManager = new ModuleManager(new ModuleDiscovery($basePath . '/modules'), new ModuleRepository($legacy));
    $legacyManager->install('redirects');
    $legacyManager->enable('redirects');
    $assert($legacyConnection->query("SELECT status FROM modules WHERE name = 'redirects'")->fetchColumn() === 'enabled', 'Existing-install module lifecycle did not converge.');

    $package = $basePath . '/dist/copot-v0.12.0.zip';
    $contents = (string) file_get_contents($package);
    foreach (['modules/redirects/module.json', 'modules/redirects/resolver.php', 'modules/redirects/routes.php', 'modules/redirects/views/admin/list.php', 'modules/redirects/views/admin/form.php', 'database/upgrades/m3_10_redirect_manager.sql'] as $entry) {
        $assert(str_contains($contents, $entry), 'Package omitted Redirect Manager artifact: ' . $entry);
    }
    $assert($permanent->id() > 0 && $temporary->id() > 0, 'Redirect fixtures did not persist.');
    echo "M3.10 WU4 redirect closure passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    foreach ($names as $name) {
        $server->exec('DROP DATABASE IF EXISTS `' . $name . '`');
    }
}
