<?php

namespace Copot\Core;

use DateTimeImmutable;

final class InstalledStateSnapshot
{
    public function __construct(
        private string $webcoreVersion,
        private DateTimeImmutable $installedAt,
        private ?string $releaseIdentity = null,
        private ?string $sourceTreeIdentity = null,
        private ?int $manifestContractVersion = null,
        private ?string $schemaStateIdentity = null,
        private ?string $migrationStateIdentity = null
    ) {
        PackageVersion::assertValid($webcoreVersion);
        self::assertIdentity($releaseIdentity, 'Release identity');
        self::assertIdentity($sourceTreeIdentity, 'Source-tree identity');
        self::assertIdentity($schemaStateIdentity, 'Schema-state identity');
        self::assertIdentity($migrationStateIdentity, 'Migration-state identity');

        if ($manifestContractVersion !== null && $manifestContractVersion < 1) {
            throw new \InvalidArgumentException('Manifest contract version is invalid.');
        }
    }

    public function webcoreVersion(): string
    {
        return $this->webcoreVersion;
    }

    public function installedAt(): DateTimeImmutable
    {
        return $this->installedAt;
    }

    public function releaseIdentity(): ?string
    {
        return $this->releaseIdentity;
    }

    public function sourceTreeIdentity(): ?string
    {
        return $this->sourceTreeIdentity;
    }

    public function manifestContractVersion(): ?int
    {
        return $this->manifestContractVersion;
    }

    public function schemaStateIdentity(): ?string
    {
        return $this->schemaStateIdentity;
    }

    public function migrationStateIdentity(): ?string
    {
        return $this->migrationStateIdentity;
    }

    public function hasLifecycleIdentity(): bool
    {
        return $this->releaseIdentity !== null && $this->manifestContractVersion !== null;
    }

    private static function assertIdentity(?string $identity, string $label): void
    {
        if ($identity !== null && ($identity === '' || trim($identity) !== $identity || preg_match('/[\x00-\x1F\x7F]/', $identity) === 1)) {
            throw new \InvalidArgumentException($label . ' is invalid.');
        }
    }
}
