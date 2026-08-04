<?php

namespace Copot\Core;

final class RuntimeCompatibilityContext
{
    public function __construct(
        private string $phpVersion,
        private array $databaseVersions,
        private array $extensions
    ) {
        PackageVersion::assertValid(self::normalizeVersion($phpVersion));

        foreach ($databaseVersions as $vendor => $version) {
            if (!is_string($vendor) || !is_string($version) || trim($vendor) === '') {
                throw new \InvalidArgumentException('Runtime database version is invalid.');
            }

            PackageVersion::assertValid(self::normalizeVersion($version));
        }

        foreach ($extensions as $extension) {
            if (!is_string($extension) || trim($extension) === '') {
                throw new \InvalidArgumentException('Runtime extension is invalid.');
            }
        }
    }

    public function supports(PackageRuntimeCompatibility $requirements): bool
    {
        if (PackageVersion::compare(self::normalizeVersion($this->phpVersion), self::normalizeVersion($requirements->minimumPhpVersion())) < 0) {
            return false;
        }

        $databases = [];

        foreach ($this->databaseVersions as $vendor => $version) {
            $databases[strtolower((string) $vendor)] = self::normalizeVersion((string) $version);
        }

        foreach ($requirements->minimumDatabaseVersions() as $vendor => $version) {
            $vendor = strtolower((string) $vendor);

            if (!isset($databases[$vendor]) || PackageVersion::compare($databases[$vendor], self::normalizeVersion($version)) < 0) {
                return false;
            }
        }

        $extensions = array_fill_keys(array_map(static fn (string $extension): string => strtolower(trim($extension)), $this->extensions), true);

        foreach ($requirements->requiredExtensions() as $extension) {
            if (!isset($extensions[strtolower($extension)])) {
                return false;
            }
        }

        return true;
    }

    private static function normalizeVersion(string $version): string
    {
        return substr_count($version, '.') === 1 ? $version . '.0' : $version;
    }
}
