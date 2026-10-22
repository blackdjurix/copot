<?php

namespace Copot\Core\BackupRecovery;

/**
 * Recovery capture input for metadata-only lifecycle operations.
 *
 * This intentionally has no package apply plan. Metadata reconciliation is
 * protected by the existing recovery manifest/domains, but it does not invent
 * a V3 apply plan merely to satisfy the normal package capture contract.
 */
final class MetadataRecoveryCaptureRequest
{
    public function __construct(
        private string $operationId,
        private string $installationIdentity,
        private string $namespaceIdentity,
        private string $deploymentIdentity,
        private string $targetPackageIdentity,
        private string $targetReleaseIdentity,
        private string $archiveIdentity,
        private string $recoveryPlanIdentity,
        private string $preLifecycleIdentity,
        private string $migrationLedgerIdentity,
        private string $databaseIdentity,
        private DatabaseScope $databaseScope,
        private ScopedDatabaseConsistencyEvidence $databaseConsistencyEvidence,
        private ApplicationWriteProtectionEvidence $applicationWriteProtectionEvidence
    ) {}

    public function operationId(): string { return $this->operationId; }
    public function installationIdentity(): string { return $this->installationIdentity; }
    public function namespaceIdentity(): string { return $this->namespaceIdentity; }
    public function deploymentIdentity(): string { return $this->deploymentIdentity; }
    public function targetPackageIdentity(): string { return $this->targetPackageIdentity; }
    public function targetReleaseIdentity(): string { return $this->targetReleaseIdentity; }
    public function archiveIdentity(): string { return $this->archiveIdentity; }
    public function recoveryPlanIdentity(): string { return $this->recoveryPlanIdentity; }
    public function preLifecycleIdentity(): string { return $this->preLifecycleIdentity; }
    public function migrationLedgerIdentity(): string { return $this->migrationLedgerIdentity; }
    public function databaseIdentity(): string { return $this->databaseIdentity; }
    public function databaseScope(): DatabaseScope { return $this->databaseScope; }
    public function databaseConsistencyEvidence(): ScopedDatabaseConsistencyEvidence { return $this->databaseConsistencyEvidence; }
    public function applicationWriteProtectionEvidence(): ApplicationWriteProtectionEvidence { return $this->applicationWriteProtectionEvidence; }
}
