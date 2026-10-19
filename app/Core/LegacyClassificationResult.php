<?php

namespace Copot\Core;

final class LegacyClassificationResult
{
    private function __construct(
        private string $classification,
        private string $reason,
        private ?string $sourceWebcoreVersion,
        private ?string $sourceSchemaIdentity,
        private array $records,
        private ?InstalledStateSnapshot $installedSnapshot
    ) {
        if (!in_array($classification, LegacyClassification::values(), true)) {
            throw new \InvalidArgumentException('Legacy classification is invalid.');
        }

        foreach ($records as $record) {
            if (!$record instanceof AppliedMigrationRecord) {
                throw new \InvalidArgumentException('Legacy classification contains an invalid migration record.');
            }
        }
    }

    public static function canonicalBaseline(string $schemaIdentity, string $sourceWebcoreVersion = Version::CURRENT, ?InstalledStateSnapshot $installedSnapshot = null): self
    {
        PackageVersion::assertValid($sourceWebcoreVersion);

        return new self(LegacyClassification::CANONICAL_SCHEMA_BASELINE, '', $sourceWebcoreVersion, $schemaIdentity, [], $installedSnapshot);
    }

    public static function knownMigrationPrefix(array $records, ?InstalledStateSnapshot $installedSnapshot = null): self
    {
        if ($records === []) {
            throw new \InvalidArgumentException('A known migration prefix cannot be empty.');
        }

        $last = $records[count($records) - 1];
        if (!$last instanceof AppliedMigrationRecord) {
            throw new \InvalidArgumentException('Known migration prefix contains an invalid record.');
        }

        return new self(
            LegacyClassification::KNOWN_MIGRATION_PREFIX,
            '',
            $last->targetWebcoreVersion(),
            $last->targetSchemaIdentity(),
            array_values($records),
            $installedSnapshot
        );
    }

    public static function unknown(string $reason): self
    {
        return new self(LegacyClassification::UNKNOWN_OR_UNPROVABLE, $reason, null, null, [], null);
    }

    public static function committed(InstalledStateSnapshot $snapshot): self
    {
        return new self(
            LegacyClassification::COMMITTED_LIFECYCLE_STATE,
            '',
            $snapshot->webcoreVersion(),
            $snapshot->schemaStateIdentity(),
            [],
            $snapshot
        );
    }

    public function classification(): string { return $this->classification; }
    public function reason(): string { return $this->reason; }
    public function sourceWebcoreVersion(): ?string { return $this->sourceWebcoreVersion; }
    public function sourceSchemaIdentity(): ?string { return $this->sourceSchemaIdentity; }
    public function records(): array { return $this->records; }
    public function installedSnapshot(): ?InstalledStateSnapshot { return $this->installedSnapshot; }

    public function isLegacyCandidate(): bool
    {
        return in_array($this->classification, [
            LegacyClassification::CANONICAL_SCHEMA_BASELINE,
            LegacyClassification::KNOWN_MIGRATION_PREFIX,
        ], true);
    }

    /** @return array<string,mixed> */
    public function toEvidenceArray(string $registryIdentity, string $installedStateStatus): array
    {
        if ($registryIdentity === '' || !in_array($installedStateStatus, [InstalledStateStatus::LEGACY, InstalledStateStatus::COMMITTED, InstalledStateStatus::INCONSISTENT, InstalledStateStatus::INVALID, InstalledStateStatus::FRESH], true)) {
            throw new \InvalidArgumentException('Legacy classification evidence context is invalid.');
        }
        return [
            'classification' => $this->classification,
            'reason' => $this->reason,
            'source_webcore_version' => $this->sourceWebcoreVersion,
            'source_schema_identity' => $this->sourceSchemaIdentity,
            'migration_registry_identity' => $registryIdentity,
            'records' => array_map(static fn (AppliedMigrationRecord $record): array => [
                'migration_id' => $record->migrationId(),
                'sequence' => $record->sequence(),
                'target_webcore_version' => $record->targetWebcoreVersion(),
                'target_schema_identity' => $record->targetSchemaIdentity(),
                'checksum' => $record->checksum(),
                'applied_at' => $record->appliedAt()->format(DATE_ATOM),
            ], $this->records),
            'installed_state_status' => $installedStateStatus,
            'installed_snapshot' => $this->installedSnapshot === null ? null : [
                'webcore_version' => $this->installedSnapshot->webcoreVersion(),
                'installed_at' => $this->installedSnapshot->installedAt()->format(DATE_ATOM),
                'release_identity' => $this->installedSnapshot->releaseIdentity(),
                'source_tree_identity' => $this->installedSnapshot->sourceTreeIdentity(),
                'manifest_contract_version' => $this->installedSnapshot->manifestContractVersion(),
                'schema_state_identity' => $this->installedSnapshot->schemaStateIdentity(),
                'migration_state_identity' => $this->installedSnapshot->migrationStateIdentity(),
            ],
        ];
    }

    /** @param array<string,mixed> $evidence */
    public static function fromEvidenceArray(array $evidence): self
    {
        $required = ['classification', 'reason', 'source_webcore_version', 'source_schema_identity', 'migration_registry_identity', 'records', 'installed_state_status', 'installed_snapshot'];
        if (array_keys($evidence) !== $required || !is_string($evidence['classification']) || !in_array($evidence['classification'], LegacyClassification::values(), true) || !is_string($evidence['reason']) || !is_string($evidence['migration_registry_identity']) || $evidence['migration_registry_identity'] === '' || !is_array($evidence['records']) || !is_string($evidence['installed_state_status']) || !is_array($evidence['installed_snapshot']) && $evidence['installed_snapshot'] !== null) {
            throw new \InvalidArgumentException('Legacy classification evidence shape is invalid.');
        }
        if (($evidence['source_webcore_version'] !== null && !is_string($evidence['source_webcore_version'])) || ($evidence['source_schema_identity'] !== null && !is_string($evidence['source_schema_identity']))) {
            throw new \InvalidArgumentException('Legacy classification source identity is invalid.');
        }
        $records = [];
        foreach ($evidence['records'] as $record) {
            if (!is_array($record) || array_keys($record) !== ['migration_id', 'sequence', 'target_webcore_version', 'target_schema_identity', 'checksum', 'applied_at']) {
                throw new \InvalidArgumentException('Legacy migration-prefix evidence is invalid.');
            }
            try {
                $records[] = new AppliedMigrationRecord($record['migration_id'], $record['sequence'], $record['target_webcore_version'], $record['target_schema_identity'], $record['checksum'], new \DateTimeImmutable($record['applied_at']));
            } catch (\Throwable $exception) {
                throw new \InvalidArgumentException('Legacy migration-prefix evidence is invalid.', 0, $exception);
            }
        }
        $snapshot = null;
        if ($evidence['installed_snapshot'] !== null) {
            $snapshotData = $evidence['installed_snapshot'];
            $snapshotKeys = ['webcore_version', 'installed_at', 'release_identity', 'source_tree_identity', 'manifest_contract_version', 'schema_state_identity', 'migration_state_identity'];
            if (array_keys($snapshotData) !== $snapshotKeys) throw new \InvalidArgumentException('Legacy installed-state evidence is invalid.');
            try {
                $snapshot = new InstalledStateSnapshot($snapshotData['webcore_version'], new \DateTimeImmutable($snapshotData['installed_at']), $snapshotData['release_identity'], $snapshotData['source_tree_identity'], $snapshotData['manifest_contract_version'], $snapshotData['schema_state_identity'], $snapshotData['migration_state_identity']);
            } catch (\Throwable $exception) {
                throw new \InvalidArgumentException('Legacy installed-state evidence is invalid.', 0, $exception);
            }
        }
        return match ($evidence['classification']) {
            LegacyClassification::CANONICAL_SCHEMA_BASELINE => ($evidence['source_webcore_version'] !== null && $evidence['source_schema_identity'] !== null)
                ? self::canonicalBaseline($evidence['source_schema_identity'], $evidence['source_webcore_version'], $snapshot)
                : throw new \InvalidArgumentException('Canonical baseline evidence is incomplete.'),
            LegacyClassification::KNOWN_MIGRATION_PREFIX => ($evidence['source_webcore_version'] !== null && $evidence['source_schema_identity'] !== null && $records !== [])
                ? self::knownMigrationPrefix($records, $snapshot)
                : throw new \InvalidArgumentException('Known migration-prefix evidence is incomplete.'),
            LegacyClassification::UNKNOWN_OR_UNPROVABLE => self::unknown($evidence['reason']),
            LegacyClassification::COMMITTED_LIFECYCLE_STATE => $snapshot === null
                ? throw new \InvalidArgumentException('Committed classification evidence is incomplete.')
                : self::committed($snapshot),
        };
    }
}
