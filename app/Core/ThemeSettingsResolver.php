<?php

namespace Copot\Core;

final class ThemeSettingsResolver
{
    public function __construct(private ThemeRepository $themes, private ThemeSettingsService $settings)
    {
    }

    public function resolve(): array
    {
        $theme = $this->themes->activeFrontend();
        if (!is_array($theme) || !is_string($theme['theme_id'] ?? null)) return [];
        try {
            $metadata = json_decode((string) ($theme['metadata'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
            return is_array($metadata) ? $this->settings->resolveMetadata($theme['theme_id'], $metadata) : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
