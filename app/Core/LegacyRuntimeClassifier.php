<?php

namespace Copot\Core;

use PDO;

final class LegacyRuntimeClassifier
{
    public function __construct(
        private CanonicalSchemaBaselineVerifier $canonicalSchema,
        private ?CanonicalSchemaBaselineCatalog $baselineCatalog = null
    )
    {
    }

    public function classify(
        InstalledStateInspection $installed,
        PDO $connection,
        string $canonicalSchemaPath,
        CoreMigrationRegistry $registry
    ): LegacyClassificationResult {
        if ($installed->status() === InstalledStateStatus::COMMITTED && $installed->snapshot() !== null) {
            return LegacyClassificationResult::committed($installed->snapshot());
        }

        if ($installed->status() !== InstalledStateStatus::LEGACY) {
            return LegacyClassificationResult::unknown('Runtime is not an uncommitted legacy installation.');
        }

        try {
            $records = (new CoreMigrationLedger())->records($connection);
        } catch (\Throwable) {
            $baseline = $this->baselineCatalog?->verify($connection, $this->canonicalSchema);
            if ($baseline instanceof CanonicalSchemaBaselineDescriptor && !$baseline->migrationLedgerPresent()) {
                return LegacyClassificationResult::canonicalBaseline($baseline->identity(), $baseline->webcoreVersion(), $installed->snapshot());
            }
            return LegacyClassificationResult::unknown('Applied migration history could not be read authoritatively.');
        }

        if ($records === []) {
            try {
                $baseline = $this->baselineCatalog?->verify($connection, $this->canonicalSchema);
                if ($baseline instanceof CanonicalSchemaBaselineDescriptor) {
                    return LegacyClassificationResult::canonicalBaseline(
                        $baseline->identity(),
                        $baseline->webcoreVersion(),
                        $installed->snapshot()
                    );
                }

                if (!$this->canonicalSchema->verify($connection, $canonicalSchemaPath)->passed()) {
                    return LegacyClassificationResult::unknown('Canonical schema baseline could not be proven.');
                }

                return LegacyClassificationResult::canonicalBaseline(
                    $this->canonicalSchema->identity($canonicalSchemaPath),
                    Version::CURRENT,
                    $installed->snapshot()
                );
            } catch (\Throwable) {
                return LegacyClassificationResult::unknown('Canonical schema baseline could not be proven.');
            }
        }

        $descriptors = $registry->migrations();
        foreach ($records as $index => $record) {
            $descriptor = $descriptors[$index] ?? null;
            if (!$descriptor instanceof CoreMigrationDescriptor
                || $record->migrationId() !== $descriptor->id()
                || $record->sequence() !== $descriptor->sequence()
                || $record->targetWebcoreVersion() !== $descriptor->targetWebcoreVersion()
                || $record->targetSchemaIdentity() !== $descriptor->targetSchemaIdentity()
                || $record->checksum() !== $descriptor->checksum()) {
                return LegacyClassificationResult::unknown('Applied migration history is not an authoritative known registry prefix.');
            }
        }

        return LegacyClassificationResult::knownMigrationPrefix($records, $installed->snapshot());
    }
}
