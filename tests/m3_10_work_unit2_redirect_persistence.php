<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\Application;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\ModuleManager;
use Copot\Core\ModuleRepository;
use Copot\Core\ModuleDiscovery;
use Copot\Core\Redirect\RedirectContract;
use Copot\Core\Request;

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
$rejects = static function (callable $operation, string $message) use ($assert): void {
    try {
        $operation();
        $assert(false, $message);
    } catch (InvalidArgumentException) {
        $assert(true, $message);
    }
};
$rejectsStale = static function (callable $operation, string $message) use ($assert): void {
    try {
        $operation();
        $assert(false, $message);
    } catch (RedirectStaleWriteException) {
        $assert(true, $message);
    }
};

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_m310_wu2_' . bin2hex(random_bytes(6));
$identifier = '`' . str_replace('`', '``', $databaseName) . '`';
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$server->exec('CREATE DATABASE ' . $identifier . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

try {
    $configuration = ['host' => $host, 'port' => $port, 'database' => $databaseName, 'username' => $username, 'password' => $password];
    (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install($configuration);
    $_ENV['DB_DATABASE'] = $databaseName;
    putenv('DB_DATABASE=' . $databaseName);
    $database = new Database(new Config($basePath . '/config'));
    $connection = $database->connection();
    $repository = new RedirectRepository($database);
    $service = new RedirectService($database, $repository);

    $column = $connection->query("SHOW FULL COLUMNS FROM redirects WHERE Field = 'source_path'")->fetch(PDO::FETCH_ASSOC);
    $index = $connection->query("SHOW INDEX FROM redirects WHERE Key_name = 'uq_redirects_source_path'")->fetch(PDO::FETCH_ASSOC);
    $assert(($column['Type'] ?? null) === 'varchar(512)', 'Redirect source column is not VARCHAR(512).');
    $assert(($column['Collation'] ?? null) === 'utf8mb4_bin', 'Redirect source collation is not binary/case-sensitive.');
    $assert(is_array($index), 'Redirect source unique key is missing.');
    $assert((int) $connection->query("SELECT COUNT(*) FROM permissions WHERE slug = 'redirects.manage'")->fetchColumn() === 1, 'Redirect permission is missing.');
    $assert((int) $connection->query("SELECT COUNT(*) FROM role_permissions INNER JOIN roles ON roles.id = role_permissions.role_id INNER JOIN permissions ON permissions.id = role_permissions.permission_id WHERE roles.slug = 'admin' AND permissions.slug = 'redirects.manage'")->fetchColumn() === 1, 'Administrator redirect permission is missing.');

    $first = $service->create(['source_path' => '/old-page/', 'target' => '/new-page', 'status_code' => 301]);
    $assert($first->sourcePath() === '/old-page' && $first->statusCode() === 301, '301 redirect create/normalization failed.');
    $second = $service->create(['source_path' => '/temporary', 'target' => 'https://example.test/final']);
    $assert($second->statusCode() === 302, 'Default redirect status is not 302.');
    $assert($service->findById($first->id())?->target() === '/new-page', 'Find by ID failed.');
    $assert($service->findBySource('/old-page/')?->id() === $first->id(), 'Find by normalized source failed.');

    $caseUpper = $service->create(['source_path' => '/Case', 'target' => '/case-destination']);
    $caseLower = $service->create(['source_path' => '/case', 'target' => '/lower-destination']);
    $assert($caseUpper->id() !== $caseLower->id(), 'Case-sensitive source identity was not preserved.');

    $duplicateRejected = false;
    try {
        $service->create(['source_path' => '/old-page', 'target' => '/another']);
    } catch (InvalidArgumentException) {
        $duplicateRejected = true;
    }
    $assert($duplicateRejected, 'Duplicate normalized source was accepted.');
    foreach ([['source_path' => '/content/old', 'target' => '/new'], ['source_path' => '/install/x', 'target' => '/new'], ['source_path' => '/bad?x=1', 'target' => '/new']] as $data) {
        $rejects(fn () => $service->create($data), 'Invalid/reserved source was accepted.');
    }
    $rejects(fn () => $service->create(['source_path' => '/bad-target', 'target' => 'javascript:alert(1)']), 'Invalid target was accepted.');
    $rejects(fn () => $service->create(['source_path' => '/bad-status', 'target' => '/new', 'status_code' => 307]), 'Unsupported status was accepted.');

    $rejects(fn () => $service->create(['source_path' => '/self', 'target' => '/self?x=1']), 'Self redirect was accepted.');
    $managedTargetRejected = false;
    try {
        $service->create(['source_path' => '/managed-source', 'target' => '/old-page']);
    } catch (InvalidArgumentException) {
        $managedTargetRejected = true;
    }
    $assert($managedTargetRejected, 'Target that is already managed was accepted.');
    $incomingRejected = false;
    try {
        $service->create(['source_path' => '/new-page', 'target' => '/terminal']);
    } catch (InvalidArgumentException) {
        $incomingRejected = true;
    }
    $assert($incomingRejected, 'Source targeted by an existing redirect was accepted.');

    $updated = $service->update($second->id(), ['source_path' => '/temporary', 'target' => '/temporary-final', 'status_code' => 301], $second->updatedAt());
    $assert($updated->target() === '/temporary-final' && $updated->statusCode() === 301, 'Redirect update failed.');
    $stale = $second->updatedAt();
    $fresh = $service->findById($second->id());
    $service->update($second->id(), ['source_path' => '/temporary', 'target' => '/fresh-final', 'status_code' => 302], $fresh?->updatedAt() ?? '');
    $rejectsStale(fn () => $service->update($second->id(), ['source_path' => '/temporary', 'target' => '/stale-final', 'status_code' => 302], $stale), 'Stale update was accepted.');
    $assert($service->findById($second->id())?->target() === '/fresh-final', 'Stale update overwrote the current row.');

    $deleteTarget = $service->create(['source_path' => '/delete-me', 'target' => '/delete-target']);
    $service->delete($deleteTarget->id(), $deleteTarget->updatedAt());
    $assert($service->findById($deleteTarget->id()) === null, 'Hard delete failed.');

    $resolver = new RedirectResolver($repository);
    $resolved = $resolver->resolve(new Request('GET', '/old-page'));
    $responseReflection = new ReflectionClass($resolved);
    $statusProperty = $responseReflection->getProperty('status');
    $statusProperty->setAccessible(true);
    $headersProperty = $responseReflection->getProperty('headers');
    $headersProperty->setAccessible(true);
    $assert($resolved !== null && $statusProperty->getValue($resolved) === 301, 'Persisted resolver status failed.');
    $assert($resolved !== null && $headersProperty->getValue($resolved)['Location'] === '/new-page', 'Persisted resolver target failed.');
    $assert($resolver->resolve(new Request('GET', '/missing')) === null, 'Unknown source did not return null.');
    $assert($resolver->resolve(new Request('GET', '/case')) !== null && $resolver->resolve(new Request('GET', '/Case')) !== null, 'Exact case-sensitive resolver lookup failed.');
    $assert($resolver->resolve(new Request('POST', '/old-page')) === null, 'Resolver accepted non-GET request.');

    $discovery = new ModuleDiscovery($basePath . '/modules');
    $modules = [];
    foreach ($discovery->discover() as $module) {
        $modules[$module->name()] = $module;
    }
    $redirectModule = $modules['redirects'] ?? null;
    $assert($redirectModule !== null && $redirectModule->resolver() === 'resolver.php', 'Redirect resolver manifest contribution was not discovered.');
    $assert($redirectModule !== null && $redirectModule->permissions()[0]['slug'] === 'redirects.manage', 'Redirect module permission metadata is incorrect.');
    $moduleManager = new ModuleManager($discovery, new ModuleRepository($database));
    $moduleManager->install('redirects');
    $moduleManager->enable('redirects');
    $moduleRow = $connection->query("SELECT status FROM modules WHERE name = 'redirects'")->fetchColumn();
    $assert($moduleRow === 'enabled', 'Redirect module install/enable lifecycle failed.');
    $enabledApp = new Application($basePath);
    $enabledApp->moduleLoader()->loadResolvers($enabledApp);
    $enabledResult = $enabledApp->router()->dispatchResult(new Request('GET', '/old-page'));
    $assert($statusProperty->getValue($enabledResult->response()) === 301, 'Enabled Redirect module did not register its resolver.');
    $moduleManager->disable('redirects');
    $disabledApp = new Application($basePath);
    $disabledApp->moduleLoader()->loadResolvers($disabledApp);
    $disabledResult = $disabledApp->router()->dispatchResult(new Request('GET', '/old-page'));
    $assert(!$disabledResult->routeMatched() && $statusProperty->getValue($disabledResult->response()) === 404, 'Disabled Redirect module contributed a resolver.');
    $moduleManager->enable('redirects');
    $assert(count($repository->all()) >= 4, 'Redirect persistence rows were not retained.');

    $upgrade = file_get_contents($basePath . '/database/upgrades/m3_10_redirect_manager.sql');
    $assert(is_string($upgrade) && str_contains($upgrade, 'CREATE TABLE IF NOT EXISTS redirects'), 'Existing-install upgrade artifact is missing.');
    foreach (array_filter(array_map('trim', explode(';', (string) $upgrade))) as $statement) {
        $connection->exec($statement);
        $connection->exec($statement);
    }
    $assert((int) $connection->query("SELECT COUNT(*) FROM permissions WHERE slug = 'redirects.manage'")->fetchColumn() === 1, 'Existing-install upgrade was not idempotent for permission provisioning.');
    $assert((int) $connection->query("SELECT COUNT(*) FROM role_permissions INNER JOIN roles ON roles.id = role_permissions.role_id INNER JOIN permissions ON permissions.id = role_permissions.permission_id WHERE roles.slug = 'admin' AND permissions.slug = 'redirects.manage'")->fetchColumn() === 1, 'Existing-install upgrade was not idempotent for Administrator mapping.');
    $assert(str_contains((string) file_get_contents($basePath . '/build/package_manifest.php'), "'modules/redirects'"), 'Redirect module is missing from package manifest.');
    $assert(str_contains((string) file_get_contents($basePath . '/app/Core/InstallerFinalizer.php'), "'redirects'"), 'Redirect module is missing from fresh-install baseline activation.');

    echo "M3.10 WU2 redirect persistence passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $identifier);
}
