<?php

declare(strict_types=1);

use Copot\Core\Application;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\Request;
use Copot\Core\Response;

$basePath = dirname(__DIR__);
chdir($basePath);
session_save_path(sys_get_temp_dir());
session_id('copotm36wu4security' . bin2hex(random_bytes(5)));
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void { $assertions++; if (!$condition) { throw new RuntimeException($message); } };
$statusOf = static fn (Response $response): int => (int) (new ReflectionProperty(Response::class, 'status'))->getValue($response);
$host = (string) Env::get('DB_HOST', '127.0.0.1'); $port = (int) Env::get('DB_PORT', '3306'); $username = (string) Env::get('DB_USERNAME', 'root'); $password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_m36_wu4_security_' . bin2hex(random_bytes(6)); $databaseIdentifier = '`' . str_replace('`', '``', $databaseName) . '`'; $configuration = ['host' => $host, 'port' => $port, 'database' => $databaseName, 'username' => $username, 'password' => $password];
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); $server->exec('CREATE DATABASE ' . $databaseIdentifier . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install($configuration); $_ENV['DB_DATABASE'] = $databaseName; putenv('DB_DATABASE=' . $databaseName);
    $app = new Application($basePath); $app->session()->start(); require $basePath . '/routes/web.php'; require $basePath . '/routes/auth.php'; require $basePath . '/routes/admin.php'; $app->modules()->install('navigation'); $app->modules()->enable('navigation'); $app->moduleLoader()->loadRoutes($app); require $basePath . '/routes/admin_fallback.php';
    $db = $app->database()->connection(); $permissionIds = [];
    foreach (['admin.access', 'navigation.manage'] as $permission) { $statement = $db->prepare('SELECT id FROM permissions WHERE slug = :slug LIMIT 1'); $statement->execute(['slug' => $permission]); $permissionIds[$permission] = (int) $statement->fetchColumn(); }
    $createActor = static function (string $label, array $permissions) use ($db, $permissionIds): int { $suffix = bin2hex(random_bytes(4)); $db->prepare('INSERT INTO users (name,email,password_hash,status,created_at,updated_at) VALUES (:name,:email,"test","active",NOW(),NOW())')->execute(['name' => $label, 'email' => $label . '-' . $suffix . '@example.test']); $uid = (int) $db->lastInsertId(); $db->prepare('INSERT INTO roles (name,slug,created_at,updated_at) VALUES (:name,:slug,NOW(),NOW())')->execute(['name' => $label, 'slug' => $label . '-' . $suffix]); $rid = (int) $db->lastInsertId(); $db->prepare('INSERT INTO user_roles (user_id,role_id) VALUES (:user_id,:role_id)')->execute(['user_id' => $uid, 'role_id' => $rid]); foreach ($permissions as $permission) { $db->prepare('INSERT INTO role_permissions (role_id,permission_id) VALUES (:role_id,:permission_id)')->execute(['role_id' => $rid, 'permission_id' => $permissionIds[$permission]]); } return $uid; };
    $full = $createActor('wu4-full', ['admin.access', 'navigation.manage']); $adminOnly = $createActor('wu4-admin-only', ['admin.access']); $switch = static function (int $id) use ($app): void { $app->auth()->logout(); $app->session()->set((string) $app->config()->get('auth.session_key', '_copot_user_id'), $id); }; $url = $app->adminUrl()->childUrl('navigation');
    $switch($adminOnly); $denied = $app->run(new Request('POST', $url, [], [])); $assert($statusOf($denied) === 403, 'navigation.manage denial did not happen before CSRF.');
    $switch($full); foreach ([[], ['_token' => 'invalid']] as $post) { $response = $app->run(new Request('POST', $url, [], $post)); $assert($statusOf($response) === 419, 'Missing or invalid CSRF did not return 419.'); }
    $malformed = $app->run(new Request('POST', $url, [], ['_token' => $app->session()->csrfToken(), 'name' => ['bad'], 'slug' => 'bad'])); $assert($statusOf($malformed) === 422, 'Malformed menu payload did not return 422.');
    $invalidRoute = $app->run(new Request('GET', $app->adminUrl()->childUrl('navigation/not-an-id/edit'))); $assert($statusOf($invalidRoute) === 404, 'Malformed menu identifier did not return 404.');
    $invalidReorder = $app->run(new Request('POST', $app->adminUrl()->childUrl('navigation/1/items/reorder'), [], ['_token' => $app->session()->csrfToken(), 'item_ids' => ['bad']])); $assert($statusOf($invalidReorder) === 404 || $statusOf($invalidReorder) === 422, 'Malformed reorder was not controlled.');
    echo "M3.6 Work Unit 4 Navigation security passed ({$assertions} assertions)." . PHP_EOL;
} finally { $server->exec('DROP DATABASE IF EXISTS ' . $databaseIdentifier); }
