<?php

namespace Copot\Core;


final class NavigationMenu
{
    public function __construct(private array $attributes)
    {
    }

    public function id(): int { return (int) $this->attributes['id']; }
    public function name(): string { return (string) $this->attributes['name']; }
    public function slug(): string { return (string) $this->attributes['slug']; }
    public function toArray(): array { return $this->attributes; }
}
