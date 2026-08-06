<?php

declare(strict_types=1);

use Copot\Core\PackageInventoryVerifier;
use Copot\Core\PackageManifestReader;
use Copot\Core\PackageLifecycleResult;
use Copot\Core\PackageLifecycleStatus;
use Copot\Core\PackageLifecycleFactory;
use Copot\Core\PackageApplyTemporaryRoot;
use Copot\Core\InstalledStateInspection;
use Copot\Core\InstalledStateSnapshot;
use Copot\Core\InstalledStateStatus;
use Copot\Core\LifecycleOperationRecord;
use Copot\Core\MigrationRunResult;
use Copot\Core\ZipIntakeService;
use Copot\Core\Version;

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

$package = $basePath . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'copot-v' . Version::CURRENT . '.zip';
if (!is_file($package)) {
    throw new RuntimeException('Official package artifact is missing; run build/package.php first.');
}

$staging = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu7-staging-' . bin2hex(random_bytes(6));
mkdir($staging, 0700, true);
$payload = null;
try {
    $factoryService = PackageLifecycleFactory::forProject($basePath);
    $assert($factoryService instanceof Copot\Core\PackageLifecycleService, 'Production lifecycle factory could not be constructed.');

    $applyTemporaryRoot = PackageApplyTemporaryRoot::forProject($basePath);
    $canonicalBasePath = realpath($basePath);
    $assert(is_string($canonicalBasePath) && !str_starts_with($applyTemporaryRoot . DIRECTORY_SEPARATOR, $canonicalBasePath . DIRECTORY_SEPARATOR), 'Factory apply temporary root overlaps the live Webcore root.');

    $otherProject = $staging . DIRECTORY_SEPARATOR . 'other-project';
    mkdir($otherProject, 0700);
    $otherApplyTemporaryRoot = PackageApplyTemporaryRoot::forProject($otherProject);
    $assert($otherApplyTemporaryRoot !== $applyTemporaryRoot, 'Different projects shared an apply temporary namespace.');

    $payload = (new ZipIntakeService($basePath, $staging))->intake($package);
    $manifest = (new PackageManifestReader())->read($payload);
    (new PackageInventoryVerifier())->verify($manifest->payload(), $manifest->contract()->inventory());
    $assert($manifest->contract()->targetWebcoreVersion() === Version::CURRENT, 'Official manifest target is incorrect.');
    $assert($manifest->contract()->packageType() === 'copot-webcore', 'Official manifest package type is incorrect.');
    $assert(count($manifest->payload()->files()) + 1 === count($payload->files()), 'Manifest was not excluded from apply payload.');
    $assert($manifest->payload()->archiveSha256() === $payload->archiveSha256(), 'Archive identity changed while reading metadata.');

    foreach (['planned' => 0, 'invalid_package' => 3, 'rejected' => 4, 'blocked' => 5, 'failed' => 6, 'unavailable' => 7] as $status => $code) {
        $assert((new PackageLifecycleResult($code === 0, $status))->exitCode() === $code, 'Exit code mapping is incorrect for ' . $status . '.');
    }

    $rawZip = $staging . DIRECTORY_SEPARATOR . 'backslash.zip';
    $zip = new ZipArchive();
    $assert($zip->open($rawZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'Raw-name regression ZIP could not be created.');
    $assert($zip->addFromString('raw\\name.txt', 'raw-name'), 'Raw-name regression entry could not be added.');
    $zip->close();
    $rawPayload = (new ZipIntakeService($basePath, $staging))->intake($rawZip);
    try {
        $rawFiles = $rawPayload->files();
        $assert(count($rawFiles) === 1 && $rawFiles[0]->path() === 'raw/name.txt', 'Backslash archive entry was not normalized canonically.');
        $assert($rawFiles[0]->byteSize() === 8 && $rawFiles[0]->sha256() === hash('sha256', 'raw-name'), 'Backslash archive entry identity changed.');
    } finally { $rawPayload->cleanup(); @unlink($rawZip); }

    $record = new LifecycleOperationRecord(
        'status-operation', 'repair', '0.12.0', 'release', str_repeat('a', 64), '/private/staging',
        str_repeat('b', 64), str_repeat('c', 64), LifecycleOperationRecord::CLEANUP_PENDING, 1, 'app.php',
        str_repeat('d', 64), MigrationRunResult::COMPLETED, gmdate(DATE_ATOM), gmdate(DATE_ATOM)
    );
    $status = PackageLifecycleStatus::describe(InstalledStateInspection::committed(new InstalledStateSnapshot('0.12.0', new DateTimeImmutable())), $record, 'interrupted');
    $assert($status['installed_state'] === InstalledStateStatus::COMMITTED && $status['maintenance'] === 'active', 'Status did not preserve committed state and maintenance state.');
    $assert(($status['operation']['state'] ?? null) === 'interrupted' && ($status['operation']['phase'] ?? null) === LifecycleOperationRecord::CLEANUP_PENDING, 'Status did not expose bounded operation recovery state.');
} finally {
    if ($payload !== null) { $payload->cleanup(); }
    if (is_dir($staging)) { @rmdir($staging); }
}

echo 'WU7 focused tests passed: ' . $assertions . ' assertions.' . PHP_EOL;
