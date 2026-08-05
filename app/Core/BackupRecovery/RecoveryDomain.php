<?php

namespace Copot\Core\BackupRecovery;

interface RecoveryDomain
{
    public function definition(): RecoveryDomainDefinition;
}
