<?php

declare(strict_types=1);

use Copot\Core\Admin\AdminUrl;
use Copot\Core\Application;
use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\Redirect\RedirectContract;
use Copot\Core\Request;
use Copot\Core\Response;
use Copot\Core\View;

$basePath = dirname(__DIR__);
chdir($basePath);
session_save_path(sys_get_temp_dir());
session_id('copotm310wu3' . bin2hex(random_bytes(5)));
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$value = static fn (Response $response, string $property): mixed => (new ReflectionProperty(Response::class, $property))->getValue($response);
$status = static fn (Response $response): int => (int) $value($response, 'status');
$content = static fn (Response $response): string => (string) $value($response, 'content');
$location = static fn (Response $response): ?string => $value($response, 'headers')['Location'] ?? null;

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_m310_wu3_' . bin2hex(random_bytes(6));
$identifier = '`' . str_replace('`', '``', $databaseName) . '`';
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
$server->exec('CREATE DATABASE ' . $identifier . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

try {
    $configuration = ['host' => $host, 'port' => $port, 'database' => $databaseName, 'username' => $username, 'password' => $password];
    (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install($configuration);
    $_ENV['DB_DATABASE'] = $databaseName;
    putenv('DB_DATABASE=' . $databaseName);
    $database = new Database(new Config($basePath . '/config'));
    $connection = $database->connection();
    $connection->exec("INSERT INTO modules (name,title,version,path,status,installed_at,created_at,updated_at) VALUES ('redirects','Redirect Manager','0.1.0','modules/redirects','enabled',NOW(),NOW(),NOW())");
    $adminHash = password_hash('admin-password', PASSWORD_DEFAULT);
    $deniedHash = password_hash('denied-password', PASSWORD_DEFAULT);
    $connection->prepare('INSERT INTO users (name,email,password_hash,status,created_at,updated_at) VALUES (:name,:email,:hash,\'active\',NOW(),NOW())')->execute(['name' => 'Redirect Administrator', 'email' => 'redirect-admin@example.test', 'hash' => $adminHash]);
    $adminUserId = (int) $connection->lastInsertId();
    $connection->prepare('INSERT INTO user_roles (user_id,role_id) SELECT :user_id,id FROM roles WHERE slug = \'admin\'')->execute(['user_id' => $adminUserId]);
    $connection->prepare('INSERT INTO roles (name,slug,created_at,updated_at) VALUES (\'Denied Redirect User\',\'redirect-denied\',NOW(),NOW())')->execute();
    $deniedRoleId = (int) $connection->lastInsertId();
    $connection->prepare('INSERT INTO users (name,email,password_hash,status,created_at,updated_at) VALUES (:name,:email,:hash,\'active\',NOW(),NOW())')->execute(['name' => 'Denied Redirect User', 'email' => 'redirect-denied@example.test', 'hash' => $deniedHash]);
    $deniedUserId = (int) $connection->lastInsertId();
    $connection->prepare('INSERT INTO user_roles (user_id,role_id) VALUES (:user_id,:role_id)')->execute(['user_id' => $deniedUserId, 'role_id' => $deniedRoleId]);
    $connection->prepare("INSERT INTO role_permissions (role_id,permission_id) SELECT :role_id,id FROM permissions WHERE slug = 'admin.access'")->execute(['role_id' => $deniedRoleId]);

    $app = new Application($basePath);
    $app->session()->start();
    require $basePath . '/routes/web.php';
    require $basePath . '/routes/auth.php';
    require $basePath . '/routes/admin.php';
    $app->moduleLoader()->loadResolvers($app);
    $app->moduleLoader()->loadRoutes($app);
    require $basePath . '/routes/admin_fallback.php';

    $adminPath = $app->adminUrl()->childUrl('redirects');
    $assert($app->auth()->attempt('redirect-admin@example.test', 'admin-password'), 'Administrator authentication failed.');
    $csrf = $app->session()->csrfToken();

    $listEmpty = $app->router()->dispatch(new Request('GET', $adminPath));
    $assert($status($listEmpty) === 200 && str_contains($content($listEmpty), 'Redirects'), 'Redirect list did not render.');
    $assert(str_contains($content($listEmpty), 'Create Redirect'), 'Redirect list omitted create action.');
    $assert(str_contains($content($listEmpty), $adminPath . '/create'), 'Redirect list did not use configured Admin URL.');

    $create = $app->router()->dispatch(new Request('POST', $adminPath, [], ['_token' => $csrf, 'source_path' => '/legacy', 'target' => '/new', 'status_code' => '302']));
    $assert($status($create) === 302 && $location($create) === $adminPath . '?notice=created', 'Redirect create did not use PRG.');
    $redirectId = (int) $connection->query("SELECT id FROM redirects WHERE source_path = '/legacy'")->fetchColumn();
    $assert($redirectId > 0, 'Redirect create did not persist the record.');

    $list = $app->router()->dispatch(new Request('GET', $adminPath, ['notice' => 'created']));
    $html = $content($list);
    $assert(str_contains($html, '/legacy') && str_contains($html, '/new') && str_contains($html, '302'), 'Redirect list omitted persisted values.');
    $assert(str_contains($html, $adminPath . '/' . $redirectId . '/edit') && str_contains($html, $adminPath . '/' . $redirectId . '/delete'), 'Redirect list actions used incorrect paths.');

    $invalid = $app->router()->dispatch(new Request('POST', $adminPath, [], ['_token' => $csrf, 'source_path' => '/unsafe', 'target' => '<script>alert(1)</script>', 'status_code' => '302']));
    $invalidHtml = $content($invalid);
    $assert($status($invalid) === 422 && str_contains($invalidHtml, '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($invalidHtml, '<script>alert(1)</script>'), 'Invalid form values were not safely escaped and preserved.');

    $createNoCsrf = $app->router()->dispatch(new Request('POST', $adminPath, [], ['source_path' => '/no-csrf', 'target' => '/new']));
    $assert($status($createNoCsrf) === 419, 'Create did not require CSRF.');

    $edit = $app->router()->dispatch(new Request('GET', $adminPath . '/' . $redirectId . '/edit'));
    $editHtml = $content($edit);
    preg_match('/name="expected_updated_at" value="([^"]+)"/', $editHtml, $tokenMatch);
    $expectedUpdatedAt = html_entity_decode((string) ($tokenMatch[1] ?? ''), ENT_QUOTES, 'UTF-8');
    $assert($status($edit) === 200 && $expectedUpdatedAt !== '', 'Edit form omitted the optimistic concurrency token.');
    $update = $app->router()->dispatch(new Request('POST', $adminPath . '/' . $redirectId . '/edit', [], ['_token' => $csrf, 'expected_updated_at' => $expectedUpdatedAt, 'source_path' => '/legacy-updated', 'target' => 'https://example.test/final', 'status_code' => '301']));
    $assert($status($update) === 302 && $location($update) === $adminPath . '?notice=updated', 'Redirect edit did not use PRG.');
    $current = $connection->query("SELECT * FROM redirects WHERE id = {$redirectId}")->fetch(PDO::FETCH_ASSOC);
    $assert($current['source_path'] === '/legacy-updated' && $current['target'] === 'https://example.test/final' && (int) $current['status_code'] === 301, 'Redirect edit did not persist all fields.');

    $service = new RedirectService($database, new RedirectRepository($database), $app->adminUrl()->baseUrl());
    $service->update($redirectId, ['source_path' => '/changed-elsewhere', 'target' => '/elsewhere', 'status_code' => 302], (string) $current['updated_at']);
    $stale = $app->router()->dispatch(new Request('POST', $adminPath . '/' . $redirectId . '/edit', [], ['_token' => $csrf, 'expected_updated_at' => $expectedUpdatedAt, 'source_path' => '/stale', 'target' => '/stale-target', 'status_code' => '302']));
    $assert($status($stale) === 422 && str_contains($content($stale), 'changed elsewhere'), 'Stale edit was not controlled.');
    $assert($connection->query("SELECT source_path FROM redirects WHERE id = {$redirectId}")->fetchColumn() === '/changed-elsewhere', 'Stale edit overwrote current state.');

    $deleteNoCsrf = $app->router()->dispatch(new Request('POST', $adminPath . '/' . $redirectId . '/delete', [], []));
    $assert($status($deleteNoCsrf) === 419, 'Delete did not require CSRF.');
    $delete = $app->router()->dispatch(new Request('POST', $adminPath . '/' . $redirectId . '/delete', [], ['_token' => $csrf]));
    $assert($status($delete) === 302 && $location($delete) === $adminPath . '?notice=deleted', 'Redirect delete did not use PRG.');
    $assert((int) $connection->query("SELECT COUNT(*) FROM redirects WHERE id = {$redirectId}")->fetchColumn() === 0, 'Redirect delete did not hard-delete the record.');

    $missing = $app->router()->dispatch(new Request('GET', $adminPath . '/999999/edit'));
    $assert($status($missing) === 404, 'Missing Redirect edit did not return 404.');

    $app->auth()->logout();
    $unauthorized = $app->router()->dispatch(new Request('POST', $adminPath, [], ['_token' => 'invalid', 'source_path' => '/blocked', 'target' => '/blocked-target']));
    $assert($status($unauthorized) === 302 && $location($unauthorized) === $app->adminUrl()->baseUrl(), 'Unauthenticated mutation did not redirect to Admin login.');
    $assert($app->auth()->attempt('redirect-denied@example.test', 'denied-password'), 'Denied-user authentication failed.');
    $denied = $app->router()->dispatch(new Request('POST', $adminPath, [], ['_token' => 'invalid', 'source_path' => '/blocked', 'target' => '/blocked-target']));
    $assert($status($denied) === 403, 'Permission denial did not occur before CSRF validation.');

    $configuredDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-m310-wu3-admin-' . bin2hex(random_bytes(4));
    mkdir($configuredDirectory, 0777, true);
    file_put_contents($configuredDirectory . '/admin.php', "<?php\nreturn ['path' => 'dapur', 'permission' => 'admin.access'];\n");
    $configuredUrl = new AdminUrl(new Config($configuredDirectory));
    $view = new View($basePath . '/resources/views');
    $renderView = static function (string $viewName, array $data) use ($basePath): string {
        extract($data, EXTR_SKIP);
        ob_start();
        require $basePath . '/modules/redirects/views/admin/' . $viewName . '.php';
        return (string) ob_get_clean();
    };
    $configuredHtml = $renderView('form', ['formAction' => '/dapur/redirects', 'formMode' => 'create', 'redirect' => ['source_path' => '<x>', 'target' => '<y>', 'status_code' => 302], 'errors' => [], 'csrfToken' => 'token', 'adminUrl' => static fn (string $path = ''): string => $configuredUrl->childUrl($path), 'heading' => 'Create Redirect', 'submitLabel' => 'Create Redirect']);
    $assert(str_contains($configuredHtml, 'action="/dapur/redirects"') && str_contains($configuredHtml, '/dapur/redirects'), 'Configured Admin path was not preserved in the Redirect form.');
    $assert(str_contains($configuredHtml, '&lt;x&gt;') && str_contains($configuredHtml, '&lt;y&gt;'), 'Configured-path form did not escape values.');

    echo "M3.10 WU3 Redirect Admin workspace passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $identifier);
}
