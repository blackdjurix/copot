<?php

namespace Copot\Core;

final class PackageRuntimeCompatibility
{
    public function __construct(
        private string $minimumPhpVersion,
        private array $minimumDatabaseVersions,
        private array $requiredExtensions
    ) {
        PackageVersion::assertValid($this->normalizePhpVersion($minimumPhpVersion));

        if ($minimumDatabaseVersions === []) {
            throw new \InvalidArgumentException('Package runtime database requirements cannot be empty.');
        }

        foreach ($minimumDatabaseVersions as $vendor => $version) {
            if (!is_string($vendor) || trim($vendor) === '' || !is_string($version)) {
                throw new \InvalidArgumentException('Package runtime database requirement is invalid.');
            }

            PackageVersion::assertValid($this->normalizeDatabaseVersion($version));
        }

        $normalizedExtensions = [];

        foreach ($requiredExtensions as $extension) {
            if (!is_string($extension) || trim($extension) === '') {
                throw new \InvalidArgumentException('Package runtime extension requirement is invalid.');
            }

            $normalizedExtensions[strtolower(trim($extension))] = true;
        }

        if ($normalizedExtensions === []) {
            throw new \InvalidArgumentException('Package runtime extension requirements cannot be empty.');
        }

        $this->requiredExtensions = array_keys($normalizedExtensions);
    }

    public function minimumPhpVersion(): string
    {
        return $this->minimumPhpVersion;
    }

    public function minimumDatabaseVersions(): array
    {
        return $this->minimumDatabaseVersions;
    }

    public function requiredExtensions(): array
    {
        return $this->requiredExtensions;
    }

    public function toArray(): array
    {
        return [
            'minimum_php_version' => $this->minimumPhpVersion,
            'minimum_database_versions' => $this->minimumDatabaseVersions,
            'required_extensions' => $this->requiredExtensions,
        ];
    }

    private function normalizePhpVersion(string $version): string
    {
        return substr_count($version, '.') === 1 ? $version . '.0' : $version;
    }

    private function normalizeDatabaseVersion(string $version): string
    {
        return substr_count($version, '.') === 1 ? $version . '.0' : $version;
    }
}
