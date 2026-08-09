<?php

namespace Copot\Core;

final class InstallerDatabaseOccupancyResult
{
    public function __construct(
        private string $classification,
        private array $objects,
        private array $copotNamespaces,
        private array $warnings = []
    ) {
    }

    public function classification(): string { return $this->classification; }
    public function objects(): array { return $this->objects; }
    public function copotNamespaces(): array { return $this->copotNamespaces; }
    public function warnings(): array { return $this->warnings; }
    public function isEmpty(): bool { return $this->classification === InstallerDatabaseOccupancy::EMPTY; }
}
