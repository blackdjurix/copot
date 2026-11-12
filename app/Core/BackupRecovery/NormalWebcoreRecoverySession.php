<?php
namespace Copot\Core\BackupRecovery;

final class NormalWebcoreRecoverySession
{
    private bool $closed = false;
    private ?RecoveryMutationPermit $permit = null;
    public function __construct(private RecoveryLifecycleCoordinator $coordinator, private RecoveryIdentity $identity, private string $manifest, private RecoveryLifecycleRecord $record, private ?DatabaseQuiescenceLease $lease = null, private ?ScopedDatabaseConsistencyEvidence $scopedDatabase = null, private ?ApplicationWriteProtectionEvidence $filesystemApplication = null, private bool $filesystemOnly = false, private mixed $restore = null, private mixed $verifyRestore = null) {}
    public function recoveryIdentity(): RecoveryIdentity { return $this->identity; }
    public function manifestIdentity(): string { return $this->manifest; }
    public function record(): RecoveryLifecycleRecord { return $this->record; }
    public function ready(): bool { return !$this->closed && $this->record->state() === RecoveryLifecycleState::READY && $this->record->captureComplete(); }
    public function authorizeMutation(): RecoveryMutationPermit
    {
        if (!$this->ready() || $this->permit instanceof RecoveryMutationPermit) throw new RecoveryLifecycleException('Recovery session is not ready for mutation.');
        $permit = $this->coordinator->authorizeMutation($this->identity, $this->manifest, $this->record->manifestIdentity(), $this->lease, $this->scopedDatabase, $this->filesystemApplication, $this->filesystemOnly);
        try {
            // authorizeMutation durably crosses the mutation boundary. Refresh
            // the session snapshot before any later failure classification so
            // failMutation cannot misclassify a post-boundary failure as a
            // pre-mutation failure.
            $this->record = $this->coordinator->read($this->identity);
            return $this->permit = $permit;
        } catch (\Throwable $exception) {
            $permit->release();
            throw $exception;
        }
    }
    public function completeMutation(): void
    {
        $this->settleMutation();
        $this->close();
    }
    public function settleMutation(): void
    {
        if (!$this->permit?->isValid()) throw new RecoveryLifecycleException('Recovery mutation permit is invalid.');
        $this->record = $this->coordinator->recordPostReconciliationVerification($this->identity, $this->manifest, $this->record->manifestIdentity());
        $this->permit->release();
    }
    public function failMutation(string $reason): RecoveryLifecycleRecord
    {
        if ($this->closed) return $this->record;
        if ($this->record->state() !== RecoveryLifecycleState::RESTORE_REQUIRED) {
            if (!$this->record->mutationStarted()) {
                $this->record = $this->coordinator->transition($this->identity, RecoveryLifecycleState::FAILED_BEFORE_MUTATION, $reason);
                $this->permit?->release();
                $this->close();
                return $this->record;
            }
            $this->record = $this->coordinator->transition($this->identity, RecoveryLifecycleState::RESTORE_REQUIRED, $reason);
        }
        $this->permit?->release();
        if (!is_callable($this->restore) || !$this->coordinator->maintenanceActive($this->identity)) {
            $this->close();
        }
        return $this->record;
    }

    public function restoreMutation(): RecoveryLifecycleRecord
    {
        if (!is_callable($this->restore) || !is_callable($this->verifyRestore)) {
            throw new RecoveryLifecycleException('Automatic filesystem recovery restore is unavailable.');
        }
        try {
            $this->record = $this->coordinator->beginRestore($this->identity);
            ($this->restore)();
            $this->record = $this->coordinator->beginVerification($this->identity);
            ($this->verifyRestore)();
            $this->record = $this->coordinator->markRestored($this->identity);
            return $this->record;
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
