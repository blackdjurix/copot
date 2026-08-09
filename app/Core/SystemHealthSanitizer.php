<?php

namespace Copot\Core;

final class SystemHealthSanitizer
{
    private const WITHHELD = 'Diagnostic detail withheld.';

    private function __construct()
    {
    }

    public static function detail(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '');
        if ($value === '') {
            return null;
        }

        if (self::isUnsafe($value)) {
            return self::WITHHELD;
        }

        return substr($value, 0, 240);
    }

    public static function summary(string $value): string
    {
        $value = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $value) ?? '');
        if ($value === '') {
            throw new \InvalidArgumentException('System Health finding summary cannot be empty.');
        }

        if (self::isUnsafe($value)) {
            return 'Diagnostic summary withheld.';
        }

        return substr($value, 0, 240);
    }

    public static function metadata(?string $value): ?string
    {
        if ($value === null || trim($value) === '' || preg_match('/[\x00-\x1F\x7F]/', $value) === 1 || self::isUnsafe($value)) {
            return null;
        }

        return substr(trim($value), 0, 80);
    }

    private static function isUnsafe(string $value): bool
    {
        return preg_match('/(?:SQLSTATE|\b(?:SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP)\b)/i', $value) === 1
            || preg_match('~[A-Za-z]:[\\\\/]~', $value) === 1
            || preg_match('~(?:^|[\s(])/(?:[A-Za-z0-9._-]+/)+~', $value) === 1
            || preg_match('~(?:^|[\s(])(?:app|storage|modules|vendor|\.copot-lifecycle)[\\\\/]~i', $value) === 1
            || preg_match('/(?:stack trace|lifecycle operation|package internals?)/i', $value) === 1;
    }
}
