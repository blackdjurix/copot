<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) { throw new RuntimeException($message); }
};

$service = file_get_contents($basePath . '/app/Core/PackageLifecycleService.php');
$factory = file_get_contents($basePath . '/app/Core/PackageLifecycleFactory.php');
$health = file_get_contents($basePath . '/app/Core/HealthIntegrityCommitCoordinator.php');

$assert(str_contains($service, 'public function continueAwaitingWu6(string $operationId)'), 'Awaiting-WU6 continuation entry point is missing.');
$assert(str_contains($service, "phase() !== LifecycleOperationRecord::AWAITING_WU6"), 'Continuation does not require the awaiting_wu6 phase.');
$assert(str_contains($service, 'fileCursor() <= 0') && str_contains($service, 'lastVerifiedPath() === null'), 'Continuation does not require completed package progress evidence.');
$assert(str_contains($service, 'migrationOutcome(), [MigrationRunResult::COMPLETED, MigrationRunResult::NOOP]'), 'Continuation does not require a completed migration outcome.');
$continuation = substr($service, strpos($service, 'public function continueAwaitingWu6'));
$assert(!str_contains($continuation, '$this->applyCoordinator->execute'), 'Continuation can re-enter package application.');
$assert(!str_contains($continuation, '$this->migrationRunner'), 'Continuation can re-enter migration execution.');
$assert(str_contains($continuation, '$this->healthCoordinator->finalize('), 'Continuation does not use canonical WU6 finalization.');
$assert(str_contains($continuation, 'reconstructCompletedMigrationPlan'), 'Continuation does not reconstruct the persisted completed migration plan.');
$assert(str_contains($service, 'Completed migration ledger does not match the persisted plan.'), 'Continuation lacks completed-ledger reconciliation.');
$assert(!str_contains($continuation, '$this->applyCoordinator->execute'), 'Continuation can re-enter package application.');
$assert(str_contains($factory, 'postReconciliationVerified()') && str_contains($factory, 'mutationStarted()'), 'Production continuation evidence validator is not post-mutation fail-closed.');
$assert(str_contains($health, 'if ($record->phase() !== LifecycleOperationRecord::AWAITING_WU6)'), 'Finalization does not retain its awaiting-WU6 phase gate.');

echo "Awaiting-WU6 continuation contract tests passed ({$assertions} assertions)." . PHP_EOL;
