<?php

namespace Copot\Core;

final class BundledCoreModuleHealthEvidence
{
    public function __construct(
        private ModuleIdentity|string $module,
        private ?ModuleLifecycleInspection $lifecycle,
        private ?HealthGateMatrix $schemaHealth = null,
        private ?HealthGateMatrix $migrationHealth = null,
        private ?HealthGateMatrix $integrityHealth = null,
        private bool $complete = true,
        private ?string $observedAt = null,
        private ?string $freshness = null
    ) {
        $this->module = $module instanceof ModuleIdentity ? $module : new ModuleIdentity($module);
    }

    public function module(): ModuleIdentity { return $this->module; }
    public function lifecycle(): ?ModuleLifecycleInspection { return $this->lifecycle; }
    public function schemaHealth(): ?HealthGateMatrix { return $this->schemaHealth; }
    public function migrationHealth(): ?HealthGateMatrix { return $this->migrationHealth; }
    public function integrityHealth(): ?HealthGateMatrix { return $this->integrityHealth; }
    public function complete(): bool { return $this->complete; }
    public function observedAt(): ?string { return $this->observedAt; }
    public function freshness(): ?string { return $this->freshness; }
}
