<?php

class TaxonomyTerm
{
    public function __construct(private array $attributes)
    {
    }

    public function id(): int
    {
        return (int) $this->attributes['id'];
    }

    public function taxonomyTypeId(): int
    {
        return (int) $this->attributes['taxonomy_type_id'];
    }

    public function parentId(): ?int
    {
        $parentId = $this->attributes['parent_id'] ?? null;

        return $parentId === null ? null : (int) $parentId;
    }

    public function name(): string
    {
        return (string) $this->attributes['name'];
    }

    public function slug(): string
    {
        return (string) $this->attributes['slug'];
    }

    public function description(): ?string
    {
        $description = $this->attributes['description'] ?? null;

        return $description === null ? null : (string) $description;
    }

    public function sortOrder(): int
    {
        return (int) $this->attributes['sort_order'];
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
