<?php

$base = dirname(__DIR__);
require $base . '/bootstrap/autoload.php';

use Copot\Core\BackupRecovery\ApplicationWriteProtectionEvidence;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\ScopedDatabaseConsistencyEvidence;
use Copot\Core\LegacyBootstrapExecutionAdmission;
use Copot\Core\LegacyBootstrapPreparation;
use Copot\Core\LegacyBootstrapRecoveryBindingResult;
use Copot\Core\LegacyBootstrapScopedProtectionComposer;
use Copot\Core\LegacyBootstrapV3ExecutionContext;
use Copot\Core\LifecycleOperationRecord;
use Copot\Core\LifecycleOperationStore;
use Copot\Core\MaintenanceCoordinator;
use Copot\Core\MigrationRunResult;
use Copot\Core\NormalWebcoreProtectedMutationSession;
use Copot\Core\PackageLifecycleService;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$throwsRetired = static function (callable $callback, string $message) use ($assert): void {
    try { $callback(); }
    catch (Throwable $exception) {
        $assert(str_contains(strtolower($exception->getMessage()), 'retired'), $message . ': wrong rejection');
        return;
    }
    $assert(false, $message . ': call was not rejected');
};
$empty = static fn (string $class): object => (new ReflectionClass($class))->newInstanceWithoutConstructor();

$retirementRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-r2-admission-' . bin2hex(random_bytes(6));
mkdir($retirementRoot . DIRECTORY_SEPARATOR . 'storage', 0700, true);
$operations = new LifecycleOperationStore($retirementRoot . DIRECTORY_SEPARATOR . 'storage');
$before = $operations->read();
$admission = $empty(LegacyBootstrapExecutionAdmission::class);
$throwsRetired(static fn () => LegacyBootstrapExecutionAdmission::issue(
    $empty(LegacyBootstrapV3ExecutionContext::class),
    $empty(LegacyBootstrapRecoveryBindingResult::class),
    $empty(NormalWebcoreProtectedMutationSession::class),
    $empty(RecoveryArtifactStore::class),
    $operations,
    $empty(LegacyBootstrapScopedProtectionComposer::class),
    $empty(ApplicationWriteProtectionEvidence::class),
    $empty(ScopedDatabaseConsistencyEvidence::class)
), 'Admission issue');
$throwsRetired(static fn () => $admission->authorize(), 'Pre-existing admission authorize');
$throwsRetired(static fn () => $admission->complete(), 'Pre-existing admission complete');
$throwsRetired(static fn () => $admission->fail('retired'), 'Pre-existing admission fail');
$authorized = new ReflectionProperty(LegacyBootstrapExecutionAdmission::class, 'authorized');
$authorized->setValue($admission, true);
$throwsRetired(static fn () => $admission->complete(), 'Pre-existing authorized admission complete');
$throwsRetired(static fn () => $admission->fail('retired-authorized'), 'Pre-existing authorized admission fail');
$assert($operations->read() === $before, 'Retired admission changed operation state.');

$operationId = 'legacy-retirement-negative-proof-' . bin2hex(random_bytes(8));
$staging = $retirementRoot . DIRECTORY_SEPARATOR . 'staging';
mkdir($staging, 0700, true);
$record = new LifecycleOperationRecord(
    $operationId,
    LegacyBootstrapPreparation::OPERATION_TYPE,
    '0.13.0',
    'retired-release',
    hash('sha256', 'retired-archive'),
    $staging,
    hash('sha256', 'retired-payload'),
    hash('sha256', 'retired-plan'),
    LifecycleOperationRecord::BLOCKED,
    0,
    null,
    hash('sha256', 'retired-migration'),
    null,
    gmdate(DATE_ATOM),
    gmdate(DATE_ATOM)
);
$operations->create($record);
$service = (new ReflectionClass(PackageLifecycleService::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(PackageLifecycleService::class, 'maintenance'))->setValue($service, new MaintenanceCoordinator($operations));
$beforeRetry = $operations->read()?->toArray();
$retry = $service->retry($operationId);
$assert($retry->status() === 'rejected', 'Persisted legacy retry was not rejected.');
$assert(str_contains(strtolower($retry->reason()), 'classification'), 'Legacy retry rejection was not classification-based.');
$assert($operations->read()?->toArray() === $beforeRetry, 'Rejected legacy retry changed persisted operation state.');
$assert($service->retrySource($operationId) === null, 'Retired legacy retry exposed a retained source path.');

$awaiting = $record->advance(LifecycleOperationRecord::AWAITING_WU6, 1, 'future.php', MigrationRunResult::COMPLETED);
$operations->save($awaiting);
$beforeWu6 = $operations->read()?->toArray();
$wu6 = $service->continueAwaitingWu6($operationId);
$assert($wu6->status() === 'rejected', 'Persisted legacy WU6 continuation was not rejected.');
$assert(str_contains(strtolower($wu6->reason()), 'retired'), 'Legacy WU6 rejection did not identify the retired boundary.');
$assert($operations->read()?->toArray() === $beforeWu6, 'Rejected legacy WU6 continuation changed persisted operation state.');
$assert($operations->read()?->phase() === LifecycleOperationRecord::AWAITING_WU6, 'Legacy WU6 rejection altered the operation phase.');

echo "Bootstrap retirement admission/WU6 assertions: {$assertions}" . PHP_EOL;
