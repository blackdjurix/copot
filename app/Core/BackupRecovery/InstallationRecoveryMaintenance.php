<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\InstallationLock;
use Copot\Core\InstallationMutex;

/** Binds recovery maintenance to the existing lifecycle mutex. */
final class InstallationRecoveryMaintenance implements RecoveryMaintenanceBoundary
{
    private ?InstallationLock $lock = null;
    private ?string $identity = null;

    public function __construct(private InstallationMutex $mutex) {}

    public function enter(RecoveryIdentity $identity): bool
    {
        if ($this->lock instanceof InstallationLock) {
            return $this->identity === $identity->value();
        }

        $lock = $this->mutex->acquire();
        if (!$lock instanceof InstallationLock) {
            return false;
        }

        $this->lock = $lock;
        $this->identity = $identity->value();
        return true;
    }

    public function isActive(RecoveryIdentity $identity): bool
    {
        return $this->lock instanceof InstallationLock && $this->identity === $identity->value();
    }

    public function leave(RecoveryIdentity $identity): bool
    {
        if (!$this->isActive($identity)) {
            return false;
        }

        $lock = $this->lock;
        $this->lock = null;
        $this->identity = null;
        $lock->release();
        return true;
    }
}
