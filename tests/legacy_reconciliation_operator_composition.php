<?php

declare(strict_types=1);

use Copot\Core\PackageLifecycleFactory;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-iu2-composition-' . bin2hex(random_bytes(4));
mkdir($root, 0700, true);
$_ENV['COPOT_RECOVERY_ROOT'] = $root;
putenv('COPOT_RECOVERY_ROOT=' . $root);

try {
    $service = PackageLifecycleFactory::forProject($base);
    $status = $service->status();
    $assert(($status['reconciliation_available'] ?? false) === true, 'Production reconciliation composition was not resolved by the factory.');

    $zip = $base . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'copot-v0.13.0.zip';
    $result = $service->reconcile($zip, true);
    $assert($result->status() === 'rejected', 'Reconciliation did not fail closed through the composed service.');
    $assert(str_contains($result->reason(), 'unknown or unprovable'), 'Unknown legacy state did not remain the authoritative rejection.');

    $unconfirmed = $service->reconcile($zip, false);
    $assert($unconfirmed->status() === 'rejected', 'Unconfirmed reconciliation did not remain non-mutating after planning.');

    $output = [];
    $exitCode = 0;
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($base . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'copot') . ' package:reconcile ' . escapeshellarg($zip) . ' --confirm --format=json 2>&1';
    exec($command, $output, $exitCode);
    $assert($exitCode === 4, 'The explicit CLI reconciliation command did not reach the fail-closed planner result.');
    $assert(str_contains(implode("\n", $output), 'unknown or unprovable'), 'CLI reconciliation did not report the authoritative classification rejection.');

    echo "IU2 production composition: {$assertions} assertions passed\n";
} finally {
    putenv('COPOT_RECOVERY_ROOT');
    unset($_ENV['COPOT_RECOVERY_ROOT']);
    $remove = static function (string $path) use (&$remove): void {
        if (is_link($path) || is_file($path)) { @unlink($path); return; }
        if (!is_dir($path)) { return; }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') { $remove($path . DIRECTORY_SEPARATOR . $entry); }
        }
        @rmdir($path);
    };
    $remove($root);
}
