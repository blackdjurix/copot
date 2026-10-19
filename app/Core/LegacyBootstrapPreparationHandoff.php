<?php

namespace Copot\Core;

/**
 * Non-operational handoff from pre-injection preparation to the bounded
 * injection step. The reviewed manifest is referenced by identity only; the
 * provisional artifact remains the authority for the captured legacy tree.
 */
final class LegacyBootstrapPreparationHandoff
{
    public function __construct(
        private LegacyBootstrapPreparation $preparation,
        private string $sourceRoot,
        private DeploymentContext $target,
        private LegacyBootstrapReviewedInjectionManifest $reviewedInjectionManifest
    ) {
    }

    public function preparation(): LegacyBootstrapPreparation { return $this->preparation; }
    public function sourceRoot(): string { return $this->sourceRoot; }
    public function target(): DeploymentContext { return $this->target; }
    public function reviewedInjectionManifest(): LegacyBootstrapReviewedInjectionManifest { return $this->reviewedInjectionManifest; }
    public function reviewedInjectionManifestIdentity(): string { return $this->reviewedInjectionManifest->identity(); }
    public function requiresPostInjectionFreshnessReconciliation(): bool { return true; }
    public function operationalReady(): bool { return false; }

    public function toArray(): array
    {
        return [
            'preparation' => $this->preparation->toArray(),
            'execution_source_root' => $this->sourceRoot,
            'target_app_root' => $this->target->appRoot(),
            'target_public_root' => $this->target->publicRoot(),
            'installation_identity' => $this->preparation->installationIdentity(),
            'original_source_inventory_fingerprint' => $this->preparation->sourceInventory()?->fingerprint(),
            'reviewed_injection_manifest_path' => $this->reviewedInjectionManifest->path(),
            'reviewed_injection_manifest_identity' => $this->reviewedInjectionManifest->identity(),
            'post_injection_freshness_reconciliation_required' => true,
            'provisional_source_evidence' => true,
            'operational_ready' => false,
        ];
    }
}
