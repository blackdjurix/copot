<?php

namespace Copot\Core\BackupRecovery;

interface RecoveryMaintenanceBoundary
{
    public function enter(RecoveryIdentity $identity): bool;
    public function isActive(RecoveryIdentity $identity): bool;
    public function leave(RecoveryIdentity $identity): bool;
}
