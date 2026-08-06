<?php

namespace Copot\Core\BackupRecovery;

interface DatabaseQuiescenceCapability
{
    public function isAvailable(): bool;
    public function acquire(): ?DatabaseQuiescenceLease;
}
