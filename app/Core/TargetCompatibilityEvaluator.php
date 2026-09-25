<?php

namespace Copot\Core;

/**
 * Pure target-relative compatibility evaluation. No mutation or route
 * selection is available through this class.
 */
final class TargetCompatibilityEvaluator
{
    public function evaluate(PackageTargetRequirements $target, TargetCompatibilityCandidate $candidate): TargetCompatibilityResult
    {
        $satisfied = [];
        $gaps = [];
        $blockers = [];
        $severity = [];

        foreach ($target->requirements() as $requirement) {
            $observation = $this->observationFor($requirement, $candidate->observations());
            if ($observation === null) {
                $blockers[] = $this->blocker('requirement', $requirement->key(), 'Requirement evidence is unavailable.');
                $severity[] = TargetCompatibilityResult::UNKNOWN;
                continue;
            }

            $evidence = $this->evaluateRequirement($requirement, $observation);
            if ($evidence->state() === TargetRequirementEvidence::SATISFIED) {
                $satisfied[] = $evidence;
                continue;
            }

            if ($evidence->state() === TargetRequirementEvidence::GAP) {
                $gaps[] = $evidence;
                if ($requirement->mandatory()) {
                    $severity[] = TargetCompatibilityResult::REQUIREMENT_GAPS;
                }
                continue;
            }

            $state = $evidence->state();
            $blockers[] = $this->blocker('requirement', $evidence->requirementKey(), $evidence->detail());
            $severity[] = $state;
        }

        foreach ($candidate->extraState() as $extra) {
            if ($extra->state() === TargetCompatibleExtraState::COMPATIBLE) {
                continue;
            }
            $blockers[] = $this->blocker('extra_state', $extra->identity(), $extra->detail());
            $severity[] = $extra->state();
        }

        foreach ($candidate->coherence() as $source => $state) {
            if ($state === TargetCompatibilityCandidate::COHERENT) {
                continue;
            }
            $blockers[] = $this->blocker($source, $source, 'Candidate evidence is not coherent.');
            $severity[] = $state;
        }

        $state = $this->overallState($severity);
        return new TargetCompatibilityResult(
            $state,
            $target->identity(),
            $satisfied,
            $gaps,
            array_values(array_filter($candidate->extraState(), static fn (TargetCompatibleExtraState $extra): bool => $extra->state() === TargetCompatibleExtraState::COMPATIBLE)),
            $blockers
        );
    }

    public function reprove(PackageTargetRequirements $target, TargetCompatibilityCandidate $freshCandidate): TargetCompatibilityResult
    {
        return $this->evaluate($target, $freshCandidate);
    }

    private function observationFor(PackageTargetRequirement $requirement, array $observations): ?TargetRequirementObservation
    {
        foreach ($observations as $observation) {
            if ($observation->requirementKey() === $requirement->key()) {
                return $observation;
            }
        }
        return null;
    }

    private function evaluateRequirement(PackageTargetRequirement $requirement, TargetRequirementObservation $observation): TargetRequirementEvidence
    {
        $identity = $observation->provenanceIdentity();
        if ($observation->requirementIdentity() !== $this->requirementIdentity($requirement)) {
            return new TargetRequirementEvidence($requirement->key(), TargetRequirementEvidence::UNKNOWN, $identity, 'Observed evidence is bound to a different target requirement.', $requirement->mandatory());
        }

        if ($observation->state() !== TargetRequirementObservation::OBSERVED) {
            return new TargetRequirementEvidence($requirement->key(), $observation->state(), $identity, $observation->detail(), $requirement->mandatory());
        }

        try {
            if ($requirement->kind() === PackageTargetRequirement::DATABASE
                && $requirement->operator() === PackageTargetRequirement::MINIMUM_VERSION
                && $observation->observedValue() !== null) {
                PackageVersion::assertValid($observation->observedValue());
                $satisfied = PackageVersion::compare($observation->observedValue(), (string) $requirement->value()) >= 0;
                return new TargetRequirementEvidence($requirement->key(), $satisfied ? TargetRequirementEvidence::SATISFIED : TargetRequirementEvidence::GAP, $identity, $satisfied ? 'Observed database version satisfies the minimum.' : 'Observed database version is below the target minimum.', $requirement->mandatory());
            }

            if ($requirement->kind() === PackageTargetRequirement::SCHEMA
                && $requirement->operator() === PackageTargetRequirement::EXACT_IDENTITY
                && $observation->observedValue() !== null) {
                $satisfied = $observation->observedValue() === $requirement->value();
                return new TargetRequirementEvidence($requirement->key(), $satisfied ? TargetRequirementEvidence::SATISFIED : TargetRequirementEvidence::GAP, $identity, $satisfied ? 'Observed schema identity satisfies the target.' : 'Observed schema identity differs from the target.', $requirement->mandatory());
            }

            if ($requirement->kind() === PackageTargetRequirement::CAPABILITY
                && $requirement->operator() === PackageTargetRequirement::PRESENT
                && $observation->observedPresence() !== null) {
                $satisfied = $observation->observedPresence();
                return new TargetRequirementEvidence($requirement->key(), $satisfied ? TargetRequirementEvidence::SATISFIED : TargetRequirementEvidence::GAP, $identity, $satisfied ? 'Observed capability is present.' : 'Observed capability is positively absent.', $requirement->mandatory());
            }
        } catch (\InvalidArgumentException) {
            return new TargetRequirementEvidence($requirement->key(), TargetRequirementEvidence::UNKNOWN, $identity, 'Observed requirement value is invalid or incomparable.', $requirement->mandatory());
        }

        return new TargetRequirementEvidence($requirement->key(), TargetRequirementEvidence::UNSUPPORTED, $identity, 'Target requirement observation semantics are unsupported.', $requirement->mandatory());
    }

    private function requirementIdentity(PackageTargetRequirement $requirement): string
    {
        return hash('sha256', json_encode($requirement->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function overallState(array $severity): string
    {
        if ($severity === []) {
            return TargetCompatibilityResult::READY;
        }

        $priority = [
            TargetCompatibilityResult::CONTRADICTORY => 70,
            TargetCompatibilityResult::UNSAFE => 60,
            TargetCompatibilityResult::AMBIGUOUS => 50,
            TargetCompatibilityResult::UNKNOWN => 40,
            TargetCompatibilityResult::UNSUPPORTED => 30,
            TargetCompatibilityResult::REQUIREMENT_GAPS => 20,
        ];
        usort($severity, static fn (string $left, string $right): int => ($priority[$right] ?? 100) <=> ($priority[$left] ?? 100));
        return $severity[0];
    }

    /** @return array{source:string,identity:string,detail:string} */
    private function blocker(string $source, string $identity, string $detail): array
    {
        return ['source' => $source, 'identity' => $identity, 'detail' => $detail];
    }
}
