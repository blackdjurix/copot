<?php

namespace Copot\Core;

final class ModuleTransitionPlan
{
    public const INSTALL = 'install'; public const PATCH = 'patch'; public const UPDATE = 'update'; public const UPGRADE = 'upgrade'; public const REPAIR = 'repair'; public const REJECTED = 'rejected'; public const LEGACY_BLOCKED = 'legacy_blocked';
    private function __construct(private string $classification, private bool $accepted, private string $reason, private ModuleLifecycleTarget $target, private ?ModuleLifecycleState $currentState, private bool $finalEnabled) {}
    public static function allow(string $classification, ModuleLifecycleTarget $target, ?ModuleLifecycleState $currentState, bool $finalEnabled): self { if (!in_array($classification, [self::INSTALL, self::PATCH, self::UPDATE, self::UPGRADE, self::REPAIR], true)) throw new \InvalidArgumentException('Module transition classification is invalid.'); return new self($classification, true, '', $target, $currentState, $finalEnabled); }
    public static function reject(string $classification, string $reason, ModuleLifecycleTarget $target, ?ModuleLifecycleState $currentState = null): self { return new self($classification, false, $reason, $target, $currentState, $currentState?->enabled() ?? false); }
    public function classification(): string { return $this->classification; } public function accepted(): bool { return $this->accepted; } public function reason(): string { return $this->reason; } public function target(): ModuleLifecycleTarget { return $this->target; } public function currentState(): ?ModuleLifecycleState { return $this->currentState; } public function finalEnabled(): bool { return $this->finalEnabled; }
    public function versionRelation(): string { if (!$this->currentState) return 'none'; return match (PackageVersion::compare($this->target->contract()->packageVersion(), $this->currentState->packageVersion()) <=> 0) { -1 => 'downgrade', 0 => 'same', 1 => 'forward' }; }
    public function releaseIdentityChanged(): bool { return $this->currentState !== null && $this->currentState->releaseIdentity() !== $this->target->contract()->releaseIdentity(); }
    public function packageIntegrityChanged(): bool { return $this->currentState !== null && $this->currentState->packageIntegrityIdentity() !== $this->target->packageIntegrityIdentity(); }
}
