<?php

namespace Copot\Core;

/** Typed pre-mutation evidence; it grants no mutation or recovery authority. */
final class LegacyBootstrapProtectionCompositionResult
{
    public const VALID = 'VALID';
    public const STALE = 'STALE';
    public const INCOMPLETE = 'INCOMPLETE';
    public const UNVERIFIED = 'UNVERIFIED';
    private string $identity;

    /** @param list<string> $diagnostics */
    public function __construct(
        private string $status,
        private string $installationIdentity,
        private string $operationIdentity,
        private string $planIdentity,
        private string $targetFingerprint,
        private string $handoffIdentity,
        private string $databaseScopeIdentity,
        private ?string $applicationProtectionIdentity,
        private ?string $databaseConsistencyIdentity,
        private ?string $freshnessVerificationIdentity,
        private array $diagnostics
    ) {
        if (!in_array($status, [self::VALID, self::STALE, self::INCOMPLETE, self::UNVERIFIED], true)) {
            throw new \InvalidArgumentException('Bootstrap protection composition status is invalid.');
        }
        foreach ($diagnostics as $diagnostic) {
            if (!is_string($diagnostic) || $diagnostic === '') throw new \InvalidArgumentException('Bootstrap protection diagnostic is invalid.');
        }
        $this->identity = hash('sha256', json_encode([
            'status' => $status, 'installation_identity' => $installationIdentity,
            'operation_identity' => $operationIdentity, 'plan_identity' => $planIdentity,
            'target_inventory_fingerprint' => $targetFingerprint, 'handoff_identity' => $handoffIdentity,
            'database_scope_identity' => $databaseScopeIdentity,
            'application_protection_identity' => $applicationProtectionIdentity,
            'database_consistency_identity' => $databaseConsistencyIdentity,
            'freshness_verification_identity' => $freshnessVerificationIdentity,
            'diagnostics' => $diagnostics,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function status(): string { return $this->status; }
    public function valid(): bool { return $this->status === self::VALID; }
    public function identity(): string { return $this->identity; }
    public function diagnostics(): array { return $this->diagnostics; }
    public function operationalReady(): bool { return false; }
    public function recoveryBound(): bool { return false; }
    public function preMutationEvidence(): bool { return true; }

    public function toArray(): array
    {
        return [
            'composition_identity' => $this->identity, 'status' => $this->status,
            'installation_identity' => $this->installationIdentity, 'operation_identity' => $this->operationIdentity,
            'plan_identity' => $this->planIdentity, 'target_inventory_fingerprint' => $this->targetFingerprint,
            'handoff_identity' => $this->handoffIdentity, 'database_scope_identity' => $this->databaseScopeIdentity,
            'application_protection_identity' => $this->applicationProtectionIdentity,
            'database_consistency_identity' => $this->databaseConsistencyIdentity,
            'freshness_verification_identity' => $this->freshnessVerificationIdentity,
            'diagnostics' => $this->diagnostics, 'pre_mutation_evidence' => true,
            'recovery_bound' => false, 'mutation_authorized' => false, 'operational_ready' => false,
        ];
    }
}
