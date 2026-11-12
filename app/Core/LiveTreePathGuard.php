<?php

namespace Copot\Core;

final class LiveTreePathGuard
{
    private string $liveRoot;
    private string $publicRoot;

    public function __construct(string|DeploymentContext $liveRoot)
    {
        $context = $liveRoot instanceof DeploymentContext ? $liveRoot : null;
        $appRoot = $context?->appRoot() ?? $liveRoot;
        if (is_link($appRoot) || !is_dir($appRoot)) {
            throw new \RuntimeException('Live Webcore root is invalid.');
        }

        $resolved = realpath($appRoot);

        if ($resolved === false || !is_dir($resolved) || is_link($resolved)) {
            throw new \RuntimeException('Live Webcore root is invalid.');
        }

        $this->liveRoot = rtrim($resolved, '/\\');
        $this->publicRoot = $context instanceof DeploymentContext
            ? $context->publicRoot()
            : $this->liveRoot . DIRECTORY_SEPARATOR . 'public';
    }

    public function liveRoot(): string { return $this->liveRoot; }
    public function publicRoot(): string { return $this->publicRoot; }

    public function destination(string $relativePath): string
    {
        $normalized = ArchiveEntryPath::normalize($relativePath);

        if ($normalized === '') {
            throw new \RuntimeException('Live-tree relative path is empty.');
        }

        [$root, $path] = $this->logicalRoot($normalized);
        $candidate = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);

        $this->assertInsideLexical($candidate, $root);

        return $candidate;
    }

    public function destinationForRoot(string $root, string $relativePath): string
    {
        if (!in_array($root, [CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PUBLIC_ROOT], true)) {
            throw new \RuntimeException('Live-tree logical root is invalid.');
        }
        $normalized = ArchiveEntryPath::normalize($relativePath);
        if ($normalized === '') {
            throw new \RuntimeException('Live-tree relative path is empty.');
        }
        $physicalRoot = $root === CanonicalTargetInventoryEntry::PUBLIC_ROOT ? $this->publicRoot : $this->liveRoot;
        $candidate = $physicalRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        $this->assertInsideLexical($candidate, $physicalRoot);
        return $candidate;
    }

    public function ensureParent(string $relativePath): string
    {
        $normalized = ArchiveEntryPath::normalize($relativePath);
        [$root, $path] = $this->logicalRoot($normalized);
        $segments = ArchiveEntryPath::segments($path);
        array_pop($segments);
        $current = $root;

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

            if ($resolved === false || is_link($current) || !$this->insideResolved($resolved, $root)) {
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
        [$root] = $this->logicalRoot(ArchiveEntryPath::normalize($relativePath));
        if (!file_exists($parent)) {
            if ($mustExist) {
                throw new \RuntimeException('Live-tree destination parent disappeared.');
            }

            return $destination;
        }
        $resolvedParent = realpath($parent);

        if ($resolvedParent === false || is_link($parent) || !$this->insideResolved($resolvedParent, $root)) {
            throw new \RuntimeException('Live-tree destination parent is unsafe.');
        }

        return $destination;
    }

    /** @return array{0:string,1:string} */
    private function logicalRoot(string $normalized): array
    {
        if ($normalized === 'public' || str_starts_with($normalized, 'public/')) {
            return [$this->publicRoot, $normalized === 'public' ? '' : substr($normalized, 7)];
        }
        return [$this->liveRoot, $normalized];
    }

    private function assertInsideLexical(string $path, string $root): void
    {
        $path = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        $root = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $root), DIRECTORY_SEPARATOR);
        $path = strtolower($path);
        $root = strtolower($root);

        if ($path !== $root && !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Live-tree path escaped the Webcore root.');
        }
    }

    private function insideResolved(string $path, string $root): bool
    {
        $path = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        $root = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $root), DIRECTORY_SEPARATOR);
        $path = strtolower($path);
        $root = strtolower($root);
        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }
}
