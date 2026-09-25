<?php

namespace Copot\Core;

/** Pure WU3 boundary classification; it does not select or execute routes. */
final class AdoptionBoundaryClassifier
{
    public function classify(AdoptionBoundaryCandidate $candidate): AdoptionBoundaryClassification
    {
        $compatibility = $candidate->compatibility();
        $legacy = $candidate->legacyEvidence();
        $blockers = [];

        if ($legacy->state() === LegacyBoundaryEvidence::EXACT_MATCH_PROVEN) {
            if ($compatibility->state() !== TargetCompatibilityResult::READY) {
                return $this->fail($compatibility, $legacy, 'exact_match', 'Historical exact-match proof conflicts with target compatibility evidence.');
            }
            return new AdoptionBoundaryClassification(AdoptionBoundaryClassification::EXACT_MATCH_ADOPTION, $compatibility->identity(), $legacy, [], []);
        }

        if ($legacy->requiresLegacy()) {
            return new AdoptionBoundaryClassification(AdoptionBoundaryClassification::LEGACY_RECONCILIATION_REQUIRED, $compatibility->identity(), $legacy, [], []);
        }

        if ($compatibility->state() === TargetCompatibilityResult::READY) {
            return new AdoptionBoundaryClassification(AdoptionBoundaryClassification::GENERALIZED_ADOPTION_COMPATIBLE, $compatibility->identity(), $legacy, [], []);
        }

        if ($compatibility->state() === TargetCompatibilityResult::REQUIREMENT_GAPS) {
            $eligible = $this->eligibleForMandatoryGaps($compatibility, $candidate->resolutionEligibility());
            if ($eligible['missing'] === []) {
                return new AdoptionBoundaryClassification(AdoptionBoundaryClassification::GENERALIZED_ADOPTION_RESOLVABLE_GAPS, $compatibility->identity(), $legacy, $eligible['accepted'], []);
            }
            foreach ($eligible['missing'] as $gapIdentity) {
                $blockers[] = ['source' => 'resolution_eligibility', 'identity' => $gapIdentity, 'detail' => 'No existing authorized lifecycle-resolution eligibility was proven for the requirement gap.'];
            }
            return new AdoptionBoundaryClassification(AdoptionBoundaryClassification::FAIL_CLOSED, $compatibility->identity(), $legacy, [], $blockers);
        }

        return $this->fail($compatibility, $legacy, 'compatibility', 'Compatibility evidence is not classifiable for Adoption or Legacy Reconciliation.');
    }

    /** @param list<LifecycleResolutionEligibility> $eligibility
     *  @return array{accepted:list<LifecycleResolutionEligibility>,missing:list<string>} */
    private function eligibleForMandatoryGaps(TargetCompatibilityResult $compatibility, array $eligibility): array
    {
        $accepted = array_values(array_filter($eligibility, static fn (LifecycleResolutionEligibility $entry): bool => $entry->eligible()));
        $missing = [];
        foreach ($compatibility->requirementGaps() as $gap) {
            if (!$gap->mandatory()) continue;
            $found = false;
            foreach ($accepted as $entry) {
                if ($entry->requirementGapIdentity() === $gap->evidenceIdentity()) { $found = true; break; }
            }
            if (!$found) $missing[] = $gap->requirementKey();
        }
        return ['accepted' => $accepted, 'missing' => $missing];
    }

    private function fail(TargetCompatibilityResult $compatibility, LegacyBoundaryEvidence $legacy, string $source, string $detail): AdoptionBoundaryClassification
    {
        return new AdoptionBoundaryClassification(AdoptionBoundaryClassification::FAIL_CLOSED, $compatibility->identity(), $legacy, [], [['source' => $source, 'identity' => $compatibility->identity(), 'detail' => $detail]]);
    }
}
