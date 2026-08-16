<?php

namespace Copot\Core;

use FilesystemIterator;

final class StagingSession
{
    private function __construct(
        private string $namespacePath,
        private string $path
    ) {
    }

    public static function create(string $liveRoot, ?string $configuredRoot = null, ?string $installationId = null): self
    {
        $livePath = realpath($liveRoot);

        if ($livePath === false || !is_dir($livePath)) {
            throw new \RuntimeException('Live Webcore root is unavailable.');
        }

        $namespace = $configuredRoot;
        if ($namespace === null && $installationId !== null) {
            $namespace = InstallationRuntimePaths::forInstallation($installationId)->packageStaging();
        }
        $namespace ??= sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-package-staging';
        $namespace = rtrim($namespace, '/\\');

        if ($namespace === '' || str_contains($namespace, "\0")) {
            throw new \RuntimeException('Package staging root is invalid.');
        }

        if (!file_exists($namespace)) {
            if (!mkdir($namespace, 0700, true) && !is_dir($namespace)) {
                throw new \RuntimeException('Package staging root could not be created.');
            }
        }

        if (is_link($namespace) || !is_dir($namespace) || !is_writable($namespace)) {
            throw new \RuntimeException('Package staging root is not a private writable directory.');
        }

        $namespacePath = realpath($namespace);

        if (
            $namespacePath === false
            || self::isInside($namespacePath, $livePath)
            || self::isInside($livePath, $namespacePath)
        ) {
            throw new \RuntimeException('Package staging root must be structurally separate from the live Webcore tree.');
        }

        @chmod($namespacePath, 0700);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = $namespacePath . DIRECTORY_SEPARATOR . 'session-' . bin2hex(random_bytes(16));

            if (mkdir($candidate, 0700)) {
                return new self($namespacePath, $candidate);
            }
        }

        throw new \RuntimeException('Unique package staging session could not be created.');
    }

    public static function reconcileStale(string $root, int $maxAgeSeconds = 86400): int
    {
        if ($maxAgeSeconds < 1 || !is_dir($root) || is_link($root)) {
            throw new \InvalidArgumentException('Staging reconciliation arguments are invalid.');
        }

        $namespacePath = realpath($root);

        if ($namespacePath === false || !is_dir($namespacePath)) {
            throw new \RuntimeException('Package staging root is unavailable.');
        }

        $removed = 0;
        $cutoff = time() - $maxAgeSeconds;
        $iterator = new FilesystemIterator($namespacePath, FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $entry) {
            if (!str_starts_with($entry->getFilename(), 'session-') || $entry->isLink()) {
                continue;
            }

            $modified = $entry->getMTime();

            if ($modified < $cutoff) {
                self::removeVerifiedTree($entry->getPathname(), $namespacePath);
                $removed++;
            }
        }

        return $removed;
    }

    public function namespacePath(): string { return $this->namespacePath; }
    public function path(): string { return $this->path; }
    public function archivePath(): string { return $this->path . DIRECTORY_SEPARATOR . 'source.zip'; }
    public function archiveTemporaryPath(): string { return $this->path . DIRECTORY_SEPARATOR . '.source.zip.tmp'; }
    public function payloadPath(): string { return $this->path . DIRECTORY_SEPARATOR . 'payload'; }

    public function cleanup(): void
    {
        if (!file_exists($this->path) && !is_link($this->path)) {
            return;
        }

        self::removeVerifiedTree($this->path, $this->namespacePath);
    }

    public static function cleanupExisting(string $path): void
    {
        $path = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        if (basename($path) === '' || !str_starts_with(basename($path), 'session-')) {
            throw new \RuntimeException('Package staging cleanup path is unverified.');
        }
        self::removeVerifiedTree($path, dirname($path));
    }

    private static function removeVerifiedTree(string $path, string $namespacePath): void
    {
        if (is_link($path) || is_file($path)) {
            if (!self::isInside($path, $namespacePath) || !@unlink($path)) {
                throw new \RuntimeException('Package staging cleanup was refused.');
            }

            return;
        }

        if (!is_dir($path) || !self::isInside($path, $namespacePath)) {
            throw new \RuntimeException('Package staging cleanup path is unverified.');
        }

        $iterator = new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $entry) {
            self::removeVerifiedTree($entry->getPathname(), $namespacePath);
        }

        if (!@rmdir($path)) {
            throw new \RuntimeException('Package staging cleanup failed.');
        }
    }

    private static function isInside(string $path, string $root): bool
    {
        $path = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        $root = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $root), DIRECTORY_SEPARATOR);
        $pathKey = strtolower($path);
        $rootKey = strtolower($root);

        return $pathKey === $rootKey || str_starts_with($pathKey, $rootKey . DIRECTORY_SEPARATOR);
    }
}
