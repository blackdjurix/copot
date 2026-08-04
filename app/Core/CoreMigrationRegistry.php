<?php

namespace Copot\Core;

final class CoreMigrationRegistry
{
    private array $migrations;

    public function __construct(private string $identity, array $migrations)
    {
        if ($identity === '' || trim($identity) !== $identity || preg_match('/[\x00-\x1F\x7F]/', $identity) === 1) {
            throw new \InvalidArgumentException('Core migration registry identity is invalid.');
        }

        $previousSequence = 0;
        $previousTarget = null;
        $ids = [];
        $sequences = [];
        $this->migrations = [];

        foreach ($migrations as $migration) {
            if (!$migration instanceof CoreMigrationDescriptor || isset($ids[$migration->id()]) || isset($sequences[$migration->sequence()])) {
                throw new \InvalidArgumentException('Core migration registry contains a duplicate or invalid descriptor.');
            }

            if ($migration->sequence() <= $previousSequence) {
                throw new \InvalidArgumentException('Core migration registry order is not monotonic.');
            }

            if ($previousTarget !== null && PackageVersion::compare($migration->targetWebcoreVersion(), $previousTarget) < 0) {
                throw new \InvalidArgumentException('Core migration targets are not forward ordered.');
            }

            $ids[$migration->id()] = true;
            $sequences[$migration->sequence()] = true;
            $previousSequence = $migration->sequence();
            $previousTarget = $migration->targetWebcoreVersion();
            $this->migrations[] = $migration;
        }
    }

    public function identity(): string { return $this->identity; }
    public function migrations(): array { return $this->migrations; }

    public function resolve(PackageMigrationDeclaration $declaration): array
    {
        if (!$declaration->declaresCoreMigrations()) {
            return [];
        }

        if ($declaration->declarationIdentity() !== $this->identity) {
            throw new \RuntimeException('Package migration declaration identity is unknown.');
        }

        return $this->migrations;
    }
}
