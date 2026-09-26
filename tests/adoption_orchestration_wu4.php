<?php

declare(strict_types=1);

use Copot\Core\AdoptionBoundaryClassifier;
use Copot\Core\AdoptionEvaluationSnapshot;
use Copot\Core\AdoptionOrchestrationRequest;
use Copot\Core\AdoptionOrchestrationResult;
use Copot\Core\AdoptionOrchestrator;
use Copot\Core\AdoptionResolutionOperationResult;
use Copot\Core\AdoptionResolutionContext;
use Copot\Core\LegacyBoundaryEvidence;
use Copot\Core\LifecycleResolutionEligibility;
use Copot\Core\PackageTargetRequirement;
use Copot\Core\PackageTargetRequirements;
use Copot\Core\TargetCompatibilityCandidate;
use Copot\Core\TargetCompatibilityEvaluator;
use Copot\Core\TargetRequirementObservation;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$database = new PackageTargetRequirement(PackageTargetRequirement::DATABASE, 'mysql', 'server', PackageTargetRequirement::MINIMUM_VERSION, '8.0.0');
$schema = new PackageTargetRequirement(PackageTargetRequirement::SCHEMA, 'webcore', 'core-schema', PackageTargetRequirement::EXACT_IDENTITY, 'schema:target');
$target = new PackageTargetRequirements([$database, $schema]);
$value = static fn (PackageTargetRequirement $requirement, string $value, string $source = 'fresh'): TargetRequirementObservation => TargetRequirementObservation::value($requirement, $value, $source . ':' . $requirement->key());
$snapshot = static function (string $databaseVersion, string $schemaIdentity = 'schema:target', string $targetIdentity = 'target-1', string $installation = 'installation-1', string $namespace = 'namespace-1', array $eligibility = []) use ($database, $schema, $value): AdoptionEvaluationSnapshot {
    return new AdoptionEvaluationSnapshot(new TargetCompatibilityCandidate([$value($database, $databaseVersion), $value($schema, $schemaIdentity)]), LegacyBoundaryEvidence::none(), $eligibility, $targetIdentity, $installation, $namespace);
};
$eligible = static function (string $gapIdentity, string $class, string $id): LifecycleResolutionEligibility {
    return new LifecycleResolutionEligibility($gapIdentity, $class, true, $id, 'Existing lifecycle authority is authorized.');
};
$evaluator = new TargetCompatibilityEvaluator();
$orchestrator = new AdoptionOrchestrator($evaluator, new AdoptionBoundaryClassifier());

// Already compatible: fresh proof, no underlying operation.
$calls = 0;
$readySnapshot = $snapshot('8.0.0');
$ready = $orchestrator->run(new AdoptionOrchestrationRequest($target, $readySnapshot), static fn (): AdoptionEvaluationSnapshot => throw new RuntimeException('Re-proof should not run for a zero-operation path.'), static function () use (&$calls): AdoptionResolutionOperationResult { $calls++; throw new RuntimeException('No operation expected.'); });
$assert($ready->state() === AdoptionOrchestrationResult::READY && $ready->operations() === [] && $calls === 0, 'Compatible Adoption did not take the zero-operation path.');

// Single Route B operation preserves its existing operation identity and re-proves freshly.
$initial = $snapshot('7.0.0');
$gap = $evaluator->evaluate($target, $initial->candidate())->requirementGaps()[0];
$route = $eligible($gap->evidenceIdentity(), LifecycleResolutionEligibility::UPGRADE, 'operation-upgrade-1');
$after = $snapshot('8.0.0');
$seenContexts = [];
$single = $orchestrator->run(new AdoptionOrchestrationRequest($target, new AdoptionEvaluationSnapshot($initial->candidate(), $initial->legacyEvidence(), [$route], $initial->targetIdentity(), $initial->installationIdentity(), $initial->namespaceIdentity())), static function () use ($after): AdoptionEvaluationSnapshot { return $after; }, static function (AdoptionResolutionContext $context, LifecycleResolutionEligibility $eligibility) use (&$seenContexts, $route): AdoptionResolutionOperationResult {
    $seenContexts[] = [$context->step(), $context->requirementGapIdentity(), $eligibility->lifecycleClass()];
    return new AdoptionResolutionOperationResult($route->evidenceIdentity(), $route->lifecycleClass(), AdoptionResolutionOperationResult::COMPLETED, 'result-upgrade-1', 'Underlying Upgrade completed.');
});
$assert($single->state() === AdoptionOrchestrationResult::READY && count($single->operations()) === 1, 'Single Route B operation did not reach fresh readiness.');
$assert($single->operations()[0]->operationId() === $route->evidenceIdentity() && $seenContexts === [[1, $gap->evidenceIdentity(), LifecycleResolutionEligibility::UPGRADE]], 'Underlying lifecycle identity or context was not preserved.');
$assert($single->orchestrationIdentity() !== $single->operations()[0]->operationId(), 'Adoption orchestration identity became a mutation identity.');

// Multiple gaps sequence independently and preserve separate operation identities.
$multiInitial = $snapshot('7.0.0', 'schema:old');
$multiResult = $evaluator->evaluate($target, $multiInitial->candidate());
$routeDb = $eligible($multiResult->requirementGaps()[0]->evidenceIdentity(), LifecycleResolutionEligibility::UPGRADE, 'operation-upgrade-2');
$routeSchema = $eligible($multiResult->requirementGaps()[1]->evidenceIdentity(), LifecycleResolutionEligibility::OWNER_AUTHORIZED_MIGRATION, 'operation-schema-1');
$freshCandidates = [
    $snapshot('8.0.0', 'schema:old', 'target-1', 'installation-1', 'namespace-1', [$routeSchema]),
    $snapshot('8.0.0', 'schema:target'),
];
$index = 0;
$multi = $orchestrator->run(new AdoptionOrchestrationRequest($target, new AdoptionEvaluationSnapshot($multiInitial->candidate(), $multiInitial->legacyEvidence(), [$routeDb, $routeSchema], $multiInitial->targetIdentity(), $multiInitial->installationIdentity(), $multiInitial->namespaceIdentity())), static function () use (&$freshCandidates, &$index): AdoptionEvaluationSnapshot { return $freshCandidates[$index++]; }, static function (AdoptionResolutionContext $context, LifecycleResolutionEligibility $eligibility): AdoptionResolutionOperationResult { return new AdoptionResolutionOperationResult('operation-' . $context->step(), $eligibility->lifecycleClass(), AdoptionResolutionOperationResult::COMPLETED, 'result-' . $context->step(), 'Underlying operation completed.'); });
$assert($multi->state() === AdoptionOrchestrationResult::READY && count($multi->operations()) === 2 && $multi->operations()[0]->operationId() !== $multi->operations()[1]->operationId(), 'Composite Resolution did not sequence independent operations.');

// Failure and recovery-required states suspend and do not continue.
$failedCalls = 0;
$failed = $orchestrator->run(new AdoptionOrchestrationRequest($target, new AdoptionEvaluationSnapshot($initial->candidate(), $initial->legacyEvidence(), [$route], 'target-1', 'installation-1', 'namespace-1')), static fn (): AdoptionEvaluationSnapshot => throw new RuntimeException('No re-proof after failure.'), static function (AdoptionResolutionContext $context, LifecycleResolutionEligibility $eligibility) use (&$failedCalls): AdoptionResolutionOperationResult { $failedCalls++; return new AdoptionResolutionOperationResult('operation-failed', $eligibility->lifecycleClass(), AdoptionResolutionOperationResult::FAILED, 'result-failed', 'Underlying lifecycle failed.'); });
$assert($failed->state() === AdoptionOrchestrationResult::SUSPENDED && $failedCalls === 1, 'Underlying failure did not suspend Adoption.');
$recovery = $orchestrator->run(new AdoptionOrchestrationRequest($target, new AdoptionEvaluationSnapshot($initial->candidate(), $initial->legacyEvidence(), [$route], 'target-1', 'installation-1', 'namespace-1')), static fn (): AdoptionEvaluationSnapshot => throw new RuntimeException('No re-proof during recovery-required state.'), static fn (AdoptionResolutionContext $context, LifecycleResolutionEligibility $eligibility): AdoptionResolutionOperationResult => new AdoptionResolutionOperationResult('operation-recovery', $eligibility->lifecycleClass(), AdoptionResolutionOperationResult::RECOVERY_REQUIRED, 'result-recovery', 'Recovery is required before continuation.'));
$assert($recovery->state() === AdoptionOrchestrationResult::SUSPENDED, 'Recovery-required state did not suspend Adoption.');

// Unauthorized/unavailable route evidence fails closed; stale identity rejects re-proof.
$unauthorized = new AdoptionResolutionOperationResult('operation-unauthorized', $route->lifecycleClass(), AdoptionResolutionOperationResult::UNAUTHORIZED, 'result-unauthorized', 'Route B authority unavailable.');
$blocked = $orchestrator->run(new AdoptionOrchestrationRequest($target, new AdoptionEvaluationSnapshot($initial->candidate(), $initial->legacyEvidence(), [$route], 'target-1', 'installation-1', 'namespace-1')), static fn (): AdoptionEvaluationSnapshot => throw new RuntimeException('No re-proof for unauthorized route.'), static function (AdoptionResolutionContext $context, LifecycleResolutionEligibility $eligibility) use ($unauthorized): AdoptionResolutionOperationResult { return $unauthorized; });
$assert($blocked->state() === AdoptionOrchestrationResult::BLOCKED, 'Unauthorized Route B did not fail closed.');
$stale = $orchestrator->run(new AdoptionOrchestrationRequest($target, new AdoptionEvaluationSnapshot($initial->candidate(), $initial->legacyEvidence(), [$route], 'target-1', 'installation-1', 'namespace-1')), static fn (): AdoptionEvaluationSnapshot => $snapshot('8.0.0', 'schema:target', 'target-2'), static fn (AdoptionResolutionContext $context, LifecycleResolutionEligibility $eligibility): AdoptionResolutionOperationResult => new AdoptionResolutionOperationResult('operation-stale', $eligibility->lifecycleClass(), AdoptionResolutionOperationResult::COMPLETED, 'result-stale', 'Completed against changed target.'));
$assert($stale->state() === AdoptionOrchestrationResult::STALE, 'Changed target identity reused orchestration evidence.');

// No callback can execute for an unresolved/unknown WU2 result and results are deterministic.
$unknownSnapshot = new AdoptionEvaluationSnapshot(new TargetCompatibilityCandidate([]), LegacyBoundaryEvidence::none(), [], 'target-1', 'installation-1', 'namespace-1');
$unknown = $orchestrator->run(new AdoptionOrchestrationRequest($target, $unknownSnapshot), static fn (): AdoptionEvaluationSnapshot => throw new RuntimeException('No re-proof for unknown state.'), static fn (AdoptionResolutionContext $context, LifecycleResolutionEligibility $eligibility): AdoptionResolutionOperationResult => throw new RuntimeException('No operation for unknown state.'));
$unknownAgain = $orchestrator->run(new AdoptionOrchestrationRequest($target, $unknownSnapshot), static fn (): AdoptionEvaluationSnapshot => throw new RuntimeException('No re-proof for unknown state.'), static fn (AdoptionResolutionContext $context, LifecycleResolutionEligibility $eligibility): AdoptionResolutionOperationResult => throw new RuntimeException('No operation for unknown state.'));
$assert($unknown->state() === AdoptionOrchestrationResult::BLOCKED && $unknown->identity() === $unknownAgain->identity(), 'Unknown state was not fail-closed and deterministic.');

// Ambiguous lifecycle eligibility fails closed without invoking Route B.
$ambiguousCalls = 0;
$ambiguous = $orchestrator->run(new AdoptionOrchestrationRequest($target, new AdoptionEvaluationSnapshot($initial->candidate(), $initial->legacyEvidence(), [
    $route,
    $eligible($gap->evidenceIdentity(), LifecycleResolutionEligibility::UPDATE, 'operation-update-ambiguous'),
], 'target-1', 'installation-1', 'namespace-1')), static fn (): AdoptionEvaluationSnapshot => throw new RuntimeException('Ambiguous eligibility must not re-proof.'), static function () use (&$ambiguousCalls): AdoptionResolutionOperationResult { $ambiguousCalls++; throw new RuntimeException('Ambiguous eligibility must not execute.'); });
$assert($ambiguous->state() === AdoptionOrchestrationResult::BLOCKED && $ambiguousCalls === 0, 'Ambiguous lifecycle eligibility was not blocked before execution.');

// A returned lifecycle-class mismatch fails closed before fresh re-proof.
$mismatchReproofs = 0;
$mismatch = $orchestrator->run(new AdoptionOrchestrationRequest($target, new AdoptionEvaluationSnapshot($initial->candidate(), $initial->legacyEvidence(), [$route], 'target-1', 'installation-1', 'namespace-1')), static function () use (&$mismatchReproofs): AdoptionEvaluationSnapshot { $mismatchReproofs++; throw new RuntimeException('Mismatched lifecycle class must not re-proof.'); }, static fn (AdoptionResolutionContext $context, LifecycleResolutionEligibility $eligibility): AdoptionResolutionOperationResult => new AdoptionResolutionOperationResult('operation-mismatch', LifecycleResolutionEligibility::REPAIR, AdoptionResolutionOperationResult::COMPLETED, 'result-mismatch', 'Wrong lifecycle class.'));
$assert($mismatch->state() === AdoptionOrchestrationResult::BLOCKED && $mismatchReproofs === 0, 'Lifecycle-class mismatch was not blocked before re-proof.');

// Composite Resolution rejects reuse of an underlying operation identity.
$duplicateInitial = new AdoptionEvaluationSnapshot($multiInitial->candidate(), $multiInitial->legacyEvidence(), [$routeDb, $routeSchema], 'target-1', 'installation-1', 'namespace-1');
$duplicateFresh = [
    $snapshot('8.0.0', 'schema:old', 'target-1', 'installation-1', 'namespace-1', [$routeSchema]),
    $snapshot('8.0.0', 'schema:target'),
];
$duplicateIndex = 0;
$duplicateCalls = 0;
$duplicate = $orchestrator->run(new AdoptionOrchestrationRequest($target, $duplicateInitial), static function () use (&$duplicateFresh, &$duplicateIndex): AdoptionEvaluationSnapshot { return $duplicateFresh[$duplicateIndex++]; }, static function (AdoptionResolutionContext $context, LifecycleResolutionEligibility $eligibility) use (&$duplicateCalls): AdoptionResolutionOperationResult { $duplicateCalls++; return new AdoptionResolutionOperationResult('operation-duplicate', $eligibility->lifecycleClass(), AdoptionResolutionOperationResult::COMPLETED, 'result-duplicate-' . $duplicateCalls, 'Reused underlying operation identity.'); });
$assert($duplicate->state() === AdoptionOrchestrationResult::BLOCKED && $duplicateCalls === 2 && count($duplicate->operations()) === 1, 'Duplicate underlying operation identity was accepted as a distinct step.');

// Installation identity drift makes the fresh post-operation proof stale.
$installationReproofs = 0;
$installationCalls = 0;
$installationDriftSnapshot = $snapshot('8.0.0', 'schema:target', 'target-1', 'installation-2', 'namespace-1');
$installationDrift = $orchestrator->run(new AdoptionOrchestrationRequest($target, new AdoptionEvaluationSnapshot($initial->candidate(), $initial->legacyEvidence(), [$route], 'target-1', 'installation-1', 'namespace-1')), static function () use (&$installationReproofs, $installationDriftSnapshot): AdoptionEvaluationSnapshot { $installationReproofs++; return $installationDriftSnapshot; }, static function (AdoptionResolutionContext $context, LifecycleResolutionEligibility $eligibility) use (&$installationCalls): AdoptionResolutionOperationResult { $installationCalls++; return new AdoptionResolutionOperationResult('operation-installation-drift', $eligibility->lifecycleClass(), AdoptionResolutionOperationResult::COMPLETED, 'result-installation-drift', 'Completed before installation identity drift.'); });
$assert($installationDrift->state() === AdoptionOrchestrationResult::STALE && $installationCalls === 1 && $installationReproofs === 1, 'Installation identity drift was not classified stale.');

// Namespace identity drift makes the fresh post-operation proof stale.
$namespaceReproofs = 0;
$namespaceCalls = 0;
$namespaceDriftSnapshot = $snapshot('8.0.0', 'schema:target', 'target-1', 'installation-1', 'namespace-2');
$namespaceDrift = $orchestrator->run(new AdoptionOrchestrationRequest($target, new AdoptionEvaluationSnapshot($initial->candidate(), $initial->legacyEvidence(), [$route], 'target-1', 'installation-1', 'namespace-1')), static function () use (&$namespaceReproofs, $namespaceDriftSnapshot): AdoptionEvaluationSnapshot { $namespaceReproofs++; return $namespaceDriftSnapshot; }, static function (AdoptionResolutionContext $context, LifecycleResolutionEligibility $eligibility) use (&$namespaceCalls): AdoptionResolutionOperationResult { $namespaceCalls++; return new AdoptionResolutionOperationResult('operation-namespace-drift', $eligibility->lifecycleClass(), AdoptionResolutionOperationResult::COMPLETED, 'result-namespace-drift', 'Completed before namespace identity drift.'); });
$assert($namespaceDrift->state() === AdoptionOrchestrationResult::STALE && $namespaceCalls === 1 && $namespaceReproofs === 1, 'Namespace identity drift was not classified stale.');

// Unavailable Route B authority blocks without fresh re-proof or continuation.
$unavailableReproofs = 0;
$unavailableCalls = 0;
$unavailable = $orchestrator->run(new AdoptionOrchestrationRequest($target, new AdoptionEvaluationSnapshot($initial->candidate(), $initial->legacyEvidence(), [$route], 'target-1', 'installation-1', 'namespace-1')), static function () use (&$unavailableReproofs): AdoptionEvaluationSnapshot { $unavailableReproofs++; throw new RuntimeException('Unavailable authority must not re-proof.'); }, static function (AdoptionResolutionContext $context, LifecycleResolutionEligibility $eligibility) use (&$unavailableCalls): AdoptionResolutionOperationResult { $unavailableCalls++; return new AdoptionResolutionOperationResult('operation-unavailable', $eligibility->lifecycleClass(), AdoptionResolutionOperationResult::UNAVAILABLE, 'result-unavailable', 'Route B authority unavailable.'); });
$assert($unavailable->state() === AdoptionOrchestrationResult::BLOCKED && $unavailableCalls === 1 && $unavailableReproofs === 0, 'Unavailable Route B authority did not fail closed.');

// Optional absence remains WU2-ready and does not cause a filler operation.
$optional = new PackageTargetRequirement(PackageTargetRequirement::CAPABILITY, 'webcore', 'optional-api', PackageTargetRequirement::PRESENT, null, false);
$optionalSnapshot = new AdoptionEvaluationSnapshot(new TargetCompatibilityCandidate([
    $value($database, '8.0.0', 'optional-check'),
    TargetRequirementObservation::presence($optional, false, 'optional-check:' . $optional->key()),
]), LegacyBoundaryEvidence::none(), [], 'target-optional', 'installation-1', 'namespace-1');
$optionalCalls = 0;
$optionalOrchestration = $orchestrator->run(new AdoptionOrchestrationRequest(new PackageTargetRequirements([$database, $optional]), $optionalSnapshot), static fn (): AdoptionEvaluationSnapshot => throw new RuntimeException('Optional non-blocking gap must not re-proof.'), static function () use (&$optionalCalls): AdoptionResolutionOperationResult { $optionalCalls++; throw new RuntimeException('Optional non-blocking gap must not execute.'); });
$assert($optionalOrchestration->state() === AdoptionOrchestrationResult::READY && $optionalOrchestration->operations() === [] && $optionalCalls === 0, 'Optional unsatisfied requirement incorrectly required lifecycle resolution.');

echo "WU4 Adoption orchestration focused tests passed ({$assertions} assertions)." . PHP_EOL;
