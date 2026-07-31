<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\ModuleDiscovery;
use Copot\Core\ModuleManager;
use Copot\Core\ModuleRepository;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');
foreach (['MediaId', 'Media', 'MediaVariant', 'MediaUsage', 'MediaRepository', 'MediaVariantRepository', 'MediaUsageRepository', 'MediaLifecycleService'] as $file) {
    require_once $basePath . '/modules/media/Services/' . $file . '.php';
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_m38_wu2_' . bin2hex(random_bytes(6));
$quoted = '`' . str_replace('`', '``', $databaseName) . '`';
$configuration = ['host' => $host, 'port' => $port, 'database' => $databaseName, 'username' => $username, 'password' => $password];
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec('CREATE DATABASE ' . $quoted . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

$executeScript = static function (PDO $connection, string $sql): void {
    foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\R|$)/', $sql) ?: [])) as $statement) $connection->exec($statement);
};

try {
    (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install($configuration);
    $connection = new PDO("mysql:host={$host};port={$port};dbname={$databaseName};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $config = new Config($basePath . '/config');
    $reflection = new ReflectionClass($config);
    $items = $reflection->getProperty('items'); $items->setAccessible(true);
    $configured = $items->getValue($config);
    foreach (['host' => $host, 'port' => $port, 'database' => $databaseName, 'username' => $username, 'password' => $password] as $key => $value) $configured['database']['connections']['mysql'][$key] = $value;
    $items->setValue($config, $configured);
    $database = new Database($config);
    $connection = $database->connection();
    $moduleManager = new ModuleManager(new ModuleDiscovery($basePath . '/modules'), new ModuleRepository($database));
    $moduleManager->install('media'); $moduleManager->enable('media');
    $assert((string) $connection->query("SELECT status FROM modules WHERE name = 'media'")->fetchColumn() === 'enabled', 'Media module was not activated through ModuleManager.');
    $assert((int) $connection->query("SELECT COUNT(*) FROM permissions WHERE slug LIKE 'media.%'")->fetchColumn() === 5, 'Fresh schema omitted Media permissions.');
    $assert((int) $connection->query("SELECT COUNT(*) FROM role_permissions INNER JOIN roles ON roles.id = role_permissions.role_id INNER JOIN permissions ON permissions.id = role_permissions.permission_id WHERE roles.slug = 'admin' AND permissions.slug LIKE 'media.%'")->fetchColumn() === 5, 'Fresh schema omitted admin Media mappings.');

    $upgrade = file_get_contents($basePath . '/database/upgrades/m3_8_media_library.sql');
    $executeScript($connection, (string) $upgrade); $executeScript($connection, (string) $upgrade);
    $assert((int) $connection->query('SELECT COUNT(*) FROM media')->fetchColumn() === 0, 'Upgrade was not idempotent.');
    $assert((int) $connection->query("SELECT COUNT(*) FROM permissions WHERE slug LIKE 'media.%'")->fetchColumn() === 5, 'Upgrade duplicated Media permissions.');
    $columns = [];
    foreach ($connection->query("SELECT table_name, column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name IN ('media', 'media_variants', 'media_usages')") as $row) $columns[$row['table_name'] . '.' . $row['column_name']] = true;
    $assert(isset($columns['media.id'], $columns['media.storage_key'], $columns['media.updated_at'], $columns['media_variants.media_id'], $columns['media_usages.consumer_id'], $columns['media_usages.created_at']) && !isset($columns['media_usages.updated_at']), 'Media schema columns do not match WU2.');
    $foreignKeys = (int) $connection->query("SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND constraint_name IN ('fk_media_variants_media', 'fk_media_usages_media')")->fetchColumn();
    $assert($foreignKeys === 2, 'Media schema foreign keys are incomplete.');

    $media = new MediaRepository($database); $variants = new MediaVariantRepository($database); $usages = new MediaUsageRepository($database); $service = new MediaLifecycleService($database, $media, $variants, $usages);
    try { new MediaId(0); $assert(false, 'Zero Media ID was accepted.'); } catch (InvalidArgumentException) { $assert(true, 'Positive Media ID validation passed.'); }
    $invalidMedia = [
        ['kind' => 'video', 'originalFilename' => 'x', 'title' => 'x', 'storageKey' => 'a', 'mimeType' => 'x', 'extension' => 'x', 'byteSize' => 1, 'width' => null, 'height' => null],
        ['kind' => 'image', 'originalFilename' => 'x.jpg', 'title' => 'x', 'storageKey' => 'a', 'mimeType' => 'image/jpeg', 'extension' => 'jpg', 'byteSize' => 1, 'width' => null, 'height' => 1],
        ['kind' => 'document', 'originalFilename' => 'x.pdf', 'title' => 'x', 'storageKey' => 'a', 'mimeType' => 'application/pdf', 'extension' => 'pdf', 'byteSize' => 1, 'width' => 1, 'height' => 1],
        ['kind' => 'document', 'originalFilename' => 'x.pdf', 'title' => 'x', 'storageKey' => 'a', 'mimeType' => 'application/pdf', 'extension' => 'pdf', 'byteSize' => 0, 'width' => null, 'height' => null],
        ['kind' => 'document', 'originalFilename' => '', 'title' => 'x', 'storageKey' => 'a', 'mimeType' => 'application/pdf', 'extension' => 'pdf', 'byteSize' => 1, 'width' => null, 'height' => null],
        ['kind' => 'document', 'originalFilename' => 'x.pdf', 'title' => 'x', 'storageKey' => 'C:\\files\\x.pdf', 'mimeType' => 'application/pdf', 'extension' => 'pdf', 'byteSize' => 1, 'width' => null, 'height' => null],
        ['kind' => 'document', 'originalFilename' => 'x.pdf', 'title' => 'x', 'storageKey' => 'https://example.test/x.pdf', 'mimeType' => 'application/pdf', 'extension' => 'pdf', 'byteSize' => 1, 'width' => null, 'height' => null],
        ['kind' => 'image', 'originalFilename' => 'x.jpg', 'title' => 'x', 'storageKey' => 'a', 'mimeType' => 'image/jpeg', 'extension' => 'JPG', 'byteSize' => 1, 'width' => 1, 'height' => 1],
    ];
    foreach ($invalidMedia as $candidate) { try { Media::validateInput($candidate); $assert(false, 'Invalid Media invariant was accepted.'); } catch (InvalidArgumentException) { $assert(true, 'Media invariant rejection passed.'); } }
    $id = $service->create(['kind' => 'image', 'original_filename' => 'hero.jpg', 'title' => 'Hero', 'storage_key' => 'opaque-hero', 'mime_type' => 'image/jpeg', 'extension' => 'jpg', 'byte_size' => 10, 'width' => 100, 'height' => 80]);
    $loaded = $media->findById($id); $assert($loaded instanceof Media && $loaded->id()->value() === $id->value(), 'Media create/find hydration failed.');
    $before = $loaded->updatedAt(); $service->updateTitle($id, 'Updated hero'); $after = $media->findById($id)->updatedAt(); $assert($after > $before && $media->findById($id)->title() === 'Updated hero', 'Title update did not advance updated_at.');
    try { $service->create(['kind' => 'image', 'original_filename' => 'duplicate.jpg', 'title' => 'Duplicate', 'storage_key' => 'opaque-hero', 'mime_type' => 'image/jpeg', 'extension' => 'jpg', 'byte_size' => 10, 'width' => 1, 'height' => 1]); $assert(false, 'Duplicate storage key was accepted.'); } catch (PDOException) { $assert(true, 'Storage-key uniqueness is enforced.'); }
    $variantId = $service->registerVariantDescriptor(['media_id' => $id->value(), 'variant_key' => 'small', 'storage_key' => 'opaque-hero-small', 'mime_type' => 'image/jpeg', 'extension' => 'jpg', 'byte_size' => 4, 'width' => 20, 'height' => 16]);
    $assert($variantId > 0 && count($variants->forMedia($id)) === 1, 'Variant descriptor persistence failed.');
    foreach ([['variant_key' => '', 'width' => 20, 'height' => 16], ['variant_key' => 'bad ', 'width' => 20, 'height' => 16], ['variant_key' => 'bad', 'width' => 0, 'height' => 16], ['variant_key' => 'bad-height', 'width' => 20, 'height' => 0]] as $candidate) { try { $service->registerVariantDescriptor(['media_id' => $id->value(), 'variant_key' => $candidate['variant_key'], 'storage_key' => 'invalid-' . bin2hex(random_bytes(3)), 'mime_type' => 'image/jpeg', 'extension' => 'jpg', 'byte_size' => 4, 'width' => $candidate['width'], 'height' => $candidate['height']]); $assert(false, 'Invalid variant invariant was accepted.'); } catch (InvalidArgumentException) { $assert(true, 'Variant invariant rejection passed.'); } }
    try { $service->registerVariantDescriptor(['media_id' => $id->value(), 'variant_key' => 'small', 'storage_key' => 'opaque-hero-small-2', 'mime_type' => 'image/jpeg', 'extension' => 'jpg', 'byte_size' => 4, 'width' => 20, 'height' => 16]); $assert(false, 'Duplicate variant key was accepted.'); } catch (PDOException) { $assert(true, 'Variant uniqueness is enforced.'); }
    $service->registerUsage($id, 'content', 7, 'hero'); $service->registerUsage($id, 'content', 7, 'hero'); $assert(count($usages->forMedia($id)) === 1, 'Usage registration was not idempotent.'); $service->removeUsage($id, 'content', 7, 'hero'); $assert($usages->forMedia($id) === [], 'Usage removal failed.');
    try { $service->registerUsage($id, '', 7, 'hero'); $assert(false, 'Invalid usage unexpectedly committed.'); } catch (InvalidArgumentException) { $assert($usages->forMedia($id) === [], 'Failed usage registration left a row behind.'); }
    $service->registerUsage($id, 'content', 7, 'hero'); try { $service->delete($id); $assert(false, 'Referenced Media was deleted.'); } catch (MediaInUseException) { $assert(true, 'Referenced Media deletion was blocked.'); }
    $service->removeUsage($id, 'content', 7, 'hero'); $service->delete($id); $assert($media->findById($id) === null && $connection->query("SELECT COUNT(*) FROM media_variants WHERE media_id = {$id->value()}")->fetchColumn() === 0, 'Unreferenced Media deletion did not cascade variants.');
    try { $connection->exec("INSERT INTO media_variants (media_id, variant_key, storage_key, mime_type, extension, byte_size, created_at, updated_at) VALUES (999999, 'bad', 'bad-key', 'image/jpeg', 'jpg', 1, NOW(), NOW())"); $assert(false, 'Orphan variant was accepted.'); } catch (PDOException) { $assert(true, 'Variant foreign-key orphan prevention passed.'); }
    try { $connection->exec("INSERT INTO media_usages (media_id, consumer_type, consumer_id, usage_key, created_at) VALUES (999999, 'content', 1, 'hero', NOW())"); $assert(false, 'Orphan usage was accepted.'); } catch (PDOException) { $assert(true, 'Usage foreign-key orphan prevention passed.'); }

    $transactionId = $service->create(['kind' => 'document', 'original_filename' => 'guide.pdf', 'title' => 'Guide', 'storage_key' => 'opaque-guide', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'byte_size' => 12, 'width' => null, 'height' => null]);
    $moduleManager->disable('media'); $assert($media->findById($transactionId) instanceof Media, 'Media deactivation removed catalogue rows.'); $moduleManager->enable('media'); $assert((string) $connection->query("SELECT status FROM modules WHERE name = 'media'")->fetchColumn() === 'enabled', 'Media reactivation failed.');
    $connection->beginTransaction(); try { $service->registerUsage($transactionId, 'content', 8, 'attachment'); $connection->rollBack(); } catch (Throwable $e) { if ($connection->inTransaction()) $connection->rollBack(); throw $e; }
    $assert($usages->forMedia($transactionId) === [], 'Nested savepoint rollback leaked usage.');
    echo "M3.8 Work Unit 2 Media lifecycle passed ({$assertions} assertions)." . PHP_EOL;
} finally { $server->exec('DROP DATABASE IF EXISTS ' . $quoted); }
