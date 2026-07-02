<?php

declare(strict_types=1);

namespace Copot\Core;

final class SiteBranding
{
    private const DEFAULT_NAME = 'copot';

    private string $name;

    public function __construct(string $name, private string $tagline)
    {
        $this->name = trim($name) === '' ? self::DEFAULT_NAME : $name;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function tagline(): string
    {
        return $this->tagline;
    }

    public function logoUrl(): ?string
    {
        return null;
    }

    public function faviconUrl(): ?string
    {
        return null;
    }
}
