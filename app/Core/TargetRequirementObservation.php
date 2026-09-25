<?php

namespace Copot\Core;

/**
 * Bounded, target-bound observation supplied by an authoritative inspector.
 * It contains observations, not a caller-selected satisfied/gap result.
 */
final class TargetRequirementObservation
{
    public const OBSERVED = 'observed';
    public const UNKNOWN = 'unknown';
    public const AMBIGUOUS = 'ambiguous';
    public const CONTRADICTORY = 'contradictory';
    public const UNSAFE = 'unsafe';
    public const UNSUPPORTED = 'unsupported';

    private function __construct(
        private PackageTargetRequirement $requirement,
        private string $state,
        private ?string $observedValue,
        private ?bool $observedPresence,
        private string $provenanceIdentity,
        private string $detail
    ) {
        if (!in_array($state, [self::OBSERVED, self::UNKNOWN, self::AMBIGUOUS, self::CONTRADICTORY, self::UNSAFE, self::UNSUPPORTED], true)) {
            throw new \InvalidArgumentException('Target requirement observation state is unsupported.');
        }
        if ($provenanceIdentity === '' || trim($provenanceIdentity) !== $provenanceIdentity || preg_match('/[\x00-\x1F\x7F]/', $provenanceIdentity) === 1) {
            throw new \InvalidArgumentException('Target requirement observation provenance is invalid.');
        }
        if ($detail === '' || trim($detail) !== $detail || preg_match('/[\x00-\x1F\x7F]/', $detail) === 1) {
            throw new \InvalidArgumentException('Target requirement observation detail is invalid.');
        }
        if ($state === self::OBSERVED && $observedValue === null && $observedPresence === null) {
            throw new \InvalidArgumentException('An observed requirement must contain a value or presence observation.');
        }
        if ($state !== self::OBSERVED && ($observedValue !== null || $observedPresence !== null)) {
            throw new \InvalidArgumentException('Non-comparable requirement evidence cannot contain an observation.');
        }
        if ($observedValue !== null && ($observedValue === '' || trim($observedValue) !== $observedValue || preg_match('/[\x00-\x1F\x7F]/', $observedValue) === 1)) {
            throw new \InvalidArgumentException('Observed requirement value is invalid.');
        }
    }

    public static function value(PackageTargetRequirement $requirement, string $value, string $provenanceIdentity): self
    {
        return new self($requirement, self::OBSERVED, $value, null, $provenanceIdentity, 'Authoritative value observation.');
    }

    public static function presence(PackageTargetRequirement $requirement, bool $present, string $provenanceIdentity): self
    {
        return new self($requirement, self::OBSERVED, null, $present, $provenanceIdentity, 'Authoritative capability presence observation.');
    }

    public static function classified(PackageTargetRequirement $requirement, string $state, string $provenanceIdentity, string $detail): self
    {
        return new self($requirement, $state, null, null, $provenanceIdentity, $detail);
    }

    public function requirement(): PackageTargetRequirement { return $this->requirement; }
    public function requirementKey(): string { return $this->requirement->key(); }
    public function requirementIdentity(): string
    {
        return hash('sha256', json_encode($this->requirement->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
    public function state(): string { return $this->state; }
    public function observedValue(): ?string { return $this->observedValue; }
    public function observedPresence(): ?bool { return $this->observedPresence; }
    public function provenanceIdentity(): string { return $this->provenanceIdentity; }
    public function detail(): string { return $this->detail; }
}
