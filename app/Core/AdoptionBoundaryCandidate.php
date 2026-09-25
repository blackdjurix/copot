<?php

namespace Copot\Core;

/** Immutable WU3 inputs from WU2 and existing lifecycle authorities. */
final class AdoptionBoundaryCandidate
{
    /** @var list<LifecycleResolutionEligibility> */
    private array $resolutionEligibility;

    /** @param list<LifecycleResolutionEligibility> $resolutionEligibility */
    public function __construct(
        private TargetCompatibilityResult $compatibility,
        private LegacyBoundaryEvidence $legacyEvidence,
        array $resolutionEligibility = []
    ) {
        $this->resolutionEligibility = [];
        foreach ($resolutionEligibility as $eligibility) {
            if (!$eligibility instanceof LifecycleResolutionEligibility) {
                throw new \InvalidArgumentException('Lifecycle resolution eligibility is invalid.');
            }
            $this->resolutionEligibility[] = $eligibility;
        }
        usort($this->resolutionEligibility, static fn (LifecycleResolutionEligibility $left, LifecycleResolutionEligibility $right): int => [$left->requirementGapIdentity(), $left->lifecycleClass(), $left->evidenceIdentity()] <=> [$right->requirementGapIdentity(), $right->lifecycleClass(), $right->evidenceIdentity()]);
    }

    public function compatibility(): TargetCompatibilityResult { return $this->compatibility; }
    public function legacyEvidence(): LegacyBoundaryEvidence { return $this->legacyEvidence; }
    /** @return list<LifecycleResolutionEligibility> */
    public function resolutionEligibility(): array { return $this->resolutionEligibility; }
}
