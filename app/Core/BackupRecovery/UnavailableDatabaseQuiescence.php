<?php

namespace Copot\Core\BackupRecovery;

final class UnavailableDatabaseQuiescence implements DatabaseQuiescenceCapability
{
    public function isAvailable(): bool { return false; }
    public function acquire(): ?DatabaseQuiescenceLease { return null; }
}
