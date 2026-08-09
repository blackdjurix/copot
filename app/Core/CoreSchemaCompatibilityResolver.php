<?php

namespace Copot\Core;

use PDO;

final class CoreSchemaCompatibilityResolver
{
    public function __construct(
        private CoreSchemaGenerationStore $generationStore,
        private CoreMigrationLedger $ledger
    ) {
    }

    public function resolve(PDO $connection): array
    {
        $generation = $this->generationStore->read($connection);

        return [
            'schema_generation' => $generation['schema_generation'] ?? null,
            'compatibility_state' => $generation['compatibility_state'] ?? null,
            'migration_identity' => CoreMigrationStateIdentity::fromRecords($this->ledger->records($connection)),
        ];
    }
}
