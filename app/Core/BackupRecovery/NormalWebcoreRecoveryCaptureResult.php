<?php
namespace Copot\Core\BackupRecovery;

final class NormalWebcoreRecoveryCaptureResult
{
    public function __construct(private RecoveryIdentity $recoveryIdentity, private string $manifestIdentity, private RecoveryLifecycleRecord $record) {}
    public function recoveryIdentity(): RecoveryIdentity { return $this->recoveryIdentity; }
    public function manifestIdentity(): string { return $this->manifestIdentity; }
    public function record(): RecoveryLifecycleRecord { return $this->record; }
    public function ready(): bool { return $this->record->state() === RecoveryLifecycleState::READY && $this->record->captureComplete(); }
}
