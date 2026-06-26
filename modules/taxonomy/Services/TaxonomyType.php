<?php

class TaxonomyType
{
    public function __construct(private array $attributes)
    {
    }

    public function id(): int
    {
        return (int) $this->attributes['id'];
    }

    public function slug(): string
    {
        return (string) $this->attributes['slug'];
    }

    public function name(): string
    {
        return (string) $this->attributes['name'];
    }

    public function description(): ?string
    {
        $description = $this->attributes['description'] ?? null;

        return $description === null ? null : (string) $description;
    }

    public function isHierarchical(): bool
    {
        return (bool) $this->attributes['is_hierarchical'];
    }

    public function createdAt(): string
    {
        return (string) $this->attributes['created_at'];
    }

    public function updatedAt(): string
    {
        return (string) $this->attributes['updated_at'];
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
