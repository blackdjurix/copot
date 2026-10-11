<?php

namespace Copot\Core;

final class PackageContract
{
    public const WEBCORE_PACKAGE_TYPE = 'copot-webcore';
    public const LEGACY_MANIFEST_CONTRACT_VERSION = 1;
    public const CURRENT_MANIFEST_CONTRACT_VERSION = 2;
    public const V3_MANIFEST_CONTRACT_VERSION = 3;
    public const FORWARD = 'forward';
    public const REPAIR = 'repair';
    public const UNSUPPORTED_DOWNGRADE = 'unsupported_downgrade';

    private array $inventory;
    private PackageTargetRequirements $targetRequirements;
    private ?CanonicalTargetInventory $targetInventory = null;
    private ?string $coverage = null;
    private array $allowedInventoryFingerprints = [];
    private array $removals = [];
    private array $renames = [];

    public function __construct(
        private string $packageType,
        private int $manifestContractVersion,
        private string $targetWebcoreVersion,
        private string $releaseIdentity,
        private ?string $sourceTreeIdentity,
        private PackageCompatibility $sourceCompatibility,
        private PackageRuntimeCompatibility $runtimeCompatibility,
        array $inventory,
        private PackageMigrationDeclaration $migrationDeclaration,
        ?PackageTargetRequirements $targetRequirements = null,
        ?CanonicalTargetInventory $targetInventory = null,
        ?string $coverage = null,
        array $allowedInventoryFingerprints = [],
        array $removals = [],
        array $renames = []
    ) {
        if ($packageType !== self::WEBCORE_PACKAGE_TYPE) {
            throw new \InvalidArgumentException('Package type is unsupported.');
        }

        if (!in_array($manifestContractVersion, [self::LEGACY_MANIFEST_CONTRACT_VERSION, self::CURRENT_MANIFEST_CONTRACT_VERSION, self::V3_MANIFEST_CONTRACT_VERSION], true)) {
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
        $this->targetRequirements = $targetRequirements ?? new PackageTargetRequirements();
        if ($manifestContractVersion === self::V3_MANIFEST_CONTRACT_VERSION) {
            if (!$targetInventory instanceof CanonicalTargetInventory || !in_array($coverage, ['full', 'partial'], true)) { throw new \InvalidArgumentException('V3 target inventory declaration is invalid.'); }
            foreach ($allowedInventoryFingerprints as $fingerprint) { if (!is_string($fingerprint) || preg_match('/^[a-f0-9]{64}$/D', $fingerprint) !== 1) { throw new \InvalidArgumentException('V3 source inventory allowlist is invalid.'); } }
            if (count($allowedInventoryFingerprints) !== count(array_unique($allowedInventoryFingerprints))) { throw new \InvalidArgumentException('V3 source inventory allowlist contains duplicates.'); }
            if ($coverage === 'partial' && $allowedInventoryFingerprints === []) { throw new \InvalidArgumentException('Partial V3 coverage requires an inventory allowlist.'); }
            $this->targetInventory = $targetInventory; $this->coverage = $coverage; $this->allowedInventoryFingerprints = array_values(array_unique($allowedInventoryFingerprints)); $this->removals = $removals; $this->renames = $renames;
        }

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

    public function targetRequirements(): PackageTargetRequirements
    {
        return $this->targetRequirements;
    }

    public function targetInventory(): ?CanonicalTargetInventory { return $this->targetInventory; }
    public function coverage(): ?string { return $this->coverage; }
    public function allowedInventoryFingerprints(): array { return $this->allowedInventoryFingerprints; }
    public function removals(): array { return $this->removals; }
    public function renames(): array { return $this->renames; }

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
