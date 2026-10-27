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

        $rawManifest = (string) file_get_contents($path);
        try {
            $data = json_decode($rawManifest, true, 32, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException $exception) {
            throw new \RuntimeException('Package manifest is invalid.', 0, $exception);
        }

        if (!is_array($data)) {
            throw new \RuntimeException('Package manifest is invalid.');
        }

        if (($data['manifest_contract_version'] ?? null) === PackageContract::V3_MANIFEST_CONTRACT_VERSION) {
            return $this->readV3($payload, $rawManifest, $path);
        }

        $expected = [
            'package_type', 'manifest_contract_version', 'target_webcore_version',
            'release_identity', 'source_tree_identity', 'source_compatibility',
            'runtime_compatibility', 'inventory', 'migration_declaration',
        ];
        if (($data['manifest_contract_version'] ?? null) === PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION) {
            $expected[] = 'target_requirements';
        }
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
                new PackageMigrationDeclaration($migration['declares_core_migrations'], $migration['declaration_identity']),
                ($data['manifest_contract_version'] === PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION)
                    ? PackageTargetRequirements::fromArray($data['target_requirements'])
                    : new PackageTargetRequirements()
            );
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Package manifest contract is invalid.', 0, $exception);
        }

        return new PackageManifest($contract, self::PATH, $payload->withoutPaths([self::PATH]));
    }

    private function readV3(StagedPayload $payload, string $rawManifest, string $path): PackageManifest
    {
        try {
            StrictJsonDuplicateKeyGuard::validate($rawManifest);
            $data = json_decode($rawManifest, true, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            $this->assertNfc($data);
            $expected = ['package_type', 'manifest_contract_version', 'target_webcore_version', 'release_identity', 'source_tree_identity', 'source_compatibility', 'runtime_compatibility', 'inventory', 'migration_declaration', 'target_requirements', 'coverage', 'target_inventory', 'source_state_requirements', 'removals', 'renames'];
            $this->assertKeys($data, $expected, 'V3 package manifest');
            $this->assertType($data['package_type'], 'string'); $this->assertType($data['manifest_contract_version'], 'int');
            if ($data['manifest_contract_version'] !== 3 || $data['package_type'] !== PackageContract::WEBCORE_PACKAGE_TYPE) { throw new \RuntimeException('V3 package manifest header is invalid.'); }
            foreach (['source_compatibility', 'runtime_compatibility', 'migration_declaration', 'target_inventory', 'source_state_requirements'] as $key) { if (!is_array($data[$key]) || array_keys($data[$key]) === array_keys(array_values($data[$key]))) { throw new \RuntimeException('V3 manifest object shape is invalid.'); } }
            foreach (['inventory', 'target_requirements', 'removals', 'renames'] as $key) { $this->assertList($data[$key], 'V3 ' . $key); }
            if (!is_string($data['coverage']) || !in_array($data['coverage'], ['full', 'partial'], true)) { throw new \RuntimeException('V3 coverage is invalid.'); }
            $source = $data['source_compatibility']; $this->assertKeys($source, ['minimum_source_version', 'maximum_source_version'], 'V3 source compatibility');
            $runtime = $data['runtime_compatibility']; $this->assertKeys($runtime, ['minimum_php_version', 'minimum_database_versions', 'required_extensions'], 'V3 runtime compatibility');
            if (!is_array($runtime['minimum_database_versions']) || array_keys($runtime['minimum_database_versions']) === array_keys(array_values($runtime['minimum_database_versions'])) || $runtime['minimum_database_versions'] === []) { throw new \RuntimeException('V3 database requirements must be a nonempty object.'); }
            foreach ($runtime['minimum_database_versions'] as $vendor => $version) { if (!is_string($vendor) || trim($vendor) === '' || !is_string($version)) { throw new \RuntimeException('V3 database requirement type is invalid.'); } }
            $this->assertList($runtime['required_extensions'], 'V3 extensions'); if ($runtime['required_extensions'] === []) { throw new \RuntimeException('V3 extensions cannot be empty.'); }
            $migration = $data['migration_declaration']; $this->assertKeys($migration, ['declares_core_migrations', 'declaration_identity'], 'V3 migration declaration');
            $this->assertType($migration['declares_core_migrations'], 'bool');
            $this->assertKeys($data['target_inventory'], ['serialization_version', 'fingerprint', 'entries'], 'V3 target inventory');
            if ($data['target_inventory']['serialization_version'] !== 1 || !is_string($data['target_inventory']['fingerprint']) || preg_match('/^[a-f0-9]{64}$/D', $data['target_inventory']['fingerprint']) !== 1) { throw new \RuntimeException('V3 target inventory header is invalid.'); }
            $this->assertList($data['target_inventory']['entries'], 'V3 target inventory entries');
            $targetEntries = [];
            foreach ($data['target_inventory']['entries'] as $entry) { $this->assertKeys($entry, ['root', 'ownership', 'path', 'byte_size', 'sha256'], 'V3 target inventory entry'); $this->assertSafeInteger($entry['byte_size']); $targetEntries[] = new CanonicalTargetInventoryEntry($entry['root'], $entry['ownership'], $entry['path'], $entry['byte_size'], $entry['sha256']); }
            $targetInventory = new CanonicalTargetInventory($targetEntries);
            if (!hash_equals($data['target_inventory']['fingerprint'], $targetInventory->fingerprint())) { throw new \RuntimeException('V3 target inventory fingerprint does not match.'); }
            $this->assertKeys($data['source_state_requirements'], ['allowed_inventory_fingerprints'], 'V3 source state requirements'); $this->assertList($data['source_state_requirements']['allowed_inventory_fingerprints'], 'V3 source inventory allowlist');
            foreach ($data['source_state_requirements']['allowed_inventory_fingerprints'] as $fingerprint) { if (!is_string($fingerprint) || preg_match('/^[a-f0-9]{64}$/D', $fingerprint) !== 1) { throw new \RuntimeException('V3 source inventory fingerprint is invalid.'); } }
            if (count($data['source_state_requirements']['allowed_inventory_fingerprints']) !== count(array_unique($data['source_state_requirements']['allowed_inventory_fingerprints']))) { throw new \RuntimeException('V3 source inventory allowlist contains duplicates.'); }
            $this->assertV3Inventory($data['inventory']);
            $this->assertV3Removals($data['removals']); $this->assertV3Renames($data['renames']);
            $sourceCompatibility = new PackageCompatibility($source['minimum_source_version'], $source['maximum_source_version']);
            $runtimeCompatibility = new PackageRuntimeCompatibility($this->normalizeV3PhpVersion($runtime['minimum_php_version']), $runtime['minimum_database_versions'], $runtime['required_extensions']);
            $contract = new PackageContract($data['package_type'], 3, $data['target_webcore_version'], $data['release_identity'], $data['source_tree_identity'], $sourceCompatibility, $runtimeCompatibility, $this->inventory($data['inventory']), new PackageMigrationDeclaration($migration['declares_core_migrations'], $migration['declaration_identity']), PackageTargetRequirements::fromArray($data['target_requirements']), $targetInventory, $data['coverage'], $data['source_state_requirements']['allowed_inventory_fingerprints'], $data['removals'], $data['renames']);
            $payloadIdentity = $contract->integrityIdentity();
            $manifestDigest = PackageV3Identity::manifestDigest($rawManifest);
            return new PackageManifest($contract, self::PATH, $payload->withoutPaths([self::PATH]), $manifestDigest, PackageV3Identity::identity($manifestDigest, $payloadIdentity));
        } catch (\Throwable $exception) { throw new \RuntimeException('Package manifest v3 is invalid.', 0, $exception); }
    }

    private function inventory(array $entries): array { $result = []; foreach ($entries as $entry) { $result[] = new PackageInventoryEntry($entry['path'], $this->safeInteger($entry['byte_size']), $entry['sha256'], $entry['ownership']); } return $result; }
    private function assertV3Inventory(array $entries): void { $seen = []; foreach ($entries as $entry) { if (!is_array($entry)) { throw new \RuntimeException('V3 inventory entry is invalid.'); } $this->assertKeys($entry, ['path', 'byte_size', 'sha256', 'ownership'], 'V3 inventory entry'); $this->assertSafeInteger($entry['byte_size']); if (!is_string($entry['path']) || !is_string($entry['sha256']) || !is_string($entry['ownership']) || $entry['path'] !== PackageInventoryEntry::normalizePath($entry['path'])) { throw new \RuntimeException('V3 inventory entry types or canonical path are invalid.'); } if ($entry['path'] === 'public/.htaccess') { throw new \RuntimeException('V3 inventory cannot claim deployment-owned .htaccess.'); } if (isset($seen[$entry['path']])) { throw new \RuntimeException('V3 inventory contains a duplicate path.'); } $seen[$entry['path']] = true; } }
    private function assertV3Removals(array $entries): void { foreach ($entries as $entry) { if (!is_array($entry)) { throw new \RuntimeException('V3 removal entry is invalid.'); } $this->assertKeys($entry, ['root', 'path', 'ownership', 'expected_source_byte_size', 'expected_source_sha256', 'reason'], 'V3 removal entry'); $this->assertSafeInteger($entry['expected_source_byte_size']); if ($entry['ownership'] !== CanonicalTargetInventoryEntry::PACKAGE_OWNED || !is_string($entry['expected_source_sha256']) || preg_match('/^[a-f0-9]{64}$/D', $entry['expected_source_sha256']) !== 1) { throw new \RuntimeException('V3 removal identity is invalid.'); } CanonicalTargetInventoryEntry::assertPath($entry['path']); if (!in_array($entry['root'], [CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PUBLIC_ROOT], true) || !in_array($entry['reason'], ['obsolete', 'replaced', 'renamed'], true)) { throw new \RuntimeException('V3 removal declaration is invalid.'); } ProtectedResourcePolicy::assertMutable($entry['root'], $entry['path']); } }
    private function assertV3Renames(array $entries): void { foreach ($entries as $entry) { if (!is_array($entry)) { throw new \RuntimeException('V3 rename entry is invalid.'); } $this->assertKeys($entry, ['source_root', 'source_path', 'target_root', 'target_path'], 'V3 rename entry'); if (!in_array($entry['source_root'], [CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PUBLIC_ROOT], true) || !in_array($entry['target_root'], [CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PUBLIC_ROOT], true)) { throw new \RuntimeException('V3 rename root is invalid.'); } CanonicalTargetInventoryEntry::assertPath($entry['source_path']); CanonicalTargetInventoryEntry::assertPath($entry['target_path']); ProtectedResourcePolicy::assertMutable($entry['source_root'], $entry['source_path']); ProtectedResourcePolicy::assertMutable($entry['target_root'], $entry['target_path']); } }
    private function assertNfc(mixed $value): void { if (is_string($value)) { CanonicalText::assert($value); } elseif (is_array($value)) { foreach ($value as $key => $child) { if (is_string($key)) { CanonicalText::assert($key); } $this->assertNfc($child); } } }
    private function assertList(mixed $value, string $label): void { if (!is_array($value) || array_keys($value) !== array_keys(array_values($value))) { throw new \RuntimeException($label . ' must be a contiguous list.'); } }
    private function assertType(mixed $value, string $type): void { $invalid = $type === 'int' ? !is_int($value) : ($type === 'bool' ? !is_bool($value) : !is_string($value)); if ($invalid) { throw new \RuntimeException('V3 manifest field type is invalid.'); } }
    private function assertSafeInteger(mixed $value): void { if (!is_int($value) || $value < 0) { throw new \RuntimeException('V3 integer is invalid or outside safe bounds.'); } }
    private function safeInteger(mixed $value): int { $this->assertSafeInteger($value); return $value; }
    private function normalizeV3PhpVersion(string $version): string { return substr_count($version, '.') === 1 ? $version . '.0' : $version; }

    private function assertKeys(array $data, array $expected, string $label): void
    {
        if (array_keys($data) !== $expected) {
            throw new \RuntimeException($label . ' contains unsupported, missing, or reordered fields.');
        }
    }
}
