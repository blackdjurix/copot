<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';
Env::load($base . '/.env');
foreach (['MediaId','Media','MediaVariant','MediaUsage','MediaRepository','MediaVariantRepository','MediaUsageRepository','MediaFileInspector','MediaStagedFile','MediaProcessingException','MediaFilesystemStorage','MediaVariantFilesystemStorage','MediaLifecycleService','MediaContentReferenceService'] as $file) require_once $base . '/modules/media/Services/' . $file . '.php';
foreach (['Content','ContentRepository','ContentService'] as $file) require_once $base . '/modules/content/Services/' . $file . '.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void { $assertions++; if (!$condition) throw new RuntimeException($message); };
$host = (string) Env::get('DB_HOST', '127.0.0.1'); $port = (int) Env::get('DB_PORT', '3306'); $username = (string) Env::get('DB_USERNAME', 'root'); $password = (string) Env::get('DB_PASSWORD', '');
$name = 'copot_m38_wu6_' . bin2hex(random_bytes(6)); $quoted = '`' . str_replace('`', '``', $name) . '`';
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec('CREATE DATABASE ' . $quoted . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$config = ['host'=>$host,'port'=>$port,'database'=>$name,'username'=>$username,'password'=>$password];

try {
    (new InstallerSchemaRunner($base . '/database/schema.sql'))->install($config);
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $upgrade = (string) file_get_contents($base . '/database/upgrades/m3_8_media_library.sql');
    foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\R|$)/', $upgrade) ?: [])) as $statement) $pdo->exec($statement);
    foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\R|$)/', $upgrade) ?: [])) as $statement) $pdo->exec($statement);
    $assert((bool) $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'content' AND column_name = 'featured_media_id'")->fetchColumn(), 'featured_media_id upgrade is not idempotent.');

    $cfg = new Config($base . '/config'); $reflection = new ReflectionClass($cfg); $property = $reflection->getProperty('items'); $property->setAccessible(true); $items = $property->getValue($cfg); foreach ($config as $key => $value) $items['database']['connections']['mysql'][$key] = $value; $property->setValue($cfg, $items);
    $database = new Database($cfg); $media = new MediaRepository($database); $variants = new MediaVariantRepository($database); $usages = new MediaUsageRepository($database); $original = new MediaFilesystemStorage($base . '/storage/test-wu6-media'); $variant = new MediaVariantFilesystemStorage($base . '/storage/test-wu6-media');
    $lifecycle = new MediaLifecycleService($database, $media, $variants, $usages, $original, $variant); $references = new MediaContentReferenceService($database, $media, $usages); $contents = new ContentRepository($database); $service = new ContentService($database, $contents, null, $references);
    $makeMedia = static function (string $key, string $mime = 'image/png') use ($media): int { return $media->create(['kind'=>'image','original_filename'=>'featured.png','title'=>'Featured','storage_key'=>str_repeat($key,32).'.png','mime_type'=>$mime,'extension'=>'png','byte_size'=>4,'width'=>10,'height'=>10])->value(); };
    $allowed = $makeMedia('a'); $second = $makeMedia('c'); $pdf = $makeMedia('d', 'application/pdf');
    $payload = ['type'=>'page','title'=>'Featured page','slug'=>'featured-page','excerpt'=>null,'body'=>'Body','status'=>'draft','author_id'=>null,'featured_media_id'=>$allowed];
    $contentId = $service->create($payload); $row = $contents->findById($contentId); $assert($row?->featuredMediaId() === $allowed, 'Content create did not persist featured_media_id.'); $assert(count($usages->forConsumer('content', $contentId, 'featured_media')) === 1, 'Content create did not register usage.');
    $service->update($contentId, [...$payload, 'featured_media_id'=>$second], [], $row->updatedAt()); $row = $contents->findById($contentId); $assert($row?->featuredMediaId() === $second && $usages->forConsumer('content', $contentId, 'featured_media')[0]->mediaId()->value() === $second, 'Content replacement did not synchronize usage.');
    $service->update($contentId, [...$payload, 'featured_media_id'=>null], [], $row->updatedAt()); $assert($contents->findById($contentId)?->featuredMediaId() === null && $usages->forConsumer('content', $contentId, 'featured_media') === [], 'Content clear did not remove usage.');
    try { $service->create([...$payload, 'slug'=>'invalid-pdf','featured_media_id'=>$pdf]); $assert(false, 'Disallowed PDF reference was accepted.'); } catch (InvalidArgumentException) { $assert(true, 'Disallowed MIME was rejected.'); }
    $service->create([...$payload, 'slug'=>'rollback-page','featured_media_id'=>null]);
    try { $service->create([...$payload, 'slug'=>'missing-media','featured_media_id'=>999999999]); $assert(false, 'Missing Media reference was accepted.'); } catch (InvalidArgumentException) { $assert((int) $pdo->query("SELECT COUNT(*) FROM content WHERE slug = 'missing-media'")->fetchColumn() === 0, 'Failed Content create was not rolled back.'); }
    $service->delete($contentId); $assert($usages->forConsumer('content', $contentId, 'featured_media') === [], 'Deleting Content did not remove usage.');
    $lifecycle->registerUsage($allowed, 'content', 77, 'featured_media'); try { $lifecycle->delete($allowed); $assert(false, 'Used Media deletion was not blocked.'); } catch (MediaInUseException) { $assert(true, 'Used Media deletion was blocked.'); } $lifecycle->removeUsage($allowed, 'content', 77, 'featured_media');
    $storageRoot = $base . '/storage/test-wu6-media'; @mkdir($storageRoot . '/originals/cc/cc', 0755, true); @mkdir($storageRoot . '/variants/bb/bb', 0755, true); file_put_contents($storageRoot . '/originals/cc/cc/' . str_repeat('c',32) . '.png', 'orig');
    $variantKey = str_repeat('b',32) . '.png'; file_put_contents($storageRoot . '/variants/bb/bb/' . $variantKey, 'variant'); $variants->saveDescriptor(['media_id'=>$second,'variant_key'=>'featured','storage_key'=>$variantKey,'mime_type'=>'image/png','extension'=>'png','byte_size'=>7,'width'=>10,'height'=>10]);
    $trigger = 'wu6_delete_fail_' . bin2hex(random_bytes(4)); $pdo->exec("CREATE TRIGGER {$trigger} BEFORE DELETE ON media FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'controlled'"); try { $lifecycle->delete($second); $assert(false, 'Quarantine compensation delete unexpectedly succeeded.'); } catch (Throwable) { $assert($media->findById($second) !== null && is_file($storageRoot . '/originals/cc/cc/' . str_repeat('c',32) . '.png') && is_file($storageRoot . '/variants/bb/bb/' . $variantKey), 'Quarantine compensation did not restore files.'); } $pdo->exec("DROP TRIGGER {$trigger}");
    $lifecycle->delete($second); $assert($media->findById($second) === null, 'Unused Media was not deleted.');
    $routes = (string) file_get_contents($base . '/modules/media/routes.php'); $assert(str_contains($routes, 'context-picker') && str_contains($routes, 'image/jpeg') && !str_contains($routes, 'storageKey'), 'Picker route contract or disclosure boundary is incorrect.');
    echo "M3.8 Work Unit 6 contextual Media passed ({$assertions} assertions).", PHP_EOL;
} finally { $server->exec('DROP DATABASE IF EXISTS ' . $quoted); }
