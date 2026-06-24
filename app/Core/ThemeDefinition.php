<?php

namespace Copot\Core;

class ThemeDefinition
{
    public function __construct(
        private string $id,
        private string $name,
        private string $version,
        private string $type,
        private string $path,
        private string $layout,
        private ?string $description = null,
        private ?string $author = null,
        private array $supports = [],
        private array $metadata = []
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function layout(): string
    {
        return $this->layout;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function author(): ?string
    {
        return $this->author;
    }

    public function supports(): array
    {
        return $this->supports;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'version' => $this->version,
            'type' => $this->type,
            'path' => $this->path,
            'layout' => $this->layout,
            'description' => $this->description,
            'author' => $this->author,
            'supports' => $this->supports,
            'metadata' => $this->metadata,
        ];
    }
}
