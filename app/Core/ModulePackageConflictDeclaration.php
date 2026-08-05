<?php

namespace Copot\Core;

final class ModulePackageConflictDeclaration
{
    public function __construct(private ModulePackageTarget $target, private ?PackageCompatibility $versionConstraint = null)
    {
    }

    public function target(): ModulePackageTarget
    {
        return $this->target;
    }

    public function versionConstraint(): ?PackageCompatibility
    {
        return $this->versionConstraint;
    }

    public function toArray(): array
    {
        return [
            'target' => $this->target->toArray(),
            'version_constraint' => $this->versionConstraint === null ? null : [
                'minimum_version' => $this->versionConstraint->minimumVersion(),
                'maximum_version' => $this->versionConstraint->maximumVersion(),
            ],
        ];
    }
}
