<?php

namespace Copot\Core;

final class PackageMigrationDeclaration
{
    public function __construct(
        private bool $declaresCoreMigrations = false,
        private ?string $declarationIdentity = null
    ) {
        if (!$declaresCoreMigrations && $declarationIdentity !== null) {
            throw new \InvalidArgumentException('A migration declaration identity requires declared migrations.');
        }

        if ($declaresCoreMigrations && ($declarationIdentity === null || trim($declarationIdentity) === '')) {
            throw new \InvalidArgumentException('Declared migrations require a migration declaration identity.');
        }
    }

    public function declaresCoreMigrations(): bool
    {
        return $this->declaresCoreMigrations;
    }

    public function declarationIdentity(): ?string
    {
        return $this->declarationIdentity;
    }

    public function toArray(): array
    {
        return [
            'declares_core_migrations' => $this->declaresCoreMigrations,
            'declaration_identity' => $this->declarationIdentity,
        ];
    }
}
