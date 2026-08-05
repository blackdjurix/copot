<?php

namespace Copot\Core;

final class PackageIdentity
{
    public function __construct(private string $value)
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/', $value) !== 1) {
            throw new \InvalidArgumentException('Package identity is invalid.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(string $value): bool
    {
        return $this->value === $value;
    }

    public function toArray(): array
    {
        return ['package_identity' => $this->value];
    }
}
