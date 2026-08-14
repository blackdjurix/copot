<?php

namespace Copot\Core;

final class MigrationSchemaSurface
{
    public function __construct(private array $tables)
    {
        if ($tables === [] || array_values($tables) !== $tables) {
            throw new \InvalidArgumentException('Migration schema surface must declare logical tables.');
        }
        foreach ($tables as $table) {
            if (!is_string($table) || preg_match('/^[a-z][a-z0-9_]*$/', $table) !== 1) {
                throw new \InvalidArgumentException('Migration schema surface contains an invalid table.');
            }
        }
        if (count(array_unique($tables)) !== count($tables)) {
            throw new \InvalidArgumentException('Migration schema surface contains duplicate tables.');
        }
    }

    public function tables(): array { return $this->tables; }
}
