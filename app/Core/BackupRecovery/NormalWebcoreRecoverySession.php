<?php
namespace Copot\Core\BackupRecovery;

use Copot\Core\InstallationLock;

final class NormalWebcoreRecoverySession
{
    private bool $closed = false;
    private ?RecoveryMutationPermit $permit = null;
    public function __construct(private RecoveryLifecycleCoordinator $coordinator, private RecoveryIdentity $identity, private string $manifest, private RecoveryLifecycleRecord $record, private ?DatabaseQuiescenceLease $lease = null, private ?ScopedDatabaseConsistencyEvidence $scopedDatabase = null, private mixed $restore = null) {}
    public function recoveryIdentity(): RecoveryIdentity { return $this->identity; }
    public function manifestIdentity(): string { return $this->manifest; }
    public function record(): RecoveryLifecycleRecord { return $this->record; }
    public function ready(): bool { return !$this->closed && $this->record->state() === RecoveryLifecycleState::READY && $this->record->captureComplete(); }
    public function authorizeMutation(): RecoveryMutationPermit
    {
        if (!$this->ready() || $this->permit instanceof RecoveryMutationPermit) throw new RecoveryLifecycleException('Recovery session is not ready for mutation.');
        return $this->permit = $this->coordinator->authorizeMutation($this->identity, $this->manifest, $this->record->manifestIdentity(), $this->lease, $this->scopedDatabase);
    }
    public function completeMutation(): void
    {
        if (!$this->permit?->isValid()) throw new RecoveryLifecycleException('Recovery mutation permit is invalid.');
        $this->record = $this->coordinator->recordPostReconciliationVerification($this->identity, $this->manifest, $this->record->manifestIdentity());
        $this->permit->release(); $this->close();
    }
    public function failMutation(string $reason): RecoveryLifecycleRecord
    {
        if ($this->record->state() !== RecoveryLifecycleState::RESTORE_REQUIRED) {
            if (!$this->record->mutationStarted() || !in_array($this->record->state(), [RecoveryLifecycleState::READY, RecoveryLifecycleState::VERIFICATION_FAILED], true)) {
                throw new RecoveryLifecycleException('Recovery failure requires a crossed mutation boundary.');
            }
            $this->record = $this->coordinator->transition($this->identity, RecoveryLifecycleState::RESTORE_REQUIRED, $reason);
        }
        $this->permit?->release();
        $activeLock = $this->coordinator->activeLifecycleLock();
        if (!is_callable($this->restore)
            || !$activeLock instanceof InstallationLock
            || !$this->coordinator->maintenanceActive($this->identity)) {
            $this->close();
        }
        return $this->record;
    }
    public function restoreMutation(): RecoveryLifecycleRecord
    {
        if (!is_callable($this->restore)) throw new RecoveryLifecycleException('Automatic recovery restore is unavailable.');
        try {
            $record = ($this->restore)();
            if (!$record instanceof RecoveryLifecycleRecord) throw new RecoveryLifecycleException('Automatic recovery restore returned an invalid lifecycle record.');
            $this->record = $record;
            return $record;
        } finally {
            $this->close();
        }
    }
    public function close(): void
    {
        if ($this->closed) return;
        $this->permit?->release();
        if ($this->lease?->isActive()) $this->lease->release();
        try { $this->coordinator->leaveMaintenance($this->identity); } finally { $this->closed = true; }
    }
}
