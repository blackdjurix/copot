<?php

namespace Copot\Core;

final class PackageOwnership
{
    public const PACKAGE_OWNED = 'package_owned';
    public const OPERATOR_OWNED = 'operator_owned';
    public const RUNTIME_GENERATED = 'runtime_generated';
    public const CONDITIONALLY_MANAGED = 'conditionally_managed';

    private function __construct()
    {
    }

    public static function isValid(string $ownership): bool
    {
        return in_array($ownership, self::values(), true);
    }

    public static function values(): array
    {
        return [
            self::PACKAGE_OWNED,
            self::OPERATOR_OWNED,
            self::RUNTIME_GENERATED,
            self::CONDITIONALLY_MANAGED,
        ];
    }

    public static function classify(string $path): string
    {
        $normalized = PackageInventoryEntry::normalizePath($path);

        if ($normalized === '.env') {
            return self::OPERATOR_OWNED;
        }

        if (in_array($normalized, ['storage/.install.lock', 'storage/installed.lock'], true)) {
            return self::RUNTIME_GENERATED;
        }

        if ($normalized === 'storage/cache/.gitkeep' || $normalized === 'storage/logs/.gitkeep') {
            return self::PACKAGE_OWNED;
        }

        foreach (['storage/cache/', 'storage/logs/', 'storage/site-assets/'] as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                return self::RUNTIME_GENERATED;
            }
        }

        return self::PACKAGE_OWNED;
    }

    public static function assertCompatible(string $path, string $ownership): void
    {
        if (!self::isValid($ownership)) {
            throw new \InvalidArgumentException('Package ownership classification is invalid.');
        }

        $classified = self::classify($path);

        if ($ownership === self::PACKAGE_OWNED
            && in_array($classified, [self::OPERATOR_OWNED, self::RUNTIME_GENERATED], true)) {
            throw new \InvalidArgumentException('Operator-owned or runtime-generated paths cannot be package-owned.');
        }
    }
}
