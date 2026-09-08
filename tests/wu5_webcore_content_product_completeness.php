<?php

declare(strict_types=1);

use Copot\Core\Application;
use Copot\Core\Config;
use Copot\Core\ContentRepository;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\MediaFileInspector;
use Copot\Core\MediaFilesystemStorage;
use Copot\Core\MediaInUseException;
use Copot\Core\MediaLifecycleService;
use Copot\Core\MediaRepository;
use Copot\Core\MediaUploadService;
use Copot\Core\MediaUploadSource;
use Copot\Core\MediaUsageRepository;
use Copot\Core\Request;

$basePath = dirname(__DIR__);
chdir($basePath);
session_save_path(sys_get_temp_dir());
session_id('copot-wu5-content-' . bin2hex(random_bytes(5)));
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void { ++$assertions; if (!$condition) throw new RuntimeException($message); };
$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_wu5_content_' . bin2hex(random_bytes(6));
$quoted = '`' . str_replace('`', '``', $databaseName) . '`';
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec('CREATE DATABASE ' . $quoted . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$createdMedia = [];

try {
    (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install(['host' => $host, 'port' => $port, 'database' => $databaseName, 'username' => $username, 'password' => $password]);
    $_ENV['DB_DATABASE'] = $databaseName;
    putenv('DB_DATABASE=' . $databaseName);
    $config = new Config($basePath . '/config');
    $database = new Copot\Core\Database($config);
    $connection = $database->connection();

    $createActor = static function (string $email, array $permissions) use ($connection): int {
        $connection->prepare("INSERT INTO users (name,email,password_hash,status,created_at,updated_at) VALUES (:name,:email,'x','active',NOW(),NOW())")->execute(['name' => $email, 'email' => $email]);
        $userId = (int) $connection->lastInsertId();
        $slug = 'wu5-' . bin2hex(random_bytes(5));
        $connection->prepare('INSERT INTO roles (name,slug,created_at,updated_at) VALUES (:name,:slug,NOW(),NOW())')->execute(['name' => $slug, 'slug' => $slug]);
        $roleId = (int) $connection->lastInsertId();
        $connection->prepare('INSERT INTO user_roles (user_id,role_id) VALUES (:user,:role)')->execute(['user' => $userId, 'role' => $roleId]);
        foreach ($permissions as $permission) {
            $statement = $connection->prepare('INSERT INTO role_permissions (role_id,permission_id) SELECT :role,id FROM permissions WHERE slug=:permission');
            $statement->execute(['role' => $roleId, 'permission' => $permission]);
        }
        return $userId;
    };
    $fullUser = $createActor('wu5-full@example.test', ['admin.access', 'content.read', 'content.create', 'content.update', 'content.publish', 'content.delete', 'media.use']);
    $limitedUser = $createActor('wu5-limited@example.test', ['admin.access', 'content.update']);

    $app = new Application($basePath);
    $app->session()->start();
    require $basePath . '/routes/web.php';
    require $basePath . '/routes/auth.php';
    require $basePath . '/routes/admin.php';
    require $basePath . '/routes/content_admin.php';
    require $basePath . '/routes/media_admin.php';
    require $basePath . '/routes/system_manager.php';
    require $basePath . '/routes/admin_fallback.php';
    $setActor = static function (int $userId) use ($app): void { $app->auth()->logout(); $app->session()->set((string) $app->config()->get('auth.session_key', '_copot_user_id'), $userId); };
    $setActor($fullUser);

    $storage = new MediaFilesystemStorage($basePath . '/storage/media');
    $media = new MediaRepository($database);
    $usages = new MediaUsageRepository($database);
    $lifecycle = new MediaLifecycleService($database, $media, null, $usages, $storage);
    $upload = new MediaUploadService($database, $lifecycle, new MediaFileInspector(), $storage);
    $png = tempnam(sys_get_temp_dir(), 'copot-wu5-image-');
    file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
    $uploadImage = static function (string $title) use ($upload, $png, &$createdMedia): int {
        $id = $upload->upload(MediaUploadSource::fromArray(['name' => 'featured.png', 'type' => 'invalid', 'tmp_name' => $png, 'error' => UPLOAD_ERR_OK, 'size' => filesize($png)]), $title)->value();
        $createdMedia[] = $id;
        return $id;
    };
    $firstImage = $uploadImage('First featured image');
    $secondImage = $uploadImage('Second featured image');
    $documentId = $media->create(['kind' => 'document', 'original_filename' => 'manual.pdf', 'title' => 'Manual', 'storage_key' => bin2hex(random_bytes(16)) . '.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'byte_size' => 10, 'width' => null, 'height' => null])->value();

    $createForm = $app->run(new Request('GET', '/admin/content/create'));
    $assert($createForm->statusCode() === 200, 'Core Content create form is unavailable without Content Manager.');
    $createHtml = $createForm->body();
    $assert(str_contains($createHtml, '<select id="type" name="type">') && str_contains($createHtml, '<option value="page" selected>Page</option>') && str_contains($createHtml, '<option value="article">Article</option>'), 'Core Content type control is not the exact bounded Page/Article select.');
    $assert(!str_contains($createHtml, 'Featured Media ID') && str_contains($createHtml, 'data-core-content-media-picker') && str_contains($createHtml, 'Select media'), 'Core Content still exposes the raw Featured Media ID workflow.');
    $assert(str_contains($createHtml, 'admin-content-form-layout') && str_contains($createHtml, 'admin-content-form-sidebar'), 'Core Content does not use the accepted responsive two-column Admin grammar.');

    $selection = $app->run(new Request('GET', '/admin/media/select', ['kind' => 'image']));
    $selectionItems = json_decode($selection->body(), true, 512, JSON_THROW_ON_ERROR)['items'];
    $selectionIds = array_column($selectionItems, 'id');
    $assert($selection->statusCode() === 200 && in_array($firstImage, $selectionIds, true) && !in_array($documentId, $selectionIds, true), 'Core Media selector is not bounded to image Media.');
    $token = $app->session()->csrfToken();
    $invalidType = $app->run(new Request('POST', '/admin/content', [], ['_token' => $token, 'type' => 'landing', 'title' => 'Invalid type', 'body' => 'Body', 'featured_media_id' => '']));
    $assert($invalidType->statusCode() === 422, 'Forged invalid Content type was accepted.');
    $missingMedia = $app->run(new Request('POST', '/admin/content', [], ['_token' => $token, 'type' => 'page', 'title' => 'Missing media', 'body' => 'Body', 'featured_media_id' => '999999']));
    $assert($missingMedia->statusCode() === 422, 'Missing Featured Media was accepted.');
    $documentMedia = $app->run(new Request('POST', '/admin/content', [], ['_token' => $token, 'type' => 'page', 'title' => 'Document media', 'body' => 'Body', 'featured_media_id' => (string) $documentId]));
    $assert($documentMedia->statusCode() === 422, 'Non-image Featured Media was accepted.');

    $created = $app->run(new Request('POST', '/admin/content', [], ['_token' => $token, 'type' => 'page', 'title' => 'Featured page', 'slug' => 'featured-page', 'body' => 'Body', 'featured_media_id' => (string) $firstImage]));
    $assert($created->statusCode() === 302, 'Core Content create with Featured Media failed.');
    $content = new ContentRepository($database);
    $entry = $connection->query("SELECT id FROM content WHERE slug='featured-page'")->fetchColumn();
    $contentId = (int) $entry;
    $assert(count($usages->forConsumer('content', $contentId, 'featured_media')) === 1, 'Create did not register authoritative Featured Media usage.');
    try { $lifecycle->delete($firstImage); $assert(false, 'Referenced Featured Media was deleted.'); } catch (MediaInUseException) { $assert(true, 'Referenced Featured Media deletion was blocked.'); }

    $current = $content->findById($contentId);
    $replaced = $app->run(new Request('POST', '/admin/content/' . $contentId, [], ['_token' => $token, 'expected_updated_at' => $current->updatedAt(), 'type' => 'article', 'title' => 'Featured page', 'slug' => 'featured-page', 'body' => 'Body', 'featured_media_id' => (string) $secondImage]));
    $assert($replaced->statusCode() === 302 && $content->findById($contentId)?->featuredMediaId() === $secondImage, 'Featured Media replacement failed.');
    $firstUsage = $usages->forMedia($firstImage);
    $secondUsage = $usages->forConsumer('content', $contentId, 'featured_media');
    $assert($firstUsage === [] && count($secondUsage) === 1 && $secondUsage[0]->mediaId()->value() === $secondImage, 'Replacement did not synchronize the authoritative usage ledger.');

    $publish = $app->run(new Request('POST', '/admin/content/' . $contentId . '/publish', [], ['_token' => $token]));
    $assert($publish->statusCode() === 302 && $content->findById($contentId)?->isPublished(), 'Content publish behavior regressed.');
    $public = $app->run(new Request('GET', '/content/featured-page'));
    $assert($public->statusCode() === 200 && str_contains($public->body(), '/media/' . $secondImage), 'Public Featured Media delivery projection regressed.');
    $assert($app->run(new Request('GET', '/media/' . $secondImage))->statusCode() === 200, 'Core Media delivery for selected Featured Media failed.');
    $csrf = $app->run(new Request('POST', '/admin/content/' . $contentId . '/archive', [], ['_token' => 'invalid']));
    $assert($csrf->statusCode() === 419, 'Content CSRF protection regressed.');

    $setActor($limitedUser);
    $unchanged = $content->findById($contentId);
    $unchangedSave = $app->run(new Request('POST', '/admin/content/' . $contentId, [], ['_token' => $app->session()->csrfToken(), 'expected_updated_at' => $unchanged->updatedAt(), 'type' => 'article', 'title' => 'Updated without media use', 'slug' => 'featured-page', 'body' => 'Body', 'featured_media_id' => (string) $secondImage]));
    $assert($unchangedSave->statusCode() === 302 && $content->findById($contentId)?->featuredMediaId() === $secondImage, 'Unchanged Featured Media did not survive unrelated Content update without media.use.');
    $limitedCurrent = $content->findById($contentId);
    $limitedClear = $app->run(new Request('POST', '/admin/content/' . $contentId, [], ['_token' => $app->session()->csrfToken(), 'expected_updated_at' => $limitedCurrent->updatedAt(), 'type' => 'article', 'title' => 'Unauthorized clear', 'slug' => 'featured-page', 'body' => 'Body', 'featured_media_id' => '']));
    $assert($limitedClear->statusCode() === 422 && $content->findById($contentId)?->featuredMediaId() === $secondImage, 'Unauthorized Featured Media clear mutated Content.');

    $setActor($fullUser);
    $beforeStale = $content->findById($contentId);
    $freshSave = $app->run(new Request('POST', '/admin/content/' . $contentId, [], ['_token' => $app->session()->csrfToken(), 'expected_updated_at' => $beforeStale->updatedAt(), 'type' => 'article', 'title' => 'Fresh save', 'slug' => 'featured-page', 'body' => 'Body', 'featured_media_id' => (string) $secondImage]));
    $assert($freshSave->statusCode() === 302, 'Fresh Content update failed before stale-write check.');
    $stale = $app->run(new Request('POST', '/admin/content/' . $contentId, [], ['_token' => $app->session()->csrfToken(), 'expected_updated_at' => $beforeStale->updatedAt(), 'type' => 'article', 'title' => 'Stale clear', 'slug' => 'featured-page', 'body' => 'Body', 'featured_media_id' => '']));
    $assert($stale->statusCode() === 422 && $content->findById($contentId)?->featuredMediaId() === $secondImage && count($usages->forConsumer('content', $contentId, 'featured_media')) === 1, 'Stale write left Content and Media usage inconsistent.');
    $clearCurrent = $content->findById($contentId);
    $cleared = $app->run(new Request('POST', '/admin/content/' . $contentId, [], ['_token' => $app->session()->csrfToken(), 'expected_updated_at' => $clearCurrent->updatedAt(), 'type' => 'article', 'title' => 'Fresh save', 'slug' => 'featured-page', 'body' => 'Body', 'featured_media_id' => '']));
    $assert($cleared->statusCode() === 302 && $content->findById($contentId)?->featuredMediaId() === null && $usages->forMedia($secondImage) === [], 'Clear did not remove authoritative Featured Media usage.');
    $lifecycle->delete($secondImage);
    $createdMedia = array_values(array_filter($createdMedia, static fn (int $id): bool => $id !== $secondImage));
    $lifecycle->delete($firstImage);
    $createdMedia = array_values(array_filter($createdMedia, static fn (int $id): bool => $id !== $firstImage));
    $archive = $app->run(new Request('POST', '/admin/content/' . $contentId . '/archive', [], ['_token' => $app->session()->csrfToken()]));
    $assert($archive->statusCode() === 302 && $content->findById($contentId)?->isArchived(), 'Content archive behavior regressed.');

    $routeSource = (string) file_get_contents($basePath . '/routes/content_admin.php');
    $assert(str_contains($routeSource, '$contentModuleEnabled') && str_contains($routeSource, 'if ($contentModuleEnabled) {') && str_contains($routeSource, 'return;'), 'Core fallback no longer yields the canonical Content route to Content Manager.');
    @unlink($png);
    echo "WU5 Webcore Content product-completeness passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if (isset($lifecycle)) {
        foreach ($createdMedia as $id) {
            try { $lifecycle->delete($id); } catch (Throwable) { }
        }
    }
    if (isset($png) && is_file($png)) @unlink($png);
    try { $server->exec('DROP DATABASE IF EXISTS ' . $quoted); } catch (Throwable) { }
}
