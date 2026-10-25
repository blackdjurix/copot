<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\CommittedSourceAuthorityRecoveryBinding;
use Copot\Core\BackupRecovery\NormalWebcoreRecoverySession;
use Copot\Core\BackupRecovery\RecoveryLifecycleCoordinator;

/**
 * Process-local owner for a committed-source authority preparation.
 *
 * The durable binding is not treated as sufficient ownership.  The holder
 * keeps the recovery session (or the re-adopted scoped owner) alive until the
 * caller has completed the WU6/cleanup boundary and explicitly closes it.
 */
final class CommittedSourceAuthorityPreparation
{
    private bool $closed = false;

    public function __construct(
        private CommittedSourceAuthorityPlan $plan,
        private CommittedSourceAuthorityRecoveryBinding $binding,
        private CommittedSourceAuthorityExecutionAdmission $admission,
        private RecoveryLifecycleCoordinator $recoveryCoordinator,
        private ?NormalWebcoreRecoverySession $session = null,
        private bool $ownsProtection = true
    ) {}

    public function plan(): CommittedSourceAuthorityPlan { return $this->plan; }
    public function binding(): CommittedSourceAuthorityRecoveryBinding { return $this->binding; }
    public function admission(): CommittedSourceAuthorityExecutionAdmission { return $this->admission; }
    public function activeLock(): ?InstallationLock { return $this->recoveryCoordinator->activeLifecycleLock(); }
    public function isClosed(): bool { return $this->closed; }

    /** Release only the process-local owner established by this preparation. */
    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        try {
            if ($this->session instanceof NormalWebcoreRecoverySession) {
                $this->session->close();
            } elseif ($this->ownsProtection) {
                $this->recoveryCoordinator->leaveMaintenance($this->binding->crossReference()->recoveryIdentity());
            }
        } finally {
            $this->closed = true;
        }
    }
}
