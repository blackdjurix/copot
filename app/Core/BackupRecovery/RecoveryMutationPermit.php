<?php

namespace Copot\Core\BackupRecovery;

final class RecoveryMutationPermit
{
    private bool $released = false;
    public function __construct(private ?DatabaseQuiescenceLease $lease = null) {}
    public function isValid(): bool { return !$this->released && ($this->lease === null || $this->lease->isActive()); }
    public function release(): void { if (!$this->released) { $this->lease?->release(); $this->released = true; } }
    public function __destruct() { $this->release(); }
}
