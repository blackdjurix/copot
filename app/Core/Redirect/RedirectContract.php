<?php

namespace Copot\Core\Redirect;

use InvalidArgumentException;

final class RedirectContract
{
    public const DEFAULT_STATUS = 302;

    public static function source(string $source, string $adminBase = '/admin'): string
    {
        $normalized = self::canonicalPath($source);

        if ($normalized === '/' || str_contains($normalized, '//') || self::containsInvalidCharacters($source) || str_contains($source, '?') || str_contains($source, '#') || str_contains($source, '\\')) {
            throw new InvalidArgumentException('Redirect source must be a non-root path without query, fragment, controls, or backslash.');
        }

        self::assertSafeEncoding($normalized);

        if (strlen($normalized) > 512 || self::isReservedSource($normalized, $adminBase)) {
            throw new InvalidArgumentException('Redirect source is reserved or exceeds the 512-byte limit.');
        }

        foreach (explode('/', trim($normalized, '/')) as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new InvalidArgumentException('Redirect source must not contain dot segments.');
            }
        }

        return $normalized;
    }

    public static function target(string $target): string
    {
        if ($target === '' || self::containsInvalidCharacters($target) || str_contains($target, '\\')) {
            throw new InvalidArgumentException('Redirect target is malformed.');
        }

        self::assertSafeEncoding($target);

        if (str_starts_with($target, '//')) {
            throw new InvalidArgumentException('Protocol-relative redirect targets are not allowed.');
        }

        if (str_starts_with($target, '/')) {
            return $target;
        }

        $parts = parse_url($target);

        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Redirect target must be root-relative or absolute http/https without userinfo.');
        }

        return $target;
    }

    public static function status(mixed $status = self::DEFAULT_STATUS): int
    {
        if (is_string($status) && ctype_digit($status)) {
            $status = (int) $status;
        }

        if (!is_int($status) || !in_array($status, [301, 302], true)) {
            throw new InvalidArgumentException('Redirect status must be 301 or 302.');
        }

        return $status;
    }

    public static function assertNotSelfRedirect(string $source, string $target): void
    {
        if (!str_starts_with($target, '/')) {
            return;
        }

        $targetPath = parse_url($target, PHP_URL_PATH) ?: '/';

        if (self::canonicalPath($source) === self::canonicalPath($targetPath)) {
            throw new InvalidArgumentException('A redirect must not target its own source.');
        }
    }

    public static function canonicalPath(string $path): string
    {
        return self::normalizePath($path);
    }

    public static function isReservedSource(string $source, string $adminBase = '/admin'): bool
    {
        $adminPrefix = self::canonicalPath($adminBase);
        $prefixes = ['/install', $adminPrefix, '/admin-assets', '/theme-assets', '/site-assets', '/content'];
        $exact = ['/login', '/protected'];

        if (in_array($source, $exact, true)) {
            return true;
        }

        foreach ($prefixes as $prefix) {
            if ($source === $prefix || str_starts_with($source, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    private static function normalizePath(string $path): string
    {
        if ($path === '' || str_starts_with($path, '//')) {
            throw new InvalidArgumentException('Redirect path is malformed.');
        }

        $normalized = '/' . trim($path, '/');

        return $normalized === '/' ? '/' : rtrim($normalized, '/');
    }

    private static function containsInvalidCharacters(string $value): bool
    {
        return preg_match('/[\x00-\x1F\x7F]/', $value) === 1;
    }

    private static function assertSafeEncoding(string $value): void
    {
        if (preg_match('/%(?![0-9A-Fa-f]{2})/', $value) === 1 || preg_match('/%(?:2f|2e|5c|00|0[0-9a-f]|1[0-9a-f]|7f)/i', $value) === 1) {
            throw new InvalidArgumentException('Redirect value contains malformed or structural percent encoding.');
        }
    }
}
