<?php

namespace Copot\Core;

final class ModuleSchemaMaterializer
{
    /** @return list<string> */
    public function statements(string $schemaPath, DatabaseTableNames $tables): array
    {
        $statements = (new InstallerSchemaRunner($schemaPath))->statements(
            (string) @file_get_contents($schemaPath)
        );
        $module = array_fill_keys(DatabaseTableNames::moduleTables(), true);
        $result = [];

        foreach ($statements as $statement) {
            if (preg_match('/\A(?:CREATE\s+TABLE|INSERT\s+INTO)\s+`?([a-z][a-z0-9_]*)`?/i', $statement, $match) === 1
                && isset($module[strtolower($match[1])])) {
                $result[] = $tables->namespaceStatement($statement);
            }
        }

        return $result;
    }

    /** @param list<string> $statements @return list<string> */
    public function namespaceStatements(array $statements, DatabaseTableNames $tables): array
    {
        if ($tables->namespace() === '') {
            return $statements;
        }

        $module = array_fill_keys(DatabaseTableNames::moduleTables(), true);
        $result = [];
        foreach ($statements as $statement) {
            if (preg_match('/\A(?:CREATE\s+TABLE|INSERT\s+INTO)\s+`?([a-z][a-z0-9_]*)`?/i', $statement, $match) === 1
                && isset($module[strtolower($match[1])])) {
                $result[] = $tables->namespaceStatement($statement);
            } else {
                $result[] = $statement;
            }
        }

        return $result;
    }
}
