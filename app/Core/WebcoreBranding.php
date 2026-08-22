<?php

namespace Copot\Core;

final class WebcoreBranding
{
    public const IDENTITY_MODES = ['logo', 'text'];
    public const IDENTITY_COLORS = ['main', 'accent', 'neutral-dark', 'neutral-light'];

    private const DEFAULT_PALETTE = [
        'main' => '#0f2342',
        'accent' => '#1769e0',
        'neutral-dark' => '#16243a',
        'neutral-light' => '#f5f8fc',
    ];

    public static function defaults(): array
    {
        return self::DEFAULT_PALETTE;
    }

    public static function builtInAccentPalette(mixed $accent): array
    {
        $accent = self::isHexColor($accent) ? strtolower($accent) : self::DEFAULT_PALETTE['accent'];
        $foreground = self::contrastRatio($accent, '#ffffff') >= self::contrastRatio($accent, '#17202a')
            ? '#ffffff'
            : '#17202a';

        return [
            'accent' => $accent,
            'accent-soft' => self::blend($accent, '#ffffff', 0.88),
            'accent-strong' => self::blend($accent, '#000000', 0.18),
            'accent-foreground' => $foreground,
        ];
    }

    public static function resolvePalette(array $palette): array
    {
        $resolved = [];

        foreach (self::DEFAULT_PALETTE as $key => $fallback) {
            $value = $palette[$key] ?? $fallback;
            $resolved[$key] = self::isHexColor($value) ? strtolower($value) : $fallback;
        }

        $resolved['foreground-main'] = self::resolveNeutral($resolved['main'], $resolved);
        $resolved['foreground-accent'] = self::resolveNeutral($resolved['accent'], $resolved);

        return $resolved;
    }

    public static function validatePalette(array $palette): void
    {
        foreach (array_keys(self::DEFAULT_PALETTE) as $key) {
            if (!isset($palette[$key]) || !is_string($palette[$key]) || !self::isHexColor($palette[$key])) {
                throw new SettingsException('Each Core palette value must be a valid hexadecimal color.');
            }
        }

        $resolved = self::resolvePalette($palette);

        foreach (['main', 'accent'] as $background) {
            $foreground = $resolved['foreground-' . $background];
            if (self::contrastRatio($resolved[$background], $resolved[$foreground]) < 4.5) {
                throw new SettingsException('The Core palette does not provide usable contrast for required branding relationships.');
            }
        }
    }

    public static function isHexColor(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/D', $value) === 1;
    }

    public static function contrastRatio(string $left, string $right): float
    {
        $leftLuminance = self::relativeLuminance($left);
        $rightLuminance = self::relativeLuminance($right);
        $light = max($leftLuminance, $rightLuminance);
        $dark = min($leftLuminance, $rightLuminance);

        return ($light + 0.05) / ($dark + 0.05);
    }

    private static function resolveNeutral(string $background, array $palette): string
    {
        $darkRatio = self::contrastRatio($background, $palette['neutral-dark']);
        $lightRatio = self::contrastRatio($background, $palette['neutral-light']);

        if ($darkRatio < 4.5 && $lightRatio < 4.5) {
            throw new SettingsException('The Core palette cannot resolve a usable neutral foreground.');
        }

        return $darkRatio >= $lightRatio ? 'neutral-dark' : 'neutral-light';
    }

    private static function relativeLuminance(string $hex): float
    {
        $rgb = [];
        foreach ([1, 3, 5] as $offset) {
            $channel = hexdec(substr($hex, $offset, 2)) / 255;
            $rgb[] = $channel <= 0.03928
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4;
        }

        return (0.2126 * $rgb[0]) + (0.7152 * $rgb[1]) + (0.0722 * $rgb[2]);
    }

    private static function blend(string $color, string $target, float $amount): string
    {
        $channels = [];
        for ($offset = 1; $offset <= 5; $offset += 2) {
            $source = hexdec(substr($color, $offset, 2));
            $destination = hexdec(substr($target, $offset, 2));
            $channels[] = (int) round($source + (($destination - $source) * $amount));
        }

        return sprintf('#%02x%02x%02x', ...$channels);
    }
}
