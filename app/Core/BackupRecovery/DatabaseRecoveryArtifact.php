<?php

namespace Copot\Core\BackupRecovery;

final class DatabaseRecoveryArtifact
{
    public function __construct(
        private RecoveryArtifactRecord $record,
        private string $bytes,
        private string $databaseIdentity,
        private string $schemaIdentity,
        private string $dataIdentity,
        private string $migrationLedgerIdentity
    ) {
        if ($record->domainIdentifier() !== 'database.webcore'
            || strlen($bytes) !== $record->byteSize()
            || hash('sha256', $bytes) !== $record->artifactIdentity()) {
            throw new DatabaseRecoveryException('Database recovery artifact identity is invalid.');
        }
    }

    public function record(): RecoveryArtifactRecord { return $this->record; }
    public function bytes(): string { return $this->bytes; }
    public function databaseIdentity(): string { return $this->databaseIdentity; }
    public function schemaIdentity(): string { return $this->schemaIdentity; }
    public function dataIdentity(): string { return $this->dataIdentity; }
    public function migrationLedgerIdentity(): string { return $this->migrationLedgerIdentity; }
}
