<?php

namespace Copot\Core;

final class ModulePermissionDeclaration
{
    public function __construct(private string $slug, private string $name)
    {
        if (trim($slug) === '' || trim($slug) !== $slug || trim($name) === '' || trim($name) !== $name) {
            throw new \InvalidArgumentException('Module permission declaration is invalid.');
        }
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function toArray(): array
    {
        return ['slug' => $this->slug, 'name' => $this->name];
    }
}
