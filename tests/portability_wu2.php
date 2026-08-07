<?php

declare(strict_types=1);

use Copot\Core\DeploymentContext;

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
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-portability-wu2-' . $label . '-' . bin2hex(random_bytes(4));

    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary test directory.');
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
        is_dir($child) && !is_link($child)
            ? $removeDirectory($child)
            : @unlink($child);
    }

    @rmdir($path);
};

try {
    $sameRoot = $makeDirectory('same');
    mkdir($sameRoot . DIRECTORY_SEPARATOR . 'public', 0777, true);

    $same = DeploymentContext::forApplicationRoot($sameRoot, '/copot');
    $assert($same->appRoot() === realpath($sameRoot), 'Same-root APP_ROOT was not resolved.');
    $assert($same->publicRoot() === realpath($sameRoot . DIRECTORY_SEPARATOR . 'public'), 'Same-root PUBLIC_ROOT was not resolved.');
    $assert($same->basePath() === '/copot', 'Base path was not normalized.');
    $assert(!$same->isSplitRoot(), 'Same-root deployment was identified as split-root.');
    $assert($same->path('config/app.php') === $same->appRoot() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php', 'APP_ROOT path resolution failed.');
    $assert($same->publicPath('build/app.css') === $same->publicRoot() . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'app.css', 'PUBLIC_ROOT path resolution failed.');
    $expectInvalid(static fn (): string => $same->path('../outside'), 'APP_ROOT path traversal was not rejected.');
    $expectInvalid(static fn (): string => $same->publicPath('assets/../../outside'), 'PUBLIC_ROOT path traversal was not rejected.');

    $splitApp = $makeDirectory('split-app');
    $splitPublic = $makeDirectory('split-public');
    $split = new DeploymentContext($splitApp, $splitPublic, '/copot');
    $assert($split->isSplitRoot(), 'Split-root deployment was not identified.');
    $assert($split->appRoot() === realpath($splitApp), 'Split APP_ROOT was not resolved.');
    $assert($split->publicRoot() === realpath($splitPublic), 'Split PUBLIC_ROOT was not resolved.');

    $entrypoint = $splitPublic . DIRECTORY_SEPARATOR . 'index.php';
    file_put_contents($entrypoint, "<?php\n");
    $previous = [];
    foreach (['COPOT_APP_ROOT', 'COPOT_PUBLIC_ROOT', 'COPOT_BASE_PATH', 'SCRIPT_NAME'] as $key) {
        $previous[$key] = array_key_exists($key, $_SERVER) ? $_SERVER[$key] : null;
    }
    $_SERVER['COPOT_APP_ROOT'] = $splitApp;
    $_SERVER['COPOT_PUBLIC_ROOT'] = $splitPublic;
    $_SERVER['COPOT_BASE_PATH'] = '/copot';
    $_SERVER['SCRIPT_NAME'] = '/copot/index.php';
    $fromEntrypoint = DeploymentContext::fromEntrypoint($entrypoint);
    $assert($fromEntrypoint->isSplitRoot(), 'Entrypoint context lost split-root deployment.');
    $assert($fromEntrypoint->basePath() === '/copot', 'Entrypoint context lost base path.');
    foreach ($previous as $key => $value) {
        if ($value === null) {
            unset($_SERVER[$key]);
        } else {
            $_SERVER[$key] = $value;
        }
    }

    $expectInvalid(static fn (): DeploymentContext => DeploymentContext::forApplicationRoot($makeDirectory('missing-public')), 'Missing public root was accepted.');
    $samePath = $makeDirectory('same-path');
    $expectInvalid(static fn (): DeploymentContext => new DeploymentContext($samePath, $samePath), 'Identical roots were accepted.');
    $publicRoot = $makeDirectory('public-parent');
    $nestedApp = $publicRoot . DIRECTORY_SEPARATOR . 'app';
    mkdir($nestedApp, 0777, true);
    $expectInvalid(static fn (): DeploymentContext => new DeploymentContext($nestedApp, $publicRoot), 'APP_ROOT inside PUBLIC_ROOT was accepted.');
    $expectInvalid(static fn (): DeploymentContext => DeploymentContext::forApplicationRoot($sameRoot, 'copot'), 'Relative base path was accepted.');
    $expectInvalid(static fn (): DeploymentContext => DeploymentContext::forApplicationRoot($sameRoot, '/copot/../escape'), 'Unsafe base path was accepted.');

    $unreadable = $makeDirectory('unreadable');
    @chmod($unreadable, 0000);
    if (!is_readable($unreadable)) {
        $expectInvalid(static fn (): DeploymentContext => new DeploymentContext($unreadable, $splitPublic), 'Unreadable APP_ROOT was accepted.');
    }
    @chmod($unreadable, 0777);
} catch (Throwable $exception) {
    foreach ($temporaryPaths as $path) {
        $removeDirectory($path);
    }
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

foreach ($temporaryPaths as $path) {
    $removeDirectory($path);
}

echo 'Portability WU2 tests passed (' . $assertions . ' assertions).' . PHP_EOL;
