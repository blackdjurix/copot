<?php

namespace Copot\Core;

final class ModulePackageDependencyDeclaration
{
    public function __construct(private ModulePackageTarget $target, private PackageCompatibility $versionConstraint)
    {
    }

    public function target(): ModulePackageTarget
    {
        return $this->target;
    }

    public function versionConstraint(): PackageCompatibility
    {
        return $this->versionConstraint;
    }

    public function toArray(): array
    {
        return [
            'target' => $this->target->toArray(),
            'version_constraint' => [
                'minimum_version' => $this->versionConstraint->minimumVersion(),
                'maximum_version' => $this->versionConstraint->maximumVersion(),
            ],
        ];
    }
}
