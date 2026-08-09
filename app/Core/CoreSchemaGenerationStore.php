<?php

namespace Copot\Core;

use PDO;

final class CoreSchemaGenerationStore
{
    public function __construct(private DatabaseTableNames $tables)
    {
    }

    public function ensure(PDO $connection): void
    {
        $table = $this->tables->table('core_schema_generation');
        $definition = 'CREATE TABLE IF NOT EXISTS ' . $table . ' (
            namespace VARCHAR(64) NOT NULL PRIMARY KEY,
            schema_generation VARCHAR(191) NOT NULL,
            compatibility_state VARCHAR(32) NOT NULL,
            updated_at DATETIME NOT NULL
        )';
        if (strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME)) !== 'sqlite') {
            $definition .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }
        $connection->exec($definition);
    }

    public function record(PDO $connection, string $generation, string $compatibilityState, ?string $namespace = null): void
    {
        $this->validate($generation, $compatibilityState);
        $this->ensure($connection);
        $namespace ??= $this->tables->namespace();
        $table = $this->tables->table('core_schema_generation');

        if (strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite') {
            $statement = $connection->prepare('INSERT INTO ' . $table . ' (namespace, schema_generation, compatibility_state, updated_at) VALUES (:namespace, :generation, :state, CURRENT_TIMESTAMP) ON CONFLICT(namespace) DO UPDATE SET schema_generation = excluded.schema_generation, compatibility_state = excluded.compatibility_state, updated_at = CURRENT_TIMESTAMP');
        } else {
            $statement = $connection->prepare('INSERT INTO ' . $table . ' (namespace, schema_generation, compatibility_state, updated_at) VALUES (:namespace, :generation, :state, NOW()) ON DUPLICATE KEY UPDATE schema_generation = VALUES(schema_generation), compatibility_state = VALUES(compatibility_state), updated_at = NOW()');
        }

        $statement->execute(['namespace' => $namespace, 'generation' => $generation, 'state' => $compatibilityState]);
    }

    public function read(PDO $connection, ?string $namespace = null): ?array
    {
        $this->ensure($connection);
        $statement = $connection->prepare('SELECT namespace, schema_generation, compatibility_state, updated_at FROM ' . $this->tables->table('core_schema_generation') . ' WHERE namespace = :namespace LIMIT 1');
        $statement->execute(['namespace' => $namespace ?? $this->tables->namespace()]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function validate(string $generation, string $compatibilityState): void
    {
        if (!str_starts_with($generation, 'core-schema-generation:') || strlen($generation) > 191) {
            throw new \InvalidArgumentException('Core schema generation identity is invalid.');
        }
        if (!preg_match('/\A[a-z][a-z0-9_-]{0,31}\z/', $compatibilityState)) {
            throw new \InvalidArgumentException('Core schema compatibility state is invalid.');
        }
    }
}
