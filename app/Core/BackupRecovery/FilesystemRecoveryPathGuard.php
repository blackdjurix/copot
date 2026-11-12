<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\ArchiveEntryPath;
use Copot\Core\LiveTreePathGuard;
use Copot\Core\ProtectedResourcePolicy;

final class FilesystemRecoveryPathGuard
{
    public function __construct(private LiveTreePathGuard $liveGuard)
    {
        $this->assertDirectory($liveGuard->liveRoot(), 'Live Webcore root');
        if (realpath($liveGuard->publicRoot()) !== false) {
            $this->assertDirectory($liveGuard->publicRoot(), 'Public Webcore root');
        }
    }

    public function liveRoot(): string { return $this->liveGuard->liveRoot(); }

    public function resolve(string $relativePath): string
    {
        $normalized = ArchiveEntryPath::normalize($relativePath);
        try {
            ProtectedResourcePolicy::assertMutableRelativePath($normalized);
        } catch (\Throwable $exception) {
            throw new FilesystemRecoveryException('Deployment-owned filesystem path is immutable.', 0, $exception);
        }
        $candidate = $this->liveGuard->verifyDestination($normalized, false);
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
        try {
            ProtectedResourcePolicy::assertMutableRelativePath($normalized);
        } catch (\Throwable $exception) {
            throw new FilesystemRecoveryException('Deployment-owned filesystem path is immutable.', 0, $exception);
        }
        try {
            return $this->liveGuard->ensureParent($normalized);
        } catch (\Throwable $exception) {
            throw new FilesystemRecoveryException($exception->getMessage(), 0, $exception);
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

}
