<?php

declare(strict_types=1);

use Copot\Core\DeploymentContext;
use Copot\Core\Admin\AdminIcon;

$basePath = dirname(__DIR__);
require_once $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$temporaryPaths = [];
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$expectInvalid = static function (callable $callback, string $message) use ($assert): void {
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }

    $assert(false, $message);
};
$makeDirectory = static function (string $label) use (&$temporaryPaths): string {
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-portability-wu4-' . $label . '-' . bin2hex(random_bytes(4));

    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary WU4 directory.');
    }

    $temporaryPaths[] = $path;
    return $path;
};
$removeDirectory = static function (string $path) use (&$removeDirectory): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $child = $path . DIRECTORY_SEPARATOR . $entry;
        is_dir($child) && !is_link($child) ? $removeDirectory($child) : @unlink($child);
    }

    @rmdir($path);
};

$previous = [];
foreach (['COPOT_APP_ROOT', 'COPOT_PUBLIC_ROOT', 'COPOT_BASE_PATH', 'SCRIPT_NAME'] as $key) {
    $previous[$key] = array_key_exists($key, $_SERVER) ? $_SERVER[$key] : null;
}

try {
    $appRoot = $makeDirectory('app');
    $publicRoot = $makeDirectory('public');
    $otherPublicRoot = $makeDirectory('other-public');
    $entrypoint = $publicRoot . DIRECTORY_SEPARATOR . 'index.php';
    $privateConfig = $appRoot . DIRECTORY_SEPARATOR . 'config';
    $publicAssets = $publicRoot . DIRECTORY_SEPARATOR . 'admin-assets' . DIRECTORY_SEPARATOR . 'icons';
    mkdir($privateConfig, 0700, true);
    mkdir($publicAssets, 0755, true);
    file_put_contents($entrypoint, "<?php\n");
    file_put_contents($publicAssets . DIRECTORY_SEPARATOR . 'icon-module.svg', '<svg viewBox="0 0 24 24"></svg>');

    $_SERVER['COPOT_APP_ROOT'] = $appRoot;
    $_SERVER['COPOT_PUBLIC_ROOT'] = $publicRoot;
    $_SERVER['COPOT_BASE_PATH'] = '/copot';
    $_SERVER['SCRIPT_NAME'] = '/copot/index.php';
    $split = DeploymentContext::fromEntrypoint($entrypoint);
    $assert($split->isSplitRoot(), 'Split-root context was not detected.');
    $assert($split->appRoot() === realpath($appRoot), 'Split APP_ROOT was not resolved.');
    $assert($split->publicRoot() === realpath($publicRoot), 'Split PUBLIC_ROOT was not resolved.');
    $assert($split->basePath() === '/copot', 'Split base path was not resolved.');
    $assert(is_file($split->publicPath('admin-assets/icons/icon-module.svg')), 'Public static asset was not resolved from PUBLIC_ROOT.');
    $assert(!is_file($split->publicPath('config/app.php')), 'Private APP_ROOT content was exposed through PUBLIC_ROOT resolution.');
    $assert(str_contains((new AdminIcon($split->publicPath('admin-assets/icons')))->render('module'), '<svg'), 'Admin icon was not loaded from PUBLIC_ROOT.');

    unset($_SERVER['COPOT_APP_ROOT'], $_SERVER['COPOT_PUBLIC_ROOT']);
    $_SERVER['COPOT_BASE_PATH'] = '/';
    $default = DeploymentContext::fromEntrypoint($basePath . '/public/index.php');
    $assert(!$default->isSplitRoot(), 'Default APP_ROOT/public deployment changed to split-root.');
    $assert(is_file($default->publicPath('admin-assets/css/admin.css')), 'Default public asset deployment is unavailable.');

    $_SERVER['COPOT_PUBLIC_ROOT'] = $otherPublicRoot;
    $expectInvalid(static fn (): DeploymentContext => DeploymentContext::fromEntrypoint($entrypoint), 'Entrypoint outside PUBLIC_ROOT was accepted.');
    $_SERVER['COPOT_PUBLIC_ROOT'] = $publicRoot;
    $_SERVER['COPOT_APP_ROOT'] = $makeDirectory('missing-app') . DIRECTORY_SEPARATOR . 'does-not-exist';
    $expectInvalid(static fn (): DeploymentContext => DeploymentContext::fromEntrypoint($entrypoint), 'Invalid APP_ROOT was accepted.');
    $_SERVER['COPOT_APP_ROOT'] = $appRoot;
    $_SERVER['COPOT_PUBLIC_ROOT'] = $makeDirectory('missing-public') . DIRECTORY_SEPARATOR . 'does-not-exist';
    $expectInvalid(static fn (): DeploymentContext => DeploymentContext::fromEntrypoint($entrypoint), 'Invalid PUBLIC_ROOT was accepted.');
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    foreach ($previous as $key => $value) {
        if ($value === null) {
            unset($_SERVER[$key]);
        } else {
            $_SERVER[$key] = $value;
        }
    }
    foreach ($temporaryPaths as $path) {
        $removeDirectory($path);
    }
}

echo 'Portability WU4 tests passed (' . $assertions . ' assertions).' . PHP_EOL;
