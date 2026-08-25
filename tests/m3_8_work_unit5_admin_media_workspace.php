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
session_id('copotm38wu5' . bin2hex(random_bytes(5)));
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$statusOf = static function (Response $response): int { $property = new ReflectionProperty($response, 'status'); $property->setAccessible(true); return (int) $property->getValue($response); };
$contentOf = static function (Response $response): string { $property = new ReflectionProperty($response, 'content'); $property->setAccessible(true); return (string) $property->getValue($response); };
$locationOf = static function (Response $response): string { $property = new ReflectionProperty($response, 'headers'); $property->setAccessible(true); return (string) (($property->getValue($response)['Location'] ?? '')); };

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_m38_wu5_' . bin2hex(random_bytes(6));
$databaseIdentifier = '`' . str_replace('`', '``', $databaseName) . '`';
$configuration = compact('host', 'port', 'username', 'password') + ['database' => $databaseName];
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$server->exec('CREATE DATABASE ' . $databaseIdentifier . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$temporaryFiles = [];
$createdStorageKeys = [];

try {
    (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install($configuration);
    $_ENV['DB_DATABASE'] = $databaseName;
    putenv('DB_DATABASE=' . $databaseName);
    $app = new Application($basePath);
    $app->session()->start();
    require $basePath . '/routes/web.php';
    require $basePath . '/routes/auth.php';
    require $basePath . '/routes/admin.php';
    foreach (['content', 'taxonomy', 'media'] as $moduleName) {
        $app->modules()->install($moduleName);
        $app->modules()->enable($moduleName);
    }
    $app->moduleLoader()->loadRoutes($app);
    require $basePath . '/routes/admin_fallback.php';
    $connection = $app->database()->connection();
    $assert(class_exists('MediaVariantKey'), 'Media route bootstrap did not load the variant key dependency.');

    $permissionIds = [];
    foreach (['admin.access', 'media.view', 'media.upload', 'media.edit', 'taxonomy.create', 'taxonomy.update', 'taxonomy.delete'] as $slug) {
        $statement = $connection->prepare('SELECT id FROM permissions WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $slug]);
        $permissionIds[$slug] = (int) $statement->fetchColumn();
        $assert($permissionIds[$slug] > 0, "Permission [{$slug}] was not provisioned.");
    }
    $createActor = static function (string $label, array $slugs) use ($connection, $permissionIds): int {
        $suffix = bin2hex(random_bytes(4));
        $connection->prepare("INSERT INTO users (name, email, password_hash, status, created_at, updated_at) VALUES (:name, :email, 'test', 'active', NOW(), NOW())")->execute(['name' => $label, 'email' => $label . '-' . $suffix . '@example.test']);
        $userId = (int) $connection->lastInsertId();
        $connection->prepare('INSERT INTO roles (name, slug, created_at, updated_at) VALUES (:name, :slug, NOW(), NOW())')->execute(['name' => $label . ' role', 'slug' => $label . '-' . $suffix]);
        $roleId = (int) $connection->lastInsertId();
        $connection->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$userId, $roleId]);
        foreach ($slugs as $slug) $connection->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)')->execute([$roleId, $permissionIds[$slug]]);
        return $userId;
    };
    $fullActor = $createActor('media-full', array_keys($permissionIds));
    $readActor = $createActor('media-read', ['admin.access', 'media.view']);
    $noReadActor = $createActor('media-none', ['admin.access']);
    $switch = static function (int $userId) use ($app): void {
        $app->auth()->logout();
        $app->session()->set((string) $app->config()->get('auth.session_key', '_copot_user_id'), $userId);
    };
    $mediaPath = $app->adminUrl()->childUrl('media');
    $uploadPath = $app->adminUrl()->childUrl('media/upload');
    $csrf = static fn () => $app->csrf()->token();

    $switch($fullActor);
    $navigation = $app->adminNavigation()->itemsFor($app->auth()->user());
    $labels = array_column($navigation, 'label');
    $assert(array_search('Content', $labels, true) < array_search('Media', $labels, true), 'Media navigation was not after Content.');
    $assert(array_search('Media', $labels, true) < array_search('Taxonomy', $labels, true), 'Media navigation was not before Taxonomy.');
    $assert(!in_array('Media', array_column($app->adminDashboard()->itemsFor($app->auth()->user()), 'title'), true), 'Media dashboard registration was added unexpectedly.');

    $assert($statusOf($app->run(new Request('GET', $mediaPath))) === 200, 'Media Admin workspace did not render.');
    $uploadPageHtml = $contentOf($app->run(new Request('GET', $uploadPath)));
    $assert(str_contains($uploadPageHtml, 'admin-panel__body') && str_contains($uploadPageHtml, 'admin-form'), 'Media upload form did not use the established Admin panel spacing structure.');
    $temporaryFiles[] = $source = tempnam(sys_get_temp_dir(), 'copot-wu5-');
    file_put_contents($source, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
    $upload = new Request('POST', $uploadPath, [], ['_token' => $csrf(), 'title' => 'A <hero> image'], ['media' => ['name' => 'unsafe/original.png', 'type' => 'image/png', 'tmp_name' => $source, 'error' => UPLOAD_ERR_OK, 'size' => filesize($source)]]);
    $uploadResponse = $app->run($upload);
    $assert($statusOf($uploadResponse) === 302 && str_contains($locationOf($uploadResponse), 'notice=uploaded'), 'Multipart upload did not use PRG.');
    $temporaryFiles[] = $invalidPdf = tempnam(sys_get_temp_dir(), 'copot-wu5-pdf-');
    file_put_contents($invalidPdf, "%PDF-1.4\nnot a complete PDF\n");
    $invalidPdfResponse = $app->run(new Request('POST', $uploadPath, [], ['_token' => $csrf(), 'title' => 'Invalid PDF'], ['media' => ['name' => 'invalid.pdf', 'type' => 'application/pdf', 'tmp_name' => $invalidPdf, 'error' => UPLOAD_ERR_OK, 'size' => filesize($invalidPdf)]]));
    $invalidPdfHtml = $contentOf($invalidPdfResponse);
    $assert($statusOf($invalidPdfResponse) === 422 && str_contains($invalidPdfHtml, 'The uploaded PDF is invalid or incomplete.') && !str_contains($invalidPdfHtml, 'PDF structure') && !str_contains($invalidPdfHtml, $invalidPdf), 'Invalid PDF upload did not return a clear sanitized validation message.');
    $mediaId = (int) $connection->query('SELECT id FROM media ORDER BY id DESC LIMIT 1')->fetchColumn();
    $assert($mediaId > 0, 'Uploaded Media identity was not persisted.');
    $createdStorageKeys[] = (string) $connection->query('SELECT storage_key FROM media WHERE id = ' . $mediaId)->fetchColumn();
    $mediaRepository = new MediaRepository($app->database());
    $mediaVariants = new MediaVariantRepository($app->database());
    $mediaUsages = new MediaUsageRepository($app->database());
    $mediaLifecycle = new MediaLifecycleService($app->database(), $mediaRepository, $mediaVariants, $mediaUsages);
    $mediaInspector = new MediaFileInspector();
    $mediaStorage = new MediaFilesystemStorage($basePath . '/storage/media');
    $mediaProcessing = new MediaProcessingService($app->database(), $mediaRepository, $mediaVariants, $mediaInspector, new MediaGdImageProcessor(), $mediaStorage, new MediaVariantFilesystemStorage($basePath . '/storage/media'));
    $mediaAdmin = new MediaAdmin($mediaRepository, new MediaUploadService($app->database(), $mediaLifecycle, $mediaInspector, $mediaStorage), $mediaLifecycle, $mediaProcessing);
    $fallbackImageId = $mediaAdmin->upload(['name' => 'holiday_photo.png', 'type' => 'image/png', 'tmp_name' => $source, 'error' => UPLOAD_ERR_OK, 'size' => filesize($source)], '   ');
    $createdStorageKeys[] = (string) $connection->query('SELECT storage_key FROM media WHERE id = ' . $fallbackImageId->value())->fetchColumn();
    $temporaryFiles[] = $fallbackPdf = tempnam(sys_get_temp_dir(), 'copot-wu5-fallback-pdf-');
    file_put_contents($fallbackPdf, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");
    $fallbackPdfId = $mediaAdmin->upload(['name' => 'press_kit.pdf', 'type' => 'application/pdf', 'tmp_name' => $fallbackPdf, 'error' => UPLOAD_ERR_OK, 'size' => filesize($fallbackPdf)], '');
    $createdStorageKeys[] = (string) $connection->query('SELECT storage_key FROM media WHERE id = ' . $fallbackPdfId->value())->fetchColumn();
    $assert($connection->query('SELECT title FROM media WHERE id = ' . $fallbackImageId->value())->fetchColumn() === 'holiday photo', 'Blank image title did not derive from the filename.');
    $assert($connection->query('SELECT title FROM media WHERE id = ' . $fallbackPdfId->value())->fetchColumn() === 'press kit', 'Blank PDF title did not derive from the filename.');
    try { $mediaAdmin->upload(['name' => '.png', 'type' => 'image/png', 'tmp_name' => $source, 'error' => UPLOAD_ERR_OK, 'size' => filesize($source)], '   '); $assert(false, 'Unusable filename fallback was accepted.'); } catch (MediaUploadValidationException) { $assert(true, 'Unusable filename fallback was rejected.'); }
    $documentId = $mediaLifecycle->create(['kind' => 'document', 'original_filename' => 'manual-guide.pdf', 'title' => 'Manual guide', 'storage_key' => 'manual-guide.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'byte_size' => 64, 'width' => null, 'height' => null])->value();
    $connection->exec("UPDATE media SET updated_at = '2026-01-01 00:00:00'");
    $workspace = $mediaRepository->workspace(['search' => 'original.png'], 24, 0);
    $assert($workspace['total'] === 1 && count($workspace['items']) === 1, 'Filename search/count did not match.');
    $assert($workspace['limit'] === 24, 'Media workspace page size was not fixed at 24.');
    $allWorkspace = $mediaRepository->workspace([], 24, 0);
    $expectedNewest = max($mediaId, $documentId, $fallbackImageId->value(), $fallbackPdfId->value());
    $assert($allWorkspace['total'] === 4 && $allWorkspace['items'][0]->id()->value() === $expectedNewest, 'Workspace ordering was not updated_at/id descending.');
    $assert($mediaRepository->workspace(['kind' => 'document'])['total'] === 2, 'Document kind filter did not match.');
    $assert($mediaRepository->workspace(['capability' => 'editable'])['total'] === 2 && $mediaRepository->workspace(['capability' => 'manage-only'])['total'] === 2, 'Capability filters did not partition editable/manage-only Media.');
    $assert($mediaRepository->workspace([], 1, 0)['items'][0]->id()->value() === $expectedNewest && $mediaRepository->workspace([], 1, 3)['items'][0]->id()->value() === min($mediaId, $documentId, $fallbackImageId->value(), $fallbackPdfId->value()), 'Bounded pagination was not deterministic.');
    $assert($mediaAdmin->preset($mediaId, 'square')->outputFormat() === 'webp' && $mediaAdmin->preset($mediaId, 'square')->resizeWidth() === 640 && $mediaAdmin->preset($mediaId, 'square')->quality() === 82, 'Square preset mapping was incorrect.');
    $assert($mediaAdmin->preset($mediaId, 'landscape')->resizeWidth() === 1280 && $mediaAdmin->preset($mediaId, 'landscape')->resizeHeight() === 720, 'Landscape preset mapping was incorrect.');
    $assert($mediaAdmin->preset($mediaId, 'contain')->fit() === 'contain' && $mediaAdmin->preset($mediaId, 'contain')->outputFormat() === null && $mediaAdmin->preset($mediaId, 'contain')->quality() === null, 'Contain preset did not preserve source/default format quality.');
    try { $mediaAdmin->preset($mediaId, 'arbitrary'); $assert(false, 'Arbitrary processing preset was accepted.'); } catch (MediaProcessingValidationException) { $assert(true, 'Arbitrary processing preset rejection passed.'); }

    $html = $contentOf($app->run(new Request('GET', $mediaPath, ['q' => 'original.png', 'kind' => 'image', 'capability' => 'editable'])));
    $assert(str_contains($html, 'admin-media-filters') && str_contains($html, 'admin-media-grid-panel') && str_contains($html, 'admin-media-grid') && str_contains($html, 'data-media-card') && str_contains($html, 'role="button"') && str_contains($html, 'admin-panel__body'), 'Media workspace did not use the accepted interactive grid/card presentation structure.');
    $assert(str_contains($html, 'Find media') && str_contains($html, 'Showing 1 item matching the current filters.') && str_contains($html, 'media-filter-help') && str_contains($html, 'Clear filters'), 'Media filter guidance and result context are incomplete.');
    $assert(str_contains($html, 'A &lt;hero&gt; image') && str_contains($html, '/media/' . $mediaId), 'Media output was not escaped or controlled.');
    $assert(!str_contains($html, 'storage/media') && !str_contains($html, 'storage_key') && !str_contains($html, '.tmp'), 'Media workspace leaked storage details.');
    $assert(str_contains($html, 'Uploaded') && str_contains($html, 'admin-media-card__separator'), 'Media cards do not present compact uploaded metadata.');
    $assert(str_contains($html, 'data-media-usage-count="0"') && str_contains($html, 'data-media-variant-count="0"') && str_contains($html, 'Editable'), 'Media cards did not expose authoritative usage, variant, and eligibility evidence.');
    $assert(str_contains($html, 'admin-media-preview') && str_contains($html, 'role="dialog"') && str_contains($html, 'aria-modal="true"') && str_contains($html, 'Previous media') && str_contains($html, 'Next media') && str_contains($html, 'Save changes') && !str_contains($html, 'Open public view') && !str_contains($html, 'Square') && !str_contains($html, 'Landscape') && !str_contains($html, 'Contain'), 'Media workspace preview actions do not match the focused Media metadata boundary.');
    $assert(!str_contains($html, 'admin-media-card__actions') && !str_contains($html, 'Download') && !str_contains($html, '/download'), 'Media Manager exposed a removed card action or Admin download affordance.');
    $adminCss = (string) file_get_contents($basePath . '/public/admin-assets/css/admin.css');
    $adminJs = (string) file_get_contents($basePath . '/public/admin-assets/js/admin-media.js');
    $assert(str_contains($adminCss, '.admin-media-grid') && str_contains($adminCss, 'repeat(auto-fill, minmax(min(100%, 18rem), 18rem))') && str_contains($adminCss, 'justify-content: start;') && str_contains($adminCss, 'overflow-wrap: anywhere;'), 'Media responsive grid styles do not retain bounded cards.');
    $assert(str_contains($adminJs, "event.key === 'Escape'") && str_contains($adminJs, 'setBackgroundInactive') && str_contains($adminJs, 'lockedScrollY') && str_contains($adminJs, 'origin.focus'), 'Media preview interaction script lacks focus, escape, or background-inert handling.');
    $assert(str_contains($adminJs, "addDetail('Usage'") && str_contains($adminJs, "addDetail('Variants'") && str_contains($adminJs, 'data-preview-delete-help'), 'Media preview did not consume authoritative usage and variant evidence.');
    $noMatchHtml = $contentOf($app->run(new Request('GET', $mediaPath, ['q' => 'no-such-media'] )));
    $assert(str_contains($noMatchHtml, 'No matching media') && str_contains($noMatchHtml, 'Try changing the search or filters') && str_contains($noMatchHtml, 'Clear filters'), 'Media no-match recovery did not remain accessible.');
    $assert($statusOf($app->run(new Request('POST', $app->adminUrl()->childUrl('media/' . $mediaId . '/title'), [], ['title' => 'No CSRF']))) === 419, 'Title mutation did not reject missing CSRF.');
    $titleResponse = $app->run(new Request('POST', $app->adminUrl()->childUrl('media/' . $mediaId . '/title'), [], ['_token' => $csrf(), 'title' => 'Updated title']));
    $assert($statusOf($titleResponse) === 302 && str_contains($locationOf($titleResponse), 'notice=title-updated'), 'Title update did not use PRG.');
    $assert($statusOf($app->run(new Request('POST', $app->adminUrl()->childUrl('media/0/title'), [], ['_token' => $csrf(), 'title' => 'Invalid']))) === 404, 'Non-positive Media ID was accepted.');
    $assert($statusOf($app->run(new Request('POST', $app->adminUrl()->childUrl('media/' . $mediaId . '/process'), [], ['_token' => $csrf(), 'preset' => 'arbitrary']))) === 422, 'Arbitrary process request was not rejected.');
    $assert($statusOf($app->run(new Request('POST', $app->adminUrl()->childUrl('media/' . $mediaId . '/delete'), [], ['_token' => $csrf()]))) === 403, 'Admin delete route did not enforce media.delete authorization.');

    $switch($readActor);
    $readHtml = $contentOf($app->run(new Request('GET', $mediaPath)));
    $assert(!str_contains($readHtml, 'Upload media') && !str_contains($readHtml, 'Save changes') && !str_contains($readHtml, 'Process'), 'Read-only Media presentation exposed management actions.');
    $switch($noReadActor);
    $assert($statusOf($app->run(new Request('GET', $mediaPath))) === 403, 'Media list access without media.view was not denied.');
    $assert($statusOf($app->run(new Request('GET', $uploadPath))) === 403, 'Upload access without media.upload was not denied.');

    echo "M3.8 Work Unit 5 Admin Media workspace passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    foreach ($temporaryFiles as $temporaryFile) if (is_string($temporaryFile) && is_file($temporaryFile)) @unlink($temporaryFile);
    if (class_exists('MediaFilesystemStorage')) {
        $cleanupStorage = new MediaFilesystemStorage($basePath . '/storage/media');
        foreach ($createdStorageKeys as $storageKey) $cleanupStorage->delete($storageKey);
        $mediaRoot = $basePath . '/storage/media';
        if (is_dir($mediaRoot)) {
            $directories = [];
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($mediaRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
                if ($entry->isDir()) $directories[] = $entry->getPathname();
            }
            foreach ($directories as $directory) if (@rmdir($directory)) {
            }
            @rmdir($mediaRoot);
        }
    }
    $server->exec('DROP DATABASE IF EXISTS ' . $databaseIdentifier);
}
