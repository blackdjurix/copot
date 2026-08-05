<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\ArchiveEntryPath;
use Copot\Core\LiveTreePathGuard;

final class FilesystemRecoveryPathGuard
{
    public function __construct(private LiveTreePathGuard $liveGuard)
    {
        $this->assertDirectory($liveGuard->liveRoot(), 'Live Webcore root');
    }

    public function liveRoot(): string { return $this->liveGuard->liveRoot(); }

    public function resolve(string $relativePath): string
    {
        $normalized = ArchiveEntryPath::normalize($relativePath);
        $candidate = $this->liveGuard->destination($normalized);
        $this->assertParents($candidate);
        if (is_link($candidate)) {
            throw new FilesystemRecoveryException('Filesystem recovery path is a link or reparse boundary.');
        }
        if (file_exists($candidate)) {
            if (!is_file($candidate) || @filetype($candidate) !== 'file') {
                throw new FilesystemRecoveryException('Filesystem recovery path is not a regular file.');
            }
            $this->assertStableMetadata($candidate);
        }
        return $candidate;
    }

    public function ensureParent(string $relativePath): string
    {
        $normalized = ArchiveEntryPath::normalize($relativePath);
        $segments = ArchiveEntryPath::segments($normalized);
        array_pop($segments);
        $current = $this->liveRoot();
        foreach ($segments as $segment) {
            $current .= DIRECTORY_SEPARATOR . $segment;
            if (!file_exists($current)) {
                if (!@mkdir($current, 0755) && !is_dir($current)) {
                    throw new FilesystemRecoveryException('Filesystem recovery parent could not be created.');
                }
            }
            $this->assertDirectory($current, 'Filesystem recovery parent');
        }
        return $current;
    }

    private function assertParents(string $candidate): void
    {
        $parent = dirname($candidate);
        if ($parent === $this->liveRoot()) {
            return;
        }
        $relative = substr($parent, strlen($this->liveRoot()) + 1);
        $current = $this->liveRoot();
        foreach (explode(DIRECTORY_SEPARATOR, $relative) as $segment) {
            if ($segment === '') { continue; }
            $current .= DIRECTORY_SEPARATOR . $segment;
            if (!file_exists($current) && !is_link($current)) {
                continue;
            }
            $this->assertDirectory($current, 'Filesystem recovery parent');
            $resolved = realpath($current);
            if ($resolved === false || !$this->inside($resolved)) {
                throw new FilesystemRecoveryException('Filesystem recovery parent escaped the live tree.');
            }
        }
    }

    private function assertDirectory(string $path, string $label): void
    {
        if (is_link($path) || !is_dir($path) || @filetype($path) !== 'dir') {
            throw new FilesystemRecoveryException($label . ' is not a safe directory.');
        }
        $this->assertStableMetadata($path);
    }

    private function assertStableMetadata(string $path): void
    {
        $stat = @stat($path);
        $lstat = @lstat($path);
        if (!is_array($stat) || !is_array($lstat)) {
            throw new FilesystemRecoveryException('Filesystem recovery entry metadata is ambiguous.');
        }
        foreach (['mode', 'dev', 'ino', 'nlink'] as $field) {
            if (($stat[$field] ?? null) !== ($lstat[$field] ?? null)) {
                throw new FilesystemRecoveryException('Filesystem recovery entry has an ambiguous reparse boundary.');
            }
        }
    }

    private function inside(string $path): bool
    {
        $path = strtolower(rtrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR));
        $root = strtolower(rtrim(str_replace('/', DIRECTORY_SEPARATOR, $this->liveRoot()), DIRECTORY_SEPARATOR));
        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }
}
