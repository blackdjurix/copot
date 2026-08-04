<?php

namespace Copot\Core;

final class TargetPackageIntegrityVerifier
{
    public function verify(PackageContract $package, LiveTreePathGuard $guard): HealthGateMatrix
    {
        $gates = [];
        foreach ($package->inventory() as $entry) {
            if (!$entry instanceof PackageInventoryEntry || $entry->ownership() !== PackageOwnership::PACKAGE_OWNED) {
                continue;
            }
            try {
                $path = $guard->destination($entry->path());
                $guard->verifyDestination($entry->path(), true);
                if (is_link($path) || !is_file($path)) {
                    $gates[] = HealthGateResult::fail('package-file:' . $entry->path(), 'Target is not a regular file.');
                    continue;
                }
                $size = @filesize($path);
                $hash = @hash_file('sha256', $path);
                $gates[] = is_int($size) && $size === $entry->byteSize() && $hash === $entry->sha256()
                    ? HealthGateResult::pass('package-file:' . $entry->path())
                    : HealthGateResult::fail('package-file:' . $entry->path(), 'Target file identity does not match the accepted inventory.');
            } catch (\Throwable $exception) {
                $gates[] = HealthGateResult::fail('package-file:' . $entry->path(), $exception->getMessage());
            }
        }
        return new HealthGateMatrix($gates);
    }
}
