<?php

namespace Copot\Core;

final class ArchiveEntryPath
{
    public static function normalize(string $rawPath): string
    {
        if ($rawPath === '' || str_contains($rawPath, "\0") || preg_match('/[\x00-\x1F\x7F]/', $rawPath) === 1) {
            throw new \InvalidArgumentException('Archive entry path contains an invalid character.');
        }

        if (preg_match('/^[A-Za-z]:/', $rawPath) === 1 || str_starts_with($rawPath, '/') || str_starts_with($rawPath, '\\')) {
            throw new \InvalidArgumentException('Archive entry path must be relative.');
        }

        $path = str_replace('\\', '/', $rawPath);

        if (str_starts_with($path, '/')) {
            throw new \InvalidArgumentException('Archive entry path must be relative.');
        }

        if (preg_match('/[^A-Za-z0-9._\/-]/', $path) === 1) {
            throw new \InvalidArgumentException('Archive entry path contains an unsupported character.');
        }

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new \InvalidArgumentException('Archive entry path cannot escape its root.');
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            throw new \InvalidArgumentException('Archive entry path is empty after normalization.');
        }

        return implode('/', $segments);
    }

    public static function collisionKey(string $normalizedPath): string
    {
        return strtolower($normalizedPath);
    }

    public static function segments(string $normalizedPath): array
    {
        return explode('/', $normalizedPath);
    }
}
