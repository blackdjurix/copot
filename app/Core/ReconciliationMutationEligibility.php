<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\DatabaseQuiescenceLease;
use Copot\Core\BackupRecovery\RecoveryIdentity;

final class ReconciliationMutationEligibility
{
    private bool $released = false;

    public function __construct(
        private RecoveryIdentity $recoveryIdentity,
        private string $operationIdentity,
        private string $planIdentity,
        private string $manifestIdentity,
        private string $targetIdentity,
        private string $confirmationIdentity,
        private DatabaseQuiescenceLease $lease,
        private \Closure $onRelease
    ) {}

    public function recoveryIdentity(): RecoveryIdentity { return $this->recoveryIdentity; }
    public function operationIdentity(): string { return $this->operationIdentity; }
    public function planIdentity(): string { return $this->planIdentity; }
    public function manifestIdentity(): string { return $this->manifestIdentity; }
    public function targetIdentity(): string { return $this->targetIdentity; }
    public function confirmationIdentity(): string { return $this->confirmationIdentity; }
    public function isValid(): bool { return !$this->released && $this->lease->isActive(); }

    public function release(): void
    {
        if ($this->released) {
            return;
        }

        $this->released = true;
        $this->lease->release();
        ($this->onRelease)();
    }

    public function __destruct() { $this->release(); }
}
