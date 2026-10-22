<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\BackupRecovery\ApplicationWriteProtectionEvidence;
use Copot\Core\BackupRecovery\ScopedDatabaseConsistencyEvidence;
use Copot\Core\BackupRecovery\RecoveryIdentity;

/** Immutable proof captured by the canonical recovery/guard path before metadata write. */
final class CommittedLifecycleMetadataReconciliationEvidence
{
    public function __construct(
        private InstallationIdentity $installation,
        private string $committedStateIdentity,
        private string $installedMarkerIdentity,
        private DatabaseScope $databaseScope,
        private RecoveryIdentity $recoveryIdentity,
        private string $recoveryManifestIdentity,
        private ApplicationWriteProtectionEvidence $guardEvidence,
        private ScopedDatabaseConsistencyEvidence $databaseEvidence,
        private int $capturedAt,
        private int $validUntil,
        private ?string $operationIdentity = null,
        private ?string $recoveryPlanIdentity = null
    ) {
        foreach ([$committedStateIdentity, $installedMarkerIdentity, $recoveryManifestIdentity] as $identity) {
            if (preg_match('/^[a-f0-9]{64}$/D', strtolower($identity)) !== 1) {
                throw new \InvalidArgumentException('Metadata reconciliation evidence identity is invalid.');
            }
        }
        if ($capturedAt < 1 || $validUntil < $capturedAt) {
            throw new \InvalidArgumentException('Metadata reconciliation evidence validity is invalid.');
        }
        foreach ([$operationIdentity, $recoveryPlanIdentity] as $identity) {
            if ($identity !== null && $identity === '') {
                throw new \InvalidArgumentException('Metadata reconciliation preparation identity is invalid.');
            }
        }
        if ($guardEvidence->scope()->identity() !== $databaseEvidence->scope()->identity()
            || !$guardEvidence->operationIdentity()->equals($recoveryIdentity)
            || !$databaseEvidence->operationIdentity()->equals($recoveryIdentity)
            || $guardEvidence->scope()->identity() !== $databaseScope->identity()
            || $guardEvidence->capturedAt() !== $databaseEvidence->capturedAt()
            || $guardEvidence->validUntil() !== $databaseEvidence->validUntil()) {
            throw new \InvalidArgumentException('Metadata reconciliation protection evidence is not operation-bound.');
        }
    }

    public function installation(): InstallationIdentity { return $this->installation; }
    public function committedStateIdentity(): string { return $this->committedStateIdentity; }
    public function installedMarkerIdentity(): string { return $this->installedMarkerIdentity; }
    public function databaseScope(): DatabaseScope { return $this->databaseScope; }
    public function recoveryIdentity(): RecoveryIdentity { return $this->recoveryIdentity; }
    public function recoveryManifestIdentity(): string { return $this->recoveryManifestIdentity; }
    public function guardEvidence(): ApplicationWriteProtectionEvidence { return $this->guardEvidence; }
    public function databaseEvidence(): ScopedDatabaseConsistencyEvidence { return $this->databaseEvidence; }
    public function guardEvidenceIdentity(): string { return $this->guardEvidence->identity(); }
    public function databaseEvidenceIdentity(): string { return $this->databaseEvidence->identity(); }
    public function operationIdentity(): ?string { return $this->operationIdentity; }
    public function recoveryPlanIdentity(): ?string { return $this->recoveryPlanIdentity; }
    public function isValidAt(?int $at = null): bool
    {
        $at ??= time();
        return $at >= $this->capturedAt && $at <= $this->validUntil;
    }
    public function identity(): string
    {
        return hash('sha256', json_encode([
            'installation' => $this->installation->value(),
            'committed_state' => $this->committedStateIdentity,
            'installed_marker' => $this->installedMarkerIdentity,
            'database_scope' => $this->databaseScope->identity(),
            'recovery' => $this->recoveryIdentity->value(),
            'recovery_manifest' => $this->recoveryManifestIdentity,
            'guard' => $this->guardEvidence->identity(),
            'database' => $this->databaseEvidence->identity(),
            'captured_at' => $this->capturedAt,
            'valid_until' => $this->validUntil,
            'operation_identity' => $this->operationIdentity,
            'recovery_plan_identity' => $this->recoveryPlanIdentity,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
