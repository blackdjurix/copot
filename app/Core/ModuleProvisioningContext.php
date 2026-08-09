<?php

namespace Copot\Core;

final class ModuleProvisioningContext
{
    public function __construct(private DatabaseTableNames $tables)
    {
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
