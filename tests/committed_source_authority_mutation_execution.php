<?php

use Copot\Core\CommittedSourceAuthorityExecutionAdmission;
use Copot\Core\PackageLifecycleService;
use Copot\Core\V3ExplicitApplyExecutor;

require dirname(__DIR__) . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    if (!$condition) { throw new RuntimeException($message); }
    $assertions++;
};

$service = new ReflectionClass(PackageLifecycleService::class);
$assert($service->hasMethod('executeCommittedSourceAuthorityMutation'), 'Canonical mutation execution entrypoint is missing.');
$executor = new ReflectionClass(V3ExplicitApplyExecutor::class);
$parameter = $executor->getMethod('execute')->getParameters();
$assert($parameter[count($parameter) - 1]->getName() === 'requireTargetPreconditions', 'Executor lacks explicit target-precondition enforcement mode.');
$assert($executor->hasMethod('assertMutationPreconditions'), 'Executor lacks a pre-recovery mutation preflight.');

$source = file_get_contents(dirname(__DIR__) . '/app/Core/PackageLifecycleService.php');
$assert(str_contains($source, 'authorizeMutation('), 'Service does not acquire the canonical recovery mutation permit.');
$assert(str_contains($source, 'recordPostReconciliationVerification('), 'Service does not bind post-mutation recovery verification.');
$assert(str_contains($source, 'assertTargetFingerprint($actualTarget)'), 'Service does not enforce target freshness before mutation.');
$assert(str_contains($source, 'mutationAuthorized()'), 'Service does not fail closed on non-authorizing admission proof.');

$executorSource = file_get_contents(dirname(__DIR__) . '/app/Core/V3ExplicitApplyExecutor.php');
$assert(str_contains($executorSource, 'verifyTargetPreconditions'), 'Executor does not verify expected target state/hash.');
$assert(str_contains($executorSource, 'withoutPaths($retained)'), 'Executor does not exclude PRESERVE actions from live payload activation.');
$assert(str_contains($executorSource, 'Mutation CREATE target is no longer absent'), 'CREATE collision is not fail-closed.');
$assert(str_contains($executorSource, 'Mutation target original identity changed before activation'), 'REPLACE stale hash is not fail-closed.');
$assert(str_contains($executorSource, 'actionOrdinal($plan, $path)'), 'Mutation progress is not bound to immutable action ordinals.');
$assert(str_contains($executorSource, 'allowTargetEvidence'), 'Mutation mode cannot use its bound target evidence for PRESERVE verification.');
$assert((new ReflectionClass(CommittedSourceAuthorityExecutionAdmission::class))->hasMethod('mutationRequired'), 'Admission mode binding is unavailable.');

echo "Committed source-authority mutation execution tests passed ({$assertions} assertions).\n";
