<?php

namespace Copot\Core;

final class ModulePackageTarget
{
    public const MODULE = 'module';
    public const PACKAGE = 'package';

    public function __construct(private string $kind, private string $identity)
    {
        if (!in_array($kind, [self::MODULE, self::PACKAGE], true)) {
            throw new \InvalidArgumentException('Module package target kind is invalid.');
        }

        if ($kind === self::MODULE) {
            ModuleIdentity::assertValid($identity);
        } else {
            new PackageIdentity($identity);
        }
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function identity(): string
    {
        return $this->identity;
    }

    public function equals(string $kind, string $identity): bool
    {
        return $this->kind === $kind && $this->identity === $identity;
    }

    public function toArray(): array
    {
        return [
            'target_kind' => $this->kind,
            'target_identity' => $this->identity,
        ];
    }
}
