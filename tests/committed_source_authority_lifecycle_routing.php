<?php

use Copot\Core\CommittedSourceAuthorityExecutionAdmission;
use Copot\Core\CommittedSourceAuthorityPlan;
use Copot\Core\CommittedSourceAuthorityPlanner;
use Copot\Core\PackageLifecycleFactory;
use Copot\Core\PackageLifecycleService;

require dirname(__DIR__) . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    if (!$condition) { throw new RuntimeException($message); }
    $assertions++;
};

$service = new ReflectionClass(PackageLifecycleService::class);
$assert($service->hasMethod('planCommittedSourceAuthority'), 'Canonical service planning entrypoint is missing.');
$assert($service->hasMethod('admitCommittedSourceAuthority'), 'Canonical service admission entrypoint is missing.');
$parameters = $service->getConstructor()?->getParameters() ?? [];
$assert(($parameters[count($parameters) - 1]->getType()?->getName() ?? '') === CommittedSourceAuthorityPlanner::class, 'Canonical service does not expose the typed planner dependency.');

$factory = file_get_contents(dirname(__DIR__) . '/app/Core/PackageLifecycleFactory.php');
$assert(str_contains($factory, 'new CommittedSourceAuthorityPlanner()'), 'PackageLifecycleFactory does not wire the canonical planner.');

$admission = new ReflectionClass(CommittedSourceAuthorityExecutionAdmission::class);
$assert($admission->hasMethod('mutationRequired'), 'Admission does not expose mutation-mode routing state.');
$source = file_get_contents(dirname(__DIR__) . '/app/Core/CommittedSourceAuthorityExecutionAdmission.php');
$assert(str_contains($source, 'require canonical mutation and recovery execution'), 'Mutation admission is not fail-closed before canonical execution.');
$assert(str_contains($source, 'cannot enter WU6 continuation'), 'Mutation admission can bypass canonical execution before WU6.');
$assert(!str_contains($source, 'if (!$plan->authorityOnly()'), 'Admission still hard-rejects typed mutation plans before canonical binding.');

$plan = new ReflectionClass(CommittedSourceAuthorityPlan::class);
$assert($plan->hasMethod('assertTargetFingerprint'), 'Mutation planning lacks execution-time freshness verification.');
$assert($plan->hasMethod('mutationRequired'), 'Mutation planning mode is not available at the canonical boundary.');

echo "Committed source-authority lifecycle routing tests passed ({$assertions} assertions).\n";
