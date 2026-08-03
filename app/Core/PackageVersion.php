<?php

namespace Copot\Core;

final class PackageVersion
{
    private const PATTERN = '/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?$/';

    private function __construct()
    {
    }

    public static function isValid(string $version): bool
    {
        return preg_match(self::PATTERN, $version) === 1;
    }

    public static function compare(string $left, string $right): int
    {
        self::assertValid($left);
        self::assertValid($right);

        return version_compare($left, $right);
    }

    public static function assertValid(string $version): void
    {
        if (!self::isValid($version)) {
            throw new \InvalidArgumentException('Package version is invalid.');
        }
    }
}
