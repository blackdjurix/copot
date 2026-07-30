<?php

declare(strict_types=1);

use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\ThemeDiscovery;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$removeDirectory = static function (string $path) use (&$removeDirectory): void {
    if (!is_dir($path)) return;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    rmdir($path);
};
$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_m37_wu6_' . bin2hex(random_bytes(5));
$configuration = compact('host', 'port', 'username', 'password') + ['database' => $databaseName];
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec('CREATE DATABASE `' . $databaseName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$fixtureRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-m37-wu6-' . bin2hex(random_bytes(6));
mkdir($fixtureRoot, 0777, true);
$writeTheme = static function (string $id, array $manifest, ?string $screenshot = null) use ($fixtureRoot): void {
    $path = $fixtureRoot . DIRECTORY_SEPARATOR . $id;
    mkdir($path . DIRECTORY_SEPARATOR . 'layouts', 0777, true);
    file_put_contents($path . '/layouts/app.php', '<?php echo $themeSettings["accent"] ?? "default";');
    if ($screenshot !== null) file_put_contents($path . '/' . $screenshot, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
    file_put_contents($path . '/theme.json', json_encode($manifest, JSON_THROW_ON_ERROR));
};
$cleanup = static function () use ($server, $databaseName, $fixtureRoot, $removeDirectory): void { $removeDirectory($fixtureRoot); $server->exec('DROP DATABASE IF EXISTS `' . $databaseName . '`'); };
try {
    (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install($configuration);
    $_ENV['DB_DATABASE'] = $databaseName;
    putenv('DB_DATABASE=' . $databaseName);
    $manifest = ['id' => 'secure', 'name' => 'Secure', 'version' => '1.0.0', 'type' => 'frontend', 'entry' => ['layout' => 'layouts/app.php'], 'screenshot' => 'preview.png', 'settings' => ['version' => 1, 'sections' => [['id' => 'appearance', 'label' => 'Appearance', 'fields' => [['key' => 'accent', 'label' => 'Accent', 'type' => 'string', 'control' => 'color', 'default' => '#ffffff', 'validation' => ['format' => 'hex_color']]]]]]];
    $writeTheme('secure', $manifest, 'preview.png');
    $writeTheme('bad-mime', array_merge($manifest, ['id' => 'bad-mime', 'name' => 'Bad MIME']), 'preview.png');
    file_put_contents($fixtureRoot . '/bad-mime/preview.png', 'not an image');
    mkdir($fixtureRoot . '/oversized', 0777, true);
    file_put_contents($fixtureRoot . '/oversized/theme.json', str_repeat('x', 262145));
    $catalog = (new ThemeDiscovery($fixtureRoot))->discoverCatalog();
    $assert(count($catalog['themes']) === 1 && $catalog['themes'][0]->id() === 'secure', 'Invalid screenshot MIME or oversized metadata was not isolated.');
    $codes = array_column($catalog['errors'], 'code');
    $assert(in_array('invalid_definition', $codes, true), 'Malformed Theme metadata did not produce a bounded diagnostic.');
    $assert((new ThemeDiscovery($fixtureRoot . '/missing'))->discoverCatalog()['themes'] === [], 'Missing Theme root did not fail closed.');
    $assert((new ThemeDiscovery($fixtureRoot . '/missing'))->discoverCatalog()['errors'][0]['code'] === 'themes_path_unavailable', 'Missing-root diagnostic was not controlled.');
    $assert(file_exists($basePath . '/modules/theme-manager/module.json'), 'Theme Manager package module is present.');
    $manifestEntries = require $basePath . '/build/package_manifest.php';
    foreach (['modules/theme-manager', 'themes/default', 'database'] as $required) {
        $assert(in_array($required, $manifestEntries['include'], true), 'Package manifest omitted required Theme integration: ' . $required);
    }
    foreach (['build', 'tests', 'docs'] as $excluded) $assert(in_array($excluded, $manifestEntries['exclude'], true), 'Package manifest retained excluded path: ' . $excluded);
    $themeManagerRoutes = file_get_contents($basePath . '/modules/theme-manager/routes.php') ?: '';
    $assert(substr_count($themeManagerRoutes, "->post(") === 3 && substr_count($themeManagerRoutes, "->get(") === 3, 'Theme routes expose an unexpected mutation/read surface.');
    $manager = file_get_contents($basePath . '/modules/theme-manager/Services/ThemeManagerAdmin.php') ?: '';
    $settingsAdmin = file_get_contents($basePath . '/modules/theme-manager/Services/ThemeSettingsAdmin.php') ?: '';
    $assert(strpos($manager, 'validateOrReject') < strpos($manager, 'themeLifecycle()->activate'), 'Activation authorization/CSRF ordering regressed.');
    $assert(strpos($settingsAdmin, 'validateOrReject') < strpos($settingsAdmin, 'settings->save'), 'Settings authorization/CSRF ordering regressed.');
    $assert(str_contains($manager, 'finfo_file') && str_contains($manager, 'MAX_SCREENSHOT_BYTES'), 'Screenshot MIME and size guards are absent.');
    $assert(str_contains($settingsAdmin, 'submittedId') && str_contains($settingsAdmin, 'validId'), 'Settings route target coherence guards are absent.');
    $assert(str_contains((string) file_get_contents($basePath . '/app/Core/ThemeSettingsService.php'), 'MAX_SUBMITTED_FIELDS'), 'Theme settings payload bound is absent.');
    $assert(str_contains((string) file_get_contents($basePath . '/app/Core/ViewRenderer.php'), "'themeSettings'"), 'Intrinsic runtime Theme settings boundary is absent.');
    echo "M3.7 Work Unit 6 security/integration passed ({$assertions} assertions)." . PHP_EOL;
} finally { $cleanup(); }
