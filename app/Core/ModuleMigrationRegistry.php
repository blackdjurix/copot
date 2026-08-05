<?php

namespace Copot\Core;

final class ModuleMigrationRegistry
{
    private array $migrations;
    public function __construct(private ModuleIdentity $owner, ModuleMigrationDeclaration $declaration)
    {
        if ($declaration->owner()->value() !== $owner->value()) throw new \InvalidArgumentException('Module migration registry owner is inconsistent.');
        $this->migrations = $declaration->migrations(); $ids = []; $sequences = []; $previous = 0;
        foreach ($this->migrations as $migration) {
            if (!$migration instanceof ModuleMigrationDescriptor || isset($ids[$migration->id()]) || isset($sequences[$migration->sequence()]) || $migration->sequence() <= $previous) throw new \InvalidArgumentException('Module migration registry is unordered or duplicated.');
            $ids[$migration->id()] = true; $sequences[$migration->sequence()] = true; $previous = $migration->sequence();
        }
    }
    public function owner(): ModuleIdentity { return $this->owner; }
    public function migrations(): array { return $this->migrations; }
}
