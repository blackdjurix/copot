<?php

namespace Copot\Core;

final class PackageContract
{
    public const WEBCORE_PACKAGE_TYPE = 'copot-webcore';
    public const CURRENT_MANIFEST_CONTRACT_VERSION = 1;
    public const FORWARD = 'forward';
    public const REPAIR = 'repair';
    public const UNSUPPORTED_DOWNGRADE = 'unsupported_downgrade';

    private array $inventory;

    public function __construct(
        private string $packageType,
        private int $manifestContractVersion,
        private string $targetWebcoreVersion,
        private string $releaseIdentity,
        private ?string $sourceTreeIdentity,
        private PackageCompatibility $sourceCompatibility,
        private PackageRuntimeCompatibility $runtimeCompatibility,
        array $inventory,
        private PackageMigrationDeclaration $migrationDeclaration
    ) {
        if ($packageType !== self::WEBCORE_PACKAGE_TYPE) {
            throw new \InvalidArgumentException('Package type is unsupported.');
        }

        if ($manifestContractVersion !== self::CURRENT_MANIFEST_CONTRACT_VERSION) {
            throw new \InvalidArgumentException('Manifest contract version is unsupported.');
        }

        PackageVersion::assertValid($targetWebcoreVersion);
        self::assertOpaqueIdentity($releaseIdentity, 'Release identity');

        if ($sourceTreeIdentity !== null) {
            self::assertOpaqueIdentity($sourceTreeIdentity, 'Source-tree identity');
        }

        if (!$sourceCompatibility->supports($sourceCompatibility->minimumSourceVersion())) {
            throw new \InvalidArgumentException('Package source compatibility is invalid.');
        }

        if ($inventory === []) {
            throw new \InvalidArgumentException('Package inventory cannot be empty.');
        }

        $this->inventory = [];

        foreach ($inventory as $entry) {
            if (!$entry instanceof PackageInventoryEntry) {
                throw new \InvalidArgumentException('Package inventory must contain inventory entries.');
            }

            if (in_array($entry->ownership(), [
                PackageOwnership::OPERATOR_OWNED,
                PackageOwnership::RUNTIME_GENERATED,
            ], true)) {
                throw new \InvalidArgumentException('Package inventory cannot contain operator-owned or runtime-generated entries.');
            }

            if (isset($this->inventory[$entry->path()])) {
                throw new \InvalidArgumentException('Package inventory contains a duplicate path.');
            }

            $this->inventory[$entry->path()] = $entry;
        }
    }

    public function packageType(): string
    {
        return $this->packageType;
    }

    public function manifestContractVersion(): int
    {
        return $this->manifestContractVersion;
    }

    public function targetWebcoreVersion(): string
    {
        return $this->targetWebcoreVersion;
    }

    public function releaseIdentity(): string
    {
        return $this->releaseIdentity;
    }

    public function sourceTreeIdentity(): ?string
    {
        return $this->sourceTreeIdentity;
    }

    public function sourceCompatibility(): PackageCompatibility
    {
        return $this->sourceCompatibility;
    }

    public function runtimeCompatibility(): PackageRuntimeCompatibility
    {
        return $this->runtimeCompatibility;
    }

    public function inventory(): array
    {
        return array_values($this->inventory);
    }

    public function migrationDeclaration(): PackageMigrationDeclaration
    {
        return $this->migrationDeclaration;
    }

    public function integrityIdentity(): string
    {
        $entries = array_map(static fn (PackageInventoryEntry $entry): string => implode(':', [
            $entry->path(), $entry->byteSize(), $entry->sha256(), $entry->ownership(),
        ]), $this->inventory());
        sort($entries, SORT_STRING);

        return hash('sha256', implode("\n", $entries));
    }

    public function versionRelation(string $installedVersion): string
    {
        $comparison = PackageVersion::compare($this->targetWebcoreVersion, $installedVersion);

        return $comparison > 0 ? self::FORWARD : ($comparison === 0 ? self::REPAIR : self::UNSUPPORTED_DOWNGRADE);
    }

    private static function assertOpaqueIdentity(string $identity, string $label): void
    {
        if ($identity === '' || trim($identity) !== $identity || preg_match('/[\x00-\x1F\x7F]/', $identity) === 1) {
            throw new \InvalidArgumentException($label . ' is invalid.');
        }
    }
}
