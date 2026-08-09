<?php

namespace Copot\Core;

/**
 * Transient, verified evidence assembled from existing installation state.
 * This is not persisted ownership metadata and is never an authority by itself.
 */
final class InstallerOwnershipProof
{
    public function __construct(
        private InstallationIdentity $installation,
        private string $namespace,
        private string $schemaGeneration,
        private string $migrationIdentity,
        private bool $schemaHealthy = true,
        private bool $migrationLedgerHealthy = true
    ) {
        new DatabaseTableNames($namespace);
        if (!str_starts_with($schemaGeneration, 'core-schema-generation:') || $schemaGeneration === '') {
            throw new \InvalidArgumentException('Core schema-generation evidence is invalid.');
        }
        if (preg_match('/\A[a-f0-9]{64}\z/', $migrationIdentity) !== 1) {
            throw new \InvalidArgumentException('Core migration-ledger evidence is invalid.');
        }
    }

    public function namespace(): string { return $this->namespace; }
    public function installationId(): string { return $this->installation->value(); }
    public function isVerified(): bool { return $this->schemaHealthy && $this->migrationLedgerHealthy; }
}
