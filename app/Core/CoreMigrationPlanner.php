<?php

namespace Copot\Core;

use PDO;

final class CoreMigrationPlanner
{
    public function __construct(private string $canonicalCurrentVersion = Version::CURRENT)
    {
        PackageVersion::assertValid($canonicalCurrentVersion);
    }

    public function plan(
        InstalledStateInspection $installed,
        PackageContract $package,
        CoreMigrationRegistry $registry,
        CoreMigrationLedger $ledger,
        PDO $connection
    ): CoreMigrationPlan {
        try {
            $records = $ledger->records($connection);
        } catch (\Throwable $exception) {
            return CoreMigrationPlan::rejected('Applied migration history could not be read.');
        }

        if ($installed->status() === InstalledStateStatus::FRESH) {
            if ($records !== [] || PackageVersion::compare($package->targetWebcoreVersion(), $this->canonicalCurrentVersion) !== 0) {
                return CoreMigrationPlan::rejected('Fresh installation requires an empty migration ledger and canonical current target.');
            }

            return CoreMigrationPlan::allow($this->canonicalCurrentVersion, $this->canonicalCurrentVersion, null, null, [], true);
        }

        if ($installed->status() !== InstalledStateStatus::COMMITTED || $installed->snapshot() === null) {
            return CoreMigrationPlan::rejected('Installed state is not migration-plannable; explicit legacy adoption is required.');
        }

        $snapshot = $installed->snapshot();

        if ($snapshot->schemaStateIdentity() === null || $snapshot->migrationStateIdentity() === null) {
            return CoreMigrationPlan::rejected('Committed state lacks approved schema or migration identity.');
        }

        if (PackageVersion::compare($package->targetWebcoreVersion(), $snapshot->webcoreVersion()) < 0) {
            return CoreMigrationPlan::rejected('Downgrade and reverse migration are unsupported.');
        }

        try {
            $registry->resolve($package->migrationDeclaration());
        } catch (\RuntimeException $exception) {
            return CoreMigrationPlan::rejected($exception->getMessage());
        }

        $descriptors = $registry->migrations();

        foreach ($records as $index => $record) {
            $descriptor = $descriptors[$index] ?? null;

            if (!$descriptor instanceof CoreMigrationDescriptor
                || $record->migrationId() !== $descriptor->id()
                || $record->sequence() !== $descriptor->sequence()
                || $record->checksum() !== $descriptor->checksum()
                || $record->targetWebcoreVersion() !== $descriptor->targetWebcoreVersion()
                || $record->targetSchemaIdentity() !== $descriptor->targetSchemaIdentity()
            ) {
                return CoreMigrationPlan::rejected('Applied migration history is not a known ordered registry prefix or was modified.');
            }

            if (PackageVersion::compare($record->targetWebcoreVersion(), $package->targetWebcoreVersion()) > 0) {
                return CoreMigrationPlan::rejected('Applied migration history is ahead of the package target.');
            }
        }

        if (CoreMigrationStateIdentity::fromRecords($records) !== $snapshot->migrationStateIdentity()) {
            return CoreMigrationPlan::rejected('Committed migration identity does not match applied migration history.');
        }

        if ($records !== []) {
            $lastRecord = $records[count($records) - 1];

            if ($lastRecord->targetWebcoreVersion() !== $snapshot->webcoreVersion() || $lastRecord->targetSchemaIdentity() !== $snapshot->schemaStateIdentity()) {
                return CoreMigrationPlan::rejected('Committed Webcore/schema state contradicts applied migration history.');
            }
        }

        $appliedCount = count($records);
        $virtualVersion = $snapshot->webcoreVersion();
        $virtualSchema = $snapshot->schemaStateIdentity();
        $planned = [];

        foreach (array_slice($descriptors, $appliedCount) as $descriptor) {
            if (PackageVersion::compare($descriptor->targetWebcoreVersion(), $package->targetWebcoreVersion()) > 0) {
                break;
            }

            if (!$descriptor->appliesTo($virtualVersion)) {
                return CoreMigrationPlan::rejected('A required migration is not applicable to the evolving virtual state.');
            }

            $planned[] = $descriptor;
            $virtualVersion = $descriptor->targetWebcoreVersion();
            $virtualSchema = $descriptor->targetSchemaIdentity();
        }

        if (!$package->migrationDeclaration()->declaresCoreMigrations() && $planned !== []) {
            return CoreMigrationPlan::rejected('Package omitted a required Core migration declaration.');
        }

        if (PackageVersion::compare($virtualVersion, $package->targetWebcoreVersion()) !== 0) {
            return CoreMigrationPlan::rejected('Migration registry cannot converge to the package target.');
        }

        return CoreMigrationPlan::allow(
            $snapshot->webcoreVersion(),
            $virtualVersion,
            $snapshot->schemaStateIdentity(),
            $virtualSchema,
            $planned
        );
    }
}
