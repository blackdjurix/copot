<?php

declare(strict_types=1);

use Copot\Core\LiveFileActivationCapability;
use Copot\Core\LiveTreePathGuard;
use Copot\Core\PackageOwnedFileApplier;

$base = dirname(__DIR__);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$throws = static function (callable $callback, string $message) use (&$assertions, $assert): void {
    try {
        $callback();
    } catch (Throwable) {
        $assertions++;
        return;
    }

    $assert(false, $message);
};
$remove = static function (string $path) use (&$remove): void {
    if (is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            $remove($path . DIRECTORY_SEPARATOR . $entry);
        }
    }
    @rmdir($path);
};

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-package-applier-root-' . bin2hex(random_bytes(6));
$live = $root . DIRECTORY_SEPARATOR . 'live';
mkdir($live, 0700, true);
$guard = new LiveTreePathGuard($live);
$capability = LiveFileActivationCapability::current();

try {
    $missingSeparate = $root . DIRECTORY_SEPARATOR . 'missing-separate';
    new PackageOwnedFileApplier($guard, $capability, $missingSeparate);
    $assert(true, 'A missing temporary root with a valid separate parent was rejected.');

    $existingSeparate = $root . DIRECTORY_SEPARATOR . 'existing-separate';
    mkdir($existingSeparate, 0700, true);
    new PackageOwnedFileApplier($guard, $capability, $existingSeparate);
    $assert(true, 'An existing separate temporary root was rejected.');

    $throws(
        static fn (): PackageOwnedFileApplier => new PackageOwnedFileApplier($guard, $capability, $live . DIRECTORY_SEPARATOR . 'inside'),
        'A temporary root inside the live root was accepted.'
    );
    $throws(
        static fn (): PackageOwnedFileApplier => new PackageOwnedFileApplier($guard, $capability, $root),
        'A temporary root that is an ancestor of the live root was accepted.'
    );
    $throws(
        static fn (): PackageOwnedFileApplier => new PackageOwnedFileApplier($guard, $capability, $root . DIRECTORY_SEPARATOR . 'missing-parent' . DIRECTORY_SEPARATOR . 'temporary'),
        'A temporary root with an unresolved parent was accepted.'
    );

    $liveAlias = $root . DIRECTORY_SEPARATOR . 'live-alias';
    $assert(symlink($live, $liveAlias), 'The live-root symlink fixture could not be created.');
    $throws(
        static fn (): PackageOwnedFileApplier => new PackageOwnedFileApplier($guard, $capability, $liveAlias . DIRECTORY_SEPARATOR . 'aliased-child'),
        'A symlink alias into the live root was accepted.'
    );

    echo "PackageOwnedFileApplier temporary-root assertions passed ({$assertions} assertions).\n";
} finally {
    $remove($root);
}
