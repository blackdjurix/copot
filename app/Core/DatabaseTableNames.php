<?php

namespace Copot\Core;

final class DatabaseTableNames
{
    private const NAMESPACE_PATTERN = '/\A[a-z][a-z0-9_]{0,30}\z/';
    private const IDENTIFIER_PATTERN = '/\A[a-z][a-z0-9_]{0,63}\z/';

    /** @var list<string> */
    private const CORE_TABLES = [
        'users', 'roles', 'permissions', 'user_roles', 'role_permissions',
        'settings', 'themes', 'content', 'media', 'media_usages', 'core_migration_history', 'core_schema_generation',
    ];

    /** @var list<string> */
    private const MODULE_TABLES = [
        'modules', 'module_permissions',
        'navigation_menus', 'navigation_items', 'navigation_menu_assignments',
        'taxonomy_types', 'taxonomy_terms', 'taxonomy_assignments',
        'media_variants', 'redirects',
        'forms', 'form_fields', 'form_field_options', 'form_submissions',
        'form_submission_values', 'form_submission_attempts',
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

    public function moduleTable(string $logicalName): string
    {
        if (preg_match(self::IDENTIFIER_PATTERN, $logicalName) !== 1) {
            throw new \InvalidArgumentException('Module database table name is invalid.');
        }

        return $this->namespace === '' ? $logicalName : $this->namespaced($logicalName);
    }

    /**
     * Resolve a known logical table through the established Core/Module naming
     * boundary. Ownership authority is deliberately separate from this
     * physical naming classification (for example, the Webcore module
     * registry tables retain the historical Module namespace path).
     */
    public function resolve(string $logicalName): string
    {
        if (in_array($logicalName, self::CORE_TABLES, true)) return $this->table($logicalName);
        if (in_array($logicalName, self::MODULE_TABLES, true)) return $this->moduleTable($logicalName);
        throw new \InvalidArgumentException('Logical database table name is unknown.');
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

    /** @return list<string> */
    public static function moduleTables(): array
    {
        return self::MODULE_TABLES;
    }

    public function namespaceStatement(string $statement): string
    {
        $literals = [];
        $statement = preg_replace_callback(
            "/'(?:''|\\\\.|[^'])*'|\"(?:\"\"|\\\\.|[^\"])*\"/s",
            static function (array $match) use (&$literals): string {
                $key = '__COPOT_SQL_LITERAL_' . count($literals) . '__';
                $literals[$key] = $match[0];
                return $key;
            },
            $statement
        ) ?? $statement;

        foreach (array_merge(self::CORE_TABLES, self::MODULE_TABLES) as $logical) {
            $physical = in_array($logical, self::CORE_TABLES, true)
                ? $this->table($logical)
                : $this->moduleTable($logical);
            $statement = preg_replace(
                '/(?<![A-Za-z0-9_])' . preg_quote($logical, '/') . '(?![A-Za-z0-9_])/',
                $physical,
                $statement
            ) ?? $statement;
        }

        $statement = preg_replace_callback(
            '/\b(CONSTRAINT|UNIQUE\s+KEY|INDEX|KEY)\s+([a-z][a-z0-9_]*)/i',
            fn (array $match): string => $match[1] . ' ' . $this->objectIdentifier($match[2]),
            $statement
        ) ?? $statement;

        return strtr($statement, $literals);
    }

    private function namespaced(string $logicalName): string
    {
        $physical = $this->namespace . '_' . $logicalName;
        if (strlen($physical) > 64) {
            throw new \InvalidArgumentException('Namespaced database object identifier is too long.');
        }

        return $physical;
    }
}
