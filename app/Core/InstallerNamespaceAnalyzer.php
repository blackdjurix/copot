<?php

namespace Copot\Core;

final class InstallerNamespaceAnalyzer
{
    public function analyze(array $objects, string $namespace, InstallerDatabaseOccupancyResult $occupancy): InstallerNamespaceResult
    {
        $tables = new DatabaseTableNames($namespace);
        $owned = array_merge(
            array_map(fn (string $name): string => $tables->table($name), DatabaseTableNames::coreTables()),
            array_map(fn (string $name): string => $tables->moduleTable($name), DatabaseTableNames::moduleTables())
        );
        $collisions = array_values(array_intersect($owned, $objects));
        if ($occupancy->classification() === InstallerDatabaseOccupancy::AMBIGUOUS) {
            return new InstallerNamespaceResult($namespace, InstallerNamespaceAvailability::AMBIGUOUS, $collisions);
        }
        if (in_array($namespace, $occupancy->copotNamespaces(), true)) {
            return new InstallerNamespaceResult($namespace, InstallerNamespaceAvailability::OWNED_BY_COPOT, $collisions);
        }
        if ($collisions === []) return new InstallerNamespaceResult($namespace, InstallerNamespaceAvailability::AVAILABLE);
        return new InstallerNamespaceResult(
            $namespace,
            count($collisions) === count($owned)
                ? InstallerNamespaceAvailability::FULL_COLLISION
                : InstallerNamespaceAvailability::PARTIAL_COLLISION,
            $collisions
        );
    }
}
