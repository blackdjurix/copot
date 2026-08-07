<?php

declare(strict_types=1);

use Copot\Core\DeploymentContext;
use Copot\Core\PackageLifecycleFactory;
use Copot\Core\BackupRecovery\RecoveryRootResolver;

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
    } catch (Throwable) {
        return;
    }

    $assert(false, $message);
};
$makeDirectory = static function (string $label) use (&$temporaryPaths): string {
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-portability-wu5-' . $label . '-' . bin2hex(random_bytes(4));

    if (!mkdir($path, 0700, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary WU5 directory.');
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
foreach (['COPOT_APP_ROOT', 'COPOT_PUBLIC_ROOT', 'COPOT_BASE_PATH', 'COPOT_RECOVERY_ROOT', 'SCRIPT_NAME', 'REQUEST_URI'] as $key) {
    $previous[$key] = array_key_exists($key, $_SERVER) ? $_SERVER[$key] : null;
}

try {
    $appRoot = $makeDirectory('app');
    $publicRoot = $makeDirectory('public');
    $recoveryRoot = $makeDirectory('recovery');
    mkdir($appRoot . DIRECTORY_SEPARATOR . 'config', 0700, true);
    mkdir($appRoot . DIRECTORY_SEPARATOR . 'storage', 0700, true);
    mkdir($appRoot . DIRECTORY_SEPARATOR . 'database', 0700, true);
    file_put_contents($appRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql', "-- portability test\n");

    $_SERVER['COPOT_APP_ROOT'] = $appRoot;
    $_SERVER['COPOT_PUBLIC_ROOT'] = $publicRoot;
    $_SERVER['COPOT_BASE_PATH'] = '/copot';
    unset($_SERVER['SCRIPT_NAME'], $_SERVER['REQUEST_URI']);

    $deployment = DeploymentContext::forCli($basePath);
    $assert($deployment->appRoot() === realpath($appRoot), 'CLI APP_ROOT was not resolved from deployment context.');
    $assert($deployment->publicRoot() === realpath($publicRoot), 'CLI PUBLIC_ROOT was not resolved from deployment context.');
    $assert($deployment->basePath() === '/copot', 'CLI base path was not resolved without web globals.');

    $configuredRecovery = $recoveryRoot . DIRECTORY_SEPARATOR . 'copot';
    mkdir($configuredRecovery, 0700, true);
    $resolvedRecovery = (new RecoveryRootResolver($deployment->appRoot(), $configuredRecovery, [$deployment->path('storage')], [$deployment->publicRoot()]))->resolve();
    $assert(str_starts_with($resolvedRecovery->path(), realpath($recoveryRoot)), 'Recovery root did not resolve outside the split public boundary.');
    $expectInvalid(static fn () => (new RecoveryRootResolver($deployment->appRoot(), $deployment->publicRoot(), [], [$deployment->publicRoot()]))->resolve(), 'Recovery root overlapping PUBLIC_ROOT was accepted.');

    $service = PackageLifecycleFactory::forProject($deployment);
    $assert($service instanceof \Copot\Core\PackageLifecycleService, 'Package lifecycle factory did not accept the resolved deployment context.');

    $_SERVER['COPOT_APP_ROOT'] = $appRoot . DIRECTORY_SEPARATOR . 'missing';
    $expectInvalid(static fn (): DeploymentContext => DeploymentContext::forCli($basePath), 'Invalid CLI APP_ROOT was accepted.');
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

echo 'Portability WU5 tests passed (' . $assertions . ' assertions).' . PHP_EOL;
