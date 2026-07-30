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
session_id('copotm37wu4admin' . bin2hex(random_bytes(5)));
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$statusOf = static fn (Response $response): int => (int) (new ReflectionProperty(Response::class, 'status'))->getValue($response);
$contentOf = static fn (Response $response): string => (string) (new ReflectionProperty(Response::class, 'content'))->getValue($response);
$locationOf = static fn (Response $response): string => (string) ((new ReflectionProperty(Response::class, 'headers'))->getValue($response)['Location'] ?? '');

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_m37_wu4_admin_' . bin2hex(random_bytes(6));
$databaseIdentifier = '`' . str_replace('`', '``', $databaseName) . '`';
$configuration = compact('host', 'port', 'username', 'password') + ['database' => $databaseName];
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec('CREATE DATABASE ' . $databaseIdentifier . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

try {
    (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install($configuration);
    $_ENV['DB_DATABASE'] = $databaseName;
    putenv('DB_DATABASE=' . $databaseName);
    $app = new Application($basePath);
    $app->session()->start();
    require $basePath . '/routes/web.php';
    require $basePath . '/routes/auth.php';
    require $basePath . '/routes/admin.php';
    $app->modules()->install('theme-manager');
    $app->modules()->enable('theme-manager');
    $app->moduleLoader()->loadRoutes($app);
    require $basePath . '/routes/admin_fallback.php';
    $db = $app->database()->connection();
    $permissionIds = [];
    foreach (['admin.access', 'themes.manage'] as $permission) {
        $statement = $db->prepare('SELECT id FROM permissions WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $permission]);
        $permissionIds[$permission] = (int) $statement->fetchColumn();
    }
    $createActor = static function (string $label, array $permissions) use ($db, $permissionIds): int {
        $suffix = bin2hex(random_bytes(4));
        $db->prepare('INSERT INTO users (name,email,password_hash,status,created_at,updated_at) VALUES (:name,:email,"test","active",NOW(),NOW())')->execute(['name' => $label, 'email' => $label . '-' . $suffix . '@example.test']);
        $userId = (int) $db->lastInsertId();
        $db->prepare('INSERT INTO roles (name,slug,created_at,updated_at) VALUES (:name,:slug,NOW(),NOW())')->execute(['name' => $label, 'slug' => $label . '-' . $suffix]);
        $roleId = (int) $db->lastInsertId();
        $db->prepare('INSERT INTO user_roles (user_id,role_id) VALUES (:user_id,:role_id)')->execute(['user_id' => $userId, 'role_id' => $roleId]);
        foreach ($permissions as $permission) {
            $db->prepare('INSERT INTO role_permissions (role_id,permission_id) VALUES (:role_id,:permission_id)')->execute(['role_id' => $roleId, 'permission_id' => $permissionIds[$permission]]);
        }
        return $userId;
    };
    $full = $createActor('wu4-full', ['admin.access', 'themes.manage']);
    $adminOnly = $createActor('wu4-admin-only', ['admin.access']);
    $themesOnly = $createActor('wu4-themes-only', ['themes.manage']);
    $switch = static function (int $userId) use ($app): void {
        $app->auth()->logout();
        $app->session()->set((string) $app->config()->get('auth.session_key', '_copot_user_id'), $userId);
    };
    $url = $app->adminUrl()->childUrl('themes');
    $token = static fn (): string => $app->session()->csrfToken();

    $guest = $app->run(new Request('GET', $url));
    $assert($statusOf($guest) === 302 && $locationOf($guest) === $app->adminUrl()->baseUrl(), 'Unauthenticated Theme request did not redirect to configured Admin login.');
    $switch($adminOnly);
    $assert($statusOf($app->run(new Request('GET', $url))) === 403, 'Admin-only user reached Theme workspace.');
    $switch($themesOnly);
    $assert($statusOf($app->run(new Request('GET', $url))) === 403, 'Theme-only user reached Theme workspace.');
    $switch($full);
    $navigation = $app->adminNavigation()->itemsFor($app->auth()->user());
    $assert(array_column($navigation, 'label') === ['Dashboard', 'Themes'], 'Theme navigation was not permission filtered or deterministically ordered.');

    $workspace = $app->run(new Request('GET', $url));
    $html = $contentOf($workspace);
    $assert($statusOf($workspace) === 200 && str_contains($html, 'Theme inventory') && str_contains($html, 'default'), 'Theme workspace did not render the default inventory.');
    $assert(str_contains($html, 'No screenshot') && str_contains($html, 'Activate Default'), 'Theme screenshot placeholder or activation action was not rendered.');
    $assert(!str_contains($html, $basePath) && !str_contains($html, 'CREATE TABLE') && !str_contains($html, 'Exception'), 'Theme workspace leaked internal diagnostics.');

    $before = (int) $db->query('SELECT COUNT(*) FROM themes')->fetchColumn();
    $getMutationProbe = $app->run(new Request('GET', $url));
    $assert($statusOf($getMutationProbe) === 200 && (int) $db->query('SELECT COUNT(*) FROM themes')->fetchColumn() === $before, 'GET Theme inventory mutated registry state.');
    $assert($statusOf($app->run(new Request('POST', $app->adminUrl()->childUrl('themes/default/activate'), [], []))) === 419, 'Missing CSRF was not rejected.');
    $assert($statusOf($app->run(new Request('POST', $app->adminUrl()->childUrl('themes/default/activate'), [], ['_token' => $token(), 'theme_id' => ['default']]))) === 422, 'Array Theme ID was not rejected after CSRF.');
    $activated = $app->run(new Request('POST', $app->adminUrl()->childUrl('themes/default/activate'), [], ['_token' => $token(), 'theme_id' => 'default']));
    $assert($statusOf($activated) === 302 && str_contains($locationOf($activated), 'notice=activated'), 'Valid Theme activation did not use PRG.');
    $assert((string) $db->query("SELECT theme_id FROM themes WHERE is_active = 1 AND type = 'frontend' LIMIT 1")->fetchColumn() === 'default', 'Theme activation did not use the lifecycle path.');
    $assert($statusOf($app->run(new Request('GET', $app->adminUrl()->childUrl('themes/default/screenshot')))) === 404, 'Missing screenshot did not fail closed.');

    echo "M3.7 Work Unit 4 Theme Admin workspace passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $databaseIdentifier);
}
