<?php

class ManagedUser
{
    public function __construct(
        private int $id,
        private string $name,
        private string $email,
        private string $status,
        private ?string $lastLoginAt,
        private string $createdAt,
        private string $updatedAt
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function lastLoginAt(): ?string
    {
        return $this->lastLoginAt;
    }

    public function createdAt(): string
    {
        return $this->createdAt;
    }

    public function updatedAt(): string
    {
        return $this->updatedAt;
    }
}
