<?php

namespace Copot\Core;

class InstallerSchemaState
{
    private const TABLES = [
        'users',
        'roles',
        'permissions',
        'user_roles',
        'role_permissions',
        'settings',
        'modules',
        'module_permissions',
        'themes',
        'content',
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

        return array_diff(self::TABLES, $tables) === [];
    }
}
