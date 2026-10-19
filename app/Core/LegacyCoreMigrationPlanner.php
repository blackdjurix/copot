<?php

namespace Copot\Core;

/** Canonical legacy migration-only planning boundary; performs no live I/O. */
final class LegacyCoreMigrationPlanner
{
    public function plan(
        PackageContract $package,
        LegacyClassificationResult $classification,
        CoreMigrationRegistry $registry,
        string $canonicalCurrentVersion = Version::CURRENT
    ): CoreMigrationPlan {
        PackageVersion::assertValid($canonicalCurrentVersion);
        if (!$classification->isLegacyCandidate()) return CoreMigrationPlan::rejected('Legacy database source identity is unavailable.');
        $sourceVersion = $classification->sourceWebcoreVersion();
        $sourceSchema = $classification->sourceSchemaIdentity();
        if ($sourceVersion === null || $sourceSchema === null) return CoreMigrationPlan::rejected('Legacy database source identity is unavailable.');
        if (PackageVersion::compare($package->targetWebcoreVersion(), $sourceVersion) < 0) return CoreMigrationPlan::rejected('Downgrade and reverse migration are unsupported.');

        try { $descriptors = $registry->resolve($package->migrationDeclaration()); }
        catch (\RuntimeException $exception) { return CoreMigrationPlan::rejected($exception->getMessage()); }

        foreach ($classification->records() as $index => $record) {
            $descriptor = $descriptors[$index] ?? null;
            if (!$descriptor instanceof CoreMigrationDescriptor || $record->migrationId() !== $descriptor->id() || $record->sequence() !== $descriptor->sequence() || $record->targetWebcoreVersion() !== $descriptor->targetWebcoreVersion() || $record->targetSchemaIdentity() !== $descriptor->targetSchemaIdentity() || $record->checksum() !== $descriptor->checksum()) {
                return CoreMigrationPlan::rejected('Applied migration history is not an authoritative known registry prefix.');
            }
        }

        $planned = [];
        $virtualVersion = $sourceVersion;
        $virtualSchema = $sourceSchema;
        $offset = count($classification->records());
        foreach (array_slice($descriptors, $offset) as $descriptor) {
            if (PackageVersion::compare($descriptor->targetWebcoreVersion(), $package->targetWebcoreVersion()) > 0) break;
            if (!$descriptor->appliesTo($virtualVersion)) return CoreMigrationPlan::rejected('A required migration is not applicable to the evolving legacy state.');
            $planned[] = $descriptor;
            $virtualVersion = $descriptor->targetWebcoreVersion();
            $virtualSchema = $descriptor->targetSchemaIdentity();
        }
        if (!$package->migrationDeclaration()->declaresCoreMigrations() && $planned !== []) return CoreMigrationPlan::rejected('Package omitted a required Core migration declaration.');
        if (PackageVersion::compare($virtualVersion, $package->targetWebcoreVersion()) !== 0) return CoreMigrationPlan::rejected('Migration registry cannot converge the proven legacy state to the package target.');

        return CoreMigrationPlan::allow($sourceVersion, $virtualVersion, $sourceSchema, $virtualSchema, $planned, $classification->classification() === LegacyClassification::CANONICAL_SCHEMA_BASELINE);
    }
}
