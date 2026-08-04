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

            if (str_ends_with($segment, '.')) {
                throw new \InvalidArgumentException('Archive entry path cannot contain a segment ending in a dot.');
            }

            $deviceName = strtolower(explode('.', $segment, 2)[0]);

            if (in_array($deviceName, ['con', 'prn', 'aux', 'nul', 'com1', 'com2', 'com3', 'com4', 'com5', 'com6', 'com7', 'com8', 'com9', 'lpt1', 'lpt2', 'lpt3', 'lpt4', 'lpt5', 'lpt6', 'lpt7', 'lpt8', 'lpt9'], true)) {
                throw new \InvalidArgumentException('Archive entry path contains a Windows device name.');
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
