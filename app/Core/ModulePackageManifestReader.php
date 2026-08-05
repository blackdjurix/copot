<?php

namespace Copot\Core;

use JsonException;

final class ModulePackageManifestReader
{
    public const PATH = '.copot/package.json';

    public function read(StagedPayload $payload): ModulePackageManifest
    {
        $metadata = $this->metadataFile($payload);
        $path = $payload->payloadPath() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::PATH);
        if (!is_file($path) || is_link($path) || !is_readable($path)
            || (int) @filesize($path) !== $metadata->byteSize()
            || @hash_file('sha256', $path) !== $metadata->sha256()) {
            throw new \RuntimeException('Module package manifest identity is invalid.');
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('Module package manifest is invalid.', 0, $exception);
        }

        if (!is_array($data)) {
            throw new \RuntimeException('Module package manifest is invalid.');
        }

        $expected = ['package_type', 'contract_version', 'package_identity', 'technical_module_identity', 'title', 'package_version', 'release_identity', 'webcore_compatibility', 'runtime_requirements', 'ownership', 'dependencies', 'conflicts', 'migration_declaration', 'provisioning_declaration', 'inventory'];
        $this->assertKeys($data, $expected, 'Module package manifest');
        try {
            $contract = new ModulePackageContract(
                $data['package_type'], $data['contract_version'], new PackageIdentity($data['package_identity']),
                new ModuleIdentity($data['technical_module_identity']), $data['title'], $data['package_version'],
                $data['release_identity'], $this->compatibility($data['webcore_compatibility'], 'Webcore compatibility'),
                $this->runtime($data['runtime_requirements']), $this->ownership($data['ownership']),
                $this->dependencies($data['dependencies']), $this->conflicts($data['conflicts']),
                $this->migrations($data['migration_declaration'], $data['technical_module_identity']),
                $this->provisioning($data['provisioning_declaration'])
            );
            $inventory = $this->inventory($data['inventory']);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Module package manifest contract is invalid.', 0, $exception);
        }

        return new ModulePackageManifest($contract, self::PATH, $payload->withoutPaths([self::PATH]), $inventory);
    }

    private function metadataFile(StagedPayload $payload): StagedFile
    {
        foreach ($payload->files() as $file) if ($file->path() === self::PATH) return $file;
        throw new \RuntimeException('Module package manifest is missing.');
    }

    private function compatibility(mixed $value, string $label): PackageCompatibility
    {
        if (!is_array($value)) throw new \InvalidArgumentException($label . ' is invalid.');
        $this->assertKeys($value, ['minimum_version', 'maximum_version'], $label);
        return new PackageCompatibility($value['minimum_version'], $value['maximum_version']);
    }

    private function runtime(mixed $value): ?PackageRuntimeCompatibility
    {
        if ($value === null) return null;
        if (!is_array($value)) throw new \InvalidArgumentException('Runtime requirements are invalid.');
        $this->assertKeys($value, ['minimum_php_version', 'minimum_database_versions', 'required_extensions'], 'Runtime requirements');
        return new PackageRuntimeCompatibility($value['minimum_php_version'], $value['minimum_database_versions'], $value['required_extensions']);
    }

    private function ownership(mixed $value): ModulePackageOwnership
    {
        if (!is_array($value)) throw new \InvalidArgumentException('Module ownership is invalid.');
        $this->assertKeys($value, ['technical_module_identity', 'root_path'], 'Module ownership');
        return new ModulePackageOwnership(new ModuleIdentity($value['technical_module_identity']), $value['root_path']);
    }

    private function target(array $value): ModulePackageTarget
    {
        $this->assertKeys($value, ['target_kind', 'target_identity'], 'Declaration target');
        return new ModulePackageTarget($value['target_kind'], $value['target_identity']);
    }

    private function dependencies(mixed $value): array
    {
        if (!is_array($value)) throw new \InvalidArgumentException('Dependencies are invalid.');
        $result = [];
        foreach ($value as $entry) {
            if (!is_array($entry)) throw new \InvalidArgumentException('Dependency declaration is invalid.');
            $this->assertKeys($entry, ['target', 'version_constraint'], 'Dependency declaration');
            $result[] = new ModulePackageDependencyDeclaration($this->target($entry['target']), $this->compatibility($entry['version_constraint'], 'Dependency version constraint'));
        }
        return $result;
    }

    private function conflicts(mixed $value): array
    {
        if (!is_array($value)) throw new \InvalidArgumentException('Conflicts are invalid.');
        $result = [];
        foreach ($value as $entry) {
            if (!is_array($entry)) throw new \InvalidArgumentException('Conflict declaration is invalid.');
            $this->assertKeys($entry, ['target', 'version_constraint'], 'Conflict declaration');
            $constraint = $entry['version_constraint'] === null ? null : $this->compatibility($entry['version_constraint'], 'Conflict version constraint');
            $result[] = new ModulePackageConflictDeclaration($this->target($entry['target']), $constraint);
        }
        return $result;
    }

    private function migrations(mixed $value, string $module): ModuleMigrationDeclaration
    {
        if (!is_array($value)) throw new \InvalidArgumentException('Migration declaration is invalid.');
        $this->assertKeys($value, ['owner', 'declares_migrations', 'declaration_identity', 'migrations'], 'Migration declaration');
        if ($value['owner'] !== $module) throw new \InvalidArgumentException('Migration declaration owner does not match the technical Module identity.');
        if (!is_array($value['migrations'])) throw new \InvalidArgumentException('Migration list is invalid.');
        $result = [];
        foreach ($value['migrations'] as $migration) {
            if (!is_array($migration)) throw new \InvalidArgumentException('Migration descriptor is invalid.');
            $this->assertKeys($migration, ['id', 'sequence', 'source_version_constraint', 'target_package_version', 'target_schema_identity'], 'Migration descriptor');
            $result[] = new ModuleMigrationDescriptor($migration['id'], $migration['sequence'], $this->compatibility($migration['source_version_constraint'], 'Migration source constraint'), $migration['target_package_version'], $migration['target_schema_identity']);
        }
        return new ModuleMigrationDeclaration(new ModuleIdentity($module), $value['declares_migrations'], $value['declaration_identity'], $result);
    }

    private function provisioning(mixed $value): ModuleProvisioningDeclaration
    {
        if (!is_array($value)) throw new \InvalidArgumentException('Provisioning declaration is invalid.');
        $this->assertKeys($value, ['schema_identity', 'permissions'], 'Provisioning declaration');
        if (!is_array($value['permissions'])) throw new \InvalidArgumentException('Permission declarations are invalid.');
        $permissions = [];
        foreach ($value['permissions'] as $permission) {
            if (!is_array($permission)) throw new \InvalidArgumentException('Permission declaration is invalid.');
            $this->assertKeys($permission, ['slug', 'name'], 'Permission declaration');
            $permissions[] = new ModulePermissionDeclaration($permission['slug'], $permission['name']);
        }
        return new ModuleProvisioningDeclaration($value['schema_identity'], $permissions);
    }

    private function inventory(mixed $value): array
    {
        if (!is_array($value)) throw new \InvalidArgumentException('Module inventory is invalid.');
        $result = [];
        foreach ($value as $entry) {
            if (!is_array($entry)) throw new \InvalidArgumentException('Module inventory entry is invalid.');
            $this->assertKeys($entry, ['path', 'byte_size', 'sha256', 'ownership'], 'Module inventory entry');
            if ($entry['path'] === self::PATH) throw new \InvalidArgumentException('Module package manifest cannot be in its own inventory.');
            $result[] = new PackageInventoryEntry($entry['path'], $entry['byte_size'], $entry['sha256'], $entry['ownership']);
        }
        return $result;
    }

    private function assertKeys(array $data, array $expected, string $label): void
    {
        if (array_keys($data) !== $expected) throw new \InvalidArgumentException($label . ' contains unsupported, missing, or reordered fields.');
    }
}
