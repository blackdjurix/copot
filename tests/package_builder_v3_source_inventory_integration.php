<?php

declare(strict_types=1);

use Copot\Core\CanonicalTargetInventory;
use Copot\Core\CanonicalTargetInventoryEntry;
use Copot\Core\CommittedLifecycleState;
use Copot\Core\CommittedLifecycleStateStore;
use Copot\Core\CoreMigrationStateIdentity;
use Copot\Core\InstallationState;
use Copot\Core\PackageContract;
use Copot\Core\PackageManifestReader;
use Copot\Core\PackageV3SourceRelativePlanner;
use Copot\Core\SourceInventoryArtifactStore;
use Copot\Core\Version;
use Copot\Core\V3SourceRelativePlan;
use Copot\Core\ZipIntakeService;

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

if (!extension_loaded('zip')) {
    echo "V3 source inventory builder integration skipped: ext-zip unavailable.\n";
    exit(0);
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-builder-v3-source-' . bin2hex(random_bytes(6));
mkdir($root, 0700, true);

$remove = static function (string $path) use (&$remove): void {
    if (is_file($path) || is_link($path)) {
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

$runBuilder = static function (string $output, ?string $fingerprint = null) use ($base): void {
    $arguments = [
        '--filesystem-only',
    ];
    if ($fingerprint !== null) {
        $arguments[] = '--source-inventory-fingerprint=' . $fingerprint;
    }
    $arguments[] = '--output=' . $output;
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($base . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'package.php');
    foreach ($arguments as $argument) {
        $command .= ' ' . escapeshellarg($argument);
    }
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $base);
    if (!is_resource($process)) {
        throw new RuntimeException('Builder process could not start.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    if ($code !== 0) {
        throw new RuntimeException("Builder failed: {$stderr}\n{$stdout}");
    }
};

$readManifest = static function (string $zipPath, string $stagingPath) use ($base): array {
    mkdir($stagingPath, 0700, true);
    $payload = (new ZipIntakeService($base, $stagingPath))->intake($zipPath);
    try {
        return [$payload, (new PackageManifestReader())->read($payload)];
    } catch (Throwable $exception) {
        $payload->cleanup();
        throw $exception;
    }
};

$expectPlannerFailure = static function (callable $operation, string $label, string $expectedDiagnostic) use ($assert): void {
    try {
        $operation();
    } catch (RuntimeException $exception) {
        $assert($exception->getMessage() === $expectedDiagnostic, $label . ' failed with an unexpected diagnostic: ' . $exception->getMessage());
        return;
    } catch (Throwable $exception) {
        throw new RuntimeException($label . ' threw an unexpected exception type: ' . get_class($exception), 0, $exception);
    }
    throw new RuntimeException($label . ' was accepted unexpectedly.');
};

try {
    $initialZip = $root . DIRECTORY_SEPARATOR . 'initial.zip';
    $allowlistedZip = $root . DIRECTORY_SEPARATOR . 'allowlisted.zip';
    $runBuilder($initialZip);
    [$initialPayload, $initialManifest] = $readManifest($initialZip, $root . DIRECTORY_SEPARATOR . 'initial-stage');
    try {
        $targetEntries = array_map(
            static fn (CanonicalTargetInventoryEntry $entry): CanonicalTargetInventoryEntry => $entry,
            $initialManifest->contract()->targetInventory()?->entries() ?? []
        );
        $targetInventory = new CanonicalTargetInventory($targetEntries);
    } finally {
        $initialPayload->cleanup();
    }

    $storage = $root . DIRECTORY_SEPARATOR . 'storage';
    mkdir($storage, 0700, true);
    $artifacts = new SourceInventoryArtifactStore($storage, static fn ($resource): bool => true);
    $sourceArtifact = $artifacts->publish(
        $targetInventory,
        'copot-v' . Version::CURRENT,
        PackageContract::V3_MANIFEST_CONTRACT_VERSION
    );
    $installationState = new InstallationState($storage);
    $installationState->createMarker(Version::CURRENT);
    $committedState = new CommittedLifecycleState(
        Version::CURRENT,
        'copot-v' . Version::CURRENT,
        null,
        PackageContract::V3_MANIFEST_CONTRACT_VERSION,
        'canonical-current',
        CoreMigrationStateIdentity::fromRecords([]),
        new DateTimeImmutable('now'),
        null,
        $sourceArtifact->reference()
    );
    $committedStore = new CommittedLifecycleStateStore($storage);
    $committedStore->commitWithSourceInventory($installationState, $committedState, $artifacts, $sourceArtifact);
    $readState = $committedStore->read();
    $assert($readState instanceof CommittedLifecycleState, 'Committed V3 lifecycle state was not written.');
    $readReference = $readState->sourceInventoryReference();
    $assert($readReference !== null, 'Committed V3 source inventory reference is missing.');
    $committedArtifact = $artifacts->read($readReference);
    $sourceFingerprint = $committedArtifact->inventory()->fingerprint();
    $assert(preg_match('/\A[a-f0-9]{64}\z/D', $sourceFingerprint) === 1, 'Committed V3 source inventory fingerprint is invalid.');

    $runBuilder($allowlistedZip, $sourceFingerprint);
    [$allowlistedPayload, $allowlistedManifest] = $readManifest($allowlistedZip, $root . DIRECTORY_SEPARATOR . 'allowlisted-stage');
    try {
        $contract = $allowlistedManifest->contract();
        $assert($contract->allowedInventoryFingerprints() === [$sourceFingerprint], 'Builder did not preserve the committed source fingerprint exactly.');
        $plan = (new PackageV3SourceRelativePlanner())->plan($allowlistedManifest, $committedArtifact);
        $assert($plan->sourceFingerprint() === $sourceFingerprint, 'Planner did not accept the matching committed source fingerprint.');

        $extraEntry = new CanonicalTargetInventoryEntry(
            CanonicalTargetInventoryEntry::APP_ROOT,
            CanonicalTargetInventoryEntry::PACKAGE_OWNED,
            'app/__source-mismatch__.php',
            1,
            hash('sha256', 'x')
        );
        $mismatchedSource = $artifacts->publish(
            new CanonicalTargetInventory([...$targetInventory->entries(), $extraEntry]),
            'copot-v' . Version::CURRENT,
            PackageContract::V3_MANIFEST_CONTRACT_VERSION
        );
        $expectPlannerFailure(
            static fn (): V3SourceRelativePlan => (new PackageV3SourceRelativePlanner())->plan($allowlistedManifest, $mismatchedSource),
            'Source fingerprint mismatch finding',
            'Committed source inventory is not compatible with the v3 source requirements.'
        );

        $targetMismatch = new CanonicalTargetInventory([...$targetInventory->entries(), $extraEntry]);
        $targetMismatchContract = new PackageContract(
            $contract->packageType(),
            $contract->manifestContractVersion(),
            $contract->targetWebcoreVersion(),
            $contract->releaseIdentity(),
            $contract->sourceTreeIdentity(),
            $contract->sourceCompatibility(),
            $contract->runtimeCompatibility(),
            $contract->inventory(),
            $contract->migrationDeclaration(),
            $contract->targetRequirements(),
            $targetMismatch,
            $contract->coverage(),
            [$sourceFingerprint],
            $contract->removals(),
            $contract->renames()
        );
        $expectPlannerFailure(
            static fn (): V3SourceRelativePlan => (new PackageV3SourceRelativePlanner())->plan($targetMismatchContract, $committedArtifact),
            'Target inventory mismatch finding',
            'Complete target inventory entry is neither delivered nor provably retained.'
        );

        $removalContract = new PackageContract(
            $contract->packageType(),
            $contract->manifestContractVersion(),
            $contract->targetWebcoreVersion(),
            $contract->releaseIdentity(),
            $contract->sourceTreeIdentity(),
            $contract->sourceCompatibility(),
            $contract->runtimeCompatibility(),
            $contract->inventory(),
            $contract->migrationDeclaration(),
            $contract->targetRequirements(),
            $contract->targetInventory(),
            $contract->coverage(),
            [$sourceFingerprint],
            [[
                'root' => CanonicalTargetInventoryEntry::APP_ROOT,
                'path' => 'app/__missing__.php',
                'ownership' => CanonicalTargetInventoryEntry::PACKAGE_OWNED,
                'expected_source_byte_size' => 1,
                'expected_source_sha256' => hash('sha256', 'x'),
                'reason' => 'obsolete',
            ]],
            $contract->renames()
        );
        $expectPlannerFailure(
            static fn (): V3SourceRelativePlan => (new PackageV3SourceRelativePlanner())->plan($removalContract, $committedArtifact),
            'Explicit removal evidence finding',
            'V3 removal source evidence is stale or untrusted.'
        );
    } finally {
        $allowlistedPayload->cleanup();
    }

    echo 'V3 source inventory builder integration passed: ' . $assertions . " assertions.\n";
    echo "Planner findings: source-mismatch=detected; target-inventory-mismatch=detected; explicit-removal-evidence-mismatch=detected.\n";
} finally {
    $remove($root);
}
