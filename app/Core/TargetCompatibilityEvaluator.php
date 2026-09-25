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
            $evidence = $this->evidenceFor($requirement, $candidate->requirementEvidence());
            if ($evidence === null) {
                $blockers[] = $this->blocker('requirement', $requirement->key(), 'Requirement evidence is unavailable.');
                $severity[] = TargetCompatibilityResult::UNKNOWN;
                continue;
            }

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

    private function evidenceFor(PackageTargetRequirement $requirement, array $evidence): ?TargetRequirementEvidence
    {
        foreach ($evidence as $entry) {
            if ($entry->requirementKey() === $requirement->key()) {
                return $entry;
            }
        }
        return null;
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
