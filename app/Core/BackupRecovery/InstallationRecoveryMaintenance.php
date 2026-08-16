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

    /** Adopt the already-held lifecycle lock; this never acquires a mutex. */
    public function adopt(InstallationLock $lock): void
    {
        if ($this->lock instanceof InstallationLock && $this->identity !== null && $this->lock !== $lock) {
            throw new RecoveryLifecycleException('Recovery maintenance is already bound to another lifecycle lock.');
        }
        $this->lock = $lock;
        $this->identity = null;
    }

    /** Drop an adopted lock reference without releasing the lifecycle owner's lock. */
    public function detach(): void
    {
        $this->lock = null;
        $this->identity = null;
    }

    public function enter(RecoveryIdentity $identity): bool
    {
        if ($this->lock instanceof InstallationLock && $this->identity === null) {
            $this->identity = $identity->value();
            return true;
        }

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
