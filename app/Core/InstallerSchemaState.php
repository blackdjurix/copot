<?php

namespace Copot\Core;

class InstallerSchemaState
{
    private const CORE_TABLES = [
        'users',
        'roles',
        'permissions',
        'user_roles',
        'role_permissions',
        'settings',
        'themes',
        'content',
        'core_migration_history',
    ];

    private const MODULE_TABLES = [
        'modules',
        'module_permissions',
        'taxonomy_types',
        'taxonomy_terms',
        'taxonomy_assignments',
    ];

    public function __construct(private Database $database)
    {
    }

    public function isReady(): bool
    {
        $statement = $this->database->connection()->query(
            "SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
                AND table_type = 'BASE TABLE'"
        );
        $tables = $statement->fetchAll(\PDO::FETCH_COLUMN);

        if (!is_array($tables)) {
            return false;
        }

        $expected = array_merge(
            array_map(fn (string $table): string => $this->database->table($table), self::CORE_TABLES),
            array_map(fn (string $table): string => $this->database->tables()->moduleTable($table), self::MODULE_TABLES)
        );

        return array_diff($expected, $tables) === [];
    }
}
