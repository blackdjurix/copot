<?php

namespace Copot\Core;

use PDO;

final class CoreSchemaMaterializer
{
    /** @return list<string> */
    public function statements(string $schemaPath, DatabaseTableNames $tables): array
    {
        $schema = (new InstallerSchemaRunner($schemaPath))->statements((string) @file_get_contents($schemaPath));
        $materialized = [];

        foreach ($schema as $statement) {
            if (preg_match('/\A(?:CREATE\s+TABLE|INSERT\s+INTO)\s+`?([a-z][a-z0-9_]*)`?/i', $statement, $match) === 1
                && in_array(strtolower($match[1]), DatabaseTableNames::coreTables(), true)) {
                $materialized[] = $this->namespaceStatement($statement, $tables);
            }
        }

        $materialized[] = $this->namespaceStatement($this->generationTableStatement(), $tables);

        return $materialized;
    }

    /** @param list<string> $statements @return list<string> */
    public function namespaceStatements(array $statements, DatabaseTableNames $tables): array
    {
        $core = array_fill_keys(DatabaseTableNames::coreTables(), true);
        $materialized = [];

        foreach ($statements as $statement) {
            if (preg_match('/\A(?:CREATE\s+TABLE|INSERT\s+INTO)\s+`?([a-z][a-z0-9_]*)`?/i', $statement, $match) !== 1) {
                $materialized[] = $statement;
                continue;
            }

            $logical = strtolower($match[1]);
            $materialized[] = isset($core[$logical])
                ? $this->namespaceStatement($statement, $tables)
                : $statement;
        }

        return $materialized;
    }

    public function install(PDO $connection, string $schemaPath, DatabaseTableNames $tables): int
    {
        $statements = $this->statements($schemaPath, $tables);
        foreach ($statements as $statement) {
            $connection->exec($statement);
        }

        return count($statements);
    }

    public function generationIdentity(string $schemaPath): string
    {
        $schema = (new InstallerSchemaRunner($schemaPath))->statements(
            (string) @file_get_contents($schemaPath)
        );
        $core = array_fill_keys(DatabaseTableNames::coreTables(), true);
        $logical = [];

        foreach ($schema as $statement) {
            if (preg_match('/\A(?:CREATE\s+TABLE|INSERT\s+INTO)\s+`?([a-z][a-z0-9_]*)`?/i', $statement, $match) === 1
                && isset($core[strtolower($match[1])])) {
                $logical[] = preg_replace('/\s+/', ' ', trim($statement));
            }
        }

        $logical[] = $this->generationTableStatement();

        return 'core-schema-generation:' . hash('sha256', implode("\n", $logical));
    }

    private function generationTableStatement(): string
    {
        return 'CREATE TABLE core_schema_generation (namespace VARCHAR(64) NOT NULL PRIMARY KEY, schema_generation VARCHAR(191) NOT NULL, compatibility_state VARCHAR(32) NOT NULL, updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    }

    private function namespaceStatement(string $statement, DatabaseTableNames $tables): string
    {
        foreach (DatabaseTableNames::coreTables() as $logical) {
            $physical = $tables->table($logical);
            $statement = preg_replace(
                '/(?<![A-Za-z0-9_])' . preg_quote($logical, '/') . '(?![A-Za-z0-9_])/',
                $physical,
                $statement
            ) ?? $statement;
        }

        $statement = preg_replace_callback(
            '/\b(CONSTRAINT|UNIQUE\s+KEY|INDEX|KEY)\s+([a-z][a-z0-9_]*)/i',
            fn (array $match): string => $match[1] . ' ' . $tables->objectIdentifier($match[2]),
            $statement
        ) ?? $statement;

        return $statement;
    }
}
