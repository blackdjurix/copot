<?php

namespace Copot\Core;

final class ModuleLifecycleInspection
{
    private function __construct(private string $status, private ?ModuleLifecycleState $state, private ?array $runtime, private string $reason = '') {}
    public static function fresh(): self { return new self(InstalledStateStatus::FRESH, null, null); }
    public static function legacy(array $runtime): self { return new self(InstalledStateStatus::LEGACY, null, $runtime); }
    public static function committed(ModuleLifecycleState $state, array $runtime): self { return new self(InstalledStateStatus::COMMITTED, $state, $runtime); }
    public static function inconsistent(string $reason, ?ModuleLifecycleState $state = null, ?array $runtime = null): self { return new self(InstalledStateStatus::INCONSISTENT, $state, $runtime, $reason); }
    public static function invalid(string $reason): self { return new self(InstalledStateStatus::INVALID, null, null, $reason); }
    public function status(): string { return $this->status; }
    public function state(): ?ModuleLifecycleState { return $this->state; }
    public function runtime(): ?array { return $this->runtime; }
    public function reason(): string { return $this->reason; }
}
