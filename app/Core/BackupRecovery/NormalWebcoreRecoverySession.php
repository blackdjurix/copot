<?php
namespace Copot\Core\BackupRecovery;

final class NormalWebcoreRecoverySession
{
    private bool $closed = false;
    private ?RecoveryMutationPermit $permit = null;
    public function __construct(private RecoveryLifecycleCoordinator $coordinator, private RecoveryIdentity $identity, private string $manifest, private RecoveryLifecycleRecord $record, private DatabaseQuiescenceLease $lease) {}
    public function recoveryIdentity(): RecoveryIdentity { return $this->identity; }
    public function manifestIdentity(): string { return $this->manifest; }
    public function record(): RecoveryLifecycleRecord { return $this->record; }
    public function ready(): bool { return !$this->closed && $this->record->state() === RecoveryLifecycleState::READY && $this->record->captureComplete(); }
    public function authorizeMutation(): RecoveryMutationPermit
    {
        if (!$this->ready() || $this->permit instanceof RecoveryMutationPermit) throw new RecoveryLifecycleException('Recovery session is not ready for mutation.');
        return $this->permit = $this->coordinator->authorizeMutation($this->identity, $this->manifest, $this->record->manifestIdentity(), $this->lease);
    }
    public function completeMutation(): void
    {
        if (!$this->permit?->isValid()) throw new RecoveryLifecycleException('Recovery mutation permit is invalid.');
        $this->record = $this->coordinator->recordPostReconciliationVerification($this->identity, $this->manifest, $this->record->manifestIdentity());
        $this->permit->release(); $this->close();
    }
    public function failMutation(string $reason): void
    {
        if ($this->closed) return;
        try { $this->coordinator->transition($this->identity, RecoveryLifecycleState::RESTORE_REQUIRED, $reason); } finally { $this->permit?->release(); $this->close(); }
    }
    public function close(): void
    {
        if ($this->closed) return;
        $this->permit?->release();
        if ($this->lease->isActive()) $this->lease->release();
        try { $this->coordinator->leaveMaintenance($this->identity); } finally { $this->closed = true; }
    }
}
