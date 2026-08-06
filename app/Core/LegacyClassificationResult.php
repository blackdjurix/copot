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
}
