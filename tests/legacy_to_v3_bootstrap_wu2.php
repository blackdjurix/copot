<?php

$base = dirname(__DIR__);
require $base . '/bootstrap/autoload.php';

use Copot\Core\CanonicalTargetInventoryEntry;
use Copot\Core\CoreMigrationRegistry;
use Copot\Core\InstalledStateStatus;
use Copot\Core\LegacyBootstrapPreparation;
use Copot\Core\LegacyBootstrapSourceArtifactStore;
use Copot\Core\LegacyBootstrapSourceEntry;
use Copot\Core\LegacyBootstrapSourceInventory;
use Copot\Core\LegacyClassificationResult;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$throws = static function (callable $callback, string $message) use ($assert): void {
    try { $callback(); }
    catch (Throwable) { return; }
    $assert(false, $message);
};

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu2-retired-' . bin2hex(random_bytes(6));
$storage = $tmp . DIRECTORY_SEPARATOR . 'storage';
mkdir($storage, 0700, true);
$artifactRoot = $storage . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'legacy-bootstrap-source';
$store = new LegacyBootstrapSourceArtifactStore($storage);
$assert(!is_dir($artifactRoot), 'Artifact-store construction must not create retired storage.');
mkdir($artifactRoot, 0700, true);

$registry = CoreMigrationRegistry::forProject($base);
$sourceEntry = new LegacyBootstrapSourceEntry(
    CanonicalTargetInventoryEntry::APP_ROOT,
    'legacy.php',
    6,
    hash('sha256', 'legacy'),
    'legacy-live-tree-scan',
    'unverified'
);
$inventory = new LegacyBootstrapSourceInventory([$sourceEntry]);
$operationIdentity = 'legacy-to-v3-bootstrap-sha256:' . str_repeat('a', 64);
$planIdentity = hash('sha256', 'retired-plan');
$packageIdentity = hash('sha256', 'retired-package');
$targetIdentity = hash('sha256', 'retired-target');
$archiveIdentity = hash('sha256', 'retired-archive');
$payloadIdentity = hash('sha256', 'retired-payload');
$classification = LegacyClassificationResult::canonicalBaseline('schema-test', '0.13.0');
$record = [
    'serialization_version' => 3,
    'operation_type' => LegacyBootstrapPreparation::OPERATION_TYPE,
    'operation_identity' => $operationIdentity,
    'installation_identity' => 'installation-retired-fixture',
    'plan_identity' => $planIdentity,
    'package_identity' => $packageIdentity,
    'target_inventory_fingerprint' => $targetIdentity,
    'archive_identity' => $archiveIdentity,
    'payload_identity' => $payloadIdentity,
    'apply_plan_identity' => $payloadIdentity,
    'recovery_identity' => null,
    'captured_at' => gmdate(DATE_ATOM),
    'provenance' => ['kind' => 'retained-historical-fixture'],
    'legacy_package_evidence' => ['retained' => true],
    'legacy_classification' => $classification->toEvidenceArray($registry->identity(), InstalledStateStatus::LEGACY),
    'source_inventory' => ['fingerprint' => $inventory->fingerprint(), 'entries' => [$sourceEntry->toArray()]],
    'collisions' => [['action' => 'ADD', 'root' => 'APP_ROOT', 'path' => 'future.php', 'reason' => 'historical target absence']],
    'status' => LegacyBootstrapPreparation::ELIGIBLE,
];
$path = $artifactRoot . DIRECTORY_SEPARATOR . hash('sha256', $operationIdentity) . '.json';
$bytes = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
file_put_contents($path, $bytes);
$beforeBytes = file_get_contents($path);
$rehydrated = $store->rehydrate($path, [
    'operation_identity' => $operationIdentity,
    'installation_identity' => $record['installation_identity'],
    'plan_identity' => $planIdentity,
    'package_identity' => $packageIdentity,
    'target_inventory_fingerprint' => $targetIdentity,
    'archive_identity' => $archiveIdentity,
    'payload_identity' => $payloadIdentity,
    'apply_plan_identity' => $payloadIdentity,
    'source_inventory_fingerprint' => $inventory->fingerprint(),
]);
$assert($rehydrated->provisional() && !$rehydrated->operationalReady(), 'Historical artifact rehydration granted authority.');
$assert($rehydrated->operationIdentity() === $operationIdentity, 'Historical operation identity changed during rehydration.');
$assert($rehydrated->classification()->classification() === $classification->classification(), 'Classification evidence changed during rehydration.');
$assert($rehydrated->sourceInventoryFingerprint() === $inventory->fingerprint(), 'Source inventory fingerprint changed during rehydration.');
$assert($rehydrated->collisions() === $record['collisions'], 'Historical collision evidence changed during rehydration.');
$assert(file_get_contents($path) === $beforeBytes, 'Rehydration changed immutable artifact bytes.');

$tampered = $record;
$tampered['status'] = 'INVALID';
file_put_contents($path, json_encode($tampered, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL);
$throws(static fn () => $store->rehydrate($path), 'Malformed/status-invalid artifact was accepted.');
file_put_contents($path, $beforeBytes);
$throws(static fn () => $store->rehydrate($path, ['operation_identity' => 'legacy-to-v3-bootstrap-sha256:' . str_repeat('b', 64)]), 'Identity-mismatched artifact was accepted.');
$assert(file_get_contents($path) === $beforeBytes, 'Identity rejection changed historical artifact bytes.');

echo "Retired WU2 historical evidence assertions: {$assertions}" . PHP_EOL;
