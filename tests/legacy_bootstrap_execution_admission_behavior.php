<?php

require __DIR__ . '/legacy_to_v3_bootstrap_wu2.php';

use Copot\Core\LifecycleOperationRecord;
use Copot\Core\LifecycleOperationStore;
use Copot\Core\LegacyBootstrapPreparation;
use Copot\Core\MaintenanceCoordinator;
use Copot\Core\MigrationRunResult;
use Copot\Core\PackageLifecycleService;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

// The shared WU2 fixture still proves that historical admission/recovery
// evidence is durable and inspectable, but it must no longer be executable.
$assert($eligible->eligible() && $eligible->sourceInventory() !== null, 'Historical bootstrap evidence lost its provisional boundary.');
$assert(is_string($eligible->artifactPath()) && is_file($eligible->artifactPath()), 'Historical bootstrap artifact is no longer durable.');
$assert($handoff->artifact()->operationIdentity() === $eligible->operationIdentity(), 'Historical handoff lost operation identity binding.');
$assert(!$handoff->recoveryBound(), 'Historical handoff unexpectedly granted recovery authority.');

$serviceReflection = new ReflectionClass(PackageLifecycleService::class);
$assert(!$serviceReflection->hasMethod('prepareLegacyToV3Bootstrap'), 'Retired bootstrap preparation API is still exposed.');
$assert(!$serviceReflection->hasMethod('executeLegacyToV3Bootstrap'), 'Retired bootstrap execution API is still exposed.');
$serviceSource = file_get_contents(dirname(__DIR__) . '/app/Core/PackageLifecycleService.php');
$assert(is_string($serviceSource), 'Package lifecycle service could not be read.');
$assert(!str_contains($serviceSource, 'new LegacyBootstrapV3ExecutionBridge'), 'Shared service still composes the retired bootstrap bridge.');

$retirementRoot = $tmp . DIRECTORY_SEPARATOR . 'retirement-admission';
mkdir($retirementRoot . DIRECTORY_SEPARATOR . 'storage', 0700, true);
$operations = new LifecycleOperationStore($retirementRoot . DIRECTORY_SEPARATOR . 'storage');
$operationId = 'legacy-retirement-negative-proof-' . bin2hex(random_bytes(8));
$staging = $retirementRoot . DIRECTORY_SEPARATOR . 'staging';
mkdir($staging, 0700, true);
$record = new LifecycleOperationRecord(
    $operationId,
    LegacyBootstrapPreparation::OPERATION_TYPE,
    $handoff->target()->contract()->targetWebcoreVersion(),
    $handoff->target()->contract()->releaseIdentity(),
    $handoff->target()->archiveIdentity(),
    $staging,
    $handoff->target()->payloadIdentity(),
    $handoff->applyPlan()->identity(),
    LifecycleOperationRecord::BLOCKED,
    0,
    null,
    hash('sha256', 'retirement-migration-plan'),
    null,
    gmdate(DATE_ATOM),
    gmdate(DATE_ATOM)
);
$operations->create($record);

$service = $serviceReflection->newInstanceWithoutConstructor();
$maintenanceProperty = new ReflectionProperty(PackageLifecycleService::class, 'maintenance');
$maintenanceProperty->setValue($service, new MaintenanceCoordinator($operations));

$beforeRetry = $operations->read()?->toArray();
$retry = $service->retry($operationId);
$afterRetry = $operations->read()?->toArray();
$assert($retry->status() === 'rejected', 'Persisted legacy retry was not rejected.');
$assert(str_contains(strtolower($retry->reason()), 'classification'), 'Legacy retry rejection was not classification-based.');
$assert($beforeRetry === $afterRetry, 'Rejected legacy retry changed persisted operation state.');
$assert($service->retrySource($operationId) === null, 'Retired legacy retry exposed a retained source path.');

$awaiting = $record->advance(
    LifecycleOperationRecord::AWAITING_WU6,
    1,
    'future.php',
    MigrationRunResult::COMPLETED
);
$operations->save($awaiting);
$beforeWu6 = $operations->read()?->toArray();
$wu6 = $service->continueAwaitingWu6($operationId);
$afterWu6 = $operations->read()?->toArray();
$assert($wu6->status() === 'rejected', 'Persisted legacy WU6 continuation was not rejected.');
$assert(str_contains(strtolower($wu6->reason()), 'retired'), 'Legacy WU6 rejection did not identify the retired boundary.');
$assert($beforeWu6 === $afterWu6, 'Rejected legacy WU6 continuation changed persisted operation state.');
$assert($operations->read()?->phase() === LifecycleOperationRecord::AWAITING_WU6, 'Legacy WU6 rejection altered the operation phase.');

echo "Bootstrap retirement admission/WU6 assertions: {$assertions}" . PHP_EOL;
