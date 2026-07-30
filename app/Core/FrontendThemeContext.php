<?php

namespace Copot\Core;

final class FrontendThemeContext
{
    public function __construct(
        private string $themeId,
        private array $metadata,
        private array $supports
    ) {
    }

    public function themeId(): string
    {
        return $this->themeId;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function supports(): array
    {
        return $this->supports;
    }
}
