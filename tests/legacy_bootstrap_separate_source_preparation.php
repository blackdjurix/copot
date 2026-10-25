<?php

$base = dirname(__DIR__);
require $base . '/bootstrap/autoload.php';

use Copot\Core\DeploymentContext;
use Copot\Core\LegacyBootstrapPreparation;
use Copot\Core\LegacyBootstrapPreparationRunner;
use Copot\Core\LegacyToV3BootstrapPreparationComposer;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$throwsRetired = static function (callable $callback, string $message) use ($assert): void {
    try { $callback(); }
    catch (Throwable $exception) {
        $assert(str_contains(strtolower($exception->getMessage()), 'retired'), $message . ': wrong rejection');
        return;
    }
    $assert(false, $message . ': call was not rejected');
};
$empty = static fn (string $class): object => (new ReflectionClass($class))->newInstanceWithoutConstructor();

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-separate-bootstrap-retired-' . bin2hex(random_bytes(6));
$sourceRoot = $tmp . DIRECTORY_SEPARATOR . 'source';
$targetApp = $tmp . DIRECTORY_SEPARATOR . 'target-app';
$targetPublic = $tmp . DIRECTORY_SEPARATOR . 'target-public';
mkdir($sourceRoot, 0700, true);
mkdir($targetApp . DIRECTORY_SEPARATOR . 'storage', 0700, true);
mkdir($targetPublic, 0700, true);
file_put_contents($sourceRoot . DIRECTORY_SEPARATOR . 'marker', 'source');
$before = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) if ($file->isFile()) $before[$iterator->getSubPathname()] = hash_file('sha256', $file->getPathname());
ksort($before);

$throwsRetired(static fn () => LegacyBootstrapPreparationRunner::forSourceAndTarget(
    $sourceRoot,
    $targetApp,
    $targetPublic
), 'Separate-source runner composition');

$runner = $empty(LegacyBootstrapPreparationRunner::class);
$composer = $empty(LegacyToV3BootstrapPreparationComposer::class);
$context = new DeploymentContext($targetApp, $targetPublic, '/');
$throwsRetired(static fn () => $runner->prepare($tmp . DIRECTORY_SEPARATOR . 'package.zip'), 'Separate-source preparation');
$throwsRetired(static fn () => $runner->prepareHandoff(
    $tmp . DIRECTORY_SEPARATOR . 'package.zip',
    $tmp . DIRECTORY_SEPARATOR . 'reviewed-manifest.json',
    str_repeat('0', 64)
), 'Separate-source handoff preparation');
$throwsRetired(static fn () => $runner->bindReviewedInjectionManifest(
    $empty(LegacyBootstrapPreparation::class),
    $tmp . DIRECTORY_SEPARATOR . 'reviewed-manifest.json',
    str_repeat('0', 64)
), 'Reviewed operational handoff binding');
$throwsRetired(static fn () => $composer->prepare($tmp . DIRECTORY_SEPARATOR . 'package.zip'), 'Composer preparation from separate source');

$after = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) if ($file->isFile()) $after[$iterator->getSubPathname()] = hash_file('sha256', $file->getPathname());
ksort($after);
$assert($before === $after, 'Rejected separate-source calls changed fixture filesystem state.');
$assert($context->appRoot() === realpath($targetApp) && $context->publicRoot() === realpath($targetPublic), 'Target identity fixture changed unexpectedly.');

echo "Separate-source retired preparation assertions: {$assertions}" . PHP_EOL;
