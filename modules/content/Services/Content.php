<?php

class Content
{
    public function __construct(private array $attributes)
    {
    }

    public function id(): int
    {
        return (int) $this->attributes['id'];
    }

    public function type(): string
    {
        return (string) $this->attributes['type'];
    }

    public function title(): string
    {
        return (string) $this->attributes['title'];
    }

    public function slug(): string
    {
        return (string) $this->attributes['slug'];
    }

    public function excerpt(): ?string
    {
        $excerpt = $this->attributes['excerpt'] ?? null;

        return $excerpt === null ? null : (string) $excerpt;
    }

    public function body(): string
    {
        return (string) $this->attributes['body'];
    }

    public function status(): string
    {
        return (string) $this->attributes['status'];
    }

    public function authorId(): ?int
    {
        $authorId = $this->attributes['author_id'] ?? null;

        return $authorId === null ? null : (int) $authorId;
    }

    public function publishedAt(): ?string
    {
        $publishedAt = $this->attributes['published_at'] ?? null;

        return $publishedAt === null ? null : (string) $publishedAt;
    }

    public function archivedAt(): ?string
    {
        $archivedAt = $this->attributes['archived_at'] ?? null;

        return $archivedAt === null ? null : (string) $archivedAt;
    }

    public function createdAt(): string
    {
        return (string) $this->attributes['created_at'];
    }

    public function updatedAt(): string
    {
        return (string) $this->attributes['updated_at'];
    }

    public function isPublished(): bool
    {
        return $this->status() === 'published';
    }

    public function isArchived(): bool
    {
        return $this->status() === 'archived';
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
