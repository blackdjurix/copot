<?php

declare(strict_types=1);

use Copot\Core\PackageTargetRequirement;
use Copot\Core\PackageTargetRequirements;
use Copot\Core\TargetCompatibilityCandidate;
use Copot\Core\TargetCompatibilityEvaluator;
use Copot\Core\TargetCompatibilityResult;
use Copot\Core\TargetCompatibleExtraState;
use Copot\Core\TargetRequirementEvidence;
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
$requirements = new PackageTargetRequirements([$database, $schema, $capability]);
$observation = static fn (PackageTargetRequirement $requirement, string $value, string $provenance = 'observed'): TargetRequirementObservation => TargetRequirementObservation::value($requirement, $value, $provenance . ':' . $requirement->key());
$presence = static fn (PackageTargetRequirement $requirement, bool $present, string $provenance = 'observed'): TargetRequirementObservation => TargetRequirementObservation::presence($requirement, $present, $provenance . ':' . $requirement->key());
$allSatisfied = static fn (): TargetCompatibilityCandidate => new TargetCompatibilityCandidate([
    $observation($database, '8.0.0'),
    $observation($schema, 'canonical-schema:1'),
    $presence($capability, true),
]);
$evaluator = new TargetCompatibilityEvaluator();

// Operator semantics are evaluated by WU2, rather than supplied as a result.
$ready = $evaluator->evaluate($requirements, $allSatisfied());
$assert($ready->isAdoptionReady() && count($ready->satisfied()) === 3, 'All satisfied target requirements were not Adoption Ready.');
$aboveMinimum = $evaluator->evaluate(new PackageTargetRequirements([$database]), new TargetCompatibilityCandidate([$observation($database, '8.1.0')]));
$assert($aboveMinimum->state() === TargetCompatibilityResult::READY, 'Database version above the minimum was not satisfied.');
$belowMinimum = $evaluator->evaluate(new PackageTargetRequirements([$database]), new TargetCompatibilityCandidate([$observation($database, '7.4.0')]));
$assert($belowMinimum->state() === TargetCompatibilityResult::REQUIREMENT_GAPS && !$belowMinimum->isAdoptionReady(), 'Database version below the minimum was not a mandatory gap.');
$assert($belowMinimum->requirementGaps()[0]->mandatory(), 'Mandatory database gap was not marked blocking.');
$assert($evaluator->evaluate(new PackageTargetRequirements([$schema]), new TargetCompatibilityCandidate([$observation($schema, 'canonical-schema:1')]))->isAdoptionReady(), 'Exact schema identity was not satisfied.');
$schemaGap = $evaluator->evaluate(new PackageTargetRequirements([$schema]), new TargetCompatibilityCandidate([$observation($schema, 'schema-extra')]));
$assert($schemaGap->state() === TargetCompatibilityResult::REQUIREMENT_GAPS, 'Differing authoritative schema identity was not bounded as a gap.');
$assert($evaluator->evaluate(new PackageTargetRequirements([$capability]), new TargetCompatibilityCandidate([$presence($capability, true)]))->isAdoptionReady(), 'Present capability was not satisfied.');
$capabilityGap = $evaluator->evaluate(new PackageTargetRequirements([$capability]), new TargetCompatibilityCandidate([$presence($capability, false)]));
$assert($capabilityGap->state() === TargetCompatibilityResult::REQUIREMENT_GAPS, 'Positively absent capability was not classified as a gap.');

// Non-comparable evidence fails closed and cannot be fabricated into a gap.
$unknown = TargetRequirementObservation::classified($database, TargetRequirementObservation::UNKNOWN, 'probe-unknown', 'Version evidence unavailable.');
$unknownResult = $evaluator->evaluate(new PackageTargetRequirements([$database]), new TargetCompatibilityCandidate([$unknown]));
$assert($unknownResult->state() === TargetCompatibilityResult::UNKNOWN && $unknownResult->requirementGaps() === [], 'Unknown version evidence was fabricated into a gap.');
$invalidVersion = $evaluator->evaluate(new PackageTargetRequirements([$database]), new TargetCompatibilityCandidate([$observation($database, 'not-a-version', 'invalid')]));
$assert($invalidVersion->state() === TargetCompatibilityResult::UNKNOWN, 'Invalid database version evidence did not fail closed.');
foreach ([TargetRequirementObservation::AMBIGUOUS, TargetRequirementObservation::CONTRADICTORY, TargetRequirementObservation::UNSAFE, TargetRequirementObservation::UNSUPPORTED] as $state) {
    $result = $evaluator->evaluate(new PackageTargetRequirements([$database]), new TargetCompatibilityCandidate([
        TargetRequirementObservation::classified($database, $state, 'classified-' . $state, 'Inspection is not safely comparable.'),
    ]));
    $assert($result->state() === $state && !$result->isAdoptionReady(), 'Classified state ' . $state . ' did not fail closed.');
}
$missing = $evaluator->evaluate($requirements, new TargetCompatibilityCandidate([]));
$assert($missing->state() === TargetCompatibilityResult::UNKNOWN && $missing->requirementGaps() === [], 'Missing evidence was not unknown.');

// Evidence is bound to the complete target declaration, not just its key.
$changedMinimum = new PackageTargetRequirement(PackageTargetRequirement::DATABASE, 'mysql', 'server', PackageTargetRequirement::MINIMUM_VERSION, '9.0.0');
$changedTarget = $evaluator->evaluate(new PackageTargetRequirements([$changedMinimum]), new TargetCompatibilityCandidate([$observation($database, '8.0.0')]));
$assert($changedTarget->state() === TargetCompatibilityResult::UNKNOWN, 'Evidence bound only by key was incorrectly trusted.');
$sameKeyNewObservation = $evaluator->evaluate(new PackageTargetRequirements([$changedMinimum]), new TargetCompatibilityCandidate([$observation($changedMinimum, '8.0.0')]));
$assert($sameKeyNewObservation->state() === TargetCompatibilityResult::REQUIREMENT_GAPS, 'Changed target minimum did not change evaluation.');
$preclassifiedRejected = false;
try {
    new TargetCompatibilityCandidate([
        new TargetRequirementEvidence($database->key(), TargetRequirementEvidence::SATISFIED, 'spoof', 'Caller supplied status'),
    ]);
} catch (InvalidArgumentException) {
    $preclassifiedRejected = true;
}
$assert($preclassifiedRejected, 'Caller-supplied satisfied status bypassed observation comparison.');

// Optional absence is explicit and non-blocking; mandatory absence remains blocking.
$optional = new PackageTargetRequirement(PackageTargetRequirement::CAPABILITY, 'webcore', 'optional-api', PackageTargetRequirement::PRESENT, null, false);
$optionalResult = $evaluator->evaluate(new PackageTargetRequirements([$optional]), new TargetCompatibilityCandidate([$presence($optional, false, 'optional-absent')]));
$assert($optionalResult->isAdoptionReady() && count($optionalResult->requirementGaps()) === 1, 'Optional absent capability blocked readiness or was not represented.');
$assert(!$optionalResult->requirementGaps()[0]->mandatory(), 'Optional gap was not explicitly marked non-blocking.');

// Compatible extras, coherence, re-proof, determinism, and no mutation remain enforced.
$withExtra = new TargetCompatibilityCandidate($allSatisfied()->observations(), [new TargetCompatibleExtraState('module:analytics:events', TargetCompatibleExtraState::COMPATIBLE, 'owned compatible extra')]);
$extraResult = $evaluator->evaluate($requirements, $withExtra);
$assert($extraResult->isAdoptionReady() && count($extraResult->compatibleExtraState()) === 1, 'Compatible extra state was not accepted.');
$unsafe = $evaluator->evaluate($requirements, new TargetCompatibilityCandidate($allSatisfied()->observations(), [], TargetCompatibilityCandidate::UNSAFE));
$assert($unsafe->state() === TargetCompatibilityResult::UNSAFE, 'Unsafe coherence state did not fail closed.');
$gap = $evaluator->evaluate(new PackageTargetRequirements([$database]), new TargetCompatibilityCandidate([$observation($database, '7.0.0', 'before-resolution')]));
$reproved = $evaluator->reprove(new PackageTargetRequirements([$database]), new TargetCompatibilityCandidate([$observation($database, '8.0.0', 'after-resolution')]));
$assert($gap->state() === TargetCompatibilityResult::REQUIREMENT_GAPS && $reproved->isAdoptionReady(), 'Re-Proof did not recompute from fresh observed evidence.');
$stillGap = $evaluator->reprove(new PackageTargetRequirements([$database]), new TargetCompatibilityCandidate([$observation($database, '7.9.0', 'insufficient-resolution')]));
$assert(!$stillGap->isAdoptionReady() && $stillGap->state() === TargetCompatibilityResult::REQUIREMENT_GAPS, 'Insufficient Re-Proof evidence incorrectly produced readiness.');
$reordered = new PackageTargetRequirements([$capability, $database, $schema]);
$reorderedReady = $evaluator->evaluate($reordered, $allSatisfied());
$assert($reordered->identity() === $requirements->identity() && $reorderedReady->toArray() === $ready->toArray(), 'Requirement evaluation was not deterministic.');
$before = $withExtra->observations();
$evaluator->evaluate($requirements, $withExtra);
$assert($before === $withExtra->observations(), 'Evaluator mutated observed candidate evidence.');

echo "WU2 target compatibility focused tests passed ({$assertions} assertions)." . PHP_EOL;
