<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';

use Copot\Core\LegacyBootstrapExecutionAdmission;
use Copot\Core\LegacyBootstrapLifecycleRequest;
use Copot\Core\LegacyBootstrapV3ExecutionContext;
use Copot\Core\LifecycleOperationRecord;
use Copot\Core\PackageLifecycleResult;
use Copot\Core\PackageLifecycleService;
use Copot\Core\V3ExplicitApplyExecutor;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$service = new ReflectionClass(PackageLifecycleService::class);
$assert(!$service->hasMethod('prepareLegacyToV3Bootstrap'), 'Retired bootstrap preparation API is still exposed.');
$assert(!$service->hasMethod('executeLegacyToV3Bootstrap'), 'Retired bootstrap execution API is still exposed.');

$serviceSource = file_get_contents(dirname(__DIR__) . '/app/Core/PackageLifecycleService.php');
$assert(is_string($serviceSource), 'Package lifecycle service source could not be loaded.');
foreach ([
    'new LegacyBootstrapV3ExecutionBridge',
    'LegacyBootstrapV3ExecutionContext',
    'LegacyBootstrapLifecycleRequest',
    'bootstrapMigrationPlanIdentity',
    '$bootstrapCleanupPending',
] as $retiredExecutionSurface) {
    $assert(!str_contains($serviceSource, $retiredExecutionSurface), "Shared service still contains {$retiredExecutionSurface}.");
}
$assert(str_contains($serviceSource, 'supportsOrdinaryRetryClassification'), 'Ordinary retry classification boundary is missing.');
$assert(substr_count($serviceSource, '$this->supportsOrdinaryRetryClassification($record)') >= 3, 'Ordinary retry guards do not cover all retry entrypoints.');
$assert(str_contains($serviceSource, 'Legacy bootstrap WU6 continuation is retired.'), 'Legacy WU6 retirement rejection is missing.');
$wu6Start = strpos($serviceSource, 'public function continueAwaitingWu6');
$wu6Reject = strpos($serviceSource, 'Legacy bootstrap WU6 continuation is retired.', $wu6Start === false ? 0 : $wu6Start);
$wu6Staging = strpos($serviceSource, '$retainedStagingPath', $wu6Start === false ? 0 : $wu6Start);
$assert($wu6Start !== false && $wu6Reject !== false && $wu6Staging !== false && $wu6Reject < $wu6Staging, 'Legacy WU6 rejection is not positioned before staged package access.');
$assert(str_contains($serviceSource, '$this->healthCoordinator->finalize('), 'Ordinary WU6/finalization path was removed.');

// The historical typed request remains a data/evidence container. Its public
// surface exposes evidence accessors only; it does not authorize or execute.
$request = new ReflectionClass(LegacyBootstrapLifecycleRequest::class);
$assert($request->isFinal(), 'Historical typed bootstrap request lost its stable type boundary.');
$requestConstructor = $request->getConstructor();
$requestParameters = $requestConstructor?->getParameters() ?? [];
$assert(($requestParameters[2]->getType()?->getName() ?? '') === LegacyBootstrapV3ExecutionContext::class, 'Typed request no longer carries the historical execution-context evidence type.');
$assert(($requestParameters[3]->getType()?->getName() ?? '') === LegacyBootstrapExecutionAdmission::class, 'Typed request no longer carries the historical admission evidence type.');
foreach (['manifest', 'payload', 'context', 'admission', 'confirmationAuthority', 'confirmation', 'journal', 'beforeActivation'] as $accessor) {
    $assert($request->hasMethod($accessor), "Typed request evidence accessor {$accessor} is missing.");
}
foreach (['execute', 'authorize', 'complete', 'fail'] as $authorityMethod) {
    $assert(!$request->hasMethod($authorityMethod), "Typed request incorrectly exposes authority method {$authorityMethod}.");
}

// Admission/completion APIs remain historical evidence contracts, but they
// are not reachable through the shared service after retirement.
$admission = new ReflectionClass(LegacyBootstrapExecutionAdmission::class);
foreach (['authorize', 'complete', 'fail'] as $method) {
    $assert($admission->hasMethod($method), "Historical admission contract lost {$method}().");
}
$assert($admission->getMethod('complete')->getReturnType()?->getName() === 'void', 'Historical completion contract changed type.');
$assert($service->getMethod('retry')->getReturnType()?->getName() === PackageLifecycleResult::class, 'Ordinary retry lifecycle result contract changed.');

$executor = new ReflectionMethod(V3ExplicitApplyExecutor::class, 'execute');
$parameterNames = array_map(static fn (ReflectionParameter $parameter): string => $parameter->getName(), $executor->getParameters());
$assert(!in_array('bootstrap', $parameterNames, true), 'Canonical executor still accepts bootstrap execution context.');
$executorSource = file_get_contents(dirname(__DIR__) . '/app/Core/V3ExplicitApplyExecutor.php');
$assert(is_string($executorSource), 'Canonical executor source could not be loaded.');
$assert(!str_contains($executorSource, 'LegacyBootstrapV3ExecutionContext'), 'Canonical executor still contains bootstrap identity bypass code.');
$assert(str_contains($executorSource, 'expectedOperationIdentity ?? $plan->operationIdentity()'), 'Canonical executor identity verification is not strict.');
$assert(str_contains($executorSource, 'captureBeforeMutation'), 'Canonical recovery capture boundary was removed.');

$record = new ReflectionClass(LifecycleOperationRecord::class);
$assert($record->hasMethod('classification'), 'Persisted operation classification authority is missing.');
$assert($record->hasConstant('PREPARING') && $record->hasConstant('AWAITING_WU6'), 'Persisted lifecycle phase contract is incomplete.');

echo "Bootstrap lifecycle retirement orchestration assertions: {$assertions}" . PHP_EOL;
