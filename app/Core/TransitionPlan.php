<?php

namespace Copot\Core;

final class TransitionPlan
{
    public const INSTALL = 'install';
    public const PATCH = 'patch';
    public const UPDATE = 'update';
    public const UPGRADE = 'upgrade';
    public const REPAIR = 'repair';
    public const DATABASE_UPDATE = 'database_update';
    public const BOOTSTRAP_REQUIRED = 'bootstrap_required';
    public const REJECTED = 'rejected';

    private function __construct(
        private string $classification,
        private bool $accepted,
        private string $reason,
        private PackageContract $package,
        private ?InstalledStateSnapshot $installedState
    ) {
    }

    public static function allow(string $classification, PackageContract $package, ?InstalledStateSnapshot $installedState = null): self
    {
        if (!in_array($classification, [self::INSTALL, self::PATCH, self::UPDATE, self::UPGRADE, self::REPAIR, self::DATABASE_UPDATE], true)) {
            throw new \InvalidArgumentException('Transition classification is invalid.');
        }

        return new self($classification, true, '', $package, $installedState);
    }

    public static function rejected(string $classification, string $reason, PackageContract $package, ?InstalledStateSnapshot $installedState = null): self
    {
        return new self($classification, false, $reason, $package, $installedState);
    }

    public function classification(): string
    {
        return $this->classification;
    }

    public function accepted(): bool
    {
        return $this->accepted;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function package(): PackageContract
    {
        return $this->package;
    }

    public function installedState(): ?InstalledStateSnapshot
    {
        return $this->installedState;
    }

    public function asDatabaseUpdate(): self
    {
        if (!$this->accepted || $this->classification !== self::REPAIR) {
            throw new \InvalidArgumentException('Only an accepted same-version Repair can become a Database-only Update.');
        }

        return new self(self::DATABASE_UPDATE, true, $this->reason, $this->package, $this->installedState);
    }
}
