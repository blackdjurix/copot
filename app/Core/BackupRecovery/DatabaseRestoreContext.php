<?php

namespace Copot\Core\BackupRecovery;

use PDO;

final class DatabaseRestoreContext
{
    public function __construct(
        private PDO $restoreConnection,
        private PDO $lockConnection,
        private string $databaseIdentity,
        private ?DatabaseRestoreAttemptContext $attempt = null,
        private ?DatabaseScope $scope = null,
        private ?ScopedDatabaseConsistencyEvidence $consistencyEvidence = null
    ) {
        if ($databaseIdentity === '' || preg_match('/^[A-Za-z0-9_]+$/D', $databaseIdentity) !== 1) {
            throw new DatabaseRecoveryException('Database identity is invalid.');
        }
        if ($restoreConnection === $lockConnection) {
            throw new DatabaseRecoveryException('Database restore requires separate restore and lock connections.');
        }
        if ($scope !== null && $scope->databaseIdentity() !== $databaseIdentity) {
            throw new DatabaseRecoveryException('Database restore scope does not match the database identity.');
        }
        if ($scope !== null && ($consistencyEvidence === null || $consistencyEvidence->scope()->identity() !== $scope->identity())) {
            throw new DatabaseRecoveryException('Scoped database restore requires matching consistency evidence.');
        }
    }

    public function restoreConnection(): PDO { return $this->restoreConnection; }
    public function lockConnection(): PDO { return $this->lockConnection; }
    public function databaseIdentity(): string { return $this->databaseIdentity; }
    public function attempt(): ?DatabaseRestoreAttemptContext { return $this->attempt; }
    public function scope(): ?DatabaseScope { return $this->scope; }
    public function consistencyEvidence(): ?ScopedDatabaseConsistencyEvidence { return $this->consistencyEvidence; }
}
