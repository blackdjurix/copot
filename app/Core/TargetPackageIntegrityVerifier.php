<?php

namespace Copot\Core;

final class TargetPackageIntegrityVerifier
{
    public function verify(PackageContract $package, LiveTreePathGuard $guard, ?CanonicalTargetInventory $targetInventory = null): HealthGateMatrix
    {
        $gates = [];
        $entries = $targetInventory?->entries() ?? $package->inventory();
        foreach ($entries as $entry) {
            if ($targetInventory === null && (!$entry instanceof PackageInventoryEntry || $entry->ownership() !== PackageOwnership::PACKAGE_OWNED)) continue;
            if ($targetInventory !== null && (!$entry instanceof CanonicalTargetInventoryEntry || $entry->ownership() !== CanonicalTargetInventoryEntry::PACKAGE_OWNED)) continue;
            if (!$entry instanceof CanonicalTargetInventoryEntry && !$entry instanceof PackageInventoryEntry) {
                continue;
            }
            try {
                $relative = $entry instanceof CanonicalTargetInventoryEntry
                    ? ($entry->root() === CanonicalTargetInventoryEntry::PUBLIC_ROOT ? 'public/' . $entry->path() : $entry->path())
                    : $entry->path();
                $path = $guard->destination($relative);
                $guard->verifyDestination($relative, true);
                if (is_link($path) || !is_file($path)) {
                    $gates[] = HealthGateResult::fail('package-file:' . $relative, 'Target is not a regular file.');
                    continue;
                }
                $size = @filesize($path);
                $hash = @hash_file('sha256', $path);
                $gates[] = is_int($size) && $size === $entry->byteSize() && $hash === $entry->sha256()
                    ? HealthGateResult::pass('package-file:' . $relative)
                    : HealthGateResult::fail('package-file:' . $relative, 'Target file identity does not match the accepted inventory.');
            } catch (\Throwable $exception) {
                $gates[] = HealthGateResult::fail('package-file:' . $entry->path(), $exception->getMessage());
            }
        }
        return new HealthGateMatrix($gates);
    }
}
