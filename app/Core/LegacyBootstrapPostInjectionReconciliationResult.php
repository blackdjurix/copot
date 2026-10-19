<?php

namespace Copot\Core;

/** Read-only, non-operational proof that a reviewed injection explains a target transition. */
final class LegacyBootstrapPostInjectionReconciliationResult
{
    public const MATCHED = 'MATCHED';
    public const STALE = 'STALE';
    public const UNVERIFIED = 'UNVERIFIED';

    private string $identity;

    public function __construct(
        private string $status,
        private string $installationIdentity,
        private string $operationIdentity,
        private string $planIdentity,
        private string $originalSourceFingerprint,
        private string $currentInventoryFingerprint,
        private string $manifestIdentity,
        private string $collisionEvidenceIdentity,
        private array $diagnostics,
        private array $collisionEvidence = []
    ) {
        if (!in_array($status, [self::MATCHED, self::STALE, self::UNVERIFIED], true)) throw new \InvalidArgumentException('Post-injection reconciliation status is invalid.');
        $this->identity = hash('sha256', json_encode([
            'status' => $status,
            'installation_identity' => $installationIdentity,
            'operation_identity' => $operationIdentity,
            'plan_identity' => $planIdentity,
            'original_source_inventory_fingerprint' => $originalSourceFingerprint,
            'current_inventory_fingerprint' => $currentInventoryFingerprint,
            'manifest_identity' => $manifestIdentity,
            'collision_evidence_identity' => $collisionEvidenceIdentity,
            'collision_evidence' => $collisionEvidence,
            'diagnostics' => $diagnostics,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function status(): string { return $this->status; }
    public function matched(): bool { return $this->status === self::MATCHED; }
    public function identity(): string { return $this->identity; }
    public function installationIdentity(): string { return $this->installationIdentity; }
    public function operationIdentity(): string { return $this->operationIdentity; }
    public function planIdentity(): string { return $this->planIdentity; }
    public function originalSourceFingerprint(): string { return $this->originalSourceFingerprint; }
    public function currentInventoryFingerprint(): string { return $this->currentInventoryFingerprint; }
    public function manifestIdentity(): string { return $this->manifestIdentity; }
    public function collisionEvidence(): array { return $this->collisionEvidence; }
    public function diagnostics(): array { return $this->diagnostics; }
    public function operationalReady(): bool { return false; }
    public function toArray(): array
    {
        return [
            'reconciliation_identity' => $this->identity,
            'status' => $this->status,
            'installation_identity' => $this->installationIdentity,
            'operation_identity' => $this->operationIdentity,
            'plan_identity' => $this->planIdentity,
            'original_source_inventory_fingerprint' => $this->originalSourceFingerprint,
            'current_inventory_fingerprint' => $this->currentInventoryFingerprint,
            'reviewed_injection_manifest_identity' => $this->manifestIdentity,
            'collision_evidence_identity' => $this->collisionEvidenceIdentity,
            'collision_evidence' => $this->collisionEvidence,
            'diagnostics' => $this->diagnostics,
            'provisional_source_evidence' => true,
            'operational_ready' => false,
            'requires_protected_boundary_revalidation' => true,
        ];
    }
}
