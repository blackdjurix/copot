<?php

namespace Copot\Core;

final class ProtectedResourcePolicy
{
    public const PUBLIC_ROOT = CanonicalTargetInventoryEntry::PUBLIC_ROOT;
    public const HTACCESS = '.htaccess';
    public const ABSOLUTE_IMMUTABILITY = 'ABSOLUTE_IMMUTABILITY';

    public static function isProtected(string $root, string $path): bool
    {
        return $root === self::PUBLIC_ROOT && $path === self::HTACCESS;
    }

    public static function assertMutable(string $root, string $path): void
    {
        if (self::isProtected($root, $path)) {
            throw new \RuntimeException('PUBLIC_ROOT/.htaccess is deployment-owned and immutable to COPOT operations.');
        }
    }

    public static function assertMutableRelativePath(string $path): void
    {
        if ($path === 'public/' . self::HTACCESS) {
            throw new \RuntimeException('public/.htaccess is deployment-owned and immutable to COPOT operations.');
        }
    }
}
