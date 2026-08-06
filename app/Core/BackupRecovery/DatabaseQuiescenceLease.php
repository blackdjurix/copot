<?php

namespace Copot\Core\BackupRecovery;

interface DatabaseQuiescenceLease
{
    public function isActive(): bool;
    public function release(): void;
}
