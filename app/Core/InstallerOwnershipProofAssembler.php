<?php

namespace Copot\Core;

use PDO;

/**
 * Assembles transient ownership proofs from already-authoritative lifecycle
 * evidence. It writes no ownership metadata and never creates an identity.
 */
final class InstallerOwnershipProofAssembler
{
    public function __construct(
        private string $storagePath,
        private CoreMigrationRegistry $registry
    ) {
    }

    /** @param list<string> $objects @return list<InstallerOwnershipProof> */
    public function assemble(PDO $connection, array $objects): array
    {
        $identity = (new InstallationIdentityStore($this->storagePath))->read();
        $committed = (new CommittedLifecycleStateStore($this->storagePath))->read();
        if (!$identity instanceof InstallationIdentity || !$committed instanceof CommittedLifecycleState) {
            return [];
        }

        $proofs = [];
        foreach ((new InstallerDatabaseOccupancyClassifier())->candidateNamespaces($objects) as $namespace) {
            $tables = new DatabaseTableNames($namespace);
            $expected = array_merge(
                array_map(fn (string $name): string => $tables->table($name), DatabaseTableNames::coreTables()),
                array_map(fn (string $name): string => $tables->moduleTable($name), DatabaseTableNames::moduleTables())
            );
            if (array_diff($expected, $objects) !== []) {
                continue;
            }

            try {
                if (!(new DatabaseHealthVerifier($tables))->verify($connection)->passed()) {
                    continue;
                }
                $generation = (new CoreSchemaGenerationStore($tables))->read($connection, $namespace);
                if (!is_array($generation) || ($generation['namespace'] ?? null) !== $namespace || !is_string($generation['schema_generation'] ?? null)) {
                    continue;
                }
                $ledger = new CoreMigrationLedger($tables);
                $migrationHealth = new CoreMigrationHealthVerifier($ledger);
                if (!$migrationHealth->verify($connection, $this->registry, $committed->migrationStateIdentity())->passed()) {
                    continue;
                }
                $migrationIdentity = $migrationHealth->identity($connection);
                if ($migrationIdentity !== $committed->migrationStateIdentity()) {
                    continue;
                }
                $proofs[] = new InstallerOwnershipProof(
                    $identity,
                    $namespace,
                    $generation['schema_generation'],
                    $migrationIdentity,
                    true,
                    true
                );
            } catch (\Throwable) {
                // Missing, malformed, or contradictory evidence is not proof.
            }
        }

        return $proofs;
    }
}
