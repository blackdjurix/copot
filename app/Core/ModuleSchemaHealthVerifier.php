<?php

namespace Copot\Core;

use PDO;

final class ModuleSchemaHealthVerifier
{
    public function verify(PDO $connection, DatabaseTableNames $tables, ?array $logicalTables = null): HealthGateMatrix
    {
        $logicalTables ??= DatabaseTableNames::moduleTables();
        $physical = $this->physicalTables($connection);
        $gates = [];

        foreach ($logicalTables as $logical) {
            $name = $tables->moduleTable($logical);
            $gates[] = in_array($name, $physical, true)
                ? HealthGateResult::pass('module-schema:' . $logical)
                : HealthGateResult::fail('module-schema:' . $logical, 'Module-owned table is unavailable: ' . $name);
        }

        return new HealthGateMatrix($gates);
    }

    /** @return list<string> */
    private function physicalTables(PDO $connection): array
    {
        $driver = strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
        if ($driver === 'sqlite') {
            return array_map(
                'strval',
                $connection->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN)
            );
        }

        return array_map(
            'strval',
            $connection->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN)
        );
    }
}
