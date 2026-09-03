<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\HomepageHeroImageService;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\MediaLifecycleService;
use Copot\Core\MediaRepository;
use Copot\Core\MediaUsageRepository;
use Copot\Core\SettingsRegistry;
use Copot\Core\SettingsRepository;
use Copot\Core\SettingsService;
use Copot\Core\SiteAssetStorage;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';
Env::load($base . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;
    if (!$condition) throw new RuntimeException($message);
};
$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_wu4_batch1_' . bin2hex(random_bytes(5));
$quoted = '`' . $databaseName . '`';
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

try {
    (new InstallerSchemaRunner($base . '/database/schema.sql'))->install([
        'host' => $host, 'port' => $port, 'database' => $databaseName,
        'username' => $username, 'password' => $password,
    ]);
    $config = new Config($base . '/config');
    $reflection = new ReflectionClass($config);
    $property = $reflection->getProperty('items');
    $items = $property->getValue($config);
    foreach (['host' => $host, 'port' => $port, 'database' => $databaseName, 'username' => $username, 'password' => $password] as $key => $value) {
        $items['database']['connections']['mysql'][$key] = $value;
    }
    $property->setValue($config, $items);
    $database = new Database($config);
    $settings = new SettingsService(SettingsRegistry::core(), new SettingsRepository($database));
    $media = new MediaRepository($database);
    $usages = new MediaUsageRepository($database);
    $lifecycle = new MediaLifecycleService($database, $media, null, $usages);
    $hero = new HomepageHeroImageService($settings, $database, $media, $usages, $lifecycle);
    $assetRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu4-assets-' . bin2hex(random_bytes(3));
    mkdir($assetRoot, 0700, true);

    foreach ([
        ['site', 'name', 'Runtime site', 'string'], ['site', 'tagline', 'Runtime tagline', 'string'],
        ['localization', 'locale', 'en_US', 'string'], ['localization', 'timezone', 'Europe/London', 'string'],
        ['localization', 'date_format', 'd/m/Y', 'string'], ['localization', 'time_format', 'H:i', 'string'],
        ['appearance', 'main_color', '#336699', 'string'],
    ] as [$namespace, $key, $value, $type]) $settings->set($namespace, $key, $value, $type);
    $assert($settings->get('site', 'name') === 'Runtime site' && $settings->get('site', 'tagline') === 'Runtime tagline', 'Site identity did not read back.');
    $assert($settings->get('localization', 'locale') === 'en_US' && $settings->get('localization', 'timezone') === 'Europe/London', 'Localization did not read back.');
    $assert($settings->get('appearance', 'main_color') === '#336699', 'Main Color did not read back.');

    $assetSource = $assetRoot . DIRECTORY_SEPARATOR . 'asset.png';
    file_put_contents($assetSource, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
    $assets = new SiteAssetStorage($assetRoot . DIRECTORY_SEPARATOR . 'active', $settings);
    $assets->store('logo', $assetSource); $assets->store('favicon', $assetSource);
    $assert($assets->url('logo') !== null && $assets->url('favicon') !== null, 'Logo/Favicon activation did not read back.');
    $assets->remove('logo'); $assets->remove('favicon');
    $assert($assets->url('logo') === null && $assets->url('favicon') === null, 'Logo/Favicon removal did not clear active descriptors.');

    $a = $lifecycle->create(['kind' => 'image', 'original_filename' => 'a.png', 'title' => 'A', 'storage_key' => 'test-a.png', 'mime_type' => 'image/png', 'extension' => 'png', 'byte_size' => 1, 'width' => 1, 'height' => 1]);
    $b = $lifecycle->create(['kind' => 'image', 'original_filename' => 'b.png', 'title' => 'B', 'storage_key' => 'test-b.png', 'mime_type' => 'image/png', 'extension' => 'png', 'byte_size' => 1, 'width' => 1, 'height' => 1]);
    $hero->set($a->value());
    $assert($hero->selectedId() === $a->value() && count($usages->forMedia($a)) === 1, 'Hero selection or usage registration failed.');
    try { $lifecycle->delete($a); $assert(false, 'Active Hero Media deletion was not blocked.'); } catch (Throwable) { $assert(true, 'Active Hero Media deletion was blocked.'); }
    $hero->set($b->value());
    $assert($hero->selectedId() === $b->value() && $usages->forMedia($a) === [] && count($usages->forMedia($b)) === 1, 'Hero replacement did not reconcile usage.');
    $hero->set(null);
    $assert($hero->selectedId() === null && $usages->forMedia($b) === [], 'Hero removal did not remove usage.');
    $lifecycle->delete($a); $lifecycle->delete($b);
    $assert($media->findById($a) === null && $media->findById($b) === null, 'Unused Hero Media could not be deleted.');
    $settings->set('site', 'homepage_hero_media', 999999, 'json');
    $assert($hero->selected() === null, 'Missing Hero reference did not fall back safely.');

    echo "WU4 Batch 1 DB tests passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $server->exec("DROP DATABASE IF EXISTS {$quoted}");
    if (isset($assetRoot) && is_file($assetRoot . DIRECTORY_SEPARATOR . 'asset.png')) @unlink($assetRoot . DIRECTORY_SEPARATOR . 'asset.png');
    if (isset($assetRoot)) { @rmdir($assetRoot . DIRECTORY_SEPARATOR . 'active' . DIRECTORY_SEPARATOR . 'logo'); @rmdir($assetRoot . DIRECTORY_SEPARATOR . 'active' . DIRECTORY_SEPARATOR . 'favicon'); @rmdir($assetRoot . DIRECTORY_SEPARATOR . 'active'); @rmdir($assetRoot); }
}
