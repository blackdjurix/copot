<?php

namespace Copot\Core;

use PDO;

final class DatabaseHealthVerifier
{
    private const TABLES = [
        'users', 'roles', 'permissions', 'user_roles', 'role_permissions', 'settings',
        'modules', 'module_permissions', 'themes', 'content', 'taxonomy_types',
        'taxonomy_terms', 'taxonomy_assignments', 'core_migration_history',
    ];

    public function verify(PDO $connection): HealthGateMatrix
    {
        try {
            $connection->query('SELECT 1')->fetchColumn();
            $tables = $this->tables($connection);
            $missing = array_values(array_diff(self::TABLES, $tables));
            if ($missing !== []) {
                return new HealthGateMatrix([HealthGateResult::fail('database-schema', 'Required Core tables are missing: ' . implode(', ', $missing))]);
            }
            $columns = $this->columns($connection, 'core_migration_history');
            $required = ['migration_id', 'sequence_number', 'target_webcore_version', 'target_schema_identity', 'migration_checksum', 'applied_at'];
            if (array_diff($required, $columns) !== []) {
                return new HealthGateMatrix([HealthGateResult::fail('database-schema', 'Core migration ledger columns are incomplete.')]);
            }
            return new HealthGateMatrix([HealthGateResult::pass('database-connection'), HealthGateResult::pass('database-schema')]);
        } catch (\Throwable $exception) {
            return new HealthGateMatrix([HealthGateResult::fail('database-schema', $exception->getMessage())]);
        }
    }

    private function tables(PDO $connection): array
    {
        try {
            $statement = $connection->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'");
            return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
        } catch (\Throwable) {
            $statement = $connection->query("SELECT name FROM sqlite_master WHERE type = 'table'");
            return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
        }
    }

    private function columns(PDO $connection, string $table): array
    {
        try {
            $statement = $connection->query('DESCRIBE ' . $table);
            return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
        } catch (\Throwable) {
            $statement = $connection->query('PRAGMA table_info(' . $table . ')');
            return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN, 1));
        }
    }
}
