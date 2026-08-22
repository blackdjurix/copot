<?php

namespace Copot\Core;


final class NavigationItem
{
    public function __construct(private array $attributes)
    {
    }

    public function id(): int { return (int) $this->attributes['id']; }
    public function menuId(): int { return (int) $this->attributes['menu_id']; }
    public function parentId(): ?int { return $this->attributes['parent_id'] === null ? null : (int) $this->attributes['parent_id']; }
    public function label(): string { return (string) $this->attributes['label']; }
    public function targetKind(): string { return (string) $this->attributes['target_kind']; }
    public function targetReference(): ?string { return $this->attributes['target_reference'] === null ? null : (string) $this->attributes['target_reference']; }
    public function customUrl(): ?string { return $this->attributes['custom_url'] === null ? null : (string) $this->attributes['custom_url']; }
    public function sortOrder(): int { return (int) $this->attributes['sort_order']; }
    public function isVisible(): bool { return (bool) $this->attributes['is_visible']; }
    public function toArray(): array { return $this->attributes; }
}
