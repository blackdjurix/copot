<?php

namespace Copot\Core;

final class LegacyBootstrapTargetFreshnessResult
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
        private string $sourceFingerprint,
        private string $targetFingerprint,
        private string $handoffIdentity,
        private array $diagnostics
    ) {
        if (!in_array($status, [self::MATCHED, self::STALE, self::UNVERIFIED], true)) throw new \InvalidArgumentException('Bootstrap target-freshness status is invalid.');
        foreach ($diagnostics as $diagnostic) if (!is_string($diagnostic) || $diagnostic === '') throw new \InvalidArgumentException('Bootstrap target-freshness diagnostic is invalid.');
        $this->identity = hash('sha256', json_encode([
            'status' => $status, 'installation_identity' => $installationIdentity, 'operation_identity' => $operationIdentity,
            'plan_identity' => $planIdentity, 'source_fingerprint' => $sourceFingerprint, 'target_fingerprint' => $targetFingerprint,
            'handoff_identity' => $handoffIdentity, 'diagnostics' => $diagnostics,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function status(): string { return $this->status; }
    public function matched(): bool { return $this->status === self::MATCHED; }
    public function identity(): string { return $this->identity; }
    public function diagnostics(): array { return $this->diagnostics; }
    public function operationalReady(): bool { return false; }
    public function requiresProtectedBoundaryRevalidation(): bool { return true; }
    public function toArray(): array { return ['verification_identity' => $this->identity, 'status' => $this->status, 'installation_identity' => $this->installationIdentity, 'operation_identity' => $this->operationIdentity, 'plan_identity' => $this->planIdentity, 'source_inventory_fingerprint' => $this->sourceFingerprint, 'target_inventory_fingerprint' => $this->targetFingerprint, 'handoff_identity' => $this->handoffIdentity, 'diagnostics' => $this->diagnostics, 'operational_ready' => false, 'requires_protected_boundary_revalidation' => true]; }
}
