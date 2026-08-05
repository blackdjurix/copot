<?php

namespace Copot\Core\BackupRecovery;

final class RecoveryRootResolver
{
    /** @var array<int, string> */
    private array $excludedRoots;

    /**
     * @param array<int, string> $excludedRoots
     */
    public function __construct(private string $projectRoot, array $excludedRoots = [])
    {
        $this->excludedRoots = $excludedRoots;
    }

    public function resolve(): RecoveryStorageRoot
    {
        $projectRoot = $this->canonicalDirectory($this->projectRoot, 'Project root');
        $parent = dirname($projectRoot);
        $canonicalParent = $this->canonicalDirectory($parent, 'Project parent');
        $projectIdentity = hash('sha256', self::identityPath($projectRoot));
        $recoveryBase = $canonicalParent . DIRECTORY_SEPARATOR . '.copot-recovery';

        if (file_exists($recoveryBase) && is_link($recoveryBase)) {
            throw new RecoveryStorageException('Recovery root contains a symlink boundary.');
        }

        $root = $recoveryBase . DIRECTORY_SEPARATOR . $projectIdentity;
        self::assertOutside($root, $projectRoot, 'Recovery root overlaps the live project root.');

        foreach ($this->excludedRoots as $excludedRoot) {
            if ($excludedRoot === '') {
                continue;
            }
            $canonicalExcluded = $this->canonicalDirectory($excludedRoot, 'Excluded root');
            self::assertOutside($root, $canonicalExcluded, 'Recovery root overlaps an excluded root.');
        }

        if (!is_writable($canonicalParent)) {
            throw new RecoveryStorageException('Project parent is not writable.');
        }

        return new RecoveryStorageRoot($projectRoot, $root, $projectIdentity);
    }

    public static function identityPath(string $canonicalPath): string
    {
        $path = str_replace('\\', '/', $canonicalPath);
        if (DIRECTORY_SEPARATOR === '\\') {
            $path = strtolower($path);
        }
        return $path;
    }

    private function canonicalDirectory(string $path, string $label): string
    {
        if ($path === '' || preg_match('#(?:^|[\\\\/])\.\.(?:[\\\\/]|$)#', $path) === 1) {
            throw new RecoveryStorageException($label . ' contains traversal.');
        }
        $this->assertPathComponents($path, $label);
        $resolved = realpath($path);
        if ($resolved === false || is_link($resolved) || !is_dir($resolved)) {
            throw new RecoveryStorageException($label . ' is not canonically resolvable.');
        }
        return $resolved;
    }

    private function assertPathComponents(string $path, string $label): void
    {
        $normalized = str_replace('\\', '/', $path);
        if (preg_match('#^([A-Za-z]:)(/.*)?$#', $normalized, $matches) === 1) {
            $current = $matches[1] . '/';
            $components = array_values(array_filter(explode('/', ltrim($matches[2] ?? '', '/')), static fn (string $part): bool => $part !== ''));
        } elseif (str_starts_with($normalized, '/')) {
            $current = '/';
            $components = array_values(array_filter(explode('/', ltrim($normalized, '/')), static fn (string $part): bool => $part !== ''));
        } else {
            throw new RecoveryStorageException($label . ' is not absolute.');
        }

        foreach ($components as $component) {
            $current = rtrim($current, '/\\') . DIRECTORY_SEPARATOR . $component;
            if (file_exists($current) || is_link($current)) {
                $this->assertDirectoryObservation($current, $label);
            }
        }
    }

    private function assertDirectoryObservation(string $path, string $label): void
    {
        if (is_link($path) || !is_dir($path) || @filetype($path) !== 'dir') {
            throw new RecoveryStorageException($label . ' contains an unsafe directory boundary.');
        }
        $stat = @stat($path);
        $lstat = @lstat($path);
        if (!is_array($stat) || !is_array($lstat)) {
            throw new RecoveryStorageException($label . ' has ambiguous directory metadata.');
        }
        foreach (['mode', 'dev', 'ino', 'nlink'] as $field) {
            if (($stat[$field] ?? null) !== ($lstat[$field] ?? null)) {
                throw new RecoveryStorageException($label . ' has an ambiguous reparse boundary.');
            }
        }
    }

    private static function assertOutside(string $candidate, string $other, string $message): void
    {
        $candidatePath = rtrim(self::identityPath($candidate), '/') . '/';
        $otherPath = rtrim(self::identityPath($other), '/') . '/';
        if (str_starts_with($candidatePath, $otherPath) || str_starts_with($otherPath, $candidatePath)) {
            throw new RecoveryStorageException($message);
        }
    }
}
