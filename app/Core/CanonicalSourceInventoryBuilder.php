<?php

namespace Copot\Core;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/** Captures the package-owned bytes of the actual deployed roots. */
final class CanonicalSourceInventoryBuilder
{
    private string $appRoot;
    private ?string $publicRoot;

    public function __construct(string $appRoot, ?string $publicRoot = null)
    {
        $this->appRoot = self::resolveRoot($appRoot, 'APP_ROOT');
        $this->publicRoot = $publicRoot === null ? null : self::resolveRoot($publicRoot, 'PUBLIC_ROOT');

        if ($this->publicRoot !== null && strcasecmp($this->appRoot, $this->publicRoot) === 0) {
            throw new \InvalidArgumentException('APP_ROOT and PUBLIC_ROOT must be different directories.');
        }
    }

    public function capture(): CanonicalTargetInventory
    {
        $entries = [];
        $this->appendRoot($entries, CanonicalTargetInventoryEntry::APP_ROOT, $this->appRoot);
        if ($this->publicRoot !== null) {
            $this->appendRoot($entries, CanonicalTargetInventoryEntry::PUBLIC_ROOT, $this->publicRoot);
        }

        if ($entries === []) {
            throw new \RuntimeException('Canonical source inventory is empty.');
        }

        return new CanonicalTargetInventory($entries);
    }

    /** @param list<CanonicalTargetInventoryEntry> $entries */
    private function appendRoot(array &$entries, string $root, string $base): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isLink()) {
                throw new \RuntimeException('Canonical source inventory encountered a symlink.');
            }
            if (!$file->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
            if ($root === CanonicalTargetInventoryEntry::PUBLIC_ROOT
                && ProtectedResourcePolicy::isProtected($root, $relative)) {
                continue;
            }

            $ownershipPath = $root === CanonicalTargetInventoryEntry::PUBLIC_ROOT
                ? 'public/' . $relative
                : $relative;
            if (PackageOwnership::isPackageMetadata($ownershipPath)) {
                continue;
            }
            if (PackageOwnership::classify($ownershipPath) !== PackageOwnership::PACKAGE_OWNED
                || self::isLifecycleOwned($relative, $root)) {
                continue;
            }

            $size = $file->getSize();
            $sha256 = hash_file('sha256', $file->getPathname());
            if (!is_int($size) || !is_string($sha256)) {
                throw new \RuntimeException('Canonical source inventory could not identify a file.');
            }

            $entries[] = new CanonicalTargetInventoryEntry(
                $root,
                CanonicalTargetInventoryEntry::PACKAGE_OWNED,
                $relative,
                $size,
                $sha256
            );
        }
    }

    private static function isLifecycleOwned(string $relative, string $root): bool
    {
        if ($root !== CanonicalTargetInventoryEntry::APP_ROOT) {
            return false;
        }

        return str_starts_with(strtolower($relative), 'storage/.copot-lifecycle/');
    }

    private static function resolveRoot(string $path, string $label): string
    {
        $resolved = realpath($path);
        if ($resolved === false || !is_dir($resolved) || is_link($path) || !is_readable($resolved)) {
            throw new \InvalidArgumentException($label . ' is missing, unreadable, or unsafe.');
        }
        return rtrim($resolved, '/\\');
    }
}
