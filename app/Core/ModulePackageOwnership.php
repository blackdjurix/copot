<?php

namespace Copot\Core;

final class ModulePackageOwnership
{
    public function __construct(private ModuleIdentity $module, private string $rootPath)
    {
        if ($rootPath !== $module->ownershipRoot()) {
            throw new \InvalidArgumentException('Module package ownership must be the technical Module root.');
        }
    }

    public function module(): ModuleIdentity
    {
        return $this->module;
    }

    public function rootPath(): string
    {
        return $this->rootPath;
    }

    public function toArray(): array
    {
        return [
            'technical_module_identity' => $this->module->value(),
            'root_path' => $this->rootPath,
        ];
    }
}
