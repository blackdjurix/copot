<?php

namespace Copot\Core;

final class ModuleMigrationDescriptor
{
    public function __construct(
        private string $id,
        private int $sequence,
        private PackageCompatibility $sourceVersionConstraint,
        private string $targetPackageVersion,
        private string $targetSchemaIdentity
    ) {
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/', $id) !== 1 || $sequence < 1) {
            throw new \InvalidArgumentException('Module migration identity is invalid.');
        }

        PackageVersion::assertValid($targetPackageVersion);

        if ($targetSchemaIdentity === ''
            || trim($targetSchemaIdentity) !== $targetSchemaIdentity
            || preg_match('/[\x00-\x1F\x7F]/', $targetSchemaIdentity) === 1) {
            throw new \InvalidArgumentException('Module migration schema identity is invalid.');
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function sequence(): int
    {
        return $this->sequence;
    }

    public function sourceVersionConstraint(): PackageCompatibility
    {
        return $this->sourceVersionConstraint;
    }

    public function targetPackageVersion(): string
    {
        return $this->targetPackageVersion;
    }

    public function targetSchemaIdentity(): string
    {
        return $this->targetSchemaIdentity;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sequence' => $this->sequence,
            'source_version_constraint' => [
                'minimum_version' => $this->sourceVersionConstraint->minimumVersion(),
                'maximum_version' => $this->sourceVersionConstraint->maximumVersion(),
            ],
            'target_package_version' => $this->targetPackageVersion,
            'target_schema_identity' => $this->targetSchemaIdentity,
        ];
    }
}
