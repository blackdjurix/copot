<?php

namespace Copot\Core\BackupRecovery;

final class InstalledLockRecoveryArtifact
{
    /** @param array{version: string, installed_at: string} $marker */
    public function __construct(private RecoveryArtifactRecord $record, private string $bytes, private array $marker)
    {
        if ($record->domainIdentifier() !== 'filesystem.lifecycle.installed-lock' || strlen($bytes) !== $record->byteSize() || hash('sha256', $bytes) !== $record->artifactIdentity() || array_keys($marker) !== ['version', 'installed_at']) {
            throw new LifecycleRecoveryException('Installed-lock recovery artifact identity is invalid.');
        }
    }

    public function record(): RecoveryArtifactRecord { return $this->record; }
    public function bytes(): string { return $this->bytes; }
    /** @return array{version: string, installed_at: string} */
    public function marker(): array { return $this->marker; }
    public function identity(): string { return $this->record->artifactIdentity(); }
}
