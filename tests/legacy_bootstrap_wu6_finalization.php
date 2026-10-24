<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$service = (string) file_get_contents($basePath . '/app/Core/PackageLifecycleService.php');
$health = (string) file_get_contents($basePath . '/app/Core/HealthIntegrityCommitCoordinator.php');

$routing = substr($service, strpos($service, 'private function finalizeAwaitingWu6'));
$assert(str_contains($routing, 'LegacyBootstrapPreparation::OPERATION_TYPE'), 'Bootstrap WU6 routing does not check the operation classification.');
$assert(str_contains($routing, '$this->healthCoordinator->finalizeV3('), 'Bootstrap WU6 routing does not use canonical V3 finalization.');
$assert(str_contains($routing, '$this->healthCoordinator->finalize('), 'Generic WU6 finalization fallback was removed.');
$assert(str_contains($routing, "coverage() !== 'full'"), 'Bootstrap WU6 routing does not require full package coverage.');
$assert(str_contains($routing, 'sourceReference() !== null'), 'Bootstrap WU6 routing does not reject a pre-existing source reference.');
$assert(str_contains($routing, 'removalActions() !== []'), 'Bootstrap WU6 routing does not enforce deliver-only finalization.');
$assert(str_contains($service, 'bootstrapCleanupPending'), 'Bootstrap cleanup-pending continuation is not explicitly bounded.');

$finalizeV3Start = strpos($health, 'public function finalizeV3');
$finalizeV3End = strpos($health, 'public function finalizeCommittedSourceAuthority', $finalizeV3Start);
$finalizeV3 = substr($health, $finalizeV3Start, $finalizeV3End - $finalizeV3Start);
$assert(str_contains($finalizeV3, 'commitWithSourceInventory'), 'V3 finalization does not durably link the committed source inventory.');
$assert(str_contains($finalizeV3, 'sourceArtifacts->read($reference)'), 'V3 cleanup continuation does not reread the committed source artifact.');
$assert(str_contains($finalizeV3, 'retryCleanup'), 'V3 cleanup-pending continuation does not use the canonical cleanup path.');
$assert(substr_count($finalizeV3, 'sourceArtifacts->publish') === 1, 'V3 finalization has an unexpected duplicate publication call.');
$healthGatePosition = strpos($finalizeV3, 'if (!$gates->passed())');
$publicationPosition = strpos($finalizeV3, '$source = $sourceArtifacts->publish');
$linkagePosition = strpos($finalizeV3, '$this->committedStore->commitWithSourceInventory');
$cleanupPosition = strpos($finalizeV3, '$this->maintenance->clear');
$assert($healthGatePosition !== false && $publicationPosition !== false && $healthGatePosition < $publicationPosition, 'Source publication is not ordered after WU6 health gates.');
$assert($publicationPosition < $linkagePosition && $linkagePosition < $cleanupPosition, 'Publication, committed linkage, and cleanup ordering is not canonical.');
$assert(str_contains($finalizeV3, "HealthIntegrityCommitResult::CLEANUP_PENDING"), 'Cleanup interruption does not preserve a truthful cleanup-pending result.');
$assert(str_contains($service, 'recoveryState() !== \\Copot\\Core\\BackupRecovery\\RecoveryLifecycleState::READY'), 'Bootstrap continuation does not retain READY recovery gating.');

$storage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-c5c-wu6-' . bin2hex(random_bytes(6));
mkdir($storage, 0700, true);
$remove = static function (string $path) use (&$remove): void {
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $remove($path . DIRECTORY_SEPARATOR . $entry);
            }
        }
        @rmdir($path);
        return;
    }
    @unlink($path);
};

try {
    $inventory = new Copot\Core\CanonicalTargetInventory([
        new Copot\Core\CanonicalTargetInventoryEntry(
            Copot\Core\CanonicalTargetInventoryEntry::APP_ROOT,
            Copot\Core\CanonicalTargetInventoryEntry::PACKAGE_OWNED,
            'app/Core/bootstrap.php',
            3,
            hash('sha256', 'v3')
        ),
    ]);
    $artifacts = new Copot\Core\SourceInventoryArtifactStore($storage);
    $first = $artifacts->publish($inventory, 'copot-v0.13.0', 3, null);
    $second = $artifacts->publish($inventory, 'copot-v0.13.0', 3, null);
    $assert($first->reference()->toArray() === $second->reference()->toArray(), 'Repeated source publication changed the artifact identity.');
    $files = array_values(array_filter(scandir($storage . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'source-inventory') ?: [], static fn (string $name): bool => str_ends_with($name, '.json')));
    $assert(count($files) === 1, 'Repeated source publication created duplicate artifacts.');
} finally {
    $remove($storage);
}

echo "Legacy bootstrap WU6 finalization assertions passed ({$assertions} assertions)." . PHP_EOL;
