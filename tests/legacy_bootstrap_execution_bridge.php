<?php

$base = dirname(__DIR__);
require $base . '/bootstrap/autoload.php';

use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\LegacyBootstrapExecutionAdmission;
use Copot\Core\LegacyBootstrapV3ExecutionBridge;
use Copot\Core\LegacyBootstrapV3ExecutionContext;
use Copot\Core\LifecycleOperationRecord;
use Copot\Core\LifecycleOperationStore;
use Copot\Core\LiveFileActivationCapability;
use Copot\Core\LiveTreePathGuard;
use Copot\Core\PackageOwnedFileApplier;
use Copot\Core\StagedPayload;
use Copot\Core\V3ExplicitApplyExecutor;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-r2-bridge-' . bin2hex(random_bytes(6));
mkdir($root . DIRECTORY_SEPARATOR . 'storage', 0700, true);
mkdir($root . DIRECTORY_SEPARATOR . 'app', 0700, true);
$operations = new LifecycleOperationStore($root . DIRECTORY_SEPARATOR . 'storage');
$operationId = 'legacy-bridge-retired-' . bin2hex(random_bytes(8));
$operation = new LifecycleOperationRecord(
    $operationId,
    'LEGACY_TO_V3_BOOTSTRAP',
    '0.13.0',
    'retired-release',
    hash('sha256', 'retired-archive'),
    $root . DIRECTORY_SEPARATOR . 'staging',
    hash('sha256', 'retired-payload'),
    hash('sha256', 'retired-plan'),
    LifecycleOperationRecord::PREPARING,
    0,
    null,
    hash('sha256', 'retired-migration'),
    null,
    gmdate(DATE_ATOM),
    gmdate(DATE_ATOM)
);
$operations->create($operation);
$context = (new ReflectionClass(LegacyBootstrapV3ExecutionContext::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(LegacyBootstrapV3ExecutionContext::class, 'operation'))->setValue($context, $operation);
$admission = (new ReflectionClass(LegacyBootstrapExecutionAdmission::class))->newInstanceWithoutConstructor();
$payload = (new ReflectionClass(StagedPayload::class))->newInstanceWithoutConstructor();
$applier = new PackageOwnedFileApplier(
    new LiveTreePathGuard($root . DIRECTORY_SEPARATOR . 'app'),
    LiveFileActivationCapability::current(),
    $root . DIRECTORY_SEPARATOR . 'apply-temp'
);
$before = $operations->read()?->toArray();
$result = (new LegacyBootstrapV3ExecutionBridge(new V3ExplicitApplyExecutor()))->execute(
    $context,
    $admission,
    $payload,
    $operations,
    $applier,
    new LiveTreePathGuard($root . DIRECTORY_SEPARATOR . 'app')
);
$assert($result->status() === 'blocked', 'Retired bootstrap bridge did not fail closed.');
$assert($result->operationId() === $operationId, 'Retired bridge lost operation correlation identity.');
$assert($result->appliedPaths() === [], 'Retired bridge reported mutation paths.');
$assert(str_contains(strtolower($result->reason()), 'retired'), 'Retired bridge reason is not deterministic.');
$assert($operations->read()?->toArray() === $before, 'Retired bridge changed persisted operation state.');

$bridgeSource = file_get_contents($base . '/app/Core/LegacyBootstrapV3ExecutionBridge.php');
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
$executorSource = file_get_contents($base . '/app/Core/V3ExplicitApplyExecutor.php');
$assert(is_string($executorSource), 'Canonical executor could not be read.');
$assert(!str_contains($executorSource, 'LegacyBootstrapV3ExecutionContext'), 'Canonical executor still contains bootstrap identity bypass code.');
$assert(str_contains($executorSource, 'expectedOperationIdentity ?? $plan->operationIdentity()'), 'Canonical executor identity verification was weakened.');

echo "Bootstrap bridge retirement assertions: {$assertions}" . PHP_EOL;
