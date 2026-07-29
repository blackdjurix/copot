<?php

declare(strict_types=1);

use Copot\Core\Application;
use Copot\Core\Config;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\Request;
use Copot\Core\Response;

$basePath = dirname(__DIR__);
chdir($basePath);
session_save_path(sys_get_temp_dir());
session_id('copotm36wu4workspace' . bin2hex(random_bytes(5)));
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

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_m36_wu4_workspace_' . bin2hex(random_bytes(6));
$databaseIdentifier = '`' . str_replace('`', '``', $databaseName) . '`';
$configuration = compact('host', 'port', 'databaseName', 'username', 'password');
$configuration['database'] = $databaseName;

$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
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
    $app->modules()->install('navigation');
    $app->modules()->enable('navigation');
    $app->moduleLoader()->loadRoutes($app);
    require $basePath . '/routes/admin_fallback.php';

    $db = $app->database()->connection();
    $permissionIds = [];
    foreach (['admin.access', 'navigation.manage'] as $permission) {
        $statement = $db->prepare('SELECT id FROM permissions WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $permission]);
        $permissionIds[$permission] = (int) $statement->fetchColumn();
    }
    $createActor = static function (string $label, array $permissions) use ($db, $permissionIds): int {
        $suffix = bin2hex(random_bytes(4));
        $db->prepare('INSERT INTO users (name, email, password_hash, status, created_at, updated_at) VALUES (:name, :email, :hash, "active", NOW(), NOW())')->execute(['name' => $label, 'email' => $label . '-' . $suffix . '@example.test', 'hash' => 'test']);
        $userId = (int) $db->lastInsertId();
        $db->prepare('INSERT INTO roles (name, slug, created_at, updated_at) VALUES (:name, :slug, NOW(), NOW())')->execute(['name' => $label . ' role', 'slug' => $label . '-' . $suffix]);
        $roleId = (int) $db->lastInsertId();
        $db->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')->execute(['user_id' => $userId, 'role_id' => $roleId]);
        foreach ($permissions as $permission) {
            $db->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)')->execute(['role_id' => $roleId, 'permission_id' => $permissionIds[$permission]]);
        }
        return $userId;
    };
    $actor = $createActor('wu4-workspace', ['admin.access', 'navigation.manage']);
    $switch = static function (int $userId) use ($app): void {
        $app->auth()->logout();
        $app->session()->set((string) $app->config()->get('auth.session_key', '_copot_user_id'), $userId);
    };
    $switch($actor);
    $navigationUrl = $app->adminUrl()->childUrl('navigation');
    $token = static fn (): string => $app->session()->csrfToken();

    $empty = $app->run(new Request('GET', $navigationUrl));
    $assert($statusOf($empty) === 200 && str_contains($contentOf($empty), 'No navigation menus yet'), 'Empty menu workspace did not render.');

    $created = $app->run(new Request('POST', $navigationUrl, [], ['_token' => $token(), 'name' => 'Primary <Menu>', 'slug' => 'Primary Menu']));
    $assert($statusOf($created) === 302, 'Menu creation did not use PRG.');
    $menuId = (int) $db->query("SELECT id FROM navigation_menus WHERE slug = 'primary-menu' LIMIT 1")->fetchColumn();
    $assert($menuId > 0, 'Menu creation did not persist.');
    $menuUpdate = $app->run(new Request('POST', $app->adminUrl()->childUrl("navigation/{$menuId}/edit"), [], ['_token' => $token(), 'name' => 'Updated Menu', 'slug' => 'updated-menu']));
    $assert($statusOf($menuUpdate) === 302 && (string) $db->query("SELECT name FROM navigation_menus WHERE id = {$menuId}")->fetchColumn() === 'Updated Menu', 'Menu update failed.');

    $itemsUrl = $app->adminUrl()->childUrl("navigation/{$menuId}/items");
    $itemsProbe = $app->run(new Request('GET', $itemsUrl));
    $assert($statusOf($itemsProbe) === 200, 'Item workspace route did not match: ' . $itemsUrl . ' status ' . $statusOf($itemsProbe));
    $root = $app->run(new Request('POST', $itemsUrl, [], ['_token' => $token(), 'label' => 'Root <item>', 'target_mode' => 'custom', 'custom_url' => '/root', 'is_visible' => '1']));
    $assert($statusOf($root) === 302, 'Custom item creation failed.');
    $rootId = (int) $db->query("SELECT id FROM navigation_items WHERE menu_id = {$menuId} AND label = 'Root <item>' LIMIT 1")->fetchColumn();
    $child = $app->run(new Request('POST', $itemsUrl, [], ['_token' => $token(), 'label' => 'Child', 'parent_id' => (string) $rootId, 'target_mode' => 'provider', 'target_kind' => 'content', 'target_reference' => 'published-page', 'is_visible' => '1']));
    $assert($statusOf($child) === 302, 'Provider child creation failed.');
    $childId = (int) $db->query("SELECT id FROM navigation_items WHERE menu_id = {$menuId} AND label = 'Child' LIMIT 1")->fetchColumn();

    $workspace = $app->run(new Request('GET', $itemsUrl));
    $html = $contentOf($workspace);
    $assert($statusOf($workspace) === 200 && str_contains($html, 'Root &lt;item&gt;') && str_contains($html, 'content:published-page'), 'Hierarchical item workspace did not escape or display targets.');
    $editRoot = $app->run(new Request('GET', $app->adminUrl()->childUrl("navigation/{$menuId}/items/{$rootId}/edit")));
    $editHtml = $contentOf($editRoot);
    $assert($statusOf($editRoot) === 200 && !str_contains($editHtml, '<option value="' . $rootId . '"'), 'Parent choices did not exclude self/descendant candidates.');

    $second = $app->run(new Request('POST', $itemsUrl, [], ['_token' => $token(), 'label' => 'Second', 'target_mode' => 'custom', 'custom_url' => '#second', 'is_visible' => '0']));
    $assert($statusOf($second) === 302, 'Second root item creation failed.');
    $secondId = (int) $db->query("SELECT id FROM navigation_items WHERE menu_id = {$menuId} AND label = 'Second' LIMIT 1")->fetchColumn();
    $reorder = $app->run(new Request('POST', $app->adminUrl()->childUrl("navigation/{$menuId}/items/reorder"), [], ['_token' => $token(), 'item_ids' => [(string) $secondId, (string) $rootId]]));
    $assert($statusOf($reorder) === 302, 'Exact sibling reorder failed.');
    $firstId = (int) $db->query("SELECT id FROM navigation_items WHERE menu_id = {$menuId} AND parent_id IS NULL ORDER BY sort_order ASC, id ASC LIMIT 1")->fetchColumn();
    $assert($firstId === $secondId, 'Sibling reorder did not persist exact order.');

    $update = $app->run(new Request('POST', $app->adminUrl()->childUrl("navigation/{$menuId}/items/{$secondId}/edit"), [], ['_token' => $token(), 'label' => 'Updated', 'target_mode' => 'custom', 'custom_url' => '/updated']));
    $assert($statusOf($update) === 302, 'Item update failed.');
    $deleteChild = $app->run(new Request('POST', $app->adminUrl()->childUrl("navigation/{$menuId}/items/{$childId}/delete"), [], ['_token' => $token()]));
    $assert($statusOf($deleteChild) === 302, 'Item deletion failed.');
    $deleteMenu = $app->run(new Request('POST', $app->adminUrl()->childUrl("navigation/{$menuId}/delete"), [], ['_token' => $token()]));
    $assert($statusOf($deleteMenu) === 302 && (int) $db->query("SELECT COUNT(*) FROM navigation_menus WHERE id = {$menuId}")->fetchColumn() === 0, 'Menu deletion did not cascade or redirect.');

    echo "M3.6 Work Unit 4 Navigation workspace passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $databaseIdentifier);
}
