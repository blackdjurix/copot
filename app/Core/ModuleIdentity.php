<?php

namespace Copot\Core;

final class ModuleIdentity
{
    public function __construct(private string $value)
    {
        self::assertValid($value);
    }

    public static function assertValid(string $value): void
    {
        if (preg_match('/^[a-z0-9_-]+$/', $value) !== 1) {
            throw new \InvalidArgumentException('Technical Module identity is invalid.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function ownershipRoot(): string
    {
        return 'modules/' . $this->value;
    }

    public function equals(string $value): bool
    {
        return $this->value === $value;
    }

    public function toArray(): array
    {
        return ['technical_module_identity' => $this->value];
    }
}
