<?php

namespace Copot\Core\BackupRecovery;

final class ApplicationWriteAdmissionLease
{
    private bool $released = false;

    public function __construct(private \Closure $release) {}
    public function isActive(): bool { return !$this->released; }
    public function release(): void
    {
        if ($this->released) { return; }
        ($this->release)();
        $this->released = true;
    }
    public function __destruct() { $this->release(); }
}
