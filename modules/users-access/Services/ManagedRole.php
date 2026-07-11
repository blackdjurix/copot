<?php

class ManagedRole
{
    public function __construct(
        private int $id,
        private string $name,
        private string $slug,
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

    public function slug(): string
    {
        return $this->slug;
    }

    public function isSeeded(): bool
    {
        return in_array($this->slug, ['admin', 'user'], true);
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
