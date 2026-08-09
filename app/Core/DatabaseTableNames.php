<?php

namespace Copot\Core;

final class DatabaseTableNames
{
    private const NAMESPACE_PATTERN = '/\A[a-z][a-z0-9_]{0,30}\z/';
    private const IDENTIFIER_PATTERN = '/\A[a-z][a-z0-9_]{0,63}\z/';

    /** @var list<string> */
    private const CORE_TABLES = [
        'users', 'roles', 'permissions', 'user_roles', 'role_permissions',
        'settings', 'themes', 'core_migration_history', 'core_schema_generation',
    ];

    public function __construct(private string $namespace = '')
    {
        if ($namespace !== '' && preg_match(self::NAMESPACE_PATTERN, $namespace) !== 1) {
            throw new \InvalidArgumentException('Database namespace is invalid.');
        }
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    public function table(string $logicalName): string
    {
        if (preg_match(self::IDENTIFIER_PATTERN, $logicalName) !== 1) {
            throw new \InvalidArgumentException('Logical database table name is invalid.');
        }

        if (!in_array($logicalName, self::CORE_TABLES, true) || $this->namespace === '') {
            return $logicalName;
        }

        $physical = $this->namespace . '_' . $logicalName;
        if (strlen($physical) > 64) {
            throw new \InvalidArgumentException('Namespaced database table name is too long.');
        }

        return $physical;
    }

    public function objectIdentifier(string $logicalName): string
    {
        if (preg_match(self::IDENTIFIER_PATTERN, $logicalName) !== 1) {
            throw new \InvalidArgumentException('Database object identifier is invalid.');
        }

        if ($this->namespace === '') {
            return $logicalName;
        }

        $identifier = $this->namespace . '_' . $logicalName;
        if (strlen($identifier) > 64) {
            throw new \InvalidArgumentException('Namespaced database object identifier is too long.');
        }

        return $identifier;
    }

    /** @return list<string> */
    public static function coreTables(): array
    {
        return self::CORE_TABLES;
    }
}
