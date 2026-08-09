<?php

namespace Copot\Core;

use PDO;

final class ModuleMigrationContext
{
    public function __construct(private PDO $connection, private DatabaseTableNames $tables)
    {
    }

    public function connection(): PDO
    {
        return $this->connection;
    }

    public function table(string $logicalName): string
    {
        return $this->tables->moduleTable($logicalName);
    }

    public function coreTable(string $logicalName): string
    {
        return $this->tables->table($logicalName);
    }

    public function sql(string $sql): string
    {
        return $this->tables->namespaceStatement($sql);
    }
}
