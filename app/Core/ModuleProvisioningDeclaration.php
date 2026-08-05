<?php

namespace Copot\Core;

final class ModuleProvisioningDeclaration
{
    private array $permissions;

    public function __construct(private ?string $schemaIdentity = null, array $permissions = [])
    {
        if ($schemaIdentity !== null
            && ($schemaIdentity === '' || trim($schemaIdentity) !== $schemaIdentity || preg_match('/[\x00-\x1F\x7F]/', $schemaIdentity) === 1)) {
            throw new \InvalidArgumentException('Module provisioning schema identity is invalid.');
        }

        $this->permissions = [];
        foreach ($permissions as $permission) {
            if (!$permission instanceof ModulePermissionDeclaration || isset($this->permissions[$permission->slug()])) {
                throw new \InvalidArgumentException('Module provisioning permissions are invalid or duplicated.');
            }

            $this->permissions[$permission->slug()] = $permission;
        }
    }

    public function schemaIdentity(): ?string
    {
        return $this->schemaIdentity;
    }

    public function permissions(): array
    {
        return array_values($this->permissions);
    }

    public function toArray(): array
    {
        return [
            'schema_identity' => $this->schemaIdentity,
            'permissions' => array_map(static fn (ModulePermissionDeclaration $permission): array => $permission->toArray(), $this->permissions),
        ];
    }
}
