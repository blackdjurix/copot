<?php

declare(strict_types=1);

use Copot\Core\Database;
use Copot\Core\Diagnostics;
use Copot\Core\Env;
use Copot\Core\SettingsRegistry;
use Copot\Core\SettingsRepository;
use Copot\Core\SettingsService;
use Copot\Core\SiteAssetStorage;

$basePath = dirname(__DIR__);
require $basePath . '/bootstrap/autoload.php';

final class Batch5SettingsRepository extends SettingsRepository
{
    private array $overrides = [];

    public function __construct()
    {
    }

    public function findOverride(string $namespace, string $key): ?array
    {
        return $this->overrides[$namespace . '.' . $key] ?? null;
    }

    public function upsertOverride(string $namespace, string $key, string $storedValue, string $valueType): void
    {
        $this->overrides[$namespace . '.' . $key] = [
            'setting_value' => $storedValue,
            'value_type' => $valueType,
        ];
    }

    public function deleteOverride(string $namespace, string $key): void
    {
        unset($this->overrides[$namespace . '.' . $key]);
    }
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$assertSame = static function (mixed $expected, mixed $actual, string $message) use (&$assertions): void {
    $assertions++;

    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true) . '.');
    }
};
$removeDirectory = static function (string $path) use (&$removeDirectory): void {
    if (!is_dir($path) || is_link($path)) {
        if (is_file($path) || is_link($path)) {
            @unlink($path);
        }
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $candidate = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($candidate) && !is_link($candidate)) {
            $removeDirectory($candidate);
        } else {
            @unlink($candidate);
        }
    }
    @rmdir($path);
};

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-m2-4-batch5-' . bin2hex(random_bytes(6));
mkdir($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs', 0777, true);

$oldSessionSecureEnv = $_ENV['SESSION_SECURE'] ?? null;
$oldSessionSecureProcess = getenv('SESSION_SECURE');

try {
    $_ENV['SESSION_SECURE'] = true;
    putenv('SESSION_SECURE=true');
    $secureConfig = require $basePath . '/config/session.php';
    $assertSame(true, $secureConfig['secure'] ?? null, 'SESSION_SECURE=true did not enable Secure cookies.');

    $_ENV['SESSION_SECURE'] = false;
    putenv('SESSION_SECURE=false');
    $localConfig = require $basePath . '/config/session.php';
    $assertSame(false, $localConfig['secure'] ?? null, 'SESSION_SECURE=false did not disable Secure cookies.');
    $assertSame(true, $localConfig['http_only'] ?? null, 'Session HttpOnly baseline changed.');
    $assertSame('Lax', $localConfig['same_site'] ?? null, 'Session SameSite baseline changed.');

    $repository = new Batch5SettingsRepository();
    $settings = new SettingsService(SettingsRegistry::core(), $repository);
    $diagnostics = new Diagnostics($root);
    $assets = new SiteAssetStorage(
        $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'site-assets',
        $settings,
        $diagnostics
    );

    $settings->set('site', 'logo', [
        'filename' => 'logo-' . str_repeat('a', 32) . '.png',
        'mime_type' => 'image/png',
        'size' => 123,
    ]);
    $assets->remove('logo');
    $assertSame(null, $settings->get('site', 'logo'), 'Removal cleanup failure left an active descriptor.');

    $logPath = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'copot.log';
    $assert(is_file($logPath), 'Material cleanup degradation was not observable.');
    $records = array_values(array_filter(array_map(
        static fn (string $line): mixed => json_decode($line, true),
        file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []
    ), 'is_array'));
    $assertSame(1, count($records), 'Cleanup degradation did not write exactly one warning record.');
    $assertSame('warning', $records[0]['level'] ?? null, 'Cleanup degradation did not use warning level.');
    $assertSame('storage.siteasset.cleanup', $records[0]['event'] ?? null, 'Cleanup degradation event is incorrect.');
    $assert(!isset($records[0]['reference']), 'Storage warning unexpectedly received an error reference.');
    $assertSame('site-assets', $records[0]['context']['component'] ?? null, 'Storage warning component context is incorrect.');
    $assertSame('cleanup', $records[0]['context']['operation'] ?? null, 'Storage warning operation context is incorrect.');
    $assertSame('logo', $records[0]['context']['slot'] ?? null, 'Storage warning slot context is incorrect.');

    $log = (string) file_get_contents($logPath);
    $assert(!str_contains($log, str_replace('\\', '/', $root)), 'Storage warning leaked an absolute temporary path.');
    $assert(!str_contains($log, 'logo-' . str_repeat('a', 32) . '.png'), 'Storage warning leaked a stored filename.');

    $applicationSource = (string) file_get_contents($basePath . '/app/Core/Application.php');
    $assetSource = (string) file_get_contents($basePath . '/app/Core/SiteAssetStorage.php');
    $sessionConfigSource = (string) file_get_contents($basePath . '/config/session.php');
    $envExample = (string) file_get_contents($basePath . '/.env.example');
    $readme = (string) file_get_contents($basePath . '/README.md');
    $hardeningDoc = (string) file_get_contents($basePath . '/docs/14_platform_hardening.md');
    $authRoutes = (string) file_get_contents($basePath . '/routes/auth.php');
    $adminRoutes = (string) file_get_contents($basePath . '/modules/settings-manager/routes.php');

    $assert(str_contains($applicationSource, '$this->diagnostics'), 'Application does not pass Diagnostics into site-asset storage.');
    $assert(str_contains($assetSource, 'storage.siteasset.cleanup'), 'Site-asset cleanup failure is not observable.');
    $assert(str_contains($assetSource, 'storage.siteasset.read'), 'Site-asset read failure is not observable.');
    $assert(str_contains($sessionConfigSource, "Env::get('SESSION_SECURE'"), 'Secure session cookie is not environment-configurable.');
    $assert(str_contains($envExample, 'SESSION_SECURE=false'), 'Environment template does not document Secure cookie configuration.');
    $assert(str_contains($readme, 'SESSION_SECURE=true'), 'README does not document HTTPS Secure cookie configuration.');
    $assert(str_contains($readme, 'display_errors=Off'), 'README does not require display_errors=Off for production.');
    $assert(str_contains($readme, 'DocumentRoot') && str_contains($readme, '/public'), 'README does not require the public document root.');
    $assert(str_contains($hardeningDoc, 'Batch 5') && str_contains($hardeningDoc, 'implemented'), 'Hardening contract does not record Batch 5 implementation.');

    $assert(str_contains($authRoutes, 'validateOrReject') || str_contains($authRoutes, 'validateCsrf'), 'Auth routes no longer enforce CSRF validation.');
    $assert(str_contains($adminRoutes, 'settings.update'), 'Settings permission boundary is missing.');
    $assert(str_contains($adminRoutes, 'is_uploaded_file'), 'Upload boundary no longer verifies PHP upload provenance.');
    $assert(!str_contains($assetSource, '$_FILES'), 'SiteAssetStorage reads HTTP upload globals directly.');
    $assert(!str_contains($assetSource, 'dispatch('), 'Storage hardening added a production event.');

    echo "M2.4 Batch 5 runtime/storage hardening smoke tests passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if ($oldSessionSecureEnv === null) {
        unset($_ENV['SESSION_SECURE']);
    } else {
        $_ENV['SESSION_SECURE'] = $oldSessionSecureEnv;
    }

    if ($oldSessionSecureProcess === false) {
        putenv('SESSION_SECURE');
    } else {
        putenv('SESSION_SECURE=' . $oldSessionSecureProcess);
    }

    $removeDirectory($root);
}
