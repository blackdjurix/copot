<?php

namespace Copot\Core;

final class ModuleMigrationDeclaration
{
    private array $migrations;

    public function __construct(
        private ModuleIdentity $owner,
        private bool $declaresMigrations = false,
        private ?string $declarationIdentity = null,
        array $migrations = []
    ) {
        if (!$declaresMigrations && ($declarationIdentity !== null || $migrations !== [])) {
            throw new \InvalidArgumentException('Module migrations require an active declaration.');
        }

        if ($declaresMigrations && ($declarationIdentity === null || trim($declarationIdentity) === '')) {
            throw new \InvalidArgumentException('Declared Module migrations require a declaration identity.');
        }

        $previousSequence = 0;
        $ids = [];
        $this->migrations = [];

        foreach ($migrations as $migration) {
            if (!$migration instanceof ModuleMigrationDescriptor
                || isset($ids[$migration->id()])
                || $migration->sequence() <= $previousSequence) {
                throw new \InvalidArgumentException('Module migration declarations are invalid or unordered.');
            }

            $ids[$migration->id()] = true;
            $previousSequence = $migration->sequence();
            $this->migrations[] = $migration;
        }

        if ($declaresMigrations && $this->migrations === []) {
            throw new \InvalidArgumentException('Declared Module migrations cannot be empty.');
        }
    }

    public function owner(): ModuleIdentity
    {
        return $this->owner;
    }

    public function declaresMigrations(): bool
    {
        return $this->declaresMigrations;
    }

    public function declarationIdentity(): ?string
    {
        return $this->declarationIdentity;
    }

    public function migrations(): array
    {
        return $this->migrations;
    }

    public function toArray(): array
    {
        return [
            'owner' => $this->owner->value(),
            'declares_migrations' => $this->declaresMigrations,
            'declaration_identity' => $this->declarationIdentity,
            'migrations' => array_map(static fn (ModuleMigrationDescriptor $migration): array => $migration->toArray(), $this->migrations),
        ];
    }
}
