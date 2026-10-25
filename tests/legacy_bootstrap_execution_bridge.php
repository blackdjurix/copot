<?php

require __DIR__ . '/legacy_to_v3_bootstrap_wu2.php';

use Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureService;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\LegacyBootstrapExecutionAdmission;
use Copot\Core\LegacyBootstrapRecoveryBindingResult;
use Copot\Core\LegacyBootstrapV3ExecutionBridge;
use Copot\Core\LegacyBootstrapV3ExecutionContext;
use Copot\Core\LegacyBootstrapV3ExecutionPlanner;
use Copot\Core\LifecycleOperationRecord;
use Copot\Core\LifecycleOperationStore;
use Copot\Core\PackageOwnedFileApplier;
use Copot\Core\PackageV3SourceRelativePlanner;
use Copot\Core\V3ExplicitApplyExecutor;
use Copot\Core\LiveFileActivationCapability;
use Copot\Core\LiveTreePathGuard;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$bridgeRoot = $tmp . DIRECTORY_SEPARATOR . 'retirement-bridge';
mkdir($bridgeRoot . DIRECTORY_SEPARATOR . 'storage', 0700, true);
$operations = new LifecycleOperationStore($bridgeRoot . DIRECTORY_SEPARATOR . 'storage');
$operationId = $handoff->artifact()->operationIdentity();
$recovery = NormalWebcoreRecoveryCaptureService::forOperation($operationId, $handoff->applyPlan()->identity());
$manifestIdentity = hash('sha256', 'retirement-bridge-manifest');
$operation = new LifecycleOperationRecord(
    $operationId,
    'LEGACY_TO_V3_BOOTSTRAP',
    $handoff->target()->contract()->targetWebcoreVersion(),
    $handoff->target()->contract()->releaseIdentity(),
    $handoff->target()->archiveIdentity(),
    $bridgeRoot . DIRECTORY_SEPARATOR . 'staging',
    $handoff->target()->payloadIdentity(),
    $handoff->applyPlan()->identity(),
    LifecycleOperationRecord::PREPARING,
    0,
    null,
    hash('sha256', 'retirement-bridge-migration'),
    null,
    gmdate(DATE_ATOM),
    gmdate(DATE_ATOM)
);
$operation = $operation->bindRecovery($recovery->value(), $manifestIdentity, RecoveryLifecycleState::READY);
$operations->create($operation);
$binding = new LegacyBootstrapRecoveryBindingResult(
    LegacyBootstrapRecoveryBindingResult::BOUND,
    $handoff->identity(),
    $operationId,
    $recovery->value(),
    $manifestIdentity,
    hash('sha256', 'retirement-bridge-cross-reference'),
    null,
    []
);
$context = (new LegacyBootstrapV3ExecutionPlanner(new PackageV3SourceRelativePlanner()))
    ->compose($manifest, $handoff, $operation, $recovery, $binding);
$assert($context instanceof LegacyBootstrapV3ExecutionContext, 'Historical bootstrap context evidence could not be reconstructed.');
$assert(!$context->mutationAuthorized() && !$context->operationalReady(), 'Historical bootstrap context granted mutation authority.');

// A disposable uninitialized admission object is intentional here: the
// retired bridge must return before touching any admission method or state.
$admission = (new ReflectionClass(LegacyBootstrapExecutionAdmission::class))->newInstanceWithoutConstructor();
$applier = new PackageOwnedFileApplier(
    new LiveTreePathGuard($tmp . '/app'),
    LiveFileActivationCapability::current(),
    $bridgeRoot . DIRECTORY_SEPARATOR . 'apply-temp'
);
$before = $operations->read()?->toArray();
$result = (new LegacyBootstrapV3ExecutionBridge(new V3ExplicitApplyExecutor()))->execute(
    $context,
    $admission,
    $manifest->payload(),
    $operations,
    $applier,
    new LiveTreePathGuard($tmp . '/app')
);
$after = $operations->read()?->toArray();
$assert($result->status() === 'blocked', 'Retired bootstrap bridge did not fail closed.');
$assert($result->operationId() === $operationId, 'Retired bridge lost operation correlation identity.');
$assert($result->appliedPaths() === [], 'Retired bridge reported mutation paths.');
$assert(str_contains(strtolower($result->reason()), 'retired'), 'Retired bridge reason is not deterministic.');
$assert($before === $after, 'Retired bridge changed the persisted operation state.');

$bridgeSource = file_get_contents(dirname(__DIR__) . '/app/Core/LegacyBootstrapV3ExecutionBridge.php');
$assert(is_string($bridgeSource), 'Retired bridge could not be read.');
foreach ([
    '$admission->authorize()',
    '$admission->fail()',
    '$this->executor->execute(',
    '$captureBeforeMutation',
] as $forbidden) {
    $assert(!str_contains($bridgeSource, $forbidden), "Retired bridge still contains {$forbidden}.");
}

$executorReflection = new ReflectionMethod(V3ExplicitApplyExecutor::class, 'execute');
$parameterNames = array_map(static fn (ReflectionParameter $parameter): string => $parameter->getName(), $executorReflection->getParameters());
$assert(!in_array('bootstrap', $parameterNames, true), 'Canonical executor still accepts bootstrap context.');
$executorSource = file_get_contents(dirname(__DIR__) . '/app/Core/V3ExplicitApplyExecutor.php');
$assert(is_string($executorSource), 'Canonical executor could not be read.');
$assert(!str_contains($executorSource, 'LegacyBootstrapV3ExecutionContext'), 'Canonical executor still contains bootstrap identity bypass code.');
$assert(str_contains($executorSource, 'expectedOperationIdentity ?? $plan->operationIdentity()'), 'Canonical executor identity verification was weakened.');

echo "Bootstrap bridge retirement assertions: {$assertions}" . PHP_EOL;
