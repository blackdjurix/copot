<?php

declare(strict_types=1);

namespace Copot\Core;

final class SiteBranding
{
    private const DEFAULT_NAME = 'copot';

    private string $name;

    public function __construct(
        string $name,
        private string $tagline,
        private ?string $logoUrl = null,
        private ?string $faviconUrl = null,
        private array $palette = [],
        private string $identityMode = 'text',
        private string $identityColor = 'neutral-light'
    )
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
        return $this->logoUrl;
    }

    public function faviconUrl(): ?string
    {
        return $this->faviconUrl;
    }

    public function palette(): array
    {
        return $this->palette;
    }

    public function identityMode(): string
    {
        return $this->identityMode;
    }

    public function identityColor(): string
    {
        return $this->identityColor;
    }

    public function identityColorValue(): ?string
    {
        return $this->palette[$this->identityColor] ?? null;
    }

    public function cssVariables(): string
    {
        $variables = [];
        foreach (['main', 'main-soft', 'main-strong', 'main-foreground', 'neutral-black', 'neutral-white', 'neutral-dark', 'neutral-light'] as $key) {
            $value = $this->palette[$key] ?? null;
            if (WebcoreBranding::isHexColor($value)) {
                $variables[] = '--site-color-' . $key . ':' . strtolower($value);
            }
        }

        return implode(';', $variables);
    }
}
