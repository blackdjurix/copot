<?php

require dirname(__DIR__) . '/bootstrap/autoload.php';

use Copot\Core\LegacyBootstrapExecutionAdmission;
use Copot\Core\LegacyBootstrapLifecycleRequest;
use Copot\Core\PackageLifecycleResult;
use Copot\Core\PackageLifecycleService;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$serviceMethod = new ReflectionMethod(PackageLifecycleService::class, 'executeLegacyToV3Bootstrap');
$assert($serviceMethod->getReturnType()?->getName() === PackageLifecycleResult::class, 'Bootstrap lifecycle entrypoint is not typed against PackageLifecycleResult.');
$assert($serviceMethod->getParameters()[0]->getType()?->getName() === LegacyBootstrapLifecycleRequest::class, 'Bootstrap lifecycle entrypoint does not require a typed request.');
$assert((new ReflectionMethod(LegacyBootstrapExecutionAdmission::class, 'complete'))->getReturnType()?->getName() === 'void', 'Bootstrap admission has no canonical completion handoff.');

$service = file_get_contents(dirname(__DIR__) . '/app/Core/PackageLifecycleService.php');
$assert(is_string($service), 'Package lifecycle service could not be read.');
foreach ([
    'new LegacyBootstrapV3ExecutionBridge',
    'MigrationRunResult::INDETERMINATE',
    'LifecycleOperationRecord::MIGRATING',
    'LifecycleOperationRecord::AWAITING_WU6',
    "'awaiting_wu6'",
    'bootstrapMigrationPlanIdentity',
] as $required) {
    $assert(str_contains($service, $required), "Bootstrap lifecycle orchestration is missing {$required}.");
}

$assert(substr_count($service, 'Bootstrap filesystem and migration execution completed; WU6 finalization is pending.') === 1, 'Bootstrap orchestration does not expose exactly one durable pre-WU6 handoff result.');
$assert(!str_contains($service, '$admission->complete();'), 'Bootstrap orchestration must not complete the admission before canonical WU6 finalization.');

$request = file_get_contents(dirname(__DIR__) . '/app/Core/LegacyBootstrapLifecycleRequest.php');
$assert(is_string($request), 'Bootstrap lifecycle request could not be read.');
foreach ([
    "PackageContract::V3_MANIFEST_CONTRACT_VERSION",
    "coverage() !== 'full'",
    "removals() !== []",
    "renames() !== []",
    'WebcoreApplyPlan::fromPayload',
    'stagingPath()',
] as $required) {
    $assert(str_contains($request, $required), "Typed bootstrap request is missing {$required}.");
}

echo "Bootstrap lifecycle orchestration contract assertions: {$assertions}" . PHP_EOL;
