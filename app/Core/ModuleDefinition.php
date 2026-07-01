<?php

namespace Copot\Core;

class ModuleDefinition
{
    public function __construct(
        private string $name,
        private string $title,
        private string $version,
        private string $path,
        private ?string $description = null,
        private ?string $author = null,
        private ?string $routes = null,
        private ?string $listeners = null,
        private array $requires = [],
        private array $permissions = []
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function author(): ?string
    {
        return $this->author;
    }

    public function routes(): ?string
    {
        return $this->routes;
    }

    public function listeners(): ?string
    {
        return $this->listeners;
    }

    public function requires(): array
    {
        return $this->requires;
    }

    public function permissions(): array
    {
        return $this->permissions;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'version' => $this->version,
            'path' => $this->path,
            'description' => $this->description,
            'author' => $this->author,
            'routes' => $this->routes,
            'listeners' => $this->listeners,
            'requires' => $this->requires,
            'permissions' => $this->permissions,
        ];
    }
}
