<?php

namespace Copot\Core;

final class SystemManagerBrandingService
{
    private const PALETTE_KEYS = ['main', 'accent', 'neutral-dark', 'neutral-light'];

    public function __construct(
        private SettingsService $settings,
        private Database $database
    ) {
    }

    public function effective(): array
    {
        $palette = [];
        foreach (self::PALETTE_KEYS as $key) {
            $palette[$key] = $this->settings->get('branding', $key, WebcoreBranding::defaults()[$key]);
        }

        try {
            $resolvedPalette = WebcoreBranding::resolvePalette($palette);
        } catch (SettingsException) {
            $resolvedPalette = WebcoreBranding::resolvePalette(WebcoreBranding::defaults());
        }

        return [
            'palette' => $resolvedPalette,
            'identity_mode' => $this->settings->get('admin', 'identity_mode', 'text'),
            'identity_color' => $this->settings->get('admin', 'identity_color', 'neutral-light'),
        ];
    }

    public function save(array $values): void
    {
        $palette = [];
        foreach (self::PALETTE_KEYS as $key) {
            $value = $values[$key] ?? null;
            if (!is_string($value)) {
                throw new SettingsException('All Core palette values are required.');
            }
            $palette[$key] = strtolower(trim($value));
            $this->settings->validate('branding', $key, $palette[$key]);
        }

        WebcoreBranding::validatePalette($palette);
        $identityMode = $values['identity_mode'] ?? null;
        $identityColor = $values['identity_color'] ?? null;

        if (!is_string($identityMode) || !in_array($identityMode, WebcoreBranding::IDENTITY_MODES, true)) {
            throw new SettingsException('Admin identity mode is invalid.');
        }
        if (!is_string($identityColor) || !in_array($identityColor, WebcoreBranding::IDENTITY_COLORS, true)) {
            throw new SettingsException('Admin identity color is invalid.');
        }
        $this->settings->validate('admin', 'identity_mode', $identityMode);
        $this->settings->validate('admin', 'identity_color', $identityColor);

        $connection = $this->database->connection();
        $connection->beginTransaction();
        try {
            foreach ($palette as $key => $value) {
                $this->settings->set('branding', $key, $value, 'string');
            }
            $this->settings->set('admin', 'identity_mode', $identityMode, 'string');
            $this->settings->set('admin', 'identity_color', $identityColor, 'string');
            $connection->commit();
        } catch (\Throwable $failure) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $failure;
        }
    }
}
