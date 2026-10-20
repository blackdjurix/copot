<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\DatabaseTableNames;
use Copot\Core\DatabaseTableOwnershipCatalog;

final class DatabaseScope
{
    /** @var list<string> */
    private array $physicalTables;

    /** @param list<string> $physicalTables */
    public function __construct(
        private string $installationIdentity,
        private string $databaseIdentity,
        private string $namespaceIdentity,
        array $physicalTables
    ) {
        foreach ([$installationIdentity, $databaseIdentity] as $value) {
            if ($value === '' || trim($value) !== $value || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                throw new DatabaseRecoveryException('Database scope identity is invalid.');
            }
        }
        if (trim($namespaceIdentity) !== $namespaceIdentity || preg_match('/[\x00-\x1F\x7F]/', $namespaceIdentity) === 1) {
            throw new DatabaseRecoveryException('Database scope namespace identity is invalid.');
        }
        if (preg_match('/^[A-Za-z0-9_]+$/D', $databaseIdentity) !== 1
            || ($namespaceIdentity !== '' && preg_match('/^[a-z][a-z0-9_]{0,30}$/D', $namespaceIdentity) !== 1)) {
            throw new DatabaseRecoveryException('Database scope database or namespace is invalid.');
        }
        $unique = [];
        foreach ($physicalTables as $table) {
            if (!is_string($table) || preg_match('/^[A-Za-z0-9_]+$/D', $table) !== 1 || isset($unique[$table])) {
                throw new DatabaseRecoveryException('Database scope physical tableset is invalid.');
            }
            $unique[$table] = true;
        }
        if ($unique === []) {
            throw new DatabaseRecoveryException('Database scope physical tableset is empty.');
        }
        ksort($unique, SORT_STRING);
        $this->physicalTables = array_keys($unique);
    }

    /**
     * Build a scope from the declared Webcore baseline plus explicitly verified
     * Module materialization evidence. Runtime table enumeration is not an
     * ownership or materialization authority.
     *
     * @param list<string> $verifiedModuleTables
     * @param array{namespace:string,schema_generation:string}|null $verifiedGenerationEvidence
     */
    public static function fromOwnershipCatalog(string $installationIdentity, string $databaseIdentity, string $namespaceIdentity, DatabaseTableOwnershipCatalog $catalog, array $verifiedModuleTables = [], ?array $verifiedGenerationEvidence = null): self
    {
        $tables = new DatabaseTableNames($namespaceIdentity);
        $physical = [];
        if ($verifiedGenerationEvidence !== null
            && (($verifiedGenerationEvidence['namespace'] ?? null) !== $namespaceIdentity
                || !is_string($verifiedGenerationEvidence['schema_generation'] ?? null)
                || !str_starts_with($verifiedGenerationEvidence['schema_generation'], 'core-schema-generation:'))) {
            throw new DatabaseRecoveryException('Verified schema-generation materialization evidence is invalid.');
        }
        $logicalNames = DatabaseTableOwnershipCatalog::materializedWebcoreLogicalNames($verifiedGenerationEvidence !== null);
        foreach ($verifiedModuleTables as $logicalName) {
            if (!is_string($logicalName) || !in_array($logicalName, DatabaseTableNames::moduleTables(), true)) {
                throw new DatabaseRecoveryException('Verified Module materialization evidence is invalid.');
            }
            if ($catalog->owner($logicalName)->isWebcore()) {
                throw new DatabaseRecoveryException('Verified Module materialization evidence crosses ownership boundary.');
            }
            $logicalNames[] = $logicalName;
        }
        foreach (array_values(array_unique($logicalNames)) as $logicalName) {
            $physical[] = $catalog->physicalName($logicalName, $tables);
        }
        return new self($installationIdentity, $databaseIdentity, $namespaceIdentity, $physical);
    }

    public function installationIdentity(): string { return $this->installationIdentity; }
    public function databaseIdentity(): string { return $this->databaseIdentity; }
    public function namespaceIdentity(): string { return $this->namespaceIdentity; }
    /** @return list<string> */
    public function physicalTables(): array { return $this->physicalTables; }
    public function tablesetFingerprint(): string { return hash('sha256', json_encode($this->physicalTables, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)); }
    public function identity(): string
    {
        return hash('sha256', json_encode([
            'installation_identity' => $this->installationIdentity,
            'database_identity' => $this->databaseIdentity,
            'namespace_identity' => $this->namespaceIdentity,
            'physical_tables' => $this->physicalTables,
            'tableset_fingerprint' => $this->tablesetFingerprint(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** @param list<string> $actualTables */
    public function assertPhysicalTables(array $actualTables): void
    {
        $actual = [];
        foreach ($actualTables as $table) {
            if (!is_string($table) || preg_match('/^[A-Za-z0-9_]+$/D', $table) !== 1 || isset($actual[$table])) {
                throw new DatabaseRecoveryException('Database scope encountered an invalid or duplicate physical table.');
            }
            $actual[$table] = true;
        }
        foreach ($this->physicalTables as $table) {
            if (!isset($actual[$table])) {
                throw new DatabaseRecoveryException('Database scope is missing an owned physical table: ' . $table);
            }
        }
        $prefix = $this->namespaceIdentity === '' ? null : $this->namespaceIdentity . '_';
        foreach (array_keys($actual) as $table) {
            if (in_array($table, $this->physicalTables, true)) { continue; }
            if ($prefix === null || str_starts_with($table, $prefix)) {
                throw new DatabaseRecoveryException('Database scope contains an unknown or overlapping target table: ' . $table);
            }
        }
    }
}
