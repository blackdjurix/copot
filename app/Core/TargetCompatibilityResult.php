<?php

namespace Copot\Core;

final class TargetCompatibilityResult
{
    public const READY = 'ready';
    public const REQUIREMENT_GAPS = 'requirement_gaps';
    public const UNKNOWN = 'unknown';
    public const AMBIGUOUS = 'ambiguous';
    public const CONTRADICTORY = 'contradictory';
    public const UNSAFE = 'unsafe';
    public const UNSUPPORTED = 'unsupported';

    /** @param list<TargetRequirementEvidence> $satisfied
     *  @param list<TargetRequirementEvidence> $gaps
     *  @param list<TargetCompatibleExtraState> $compatibleExtraState
     *  @param list<array{source:string,identity:string,detail:string}> $blockers */
    public function __construct(
        private string $state,
        private string $targetRequirementIdentity,
        private array $satisfied,
        private array $gaps,
        private array $compatibleExtraState,
        private array $blockers
    ) {
        if (!in_array($state, [self::READY, self::REQUIREMENT_GAPS, self::UNKNOWN, self::AMBIGUOUS, self::CONTRADICTORY, self::UNSAFE, self::UNSUPPORTED], true)) {
            throw new \InvalidArgumentException('Target compatibility result state is unsupported.');
        }
    }

    public function state(): string { return $this->state; }
    public function isAdoptionReady(): bool { return $this->state === self::READY; }
    public function targetRequirementIdentity(): string { return $this->targetRequirementIdentity; }
    /** @return list<TargetRequirementEvidence> */
    public function satisfied(): array { return $this->satisfied; }
    /** @return list<TargetRequirementEvidence> */
    public function requirementGaps(): array { return $this->gaps; }
    /** @return list<TargetCompatibleExtraState> */
    public function compatibleExtraState(): array { return $this->compatibleExtraState; }
    public function blockers(): array { return $this->blockers; }

    public function identity(): string
    {
        return hash('sha256', json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'target_requirement_identity' => $this->targetRequirementIdentity,
            'satisfied' => array_map(static fn (TargetRequirementEvidence $evidence): array => $evidence->toArray(), $this->satisfied),
            'requirement_gaps' => array_map(static fn (TargetRequirementEvidence $evidence): array => $evidence->toArray(), $this->gaps),
            'compatible_extra_state' => array_map(static fn (TargetCompatibleExtraState $state): array => $state->toArray(), $this->compatibleExtraState),
            'blockers' => $this->blockers,
        ];
    }
}
