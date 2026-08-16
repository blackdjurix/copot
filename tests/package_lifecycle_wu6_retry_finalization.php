<?php

declare(strict_types=1);

use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryLifecycleRecord;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;
use Copot\Core\BackupRecovery\RecoveryStorageRoot;
use Copot\Core\LifecycleOperationRecord;
use Copot\Core\LifecycleOperationStore;
use Copot\Core\MaintenanceCoordinator;
use Copot\Core\PackageLifecycleService;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) { throw new RuntimeException($message); }
};

$serviceSource = (string) file_get_contents($base . '/app/Core/PackageLifecycleService.php');
$factorySource = (string) file_get_contents($base . '/app/Core/PackageLifecycleFactory.php');
$managerSource = (string) file_get_contents($base . '/app/Core/SystemManagerLifecycleService.php');
$assert(str_contains($factorySource, 'new HealthIntegrityCommitCoordinator('), 'Production factory does not compose the health/integrity commit coordinator.');
$assert(str_contains($managerSource, '$this->lifecycle->retry($operationId)'), 'System Manager does not route Retry through PackageLifecycleService.');
$retryStart = strpos($serviceSource, 'public function retry(string $operationId)');
$statusStart = strpos($serviceSource, 'public function status()', $retryStart === false ? 0 : $retryStart);
$retrySource = $retryStart !== false && $statusStart !== false ? substr($serviceSource, $retryStart, $statusStart - $retryStart) : '';
$assert($retrySource !== '', 'Retry implementation boundary could not be inspected.');
$assert(str_contains($retrySource, '$this->applyCoordinator->execute($applyPlan, $transition, $migration, $record)'), 'Retry does not resume the persisted operation lineage.');
$assert(str_contains($retrySource, '$this->healthCoordinator->finalize('), 'Retry does not use the existing health/integrity finalization path.');
$assert(str_contains($retrySource, "HealthIntegrityCommitResult::COMPLETED"), 'Retry does not require completed health/integrity finalization.');
$assert(str_contains($retrySource, "new PackageLifecycleResult(true, 'completed'"), 'Retry does not return committed success only after finalization.');
$assert(str_contains($retrySource, '$payload->cleanup();'), 'Retry does not clean up its staged payload after resumed execution.');
$assert(str_contains($serviceSource, 'LifecycleOperationRecord::INDETERMINATE'), 'Retry does not preserve the accepted indeterminate recovery lineage boundary.');
$assert(!str_contains($retrySource, "'awaiting_wu6'"), 'Retry still exits before WU6 finalization.');

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu6-retry-' . bin2hex(random_bytes(5));
mkdir($root . DIRECTORY_SEPARATOR . 'storage', 0700, true);
mkdir($root . DIRECTORY_SEPARATOR . 'staging', 0700, true);
$remove = static function (string $path) use (&$remove): void {
    if (is_file($path) || is_link($path)) { @unlink($path); return; }
    if (!is_dir($path)) { return; }
    foreach (scandir($path) ?: [] as $entry) { if ($entry !== '.' && $entry !== '..') { $remove($path . DIRECTORY_SEPARATOR . $entry); } }
    @rmdir($path);
};

try {
    $operationId = 'wu6-retry-operation';
    $recovery = new RecoveryIdentity('wu6-retry-recovery');
    $manifest = hash('sha256', 'wu6-retry-manifest');
    $now = gmdate(DATE_ATOM);
    $staging = $root . DIRECTORY_SEPARATOR . 'staging';
    file_put_contents($staging . DIRECTORY_SEPARATOR . 'source.zip', 'retained-package');
    $operations = new LifecycleOperationStore($root . DIRECTORY_SEPARATOR . 'storage');
    $operations->create(new LifecycleOperationRecord(
        $operationId,
        'update',
        '1.2.3',
        'wu6-release',
        hash('sha256', 'archive'),
        $staging,
        hash('sha256', 'payload'),
        hash('sha256', 'apply-plan'),
        LifecycleOperationRecord::INDETERMINATE,
        1,
        'app/example.php',
        hash('sha256', 'migration-plan'),
        null,
        $now,
        $now,
        'interrupted',
        $recovery->value(),
        $manifest,
        RecoveryLifecycleState::READY
    ));
    $recoveryStore = new RecoveryLifecycleStore(new RecoveryStorageRoot($root, $root . DIRECTORY_SEPARATOR . 'recovery', 'wu6-retry-project'));
    $recoveryStore->create(new RecoveryLifecycleRecord($recovery, $manifest, $operationId, RecoveryLifecycleState::READY, false, true, $recovery->value(), $manifest, $manifest));
    $service = (new ReflectionClass(PackageLifecycleService::class))->newInstanceWithoutConstructor();
    $set = static function (string $property, mixed $value) use ($service): void {
        $reflection = new ReflectionProperty($service, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($service, $value);
    };
    $set('maintenance', new MaintenanceCoordinator($operations));
    $set('recoveryEvidenceValidator', static function (LifecycleOperationRecord $record) use ($recoveryStore): bool {
        $evidence = $recoveryStore->read(new RecoveryIdentity((string) $record->recoveryIdentity()));
        return $evidence->operationIdentity() === $record->operationId()
            && $evidence->manifestIdentity() === $record->recoveryManifestIdentity()
            && $evidence->state() === RecoveryLifecycleState::READY
            && !$evidence->mutationStarted();
    });
    $assert($service->retryEvidence($operationId), 'Persisted READY Retry evidence was not accepted.');
    $assert($service->retrySource($operationId) === $staging . DIRECTORY_SEPARATOR . 'source.zip', 'Retained package source was not recovered.');
    $assert($operations->read()?->operationId() === $operationId, 'Persisted operation lineage was not preserved.');
    $assert($operations->read()?->phase() === LifecycleOperationRecord::INDETERMINATE, 'Failure lineage was not retained as indeterminate for recovery.');
    echo "WU6 Retry finalization topology passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $remove($root);
}
