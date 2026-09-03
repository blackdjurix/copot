<?php

namespace Copot\Core;

final class WebcoreColorScheme
{
    public const SETTING_NAMESPACE = 'appearance';
    public const SETTING_KEY = 'main_color';

    public static function defaults(): array
    {
        return self::resolve('#1769e0');
    }

    public static function resolve(mixed $main): array
    {
        $main = self::isHexColor($main) ? strtolower($main) : '#1769e0';
        $soft = self::blend($main, '#ffffff', .88);
        $strong = self::blend($main, '#000000', .18);
        $foreground = self::contrast($main, '#ffffff') >= self::contrast($main, '#000000') ? '#ffffff' : '#000000';

        return [
            'main' => $main,
            'main-soft' => $soft,
            'main-strong' => $strong,
            'main-foreground' => $foreground,
            'neutral-black' => '#000000',
            'neutral-white' => '#ffffff',
            'neutral-dark' => '#111111',
            'neutral-light' => '#ffffff',
        ];
    }

    public static function isHexColor(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/D', $value) === 1;
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

    private static function contrast(string $left, string $right): float
    {
        $luminance = static function (string $hex): float {
            $channels = [];
            foreach ([1, 3, 5] as $offset) {
                $channel = hexdec(substr($hex, $offset, 2)) / 255;
                $channels[] = $channel <= .03928 ? $channel / 12.92 : (($channel + .055) / 1.055) ** 2.4;
            }
            return .2126 * $channels[0] + .7152 * $channels[1] + .0722 * $channels[2];
        };
        $a = $luminance($left); $b = $luminance($right);
        return (max($a, $b) + .05) / (min($a, $b) + .05);
    }
}
