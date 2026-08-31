<?php

declare(strict_types=1);

use Copot\Core\Application;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\MediaLifecycleService;
use Copot\Core\MediaRepository;
use Copot\Core\MediaUsageRepository;
use Copot\Core\Request;
use Copot\Core\Response;

$basePath = dirname(__DIR__);
chdir($basePath);
session_save_path(sys_get_temp_dir());
session_id('copot-wu2-' . bin2hex(random_bytes(5)));
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void { ++$assertions; if (!$condition) throw new RuntimeException($message); };
$status = static function (Response $response): int { $p = new ReflectionProperty($response, 'status'); $p->setAccessible(true); return (int) $p->getValue($response); };
$body = static function (Response $response): string { $p = new ReflectionProperty($response, 'content'); $p->setAccessible(true); return (string) $p->getValue($response); };
$location = static function (Response $response): string { $p = new ReflectionProperty($response, 'headers'); $p->setAccessible(true); return (string) (($p->getValue($response)['Location'] ?? '')); };

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$database = 'copot_wu2_' . bin2hex(random_bytes(6));
$quoted = '`' . str_replace('`', '``', $database) . '`';
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec('CREATE DATABASE ' . $quoted . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

try {
    (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install(compact('host', 'port', 'username', 'password') + ['database' => $database]);
    putenv('DB_DATABASE=' . $database); $_ENV['DB_DATABASE'] = $database;
    $app = new Application($basePath);
    $app->session()->start();
    require $basePath . '/routes/web.php';
    require $basePath . '/routes/auth.php';
    require $basePath . '/routes/admin.php';
    require $basePath . '/routes/content_admin.php';
    require $basePath . '/routes/media_admin.php';
    require $basePath . '/routes/system_manager.php';
    $app->moduleLoader()->loadListeners($app);
    $app->moduleLoader()->loadResolvers($app);
    $app->moduleLoader()->loadFrontendContextContributors($app);
    $app->moduleLoader()->loadRoutes($app);
    $app->frontendThemeContext()->freeze();
    require $basePath . '/routes/admin_fallback.php';
    $db = $app->database()->connection();
    $permissions = [];
    foreach (['admin.access', 'media.view', 'media.upload', 'media.use', 'media.edit', 'media.delete'] as $slug) {
        $s = $db->prepare('SELECT id FROM permissions WHERE slug = ?'); $s->execute([$slug]); $permissions[$slug] = (int) $s->fetchColumn(); $assert($permissions[$slug] > 0, "Missing {$slug} permission.");
    }
    $db->exec("INSERT INTO users (name,email,password_hash,status,created_at,updated_at) VALUES ('WU2','wu2@example.test','x','active',NOW(),NOW())"); $userId = (int) $db->lastInsertId();
    $db->exec("INSERT INTO roles (name,slug,created_at,updated_at) VALUES ('WU2 role','wu2-role',NOW(),NOW())"); $roleId = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_roles (user_id,role_id) VALUES (?,?)')->execute([$userId, $roleId]);
    foreach ($permissions as $permissionId) $db->prepare('INSERT INTO role_permissions (role_id,permission_id) VALUES (?,?)')->execute([$roleId, $permissionId]);
    $app->session()->set((string) $app->config()->get('auth.session_key', '_copot_user_id'), $userId);
    $mediaPath = $app->adminUrl()->childUrl('media'); $uploadPath = $app->adminUrl()->childUrl('media/upload');
    $assert($status($app->run(new Request('GET', $mediaPath))) === 200, 'Core Media Admin did not operate without the Media Module.');
    $inventoryHtml = $body($app->run(new Request('GET', $mediaPath)));
    $assert(str_contains($inventoryHtml, 'Core Media inventory') && str_contains($inventoryHtml, 'admin-page-frame'), 'Core inventory view was not rendered through the shared Page Frame.');
    $assert(substr_count($inventoryHtml, 'admin-page-frame__title') === 1, 'Core inventory still duplicates its shared Page Frame heading.');
    $assert(!str_contains($inventoryHtml, 'name="title"'), 'Core inventory still exposes baseline title editing.');
    $png = tempnam(sys_get_temp_dir(), 'copot-wu2-'); file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
    $request = new Request('POST', $uploadPath, [], ['_token' => $app->csrf()->token()], ['media' => ['name' => 'baseline.png', 'type' => 'bad', 'tmp_name' => $png, 'error' => UPLOAD_ERR_OK, 'size' => filesize($png)]]);
    $uploadResponse = $app->run($request); $assert($status($uploadResponse) === 302 && str_contains($location($uploadResponse), 'notice=uploaded'), 'Core upload failed.');
    $id = (int) $db->query('SELECT id FROM media ORDER BY id DESC LIMIT 1')->fetchColumn(); $assert($id > 0, 'Core upload did not persist Media.');
    $assert($db->query('SELECT title FROM media WHERE id = ' . $id)->fetchColumn() === 'baseline', 'Upload compatibility fallback did not derive the persisted title from the filename.');
    $assert($status($app->run(new Request('GET', $app->adminUrl()->childUrl('media/select')))) === 200, 'Generic Media selection failed.');
    $assert($status($app->run(new Request('GET', $app->url('/media/' . $id)))) === 200, 'Core original Media delivery failed.');
    $uploadHtml = $body($app->run(new Request('GET', $uploadPath)));
    $assert(str_contains($uploadHtml, 'admin-page-frame') && str_contains($uploadHtml, 'aria-label="Breadcrumb"') && str_contains($uploadHtml, '>Upload</button>'), 'Upload surface did not use the shared Page Frame hierarchy or accepted action label.');
    $assert(!str_contains($uploadHtml, 'name="title"') && !str_contains($uploadHtml, 'View Media'), 'Upload surface retained the removed title field or redundant return action.');
    $postUploadHtml = $body($app->run(new Request('GET', $mediaPath)));
    $assert(str_contains($postUploadHtml, 'data-media-preview-open') && str_contains($postUploadHtml, 'role="button"') && str_contains($postUploadHtml, 'admin-core-media.js') && str_contains($postUploadHtml, '>Upload</a>'), 'Core Media row activation, quick-preview surface, or accepted upload label is missing.');
    $assert(!str_contains($postUploadHtml, '<th scope="col">Action') && !str_contains($postUploadHtml, '>Preview</button>'), 'Core Media retained a dedicated action/preview column.');
    $assert($status($app->run(new Request('POST', $app->adminUrl()->childUrl("media/{$id}/title"), [], ['title' => 'not a baseline route']))) === 404, 'Core baseline retained title editing presentation.');
    $lifecycle = new MediaLifecycleService($app->database(), new MediaRepository($app->database()), null, new MediaUsageRepository($app->database()), new \Copot\Core\MediaFilesystemStorage($basePath . '/storage/media'));
    $lifecycle->registerUsage($id, 'test', 1, 'reference');
    $blocked = $app->run(new Request('POST', $app->adminUrl()->childUrl("media/{$id}/delete"), [], ['_token' => $app->csrf()->token()])); $assert($status($blocked) === 303 && str_contains(urldecode($location($blocked)), 'in use'), 'Referenced Media deletion was not blocked.');
    $lifecycle->removeUsage($id, 'test', 1, 'reference');
    $deleted = $app->run(new Request('POST', $app->adminUrl()->childUrl("media/{$id}/delete"), [], ['_token' => $app->csrf()->token()])); $assert($status($deleted) === 302, 'Unused Media deletion failed.');
    @unlink($png);
    $source = (string) file_get_contents($basePath . '/routes/media_admin.php'); $module = (string) file_get_contents($basePath . '/modules/media/routes.php');
    $assert(!str_contains($source, 'MediaProcessing') && !str_contains($source, 'MediaVariant') && !str_contains($source, 'MediaPending'), 'Core baseline acquired an extension dependency.');
    $assert(!str_contains($module, "adminNavigation()->add('Media'") && !str_contains($module, "get('/media/{id}'"), 'Media Module retained a competing baseline registration.');
    $coreScript = (string) file_get_contents($basePath . '/public/admin-assets/js/admin-core-media.js'); $coreCss = (string) file_get_contents($basePath . '/public/admin-assets/css/admin.css');
    $assert(str_contains($coreScript, "event.key !== 'Enter'") && str_contains($coreScript, "event.key !== ' '") && str_contains($coreScript, 'origin.focus'), 'Core Media row/preview keyboard and focus behavior is incomplete.');
    $assert(str_contains($coreScript, 'mediaDeleteEligible') && str_contains($coreScript, 'data-media-preview-close') && str_contains($coreCss, 'object-fit: contain'), 'Core Media preview eligibility or bounded aspect-ratio behavior is incomplete.');
    echo "Webcore Core Media Admin baseline passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $quoted);
}
