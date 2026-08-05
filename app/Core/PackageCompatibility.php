<?php

namespace Copot\Core;

final class PackageCompatibility
{
    public function __construct(
        private string $minimumSourceVersion,
        private ?string $maximumSourceVersion = null
    ) {
        PackageVersion::assertValid($minimumSourceVersion);

        if ($maximumSourceVersion !== null) {
            PackageVersion::assertValid($maximumSourceVersion);

            if (PackageVersion::compare($maximumSourceVersion, $minimumSourceVersion) <= 0) {
                throw new \InvalidArgumentException('Package source compatibility range is invalid.');
            }
        }
    }

    public function minimumSourceVersion(): string
    {
        return $this->minimumSourceVersion;
    }

    public function maximumSourceVersion(): ?string
    {
        return $this->maximumSourceVersion;
    }

    public function minimumVersion(): string
    {
        return $this->minimumSourceVersion;
    }

    public function maximumVersion(): ?string
    {
        return $this->maximumSourceVersion;
    }

    public function supports(string $sourceVersion): bool
    {
        PackageVersion::assertValid($sourceVersion);

        if (PackageVersion::compare($sourceVersion, $this->minimumSourceVersion) < 0) {
            return false;
        }

        return $this->maximumSourceVersion === null
            || PackageVersion::compare($sourceVersion, $this->maximumSourceVersion) < 0;
    }

    public function toArray(): array
    {
        return [
            'minimum_source_version' => $this->minimumSourceVersion,
            'maximum_source_version' => $this->maximumSourceVersion,
        ];
    }
}
