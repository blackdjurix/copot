<?php

namespace Copot\Core;

/**
 * Bounded WU4 Adoption orchestration. The executor callback is an existing
 * lifecycle authority; this class owns no database, schema, migration, or
 * filesystem mutation capability.
 */
final class AdoptionOrchestrator
{
    public function __construct(
        private TargetCompatibilityEvaluator $evaluator,
        private AdoptionBoundaryClassifier $classifier
    ) {
    }

    /**
     * @param callable(): AdoptionEvaluationSnapshot $freshEvaluation
     * @param callable(AdoptionResolutionContext, LifecycleResolutionEligibility): AdoptionResolutionOperationResult $execute
     */
    public function run(AdoptionOrchestrationRequest $request, callable $freshEvaluation, callable $execute): AdoptionOrchestrationResult
    {
        $orchestrationIdentity = $request->identity();
        $operations = [];
        $attemptedGaps = [];
        $snapshot = $request->initial();
        $evaluation = $this->evaluate($request->target(), $snapshot);
        $classification = $evaluation['classification'];

        for ($step = 1; ; $step++) {
            if (!$this->sameBoundary($request->initial(), $snapshot)) {
                return $this->result(AdoptionOrchestrationResult::STALE, $orchestrationIdentity, $evaluation['compatibility'], $classification, $operations, 'Target, installation, or namespace identity changed.');
            }

            if ($classification->state() === AdoptionBoundaryClassification::EXACT_MATCH_ADOPTION
                || $classification->state() === AdoptionBoundaryClassification::GENERALIZED_ADOPTION_COMPATIBLE) {
                return $this->result(AdoptionOrchestrationResult::READY, $orchestrationIdentity, $evaluation['compatibility'], $classification, $operations, 'Fresh compatibility proof produced Adoption Readiness.');
            }

            if ($classification->state() !== AdoptionBoundaryClassification::GENERALIZED_ADOPTION_RESOLVABLE_GAPS) {
                return $this->result(AdoptionOrchestrationResult::BLOCKED, $orchestrationIdentity, $evaluation['compatibility'], $classification, $operations, 'Compatibility or legacy evidence is not eligible for Route B resolution.');
            }

            $next = $this->nextEligibility($evaluation['compatibility'], $classification, $attemptedGaps);
            if ($next === null) {
                return $this->result(AdoptionOrchestrationResult::BLOCKED, $orchestrationIdentity, $evaluation['compatibility'], $classification, $operations, 'No unambiguous authorized lifecycle operation is available for the current requirement gaps.');
            }

            $attemptedGaps[$next->requirementGapIdentity()] = true;
            $context = new AdoptionResolutionContext(
                $orchestrationIdentity,
                $snapshot->targetIdentity(),
                $snapshot->installationIdentity(),
                $snapshot->namespaceIdentity(),
                $next->requirementGapIdentity(),
                $next->lifecycleClass(),
                $step
            );

            try {
                $operation = $execute($context, $next);
            } catch (\Throwable $exception) {
                return $this->result(AdoptionOrchestrationResult::SUSPENDED, $orchestrationIdentity, $evaluation['compatibility'], $classification, $operations, 'Underlying lifecycle authority failed before returning a result: ' . $exception->getMessage());
            }
            if (!$operation instanceof AdoptionResolutionOperationResult) {
                return $this->result(AdoptionOrchestrationResult::BLOCKED, $orchestrationIdentity, $evaluation['compatibility'], $classification, $operations, 'Underlying lifecycle authority returned invalid operation evidence.');
            }
            if ($operation->lifecycleClass() !== $next->lifecycleClass()) {
                return $this->result(AdoptionOrchestrationResult::BLOCKED, $orchestrationIdentity, $evaluation['compatibility'], $classification, $operations, 'Underlying lifecycle operation classification does not match authorized eligibility.');
            }
            foreach ($operations as $previous) {
                if ($previous->operationId() === $operation->operationId()) {
                    return $this->result(AdoptionOrchestrationResult::BLOCKED, $orchestrationIdentity, $evaluation['compatibility'], $classification, $operations, 'Composite Resolution reused an underlying operation identity.');
                }
            }
            $operations[] = $operation;
            if (!$operation->completed()) {
                $state = in_array($operation->status(), [AdoptionResolutionOperationResult::UNAUTHORIZED, AdoptionResolutionOperationResult::UNAVAILABLE], true)
                    ? AdoptionOrchestrationResult::BLOCKED
                    : AdoptionOrchestrationResult::SUSPENDED;
                return $this->result($state, $orchestrationIdentity, $evaluation['compatibility'], $classification, $operations, $operation->detail());
            }

            try {
                $snapshot = $freshEvaluation();
            } catch (\Throwable $exception) {
                return $this->result(AdoptionOrchestrationResult::SUSPENDED, $orchestrationIdentity, $evaluation['compatibility'], $classification, $operations, 'Fresh compatibility re-proof was unavailable: ' . $exception->getMessage());
            }
            if (!$snapshot instanceof AdoptionEvaluationSnapshot) {
                return $this->result(AdoptionOrchestrationResult::SUSPENDED, $orchestrationIdentity, $evaluation['compatibility'], $classification, $operations, 'Fresh compatibility re-proof returned invalid evidence.');
            }
            $evaluation = $this->evaluate($request->target(), $snapshot);
            $classification = $evaluation['classification'];
        }
    }

    /** @return array{compatibility:TargetCompatibilityResult,classification:AdoptionBoundaryClassification} */
    private function evaluate(PackageTargetRequirements $target, AdoptionEvaluationSnapshot $snapshot): array
    {
        $compatibility = $this->evaluator->evaluate($target, $snapshot->candidate());
        $classification = $this->classifier->classify(new AdoptionBoundaryCandidate($compatibility, $snapshot->legacyEvidence(), $snapshot->resolutionEligibility()));
        return ['compatibility' => $compatibility, 'classification' => $classification];
    }

    private function sameBoundary(AdoptionEvaluationSnapshot $initial, AdoptionEvaluationSnapshot $current): bool
    {
        return $initial->targetIdentity() === $current->targetIdentity()
            && $initial->installationIdentity() === $current->installationIdentity()
            && $initial->namespaceIdentity() === $current->namespaceIdentity();
    }

    private function nextEligibility(TargetCompatibilityResult $compatibility, AdoptionBoundaryClassification $classification, array $attemptedGaps): ?LifecycleResolutionEligibility
    {
        $eligible = [];
        foreach ($classification->resolutionEligibility() as $entry) {
            if ($entry->eligible() && !$this->alreadyAttempted($entry, $attemptedGaps)) {
                $eligible[$entry->requirementGapIdentity()][] = $entry;
            }
        }
        foreach ($compatibility->requirementGaps() as $gap) {
            if (!$gap->mandatory() || isset($attemptedGaps[$gap->evidenceIdentity()])) continue;
            $choices = $eligible[$gap->evidenceIdentity()] ?? [];
            if (count($choices) !== 1) return null;
            return $choices[0];
        }
        return null;
    }

    private function alreadyAttempted(LifecycleResolutionEligibility $entry, array $attemptedGaps): bool
    {
        return isset($attemptedGaps[$entry->requirementGapIdentity()]);
    }

    private function result(string $state, string $orchestrationIdentity, TargetCompatibilityResult $compatibility, AdoptionBoundaryClassification $classification, array $operations, string $detail): AdoptionOrchestrationResult
    {
        return new AdoptionOrchestrationResult($state, $orchestrationIdentity, $compatibility->identity(), $classification->state(), $operations, $detail);
    }
}
