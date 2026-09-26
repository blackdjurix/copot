<?php

declare(strict_types=1);

use Copot\Core\AdoptionBoundaryClassification;
use Copot\Core\AdoptionBoundaryClassifier;
use Copot\Core\AdoptionBoundaryCandidate;
use Copot\Core\AdoptionEvaluationSnapshot;
use Copot\Core\AdoptionOrchestrationRequest;
use Copot\Core\AdoptionOrchestrationResult;
use Copot\Core\AdoptionOrchestrator;
use Copot\Core\AdoptionResolutionOperationResult;
use Copot\Core\InstallerAdoptionDecision;
use Copot\Core\InstallerAdoptionIntegration;
use Copot\Core\InstallerDatabaseOccupancy;
use Copot\Core\InstallerDatabaseOccupancyResult;
use Copot\Core\InstallerIntent;
use Copot\Core\InstallerOwnershipProof;
use Copot\Core\InstallerRoutingPlanner;
use Copot\Core\InstallationIdentity;
use Copot\Core\LegacyBoundaryEvidence;
use Copot\Core\LifecycleResolutionEligibility;
use Copot\Core\PackageTargetRequirement;
use Copot\Core\PackageTargetRequirements;
use Copot\Core\TargetCompatibilityCandidate;
use Copot\Core\TargetCompatibilityEvaluator;
use Copot\Core\TargetCompatibleExtraState;
use Copot\Core\TargetRequirementObservation;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$database = new PackageTargetRequirement(
    PackageTargetRequirement::DATABASE,
    'mysql',
    'server',
    PackageTargetRequirement::MINIMUM_VERSION,
    '8.0.0'
);
$optional = new PackageTargetRequirement(
    PackageTargetRequirement::CAPABILITY,
    'webcore',
    'optional-api',
    PackageTargetRequirement::PRESENT,
    null,
    false
);
$target = new PackageTargetRequirements([$database, $optional]);
$value = static fn (PackageTargetRequirement $requirement, string $observed, string $provenance = 'wu6'): TargetRequirementObservation => TargetRequirementObservation::value($requirement, $observed, $provenance . ':' . $requirement->key());
$presence = static fn (PackageTargetRequirement $requirement, bool $present): TargetRequirementObservation => TargetRequirementObservation::presence($requirement, $present, 'wu6:' . $requirement->key());
$candidate = static fn (string $version, bool $extra = false): TargetCompatibilityCandidate => new TargetCompatibilityCandidate(
    [$value($database, $version), $presence($optional, false)],
    $extra ? [new TargetCompatibleExtraState('module:analytics:events', TargetCompatibleExtraState::COMPATIBLE, 'Owned compatible extra state.')] : []
);
$snapshot = static fn (TargetCompatibilityCandidate $candidate, string $targetIdentity = 'target-1', string $installation = 'inst_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', string $namespace = 'alpha', array $eligibility = []): AdoptionEvaluationSnapshot => new AdoptionEvaluationSnapshot(
    $candidate,
    LegacyBoundaryEvidence::none(),
    $eligibility,
    $targetIdentity,
    $installation,
    $namespace
);

$evaluator = new TargetCompatibilityEvaluator();
$orchestrator = new AdoptionOrchestrator($evaluator, new AdoptionBoundaryClassifier());
$integration = new InstallerAdoptionIntegration();
$proof = new InstallerOwnershipProof(new InstallationIdentity('inst_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'), 'alpha', 'core-schema-generation:wu6', str_repeat('a', 64));
$occupancy = new InstallerDatabaseOccupancyResult(InstallerDatabaseOccupancy::COPOT, [], ['alpha'], [], true);
$routing = (new InstallerRoutingPlanner())->plan($occupancy, InstallerIntent::ADOPT, 'alpha');
$run = static function (AdoptionEvaluationSnapshot $initial, callable $fresh, callable $execute) use ($orchestrator, $target): AdoptionOrchestrationResult {
    return $orchestrator->run(new AdoptionOrchestrationRequest($target, $initial), $fresh, $execute);
};

// Exact-match and generalized-compatible candidates both reach the non-mutating Installer gate.
$exactInitial = new AdoptionEvaluationSnapshot($candidate('8.0.0'), LegacyBoundaryEvidence::exactMatch('historical-exact'), [], 'target-1', 'inst_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'alpha');
$exact = $run($exactInitial, static fn (): AdoptionEvaluationSnapshot => throw new RuntimeException('Exact-match path must not re-proof.'), static fn (): AdoptionResolutionOperationResult => throw new RuntimeException('Exact-match path must not execute lifecycle work.'));
$assert($exact->state() === AdoptionOrchestrationResult::READY && $exact->classification() === AdoptionBoundaryClassification::EXACT_MATCH_ADOPTION, 'Historical exact-match path did not remain ready.');
$assert($integration->decide($routing, $exact, [$proof])->state() === InstallerAdoptionDecision::TERMINAL_ADOPT, 'Historical exact-match result did not reach terminal Adopt.');

$generalized = $run($snapshot($candidate('8.1.0', true)), static fn (): AdoptionEvaluationSnapshot => throw new RuntimeException('Compatible path must not re-proof.'), static fn (): AdoptionResolutionOperationResult => throw new RuntimeException('Compatible path must not execute lifecycle work.'));
$assert($generalized->state() === AdoptionOrchestrationResult::READY && $generalized->classification() === AdoptionBoundaryClassification::GENERALIZED_ADOPTION_COMPATIBLE, 'Generalized compatible extra-state path was not ready.');
$assert($integration->decide($routing, $generalized, [$proof])->terminal(), 'Generalized compatible result did not reach terminal Adopt.');

// A mandatory gap uses one existing lifecycle authority, then requires fresh re-proof before Adopt.
$gapEvaluation = $evaluator->evaluate($target, $candidate('7.4.0'));
$gap = array_values(array_filter($gapEvaluation->requirementGaps(), static fn ($entry): bool => $entry->mandatory()))[0] ?? throw new RuntimeException('Mandatory database gap was not produced.');
$eligibility = new LifecycleResolutionEligibility($gap->evidenceIdentity(), LifecycleResolutionEligibility::UPDATE, true, 'wu6:update', 'Existing Update authority is eligible.');
$operations = 0;
$resolved = $run(
    $snapshot($candidate('7.4.0'), 'target-1', 'inst_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'alpha', [$eligibility]),
    static function () use ($snapshot, $candidate): AdoptionEvaluationSnapshot { return $snapshot($candidate('8.0.0')); },
    static function (\Copot\Core\AdoptionResolutionContext $context, LifecycleResolutionEligibility $selected) use (&$operations): AdoptionResolutionOperationResult {
        $operations++;
        return new AdoptionResolutionOperationResult('wu6-update-operation', $selected->lifecycleClass(), AdoptionResolutionOperationResult::COMPLETED, 'wu6-update-result', 'Existing lifecycle authority completed.');
    }
);
$assert($resolved->state() === AdoptionOrchestrationResult::READY && count($resolved->operations()) === 1 && $operations === 1, 'Route B did not perform one authorized resolution and fresh re-proof.');
$assert($integration->decide($routing, $resolved, [$proof])->terminal(), 'Freshly re-proven Route B result did not reach terminal Adopt.');

// Legacy evidence remains distinct from ordinary target-relative compatibility.
$legacyInitial = new AdoptionEvaluationSnapshot($candidate('8.0.0'), LegacyBoundaryEvidence::legacy(LegacyBoundaryEvidence::GENUINE_LEGACY, 'legacy-wu6', 'Genuine legacy evidence.'), [], 'target-1', 'inst_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'alpha');
$legacy = $run($legacyInitial, static fn (): AdoptionEvaluationSnapshot => throw new RuntimeException('Legacy classification must not route normally.'), static fn (): AdoptionResolutionOperationResult => throw new RuntimeException('Legacy classification must not execute Route B.'));
$assert($legacy->state() === AdoptionOrchestrationResult::BLOCKED, 'Genuine Legacy Reconciliation evidence was treated as normal Adoption.');
$assert($integration->decide($routing, $legacy, [$proof])->state() === InstallerAdoptionDecision::BLOCKED, 'Legacy result reached terminal Adopt.');

// Material identity drift and unknown evidence fail closed without further lifecycle execution.
$driftOperations = 0;
$drift = $run(
    $snapshot($candidate('7.4.0'), 'target-1', 'inst_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'alpha', [$eligibility]),
    static function () use ($snapshot, $candidate): AdoptionEvaluationSnapshot { return $snapshot($candidate('8.0.0'), 'target-1', 'inst_bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'alpha'); },
    static function (\Copot\Core\AdoptionResolutionContext $context, LifecycleResolutionEligibility $selected) use (&$driftOperations): AdoptionResolutionOperationResult {
        $driftOperations++;
        return new AdoptionResolutionOperationResult('wu6-drift-operation', $selected->lifecycleClass(), AdoptionResolutionOperationResult::COMPLETED, 'wu6-drift-result', 'Completed before identity drift.');
    }
);
$assert($drift->state() === AdoptionOrchestrationResult::STALE && $driftOperations === 1, 'Installation identity drift was not stale after the completed operation.');
$unknown = $run($snapshot(new TargetCompatibilityCandidate([])), static fn (): AdoptionEvaluationSnapshot => throw new RuntimeException('Unknown state must not re-proof.'), static fn (): AdoptionResolutionOperationResult => throw new RuntimeException('Unknown state must not execute lifecycle work.'));
$assert($unknown->state() === AdoptionOrchestrationResult::BLOCKED && !$integration->decide($routing, $unknown, [$proof])->terminal(), 'Unknown compatibility state did not fail closed.');

echo "WU6 Database Adoption cross-lifecycle acceptance passed ({$assertions} assertions)." . PHP_EOL;
