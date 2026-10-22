<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\NormalWebcoreRecoverySession;

/** Prepared, recovery-backed metadata operation; it is not an execution permit. */
final class CommittedLifecycleMetadataReconciliationPreparation
{
    public function __construct(
        private CommittedLifecycleMetadataReconciliationPlan $plan,
        private NormalWebcoreRecoverySession $recoverySession
    ) {}

    public function plan(): CommittedLifecycleMetadataReconciliationPlan { return $this->plan; }
    public function recoverySession(): NormalWebcoreRecoverySession { return $this->recoverySession; }
    public function recoveryIdentity(): \Copot\Core\BackupRecovery\RecoveryIdentity { return $this->recoverySession->recoveryIdentity(); }
    public function recoveryManifestIdentity(): string { return $this->recoverySession->manifestIdentity(); }
    public function ready(): bool { return $this->recoverySession->ready(); }
    public function close(): void { $this->recoverySession->close(); }
}
