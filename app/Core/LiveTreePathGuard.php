<?php

namespace Copot\Core;

final class LiveTreePathGuard
{
    private string $liveRoot;

    public function __construct(string $liveRoot)
    {
        if (is_link($liveRoot) || !is_dir($liveRoot)) {
            throw new \RuntimeException('Live Webcore root is invalid.');
        }

        $resolved = realpath($liveRoot);

        if ($resolved === false || !is_dir($resolved) || is_link($resolved)) {
            throw new \RuntimeException('Live Webcore root is invalid.');
        }

        $this->liveRoot = rtrim($resolved, '/\\');
    }

    public function liveRoot(): string { return $this->liveRoot; }

    public function destination(string $relativePath): string
    {
        $normalized = ArchiveEntryPath::normalize($relativePath);

        if ($normalized === '') {
            throw new \RuntimeException('Live-tree relative path is empty.');
        }

        $candidate = $this->liveRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);

        if (!$this->insideLexical($candidate)) {
            throw new \RuntimeException('Live-tree path escaped the Webcore root.');
        }

        return $candidate;
    }

    public function ensureParent(string $relativePath): string
    {
        $normalized = ArchiveEntryPath::normalize($relativePath);
        $segments = ArchiveEntryPath::segments($normalized);
        array_pop($segments);
        $current = $this->liveRoot;

        foreach ($segments as $segment) {
            $current .= DIRECTORY_SEPARATOR . $segment;

            if (is_link($current) || (file_exists($current) && !is_dir($current))) {
                throw new \RuntimeException('Live-tree parent is not a real directory.');
            }

            if (!file_exists($current) && (!mkdir($current, 0755) || !is_dir($current))) {
                throw new \RuntimeException('Live-tree parent could not be created.');
            }

            if (is_link($current) || !is_dir($current)) {
                throw new \RuntimeException('Live-tree parent is not a real directory.');
            }

            $resolved = realpath($current);

            if ($resolved === false || is_link($current) || !$this->insideResolved($resolved)) {
                throw new \RuntimeException('Live-tree parent escaped the Webcore root.');
            }
        }

        return $current;
    }

    public function verifyDestination(string $relativePath, bool $mustExist = false): string
    {
        $destination = $this->destination($relativePath);

        if (is_link($destination) || (file_exists($destination) && !is_file($destination))) {
            throw new \RuntimeException('Live-tree destination is not a regular file.');
        }

        if ($mustExist && !is_file($destination)) {
            throw new \RuntimeException('Live-tree destination disappeared.');
        }

        $parent = dirname($destination);
        if (!file_exists($parent)) {
            if ($mustExist) {
                throw new \RuntimeException('Live-tree destination parent disappeared.');
            }

            return $destination;
        }
        $resolvedParent = realpath($parent);

        if ($resolvedParent === false || is_link($parent) || !$this->insideResolved($resolvedParent)) {
            throw new \RuntimeException('Live-tree destination parent is unsafe.');
        }

        return $destination;
    }

    private function insideLexical(string $path): bool
    {
        $path = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        $root = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $this->liveRoot), DIRECTORY_SEPARATOR);
        $path = strtolower($path);
        $root = strtolower($root);

        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }

    private function insideResolved(string $path): bool
    {
        return $this->insideLexical($path);
    }
}
