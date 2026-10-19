<?php

require __DIR__ . '/legacy_to_v3_bootstrap_wu2.php';

use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\LegacyBootstrapRecoveryBindingResult;
use Copot\Core\LegacyBootstrapV3ExecutionPlanner;
use Copot\Core\LifecycleOperationRecord;

$bridgeAssertions = 0;
$bridgeAssert = static function (bool $condition, string $message) use (&$bridgeAssertions): void {
    $bridgeAssertions++;
    if (!$condition) throw new RuntimeException($message);
};

$operationStore = new Copot\Core\LifecycleOperationStore($tmp . '/app/storage');
$operation = new LifecycleOperationRecord(
    $handoff->artifact()->operationIdentity(),
    'LEGACY_TO_V3_BOOTSTRAP',
    $handoff->target()->contract()->targetWebcoreVersion(),
    $handoff->target()->contract()->releaseIdentity(),
    $handoff->target()->archiveIdentity(),
    $tmp . '/staging',
    $handoff->target()->payloadIdentity(),
    $handoff->applyPlan()->identity(),
    LifecycleOperationRecord::PREPARING,
    0,
    null,
    hash('sha256', 'migration'),
    null,
    gmdate(DATE_ATOM),
    gmdate(DATE_ATOM)
);
$operationStore->create($operation);
$recovery = Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureService::forOperation($operation->operationId(), $handoff->applyPlan()->identity());
$binding = new LegacyBootstrapRecoveryBindingResult(
    LegacyBootstrapRecoveryBindingResult::BOUND,
    $handoff->identity(),
    $operation->operationId(),
    $recovery->value(),
    hash('sha256', 'manifest'),
    hash('sha256', 'cross-reference'),
    null,
    []
);
$operation = $operation->bindRecovery($recovery->value(), $binding->toArray()['manifest_identity'], Copot\Core\BackupRecovery\RecoveryLifecycleState::READY);
$operationStore->save($operation);
$context = (new LegacyBootstrapV3ExecutionPlanner(new Copot\Core\PackageV3SourceRelativePlanner()))->compose($manifest, $handoff, $operation, $recovery, $binding);
$bridgeAssert($context->toArray()['operation_type'] === 'LEGACY_TO_V3_BOOTSTRAP', 'Bootstrap operation type was not durable in the bridge context.');
$bridgeAssert($context->plan()->sourceReference() === null && $context->plan()->coverage() === 'full', 'Bridge manufactured or required committed source authority.');
$bridgeAssert($context->operation()->operationId() !== $context->plan()->operationIdentity(), 'Bootstrap/V3 identity distinction was lost.');
$bridgeAssert(!$context->mutationAuthorized() && !$context->operationalReady(), 'Bridge context granted mutation or readiness authority.');
$bridgeAssert((new ReflectionMethod(Copot\Core\LegacyBootstrapV3ExecutionBridge::class, 'execute'))->getReturnType()?->getName() === Copot\Core\WebcoreApplyResult::class, 'Bootstrap execution entrypoint is not typed against the canonical result.');
$bridgeParameters = (new ReflectionMethod(Copot\Core\LegacyBootstrapV3ExecutionBridge::class, 'execute'))->getParameters();
$bridgeAssert(($bridgeParameters[1]->getType()?->getName() ?? '') === Copot\Core\LegacyBootstrapExecutionAdmission::class, 'Bootstrap execution does not require typed canonical admission.');
$admissionSource = file_get_contents(__DIR__ . '/../app/Core/LegacyBootstrapExecutionAdmission.php');
$bridgeAssert(str_contains($admissionSource, 'LegacyBootstrapScopedProtectionComposer') && str_contains($admissionSource, 'readManifest') && str_contains($admissionSource, 'readArtifact'), 'Admission does not rerun scoped protection or reread durable recovery evidence.');
$bridgeSource = file_get_contents(__DIR__ . '/../app/Core/LegacyBootstrapV3ExecutionBridge.php');
$bridgeAssert(str_contains($bridgeSource, '$admission->authorize()') && !str_contains($bridgeSource, '$captureBeforeMutation'), 'Bridge still accepts caller-supplied recovery proof.');

$throws = static function (callable $callback, string $message) use ($bridgeAssert): void {
    try { $callback(); $bridgeAssert(false, $message); } catch (Throwable) { $bridgeAssert(true, $message); }
};
$throws(static fn () => (new LegacyBootstrapV3ExecutionPlanner(new Copot\Core\PackageV3SourceRelativePlanner()))->compose($manifest, $handoff, $operation, $recovery, new LegacyBootstrapRecoveryBindingResult(LegacyBootstrapRecoveryBindingResult::STALE, $handoff->identity(), $operation->operationId(), $recovery->value(), null, null, null, ['stale'])), 'Stale recovery binding was accepted.');
$throws(static fn () => new Copot\Core\LegacyBootstrapV3ExecutionContext($handoff->artifact()->installationIdentity(), $operation, $handoff, $context->plan(), $recovery, new LegacyBootstrapRecoveryBindingResult(LegacyBootstrapRecoveryBindingResult::BOUND, $handoff->identity(), 'wrong-operation', $recovery->value(), hash('sha256', 'manifest'), null, null, [])), 'Mismatched recovery operation was accepted.');
$bridgeAssert(str_contains(file_get_contents(__DIR__ . '/../app/Core/V3ExplicitApplyExecutor.php'), 'LegacyBootstrapV3ExecutionContext'), 'Canonical executor has no bootstrap identity bridge integration.');

echo "Bootstrap execution bridge assertions: {$bridgeAssertions}" . PHP_EOL;
