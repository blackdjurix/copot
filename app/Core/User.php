<?php

namespace Copot\Core;

class User
{
    public function __construct(
        private array $attributes,
        private PermissionChecker $permissions
    ) {
    }

    public function id(): int
    {
        return (int) $this->attributes['id'];
    }

    public function name(): string
    {
        return (string) $this->attributes['name'];
    }

    public function email(): string
    {
        return (string) $this->attributes['email'];
    }

    public function passwordHash(): string
    {
        return (string) $this->attributes['password_hash'];
    }

    public function status(): string
    {
        return (string) $this->attributes['status'];
    }

    public function isActive(): bool
    {
        return $this->status() === 'active';
    }

    public function hasRole(string $role): bool
    {
        return $this->permissions->userHasRole($this->id(), $role);
    }

    public function can(string $permission): bool
    {
        return $this->permissions->userCan($this->id(), $permission);
    }

    public function toArray(): array
    {
        $attributes = $this->attributes;
        unset($attributes['password_hash']);

        return $attributes;
    }
}
