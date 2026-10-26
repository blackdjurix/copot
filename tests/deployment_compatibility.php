<?php

declare(strict_types=1);

use Copot\Core\DeploymentCompatibilityVerifier;
use Copot\Core\DeploymentContext;

$base = dirname(__DIR__);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-deployment-compat-' . bin2hex(random_bytes(5));
$app = $root . DIRECTORY_SEPARATOR . 'app';
$public = $root . DIRECTORY_SEPARATOR . 'public';
mkdir($app . DIRECTORY_SEPARATOR . 'bootstrap', 0700, true);
mkdir($public, 0700, true);
file_put_contents($app . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'autoload.php', '<?php');
file_put_contents($public . DIRECTORY_SEPARATOR . 'index.php', '<?php');

try {
    $context = new DeploymentContext($app, $public, '/copot');
    $evidence = DeploymentCompatibilityVerifier::verify($context);
    $assert($evidence['app_root'] === realpath($app), 'APP_ROOT evidence is not resolved.');
    $assert($evidence['public_root'] === realpath($public), 'PUBLIC_ROOT evidence is not resolved.');
    $assert($evidence['base_path'] === '/copot', 'BASE_PATH evidence is not preserved.');

    $invalid = $root . DIRECTORY_SEPARATOR . 'invalid';
    mkdir($invalid, 0700, true);
    try {
        DeploymentCompatibilityVerifier::verify(new DeploymentContext($app, $invalid));
        throw new RuntimeException('Missing public entrypoint was accepted.');
    } catch (RuntimeException) {
        $assertions++;
    }
} finally {
    $remove = static function (string $path) use (&$remove): void {
        if (is_dir($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $remove($path . DIRECTORY_SEPARATOR . $entry);
                }
            }
            @rmdir($path);
        } else {
            @unlink($path);
        }
    };
    $remove($root);
}

echo 'Deployment compatibility focused tests passed: ' . $assertions . " assertions.\n";
