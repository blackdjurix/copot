<?php

namespace Copot\Core;

final class AdoptionBoundaryClassification
{
    public const EXACT_MATCH_ADOPTION = 'exact_match_adoption';
    public const GENERALIZED_ADOPTION_COMPATIBLE = 'generalized_adoption_compatible';
    public const GENERALIZED_ADOPTION_RESOLVABLE_GAPS = 'generalized_adoption_resolvable_gaps';
    public const LEGACY_RECONCILIATION_REQUIRED = 'legacy_reconciliation_required';
    public const FAIL_CLOSED = 'fail_closed';

    /** @param list<LifecycleResolutionEligibility> $resolutionEligibility
     *  @param list<array{source:string,identity:string,detail:string}> $blockers */
    public function __construct(
        private string $state,
        private string $compatibilityIdentity,
        private LegacyBoundaryEvidence $legacyEvidence,
        private array $resolutionEligibility,
        private array $blockers
    ) {
        if (!in_array($state, [self::EXACT_MATCH_ADOPTION, self::GENERALIZED_ADOPTION_COMPATIBLE, self::GENERALIZED_ADOPTION_RESOLVABLE_GAPS, self::LEGACY_RECONCILIATION_REQUIRED, self::FAIL_CLOSED], true)) {
            throw new \InvalidArgumentException('Adoption boundary classification is unsupported.');
        }
    }

    public function state(): string { return $this->state; }
    public function compatibilityIdentity(): string { return $this->compatibilityIdentity; }
    public function legacyEvidence(): LegacyBoundaryEvidence { return $this->legacyEvidence; }
    /** @return list<LifecycleResolutionEligibility> */
    public function resolutionEligibility(): array { return $this->resolutionEligibility; }
    public function blockers(): array { return $this->blockers; }
    public function identity(): string { return hash('sha256', json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)); }
    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'compatibility_identity' => $this->compatibilityIdentity,
            'legacy_evidence' => $this->legacyEvidence->toArray(),
            'resolution_eligibility' => array_map(static fn (LifecycleResolutionEligibility $eligibility): array => $eligibility->toArray(), $this->resolutionEligibility),
            'blockers' => $this->blockers,
        ];
    }
}
