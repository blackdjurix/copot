<?php

declare(strict_types=1);

use Copot\Core\AdoptionBoundaryCandidate;
use Copot\Core\AdoptionBoundaryClassification;
use Copot\Core\AdoptionBoundaryClassifier;
use Copot\Core\LegacyBoundaryEvidence;
use Copot\Core\LifecycleResolutionEligibility;
use Copot\Core\LegacyClassificationResult;
use Copot\Core\PackageTargetRequirement;
use Copot\Core\PackageTargetRequirements;
use Copot\Core\TargetCompatibilityCandidate;
use Copot\Core\TargetCompatibilityEvaluator;
use Copot\Core\TargetCompatibilityResult;
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

$database = new PackageTargetRequirement(PackageTargetRequirement::DATABASE, 'mysql', 'server', PackageTargetRequirement::MINIMUM_VERSION, '8.0.0');
$schema = new PackageTargetRequirement(PackageTargetRequirement::SCHEMA, 'webcore', 'core-schema', PackageTargetRequirement::EXACT_IDENTITY, 'canonical-schema:1');
$capability = new PackageTargetRequirement(PackageTargetRequirement::CAPABILITY, 'webcore', 'content-api', PackageTargetRequirement::PRESENT);
$target = new PackageTargetRequirements([$database, $schema, $capability]);
$observe = static fn (PackageTargetRequirement $requirement, string $value, string $source = 'probe'): TargetRequirementObservation => TargetRequirementObservation::value($requirement, $value, $source . ':' . $requirement->key());
$present = static fn (PackageTargetRequirement $requirement, bool $value, string $source = 'probe'): TargetRequirementObservation => TargetRequirementObservation::presence($requirement, $value, $source . ':' . $requirement->key());
$evaluator = new TargetCompatibilityEvaluator();
$classifier = new AdoptionBoundaryClassifier();
$ready = static fn (): TargetCompatibilityResult => $evaluator->evaluate($target, new TargetCompatibilityCandidate([
    $observe($database, '8.0.0'), $observe($schema, 'canonical-schema:1'), $present($capability, true),
]));

// Historical exact-match adoption remains a distinct positive fast path.
$exact = $classifier->classify(new AdoptionBoundaryCandidate($ready(), LegacyBoundaryEvidence::exactMatch('historical-exact-proof')));
$assert($exact->state() === AdoptionBoundaryClassification::EXACT_MATCH_ADOPTION, 'Historical exact-match Adoption path was not preserved.');

// Target-relative compatibility and compatible extras do not become Legacy Reconciliation.
$compatible = $classifier->classify(new AdoptionBoundaryCandidate($ready(), LegacyBoundaryEvidence::none()));
$assert($compatible->state() === AdoptionBoundaryClassification::GENERALIZED_ADOPTION_COMPATIBLE, 'Compatible target-relative candidate became the wrong boundary.');
$extraCompatible = $evaluator->evaluate($target, new TargetCompatibilityCandidate([
    $observe($database, '8.0.0'), $observe($schema, 'canonical-schema:1'), $present($capability, true),
], [new TargetCompatibleExtraState('module:analytics:events', TargetCompatibleExtraState::COMPATIBLE, 'owned extra state')]));
$assert($classifier->classify(new AdoptionBoundaryCandidate($extraCompatible, LegacyBoundaryEvidence::none()))->state() === AdoptionBoundaryClassification::GENERALIZED_ADOPTION_COMPATIBLE, 'Compatible extra state forced Legacy Reconciliation.');

// Positively proven gaps become bounded eligibility facts, without selecting or executing a route.
$gapCompatibility = $evaluator->evaluate(new PackageTargetRequirements([$database]), new TargetCompatibilityCandidate([$observe($database, '7.0.0', 'gap')]));
$gapIdentity = $gapCompatibility->requirementGaps()[0]->evidenceIdentity();
$eligible = new LifecycleResolutionEligibility($gapIdentity, LifecycleResolutionEligibility::UPGRADE, true, 'upgrade-authority-1', 'Existing Upgrade authority is eligible.');
$resolvable = $classifier->classify(new AdoptionBoundaryCandidate($gapCompatibility, LegacyBoundaryEvidence::none(), [$eligible]));
$assert($resolvable->state() === AdoptionBoundaryClassification::GENERALIZED_ADOPTION_RESOLVABLE_GAPS, 'Resolvable requirement gap was not classified for normal lifecycle resolution.');
$assert(count($resolvable->resolutionEligibility()) === 1 && $resolvable->resolutionEligibility()[0]->lifecycleClass() === LifecycleResolutionEligibility::UPGRADE, 'Lifecycle eligibility evidence was not retained.');
$multiGapTarget = new PackageTargetRequirements([$database, $schema]);
$multiGap = $evaluator->evaluate($multiGapTarget, new TargetCompatibilityCandidate([$observe($database, '7.0.0', 'gap-db'), $observe($schema, 'schema-extra', 'gap-schema')]));
$multiEligible = $classifier->classify(new AdoptionBoundaryCandidate($multiGap, LegacyBoundaryEvidence::none(), [
    new LifecycleResolutionEligibility($multiGap->requirementGaps()[0]->evidenceIdentity(), LifecycleResolutionEligibility::UPDATE, true, 'update-1', 'Update authority.'),
    new LifecycleResolutionEligibility($multiGap->requirementGaps()[1]->evidenceIdentity(), LifecycleResolutionEligibility::OWNER_AUTHORIZED_MIGRATION, true, 'migration-1', 'Owner authority.'),
]));
$assert($multiEligible->state() === AdoptionBoundaryClassification::GENERALIZED_ADOPTION_RESOLVABLE_GAPS && count($multiEligible->resolutionEligibility()) === 2, 'Multiple gaps were not independently classified without orchestration.');

// Optional non-blocking gaps remain generalized compatible.
$optional = new PackageTargetRequirement(PackageTargetRequirement::CAPABILITY, 'webcore', 'optional-api', PackageTargetRequirement::PRESENT, null, false);
$optionalResult = $evaluator->evaluate(new PackageTargetRequirements([$optional]), new TargetCompatibilityCandidate([
    $present($optional, false, 'optional-absence'),
]));
$assert($optionalResult->state() === TargetCompatibilityResult::READY, 'Optional gap did not remain non-blocking in WU2.');
$assert($classifier->classify(new AdoptionBoundaryCandidate($optionalResult, LegacyBoundaryEvidence::none()))->state() === AdoptionBoundaryClassification::GENERALIZED_ADOPTION_COMPATIBLE, 'Optional non-blocking gap forced Legacy Reconciliation.');

// Genuine legacy, partial/uncommitted, provenance, and broader convergence evidence retain Legacy authority.
foreach ([
    LegacyBoundaryEvidence::GENUINE_LEGACY,
    LegacyBoundaryEvidence::UNCOMMITTED,
    LegacyBoundaryEvidence::PARTIAL_COMMIT,
    LegacyBoundaryEvidence::UNPROVABLE_PROVENANCE,
    LegacyBoundaryEvidence::FILESYSTEM_DRIFT,
    LegacyBoundaryEvidence::BROADER_CONVERGENCE,
] as $legacyState) {
    $legacyResult = $classifier->classify(new AdoptionBoundaryCandidate($ready(), LegacyBoundaryEvidence::legacy($legacyState, 'legacy-' . $legacyState, 'Authoritative legacy boundary evidence.')));
    $assert($legacyResult->state() === AdoptionBoundaryClassification::LEGACY_RECONCILIATION_REQUIRED, 'Legacy state ' . $legacyState . ' was not preserved.');
}
$existingUnknown = LegacyBoundaryEvidence::fromLegacyClassification(LegacyClassificationResult::unknown('Existing migration provenance is unprovable.'));
$assert($classifier->classify(new AdoptionBoundaryCandidate($ready(), $existingUnknown))->state() === AdoptionBoundaryClassification::LEGACY_RECONCILIATION_REQUIRED, 'Existing unknown legacy authority was not preserved.');
$recognized = LegacyBoundaryEvidence::fromLegacyClassification(LegacyClassificationResult::canonicalBaseline('canonical-schema:recognized'));
$assert($classifier->classify(new AdoptionBoundaryCandidate($ready(), $recognized))->state() === AdoptionBoundaryClassification::GENERALIZED_ADOPTION_COMPATIBLE, 'Recognized historical baseline was incorrectly forced to Legacy Reconciliation.');

// Unknown compatibility fails closed; unsupported resolution does not fabricate a route.
$unknown = $evaluator->evaluate($target, new TargetCompatibilityCandidate([]));
$unknownClassification = $classifier->classify(new AdoptionBoundaryCandidate($unknown, LegacyBoundaryEvidence::none()));
$assert($unknownClassification->state() === AdoptionBoundaryClassification::FAIL_CLOSED, 'Unknown compatibility was forced into an Adoption path.');
$unsupportedRoute = new LifecycleResolutionEligibility($gapIdentity, LifecycleResolutionEligibility::UPGRADE, false, 'unsupported-1', 'Eligibility not proven.');
$unsupportedClassification = $classifier->classify(new AdoptionBoundaryCandidate($gapCompatibility, LegacyBoundaryEvidence::none(), [$unsupportedRoute]));
$assert($unsupportedClassification->state() === AdoptionBoundaryClassification::FAIL_CLOSED && $unsupportedClassification->resolutionEligibility() === [], 'Unsupported lifecycle resolution became a fabricated route.');

// Exact proof cannot override contradictory compatibility; no route or lifecycle operation is invoked.
$contradictoryExact = $classifier->classify(new AdoptionBoundaryCandidate($gapCompatibility, LegacyBoundaryEvidence::exactMatch('incorrect-exact-proof')));
$assert($contradictoryExact->state() === AdoptionBoundaryClassification::FAIL_CLOSED, 'Exact-match evidence overrode incompatible target evidence.');

// Identical inputs are deterministic and classification does not mutate its candidate.
$candidate = new AdoptionBoundaryCandidate($ready(), LegacyBoundaryEvidence::none());
$before = [$candidate->compatibility()->identity(), $candidate->legacyEvidence()->toArray(), $candidate->resolutionEligibility()];
$first = $classifier->classify($candidate);
$second = $classifier->classify($candidate);
$assert($first->identity() === $second->identity() && $first->toArray() === $second->toArray(), 'Boundary classification identity/output was not deterministic.');
$assert($before === [$candidate->compatibility()->identity(), $candidate->legacyEvidence()->toArray(), $candidate->resolutionEligibility()], 'Boundary classifier mutated its candidate.');

echo "WU3 Adoption boundary classification focused tests passed ({$assertions} assertions)." . PHP_EOL;
