<?php

namespace Copot\Core;

use JsonException;

final class PackageManifestReader
{
    public const PATH = '.copot/package.json';

    public function read(StagedPayload $payload): PackageManifest
    {
        $metadata = null;
        foreach ($payload->files() as $file) {
            if ($file->path() === self::PATH) {
                $metadata = $file;
                break;
            }
        }

        if (!$metadata instanceof StagedFile) {
            throw new \RuntimeException('Package manifest is missing.');
        }

        $path = $payload->payloadPath() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::PATH);
        if (!is_file($path) || is_link($path) || !is_readable($path)) {
            throw new \RuntimeException('Package manifest is unavailable.');
        }

        if ((int) @filesize($path) !== $metadata->byteSize() || @hash_file('sha256', $path) !== $metadata->sha256()) {
            throw new \RuntimeException('Package manifest identity is invalid.');
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('Package manifest is invalid.', 0, $exception);
        }

        if (!is_array($data)) {
            throw new \RuntimeException('Package manifest is invalid.');
        }

        $expected = [
            'package_type', 'manifest_contract_version', 'target_webcore_version',
            'release_identity', 'source_tree_identity', 'source_compatibility',
            'runtime_compatibility', 'inventory', 'migration_declaration',
        ];
        $this->assertKeys($data, $expected, 'Package manifest');

        $source = $data['source_compatibility'];
        $runtime = $data['runtime_compatibility'];
        $migration = $data['migration_declaration'];
        if (!is_array($source) || !is_array($runtime) || !is_array($migration) || !is_array($data['inventory'])) {
            throw new \RuntimeException('Package manifest structure is invalid.');
        }

        $this->assertKeys($source, ['minimum_source_version', 'maximum_source_version'], 'Source compatibility');
        $this->assertKeys($runtime, ['minimum_php_version', 'minimum_database_versions', 'required_extensions'], 'Runtime compatibility');
        $this->assertKeys($migration, ['declares_core_migrations', 'declaration_identity'], 'Migration declaration');

        $inventory = [];
        foreach ($data['inventory'] as $entry) {
            if (!is_array($entry)) { throw new \RuntimeException('Package inventory entry is invalid.'); }
            $this->assertKeys($entry, ['path', 'byte_size', 'sha256', 'ownership'], 'Package inventory entry');
            if ($entry['path'] === self::PATH) {
                throw new \RuntimeException('Package manifest cannot be part of its own inventory.');
            }
            $inventory[] = new PackageInventoryEntry($entry['path'], $entry['byte_size'], $entry['sha256'], $entry['ownership']);
        }

        try {
            $contract = new PackageContract(
                $data['package_type'],
                $data['manifest_contract_version'],
                $data['target_webcore_version'],
                $data['release_identity'],
                $data['source_tree_identity'],
                new PackageCompatibility($source['minimum_source_version'], $source['maximum_source_version']),
                new PackageRuntimeCompatibility($runtime['minimum_php_version'], $runtime['minimum_database_versions'], $runtime['required_extensions']),
                $inventory,
                new PackageMigrationDeclaration($migration['declares_core_migrations'], $migration['declaration_identity'])
            );
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Package manifest contract is invalid.', 0, $exception);
        }

        return new PackageManifest($contract, self::PATH, $payload->withoutPaths([self::PATH]));
    }

    private function assertKeys(array $data, array $expected, string $label): void
    {
        if (array_keys($data) !== $expected) {
            throw new \RuntimeException($label . ' contains unsupported, missing, or reordered fields.');
        }
    }
}
