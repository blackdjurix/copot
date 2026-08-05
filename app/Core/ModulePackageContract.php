<?php

namespace Copot\Core;

final class ModulePackageContract
{
    public const MODULE_PACKAGE_TYPE = 'copot-module';
    public const CURRENT_CONTRACT_VERSION = 1;

    private array $dependencies;
    private array $conflicts;

    public function __construct(
        private string $packageType,
        private int $contractVersion,
        private PackageIdentity $packageIdentity,
        private ModuleIdentity $moduleIdentity,
        private string $title,
        private string $packageVersion,
        private string $releaseIdentity,
        private PackageCompatibility $webcoreCompatibility,
        private ?PackageRuntimeCompatibility $runtimeRequirements,
        private ModulePackageOwnership $ownership,
        array $dependencies,
        array $conflicts,
        private ModuleMigrationDeclaration $migrationDeclaration,
        private ModuleProvisioningDeclaration $provisioningDeclaration
    ) {
        if ($packageType !== self::MODULE_PACKAGE_TYPE) {
            throw new \InvalidArgumentException('Module package type is unsupported.');
        }

        if ($contractVersion !== self::CURRENT_CONTRACT_VERSION) {
            throw new \InvalidArgumentException('Module package contract version is unsupported.');
        }

        if ($title === '' || trim($title) !== $title) {
            throw new \InvalidArgumentException('Module package title is invalid.');
        }

        PackageVersion::assertValid($packageVersion);
        self::assertOpaqueIdentity($releaseIdentity, 'Module release identity');

        if ($packageIdentity->equals($moduleIdentity->value())
            || $releaseIdentity === $packageVersion
            || $releaseIdentity === $moduleIdentity->value()) {
            throw new \InvalidArgumentException('Module package identities are not distinct.');
        }

        if ($ownership->module()->value() !== $moduleIdentity->value()) {
            throw new \InvalidArgumentException('Module package ownership does not match technical Module identity.');
        }

        if ($migrationDeclaration->owner()->value() !== $moduleIdentity->value()) {
            throw new \InvalidArgumentException('Module migration ownership does not match technical Module identity.');
        }

        $this->dependencies = self::normalizeDeclarations($dependencies, ModulePackageDependencyDeclaration::class, 'dependencies');
        $this->conflicts = self::normalizeDeclarations($conflicts, ModulePackageConflictDeclaration::class, 'conflicts');

        foreach ($this->dependencies as $dependency) {
            if ($this->isSelfTarget($dependency->target())) {
                throw new \InvalidArgumentException('Module package cannot declare a self-dependency.');
            }
        }

        foreach ($this->conflicts as $conflict) {
            if ($this->isSelfTarget($conflict->target())) {
                throw new \InvalidArgumentException('Module package cannot declare a self-conflict.');
            }
        }
    }

    public function packageType(): string { return $this->packageType; }
    public function contractVersion(): int { return $this->contractVersion; }
    public function packageIdentity(): PackageIdentity { return $this->packageIdentity; }
    public function moduleIdentity(): ModuleIdentity { return $this->moduleIdentity; }
    public function title(): string { return $this->title; }
    public function packageVersion(): string { return $this->packageVersion; }
    public function releaseIdentity(): string { return $this->releaseIdentity; }
    public function webcoreCompatibility(): PackageCompatibility { return $this->webcoreCompatibility; }
    public function runtimeRequirements(): ?PackageRuntimeCompatibility { return $this->runtimeRequirements; }
    public function ownership(): ModulePackageOwnership { return $this->ownership; }
    public function dependencies(): array { return $this->dependencies; }
    public function conflicts(): array { return $this->conflicts; }
    public function migrationDeclaration(): ModuleMigrationDeclaration { return $this->migrationDeclaration; }
    public function provisioningDeclaration(): ModuleProvisioningDeclaration { return $this->provisioningDeclaration; }

    public function supportsCommittedWebcoreVersion(string $version): bool
    {
        return $this->webcoreCompatibility->supports($version);
    }

    public function toArray(): array
    {
        return [
            'package_type' => $this->packageType,
            'contract_version' => $this->contractVersion,
            'package_identity' => $this->packageIdentity->value(),
            'technical_module_identity' => $this->moduleIdentity->value(),
            'title' => $this->title,
            'package_version' => $this->packageVersion,
            'release_identity' => $this->releaseIdentity,
            'webcore_compatibility' => [
                'minimum_version' => $this->webcoreCompatibility->minimumVersion(),
                'maximum_version' => $this->webcoreCompatibility->maximumVersion(),
            ],
            'runtime_requirements' => $this->runtimeRequirements?->toArray(),
            'ownership' => $this->ownership->toArray(),
            'dependencies' => array_map(static fn (ModulePackageDependencyDeclaration $dependency): array => $dependency->toArray(), $this->dependencies),
            'conflicts' => array_map(static fn (ModulePackageConflictDeclaration $conflict): array => $conflict->toArray(), $this->conflicts),
            'migration_declaration' => $this->migrationDeclaration->toArray(),
            'provisioning_declaration' => $this->provisioningDeclaration->toArray(),
        ];
    }

    private function isSelfTarget(ModulePackageTarget $target): bool
    {
        return $target->equals(ModulePackageTarget::MODULE, $this->moduleIdentity->value())
            || $target->equals(ModulePackageTarget::PACKAGE, $this->packageIdentity->value());
    }

    private static function normalizeDeclarations(array $declarations, string $class, string $label): array
    {
        $normalized = [];
        foreach ($declarations as $declaration) {
            if (!$declaration instanceof $class) {
                throw new \InvalidArgumentException('Module package ' . $label . ' are invalid.');
            }

            $key = $declaration->target()->kind() . ':' . $declaration->target()->identity();
            if (isset($normalized[$key])) {
                throw new \InvalidArgumentException('Module package ' . $label . ' contain duplicate targets.');
            }

            $normalized[$key] = $declaration;
        }

        return array_values($normalized);
    }

    private static function assertOpaqueIdentity(string $identity, string $label): void
    {
        if ($identity === '' || trim($identity) !== $identity || preg_match('/[\x00-\x1F\x7F]/', $identity) === 1) {
            throw new \InvalidArgumentException($label . ' is invalid.');
        }
    }
}
