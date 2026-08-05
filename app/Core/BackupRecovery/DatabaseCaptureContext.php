<?php

namespace Copot\Core\BackupRecovery;

use PDO;

final class DatabaseCaptureContext
{
    public function __construct(
        private PDO $snapshotConnection,
        private PDO $lockConnection,
        private string $databaseIdentity,
        private int $maximumArtifactBytes = 67108864
    ) {
        if ($databaseIdentity === '' || preg_match('/^[A-Za-z0-9_]+$/D', $databaseIdentity) !== 1) {
            throw new DatabaseRecoveryException('Database identity is invalid.');
        }
        if ($maximumArtifactBytes < 1) {
            throw new DatabaseRecoveryException('Database artifact limit is invalid.');
        }
        if ($snapshotConnection === $lockConnection) {
            throw new DatabaseRecoveryException('Database capture requires separate snapshot and lock connections.');
        }
    }

    public function snapshotConnection(): PDO { return $this->snapshotConnection; }
    public function lockConnection(): PDO { return $this->lockConnection; }
    public function databaseIdentity(): string { return $this->databaseIdentity; }
    public function maximumArtifactBytes(): int { return $this->maximumArtifactBytes; }
}
