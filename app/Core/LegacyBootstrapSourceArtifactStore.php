<?php

namespace Copot\Core;

final class LegacyBootstrapSourceArtifactStore
{
    private string $root;
    public function __construct(string $storagePath, private ?CoreMigrationRegistry $registry = null)
    {
        if (is_link($storagePath) || !is_dir($storagePath) || !is_writable($storagePath)) throw new \RuntimeException('Legacy bootstrap storage is unavailable.');
        $base = realpath($storagePath);
        if ($base === false) throw new \RuntimeException('Legacy bootstrap storage is unavailable.');
        $this->root = $base . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'legacy-bootstrap-source';
        if (!is_dir($this->root) && !mkdir($this->root, 0700, true) && !is_dir($this->root)) throw new \RuntimeException('Legacy bootstrap storage could not be created.');
        if (is_link($this->root) || !is_dir($this->root) || !is_writable($this->root)) throw new \RuntimeException('Legacy bootstrap storage is invalid.');
    }

    /** @param array<string,mixed> $record */
    public function publish(array $record): string
    {
        $this->validateRecord($record);
        $path = $this->root . DIRECTORY_SEPARATOR . hash('sha256', $record['operation_identity']) . '.json';
        $contents = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;

        if (file_exists($path) || is_link($path)) {
            if (is_link($path) || !is_file($path) || !is_readable($path)) {
                throw new \RuntimeException('Legacy bootstrap artifact is invalid and cannot be reused.');
            }
            $existingContents = @file_get_contents($path);
            if (!is_string($existingContents) || !function_exists('hash_file') || @hash_file('sha256', $path) === false) {
                throw new \RuntimeException('Legacy bootstrap artifact integrity could not be verified.');
            }
            try {
                $existing = json_decode($existingContents, true, 32, JSON_THROW_ON_ERROR);
            } catch (\Throwable $exception) {
                throw new \RuntimeException('Legacy bootstrap artifact is malformed.', 0, $exception);
            }
            if (!is_array($existing)) {
                throw new \RuntimeException('Legacy bootstrap artifact is malformed.');
            }
            $this->validateRecord($existing);
            $existingComparable = $existing;
            $recordComparable = $record;
            unset($existingComparable['captured_at'], $recordComparable['captured_at']);
            if ($existingComparable !== $recordComparable) {
                throw new \RuntimeException('Legacy bootstrap artifact identity collision detected.');
            }
            return $path;
        }

        $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $handle = fopen($temporary, 'xb');
        if (!is_resource($handle)) throw new \RuntimeException('Legacy bootstrap artifact could not be prepared.');
        try {
            $offset = 0;
            while ($offset < strlen($contents)) {
                $written = fwrite($handle, substr($contents, $offset));
                if (!is_int($written) || $written < 1) throw new \RuntimeException('Legacy bootstrap artifact could not be written.');
                $offset += $written;
            }
            if (!fflush($handle) || !function_exists('fsync') || !fsync($handle)) throw new \RuntimeException('Legacy bootstrap artifact could not be durably finalized.');
        }
        finally { fclose($handle); }
        chmod($temporary, 0600);
        if (!rename($temporary, $path)) { @unlink($temporary); throw new \RuntimeException('Legacy bootstrap artifact could not be activated.'); }
        if (@filesize($path) !== strlen($contents) || @hash_file('sha256', $path) !== hash('sha256', $contents) || @file_get_contents($path) !== $contents) {
            throw new \RuntimeException('Legacy bootstrap artifact failed read-back verification.');
        }
        return $path;
    }

    /**
     * Rehydrate immutable provisional evidence only. This never inspects the
     * current live tree and never promotes the result to committed authority.
     *
     * @param array<string,string>|null $expected
     */
    public function rehydrate(string $path, ?array $expected = null): LegacyBootstrapSourceArtifact
    {
        $realRoot = realpath($this->root);
        $realPath = realpath($path);
        if ($realRoot === false || $realPath === false || dirname($realPath) !== $realRoot || is_link($path) || !is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('Legacy bootstrap artifact is unavailable.');
        }
        $contents = @file_get_contents($realPath);
        if (!is_string($contents) || $contents === '' || !str_ends_with($contents, PHP_EOL)) {
            throw new \RuntimeException('Legacy bootstrap artifact integrity could not be verified.');
        }
        try {
            StrictJsonDuplicateKeyGuard::validate($contents);
            $record = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Legacy bootstrap artifact is malformed.', 0, $exception);
        }
        if (!is_array($record)) throw new \RuntimeException('Legacy bootstrap artifact is malformed.');
        if (json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL !== $contents) {
            throw new \RuntimeException('Legacy bootstrap artifact canonical encoding changed.');
        }
        $this->validateRecord($record);
        $expected ??= [];
        foreach ($expected as $key => $value) {
            if (!in_array($key, ['operation_identity', 'installation_identity', 'plan_identity', 'package_identity', 'target_inventory_fingerprint', 'archive_identity', 'payload_identity', 'apply_plan_identity', 'source_inventory_fingerprint'], true) || !is_string($value) || $value === '') {
                throw new \InvalidArgumentException('Legacy bootstrap artifact expectation is invalid.');
            }
            $actual = $key === 'source_inventory_fingerprint' ? $record['source_inventory']['fingerprint'] : $record[$key];
            if (!hash_equals($actual, $value)) throw new \RuntimeException('Legacy bootstrap artifact identity binding mismatch.');
        }
        $expectedPath = $this->root . DIRECTORY_SEPARATOR . hash('sha256', $record['operation_identity']) . '.json';
        if (realpath($expectedPath) !== $realPath) throw new \RuntimeException('Legacy bootstrap artifact path identity mismatch.');
        return LegacyBootstrapSourceArtifact::fromRecord($record, $realPath);
    }

    /** @param array<string,mixed> $record */
    private function validateRecord(array $record): void
    {
        $required = ['serialization_version','operation_type','operation_identity','installation_identity','plan_identity','package_identity','target_inventory_fingerprint','archive_identity','payload_identity','apply_plan_identity','recovery_identity','captured_at','provenance','legacy_package_evidence','legacy_classification','source_inventory','collisions','status'];
        if (array_keys($record) !== $required || $record['serialization_version'] !== 3 || $record['operation_type'] !== LegacyBootstrapPreparation::OPERATION_TYPE) throw new \InvalidArgumentException('Legacy bootstrap artifact shape is invalid.');
        foreach (['operation_identity','installation_identity','plan_identity','package_identity','target_inventory_fingerprint','captured_at','status'] as $key) {
            if (!is_string($record[$key]) || $record[$key] === '') throw new \InvalidArgumentException('Legacy bootstrap artifact identity is invalid.');
        }
        foreach (['plan_identity', 'target_inventory_fingerprint', 'archive_identity', 'payload_identity', 'apply_plan_identity'] as $key) {
            if (preg_match('/^[a-f0-9]{64}$/D', $record[$key]) !== 1) throw new \InvalidArgumentException('Legacy bootstrap artifact fingerprint is invalid.');
        }
        if ($record['payload_identity'] !== $record['apply_plan_identity']) throw new \InvalidArgumentException('Legacy bootstrap payload/apply-plan identity is inconsistent.');
        if (!is_string($record['operation_identity']) || !str_starts_with($record['operation_identity'], 'legacy-to-v3-bootstrap-sha256:')) throw new \InvalidArgumentException('Legacy bootstrap operation identity is invalid.');
        if ($record['recovery_identity'] !== null && !is_string($record['recovery_identity'])) throw new \InvalidArgumentException('Legacy bootstrap recovery identity is invalid.');
        if (!is_array($record['provenance']) || !is_array($record['legacy_package_evidence']) || !is_array($record['legacy_classification']) || !is_array($record['source_inventory']) || !is_array($record['collisions'])) throw new \InvalidArgumentException('Legacy bootstrap artifact evidence is invalid.');
        $classification = LegacyClassificationResult::fromEvidenceArray($record['legacy_classification']);
        if ($this->registry !== null) {
            if ($record['legacy_classification']['migration_registry_identity'] !== $this->registry->identity()) throw new \InvalidArgumentException('Legacy migration registry identity changed.');
            $descriptors = $this->registry->migrations();
            foreach ($classification->records() as $index => $recorded) {
                $descriptor = $descriptors[$index] ?? null;
                if (!$descriptor instanceof CoreMigrationDescriptor || $recorded->migrationId() !== $descriptor->id() || $recorded->sequence() !== $descriptor->sequence() || $recorded->targetWebcoreVersion() !== $descriptor->targetWebcoreVersion() || $recorded->targetSchemaIdentity() !== $descriptor->targetSchemaIdentity() || $recorded->checksum() !== $descriptor->checksum()) {
                    throw new \InvalidArgumentException('Legacy migration-prefix evidence does not match the canonical registry.');
                }
            }
        }
        if (array_keys($record['source_inventory']) !== ['fingerprint', 'entries'] || !is_string($record['source_inventory']['fingerprint']) || preg_match('/^[a-f0-9]{64}$/D', $record['source_inventory']['fingerprint']) !== 1 || !is_array($record['source_inventory']['entries'])) throw new \InvalidArgumentException('Legacy bootstrap source inventory is invalid.');
        $source = [];
        foreach ($record['source_inventory']['entries'] as $entry) {
            if (!is_array($entry) || array_keys($entry) !== ['root', 'path', 'byte_size', 'sha256', 'provenance', 'ownership_evidence']) throw new \InvalidArgumentException('Legacy bootstrap source entry is invalid.');
            $source[] = new LegacyBootstrapSourceEntry($entry['root'], $entry['path'], $entry['byte_size'], $entry['sha256'], $entry['provenance'], $entry['ownership_evidence']);
        }
        if ((new LegacyBootstrapSourceInventory($source))->fingerprint() !== $record['source_inventory']['fingerprint']) throw new \InvalidArgumentException('Legacy bootstrap source fingerprint binding is invalid.');
        if (!in_array($record['status'], [LegacyBootstrapPreparation::ELIGIBLE, LegacyBootstrapPreparation::BLOCKED, LegacyBootstrapPreparation::INCOMPLETE], true)) throw new \InvalidArgumentException('Legacy bootstrap status is invalid.');
        foreach ($record['collisions'] as $collision) {
            if (!is_array($collision) || !isset($collision['action'], $collision['root'], $collision['path'], $collision['reason']) || !in_array($collision['action'], ['ADD', 'PRESERVE', 'REPLACE', 'BLOCK', 'UNVERIFIED'], true) || !is_string($collision['root']) || !is_string($collision['path']) || !is_string($collision['reason'])) throw new \InvalidArgumentException('Legacy bootstrap collision evidence is invalid.');
        }
    }
}
