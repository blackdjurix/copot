<?php

namespace Copot\Core;

final class Content
{
    public function __construct(private array $attributes)
    {
    }

    public function id(): int { return (int) $this->attributes['id']; }
    public function type(): string { return (string) $this->attributes['type']; }
    public function title(): string { return (string) $this->attributes['title']; }
    public function slug(): string { return (string) $this->attributes['slug']; }
    public function excerpt(): ?string { return isset($this->attributes['excerpt']) ? (string) $this->attributes['excerpt'] : null; }
    public function body(): string { return (string) $this->attributes['body']; }
    public function status(): string { return (string) $this->attributes['status']; }
    public function authorId(): ?int { return isset($this->attributes['author_id']) && $this->attributes['author_id'] !== null ? (int) $this->attributes['author_id'] : null; }
    public function featuredMediaId(): ?int { return isset($this->attributes['featured_media_id']) && $this->attributes['featured_media_id'] !== null ? (int) $this->attributes['featured_media_id'] : null; }
    public function publishedAt(): ?string { return isset($this->attributes['published_at']) ? (string) $this->attributes['published_at'] : null; }
    public function archivedAt(): ?string { return isset($this->attributes['archived_at']) ? (string) $this->attributes['archived_at'] : null; }
    public function createdAt(): string { return (string) $this->attributes['created_at']; }
    public function updatedAt(): string { return (string) $this->attributes['updated_at']; }
    public function isPublished(): bool { return $this->status() === 'published'; }
    public function isArchived(): bool { return $this->status() === 'archived'; }
    public function toArray(): array { return $this->attributes; }

    /** @return array<string,mixed> */
    public function toRenderData(): array
    {
        return [
            'id' => $this->id(),
            'type' => $this->type(),
            'title' => $this->title(),
            'slug' => $this->slug(),
            'excerpt' => $this->excerpt(),
            'body' => $this->body(),
            'author_id' => $this->authorId(),
            'published_at' => $this->publishedAt(),
            'featured_media_id' => $this->featuredMediaId(),
        ];
    }
}
