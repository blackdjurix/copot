<?php

declare(strict_types=1);

use Copot\Core\PackageTargetRequirement;
use Copot\Core\PackageTargetRequirements;
use Copot\Core\TargetCompatibilityCandidate;
use Copot\Core\TargetCompatibilityEvaluator;
use Copot\Core\TargetCompatibilityResult;
use Copot\Core\TargetCompatibleExtraState;
use Copot\Core\TargetRequirementEvidence;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$requirements = new PackageTargetRequirements([
    new PackageTargetRequirement(PackageTargetRequirement::DATABASE, 'mysql', 'server', PackageTargetRequirement::MINIMUM_VERSION, '8.0.0'),
    new PackageTargetRequirement(PackageTargetRequirement::SCHEMA, 'webcore', 'core-schema', PackageTargetRequirement::EXACT_IDENTITY, 'canonical-schema:1'),
    new PackageTargetRequirement(PackageTargetRequirement::CAPABILITY, 'webcore', 'content-api', PackageTargetRequirement::PRESENT),
]);
$evidence = static function (string $key, string $state, string $identity = 'proof'): TargetRequirementEvidence {
    return new TargetRequirementEvidence($key, $state, $identity . ':' . $key, $state . ' evidence');
};
$allSatisfied = static fn (): TargetCompatibilityCandidate => new TargetCompatibilityCandidate(array_map(
    static fn ($requirement): TargetRequirementEvidence => new TargetRequirementEvidence($requirement->key(), TargetRequirementEvidence::SATISFIED, 'proof:' . $requirement->key(), 'satisfied evidence'),
    $requirements->requirements()
));
$evaluator = new TargetCompatibilityEvaluator();

// All requirements satisfied, including the explicit exact-match fast path.
$ready = $evaluator->evaluate($requirements, $allSatisfied());
$assert($ready->isAdoptionReady() && $ready->state() === TargetCompatibilityResult::READY, 'Satisfied target requirements were not Adoption Ready.');
$assert(count($ready->satisfied()) === 3 && $ready->requirementGaps() === [], 'Satisfied requirement evidence was incomplete.');

// Compatible extra state is retained and does not force exact aggregate-schema equality.
$withExtra = new TargetCompatibilityCandidate(
    $allSatisfied()->requirementEvidence(),
    [new TargetCompatibleExtraState('module:analytics:table:events', TargetCompatibleExtraState::COMPATIBLE, 'owned compatible extra')]
);
$extraResult = $evaluator->evaluate($requirements, $withExtra);
$assert($extraResult->isAdoptionReady(), 'Compatible extra state blocked readiness.');
$assert(count($extraResult->compatibleExtraState()) === 1, 'Compatible extra state was not represented.');

// A positively proven single gap is not ready and remains distinct from unknown.
$singleGap = new TargetCompatibilityCandidate([
    $evidence($requirements->requirements()[0]->key(), TargetRequirementEvidence::GAP, 'missing-db'),
    $evidence($requirements->requirements()[1]->key(), TargetRequirementEvidence::SATISFIED),
    $evidence($requirements->requirements()[2]->key(), TargetRequirementEvidence::SATISFIED),
]);
$gapResult = $evaluator->evaluate($requirements, $singleGap);
$assert($gapResult->state() === TargetCompatibilityResult::REQUIREMENT_GAPS && !$gapResult->isAdoptionReady(), 'Positive requirement gap was not classified.');
$assert(count($gapResult->requirementGaps()) === 1 && $gapResult->requirementGaps()[0]->evidenceIdentity() === 'missing-db:' . $requirements->requirements()[0]->key(), 'Gap provenance was not retained.');

// Multiple independent gaps remain separate and no route is selected.
$multipleGaps = new TargetCompatibilityCandidate([
    $evidence($requirements->requirements()[0]->key(), TargetRequirementEvidence::GAP, 'missing-db'),
    $evidence($requirements->requirements()[1]->key(), TargetRequirementEvidence::GAP, 'missing-schema'),
    $evidence($requirements->requirements()[2]->key(), TargetRequirementEvidence::SATISFIED),
]);
$multipleResult = $evaluator->evaluate($requirements, $multipleGaps);
$assert($multipleResult->state() === TargetCompatibilityResult::REQUIREMENT_GAPS && count($multipleResult->requirementGaps()) === 2, 'Multiple requirement gaps were not independently classified.');
$assert($multipleResult->blockers() === [] && count($multipleResult->requirementGaps()) === 2, 'Requirement gaps were incorrectly treated as unknown blockers.');

foreach ([
    [TargetRequirementEvidence::UNKNOWN, TargetCompatibilityResult::UNKNOWN],
    [TargetRequirementEvidence::AMBIGUOUS, TargetCompatibilityResult::AMBIGUOUS],
    [TargetRequirementEvidence::CONTRADICTORY, TargetCompatibilityResult::CONTRADICTORY],
    [TargetRequirementEvidence::UNSAFE, TargetCompatibilityResult::UNSAFE],
    [TargetRequirementEvidence::UNSUPPORTED, TargetCompatibilityResult::UNSUPPORTED],
] as [$evidenceState, $resultState]) {
    $candidateEvidence = $allSatisfied()->requirementEvidence();
    $candidateEvidence[0] = $evidence($requirements->requirements()[0]->key(), $evidenceState);
    $result = $evaluator->evaluate($requirements, new TargetCompatibilityCandidate($candidateEvidence));
    $assert($result->state() === $resultState && !$result->isAdoptionReady(), 'Fail-closed state ' . $resultState . ' was not preserved.');
}

// Missing evidence is unknown, not a positively classified gap.
$missing = $evaluator->evaluate($requirements, new TargetCompatibilityCandidate([]));
$assert($missing->state() === TargetCompatibilityResult::UNKNOWN && $missing->requirementGaps() === [], 'Missing evidence was incorrectly classified as a requirement gap.');

// Coherence and extra-state safety are independent readiness gates.
$unsafeCandidate = new TargetCompatibilityCandidate($allSatisfied()->requirementEvidence(), [], TargetCompatibilityCandidate::UNSAFE);
$assert($evaluator->evaluate($requirements, $unsafeCandidate)->state() === TargetCompatibilityResult::UNSAFE, 'Unsafe installation identity did not fail closed.');
$ambiguousExtra = new TargetCompatibilityCandidate($allSatisfied()->requirementEvidence(), [new TargetCompatibleExtraState('extra:unknown', TargetCompatibleExtraState::UNKNOWN, 'unproven extra')]);
$assert($evaluator->evaluate($requirements, $ambiguousExtra)->state() === TargetCompatibilityResult::UNKNOWN, 'Unknown extra state did not fail closed.');

// Mandatory absence blocks; an optional gap remains recorded but does not block readiness.
$mandatoryAbsent = $evaluator->evaluate($requirements, $singleGap);
$assert(!$mandatoryAbsent->isAdoptionReady(), 'Absent mandatory requirement produced readiness.');
$optional = new PackageTargetRequirements([
    new PackageTargetRequirement(PackageTargetRequirement::CAPABILITY, 'webcore', 'optional-api', PackageTargetRequirement::PRESENT, null, false),
]);
$optionalResult = $evaluator->evaluate($optional, new TargetCompatibilityCandidate([
    $evidence('capability:webcore:optional-api', TargetRequirementEvidence::GAP, 'optional-missing'),
]));
$assert($optionalResult->isAdoptionReady() && count($optionalResult->requirementGaps()) === 1, 'Optional gap handling was not bounded to mandatory readiness.');

// Re-proof is a fresh evaluation: prior gaps do not survive a changed candidate.
$reproved = $evaluator->reprove($requirements, $allSatisfied());
$assert($reproved->isAdoptionReady() && $reproved->identity() !== $gapResult->identity(), 'Compatibility Re-Proof did not produce fresh evidence.');
$insufficient = $evaluator->reprove($requirements, new TargetCompatibilityCandidate([
    $evidence($requirements->requirements()[0]->key(), TargetRequirementEvidence::SATISFIED),
    $evidence($requirements->requirements()[1]->key(), TargetRequirementEvidence::GAP, 'still-missing-schema'),
    $evidence($requirements->requirements()[2]->key(), TargetRequirementEvidence::SATISFIED),
]));
$assert(!$insufficient->isAdoptionReady() && $insufficient->state() === TargetCompatibilityResult::REQUIREMENT_GAPS, 'Insufficient external resolution incorrectly produced readiness.');

// Deterministic ordering/identity and evaluation-only behavior.
$reordered = new PackageTargetRequirements(array_reverse($requirements->requirements()));
$reorderedResult = $evaluator->evaluate($reordered, $allSatisfied());
$assert($reordered->identity() === $requirements->identity() && $reorderedResult->toArray() === $ready->toArray(), 'Target evaluation was not deterministic.');
$before = $withExtra->extraState();
$evaluator->evaluate($requirements, $withExtra);
$assert($before === $withExtra->extraState(), 'Evaluator mutated candidate evidence.');

echo "WU2 target compatibility focused tests passed ({$assertions} assertions)." . PHP_EOL;
