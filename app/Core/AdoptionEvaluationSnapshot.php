<?php

namespace Copot\Core;

/** Fresh WU4 input for one target-relative Adoption evaluation. */
final class AdoptionEvaluationSnapshot
{
    /** @var list<LifecycleResolutionEligibility> */
    private array $resolutionEligibility;

    /** @param list<LifecycleResolutionEligibility> $resolutionEligibility */
    public function __construct(
        private TargetCompatibilityCandidate $candidate,
        private LegacyBoundaryEvidence $legacyEvidence,
        array $resolutionEligibility,
        private string $targetIdentity,
        private string $installationIdentity,
        private string $namespaceIdentity
    ) {
        foreach ([$targetIdentity, $installationIdentity, $namespaceIdentity] as $value) {
            if ($value === '' || trim($value) !== $value || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                throw new \InvalidArgumentException('Adoption evaluation identity is invalid.');
            }
        }

        $this->resolutionEligibility = [];
        foreach ($resolutionEligibility as $eligibility) {
            if (!$eligibility instanceof LifecycleResolutionEligibility) {
                throw new \InvalidArgumentException('Adoption resolution eligibility is invalid.');
            }
            $this->resolutionEligibility[] = $eligibility;
        }
        usort($this->resolutionEligibility, static fn (LifecycleResolutionEligibility $left, LifecycleResolutionEligibility $right): int => [$left->requirementGapIdentity(), $left->lifecycleClass(), $left->evidenceIdentity()] <=> [$right->requirementGapIdentity(), $right->lifecycleClass(), $right->evidenceIdentity()]);
    }

    public function candidate(): TargetCompatibilityCandidate { return $this->candidate; }
    public function legacyEvidence(): LegacyBoundaryEvidence { return $this->legacyEvidence; }
    /** @return list<LifecycleResolutionEligibility> */
    public function resolutionEligibility(): array { return $this->resolutionEligibility; }
    public function targetIdentity(): string { return $this->targetIdentity; }
    public function installationIdentity(): string { return $this->installationIdentity; }
    public function namespaceIdentity(): string { return $this->namespaceIdentity; }
}
