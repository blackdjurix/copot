<?php

declare(strict_types=1);

use Copot\Core\PackageInventoryVerifier;
use Copot\Core\PackageManifestReader;
use Copot\Core\ZipIntakeService;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) { throw new RuntimeException($message); }
};

if (!class_exists(ZipArchive::class) || !extension_loaded('zip')) {
    echo 'WU7 skipped: ZipArchive/ext-zip unavailable.' . PHP_EOL;
    exit(0);
}

$package = $basePath . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'copot-v0.12.0.zip';
if (!is_file($package)) {
    throw new RuntimeException('Official package artifact is missing; run build/package.php first.');
}

$staging = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu7-staging-' . bin2hex(random_bytes(6));
mkdir($staging, 0700, true);
$payload = null;
try {
    $payload = (new ZipIntakeService($basePath, $staging))->intake($package);
    $manifest = (new PackageManifestReader())->read($payload);
    (new PackageInventoryVerifier())->verify($manifest->payload(), $manifest->contract()->inventory());
    $assert($manifest->contract()->targetWebcoreVersion() === '0.12.0', 'Official manifest target is incorrect.');
    $assert($manifest->contract()->packageType() === 'copot-webcore', 'Official manifest package type is incorrect.');
    $assert(count($manifest->payload()->files()) + 1 === count($payload->files()), 'Manifest was not excluded from apply payload.');
    $assert($manifest->payload()->archiveSha256() === $payload->archiveSha256(), 'Archive identity changed while reading metadata.');
} finally {
    if ($payload !== null) { $payload->cleanup(); }
    if (is_dir($staging)) { @rmdir($staging); }
}

echo 'WU7 focused tests passed: ' . $assertions . ' assertions.' . PHP_EOL;
