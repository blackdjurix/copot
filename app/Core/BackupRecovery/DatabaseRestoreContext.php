<?php

namespace Copot\Core\BackupRecovery;

use PDO;

final class DatabaseRestoreContext
{
    public function __construct(
        private PDO $restoreConnection,
        private PDO $lockConnection,
        private string $databaseIdentity
    ) {
        if ($databaseIdentity === '' || preg_match('/^[A-Za-z0-9_]+$/D', $databaseIdentity) !== 1) {
            throw new DatabaseRecoveryException('Database identity is invalid.');
        }
        if ($restoreConnection === $lockConnection) {
            throw new DatabaseRecoveryException('Database restore requires separate restore and lock connections.');
        }
    }

    public function restoreConnection(): PDO { return $this->restoreConnection; }
    public function lockConnection(): PDO { return $this->lockConnection; }
    public function databaseIdentity(): string { return $this->databaseIdentity; }
}
