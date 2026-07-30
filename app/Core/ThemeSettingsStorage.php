<?php

namespace Copot\Core;

final class ThemeSettingsStorage
{
    private const PREFIX = 'theme_';

    public static function namespaceFor(string $themeId): string
    {
        if (preg_match('/^[a-z0-9-]+$/D', $themeId) !== 1) {
            throw new SettingsException('Theme settings identifier is invalid.');
        }

        return self::PREFIX . substr(hash('sha256', $themeId), 0, 32);
    }
}
