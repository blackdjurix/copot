<?php

namespace Copot\Core;

final class WebcoreLifecycleHealthEvidence
{
    public function __construct(
        private ?InstalledStateInspection $installedState,
        private ?CommittedLifecycleState $committedState,
        private ?HealthGateMatrix $databaseHealth = null,
        private ?HealthGateMatrix $migrationHealth = null,
        private ?HealthGateMatrix $runtimeHealth = null,
        private ?HealthGateMatrix $integrityHealth = null,
        private ?LifecycleOperationRecord $operation = null,
        private bool $complete = true,
        private ?string $observedAt = null,
        private ?string $freshness = null
    ) {
    }

    public function installedState(): ?InstalledStateInspection { return $this->installedState; }
    public function committedState(): ?CommittedLifecycleState { return $this->committedState; }
    public function databaseHealth(): ?HealthGateMatrix { return $this->databaseHealth; }
    public function migrationHealth(): ?HealthGateMatrix { return $this->migrationHealth; }
    public function runtimeHealth(): ?HealthGateMatrix { return $this->runtimeHealth; }
    public function integrityHealth(): ?HealthGateMatrix { return $this->integrityHealth; }
    public function operation(): ?LifecycleOperationRecord { return $this->operation; }
    public function complete(): bool { return $this->complete; }
    public function observedAt(): ?string { return $this->observedAt; }
    public function freshness(): ?string { return $this->freshness; }
}
