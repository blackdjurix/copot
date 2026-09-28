<?php

namespace Copot\Core;

final class DeviceDescriptor
{
    public static function fromRuntime(): string
    {
        return self::fromUserAgent((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }

    public static function fromUserAgent(string $userAgent): string
    {
        $browser = match (true) {
            stripos($userAgent, 'Edg/') !== false => 'Edge',
            stripos($userAgent, 'OPR/') !== false => 'Opera',
            stripos($userAgent, 'Firefox/') !== false => 'Firefox',
            stripos($userAgent, 'Chrome/') !== false => 'Chrome',
            stripos($userAgent, 'Safari/') !== false => 'Safari',
            default => 'Unknown browser',
        };
        $platform = match (true) {
            stripos($userAgent, 'Windows') !== false => 'Windows',
            stripos($userAgent, 'Android') !== false => 'Android',
            stripos($userAgent, 'iPhone') !== false || stripos($userAgent, 'iPad') !== false => 'iOS',
            stripos($userAgent, 'Mac OS') !== false => 'macOS',
            stripos($userAgent, 'Linux') !== false => 'Linux',
            default => 'unknown device',
        };

        return self::sanitize($browser . ' on ' . $platform);
    }

    public static function sanitize(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';

        return substr($value === '' ? 'Unknown device' : $value, 0, 255);
    }
}
