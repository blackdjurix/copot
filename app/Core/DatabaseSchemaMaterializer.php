<?php

namespace Copot\Core;

final class DatabaseSchemaMaterializer
{
    /** @param list<string> $statements @return list<string> */
    public function namespaceStatements(array $statements, DatabaseTableNames $tables): array
    {
        if ($tables->namespace() === '') {
            return $statements;
        }

        return array_map(
            static fn (string $statement): string => $tables->namespaceStatement($statement),
            $statements
        );
    }
}
