<?php

namespace Copot\Core;

final class LegacyReconciliationPlanner
{
    public function plan(
        TrustedWebcorePackageTarget $target,
        LegacyClassificationResult $classification,
        RuntimeCompatibilityContext $runtime,
        CoreMigrationRegistry $registry,
        LiveTreePathGuard $liveGuard,
        string $canonicalCurrentVersion = Version::CURRENT
    ): LegacyReconciliationPlan {
        if (!$classification->isLegacyCandidate()) {
            throw new \RuntimeException($classification->classification() === LegacyClassification::COMMITTED_LIFECYCLE_STATE
                ? 'Committed lifecycle state is not an IU2 legacy reconciliation candidate.'
                : 'Legacy runtime state is unknown or unprovable.');
        }
        if (!$runtime->supports($target->contract()->runtimeCompatibility())) {
            throw new \RuntimeException('Runtime requirements are not satisfied by the trusted package target.');
        }

        PackageVersion::assertValid($canonicalCurrentVersion);
        $migrationPlan = $this->migrationPlan($target->contract(), $classification, $registry, $canonicalCurrentVersion);
        $actions = $this->filesystemActions($target->contract(), $liveGuard);
        $preStateIdentity = self::hash([
            'classification' => $classification->classification(),
            'source_webcore_version' => $classification->sourceWebcoreVersion(),
            'source_schema_identity' => $classification->sourceSchemaIdentity(),
            'migration_state_identity' => CoreMigrationStateIdentity::fromRecords($classification->records()),
            'installed_marker_identity' => $classification->installedSnapshot() === null ? null : [
                'version' => $classification->installedSnapshot()->webcoreVersion(),
                'installed_at' => $classification->installedSnapshot()->installedAt()->format(DATE_ATOM),
            ],
        ]);
        $expectedMigrationStateIdentity = CoreMigrationStateIdentity::fromRecordsAndDescriptors(
            $classification->records(),
            $migrationPlan->migrations()
        );
        $expectedPostStateIdentity = self::hash([
            'webcore_version' => $target->contract()->targetWebcoreVersion(),
            'release_identity' => $target->contract()->releaseIdentity(),
            'schema_state_identity' => $migrationPlan->virtualFinalSchemaIdentity(),
            'migration_state_identity' => $expectedMigrationStateIdentity,
            'package_identity' => $target->packageIdentity(),
        ]);

        return new LegacyReconciliationPlan(
            $target,
            $classification,
            $actions,
            $migrationPlan,
            $preStateIdentity,
            $expectedPostStateIdentity,
            $expectedMigrationStateIdentity
        );
    }

    private function migrationPlan(PackageContract $package, LegacyClassificationResult $classification, CoreMigrationRegistry $registry, string $canonicalCurrentVersion): CoreMigrationPlan
    {
        $sourceVersion = $classification->sourceWebcoreVersion();
        $sourceSchema = $classification->sourceSchemaIdentity();
        if ($sourceVersion === null || $sourceSchema === null) {
            return CoreMigrationPlan::rejected('Legacy database source identity is unavailable.');
        }
        if (PackageVersion::compare($package->targetWebcoreVersion(), $sourceVersion) < 0) {
            return CoreMigrationPlan::rejected('Downgrade and reverse migration are unsupported.');
        }

        try {
            $descriptors = $registry->resolve($package->migrationDeclaration());
        } catch (\RuntimeException $exception) {
            return CoreMigrationPlan::rejected($exception->getMessage());
        }

        $planned = [];
        $virtualVersion = $sourceVersion;
        $virtualSchema = $sourceSchema;
        $offset = count($classification->records());
        foreach (array_slice($descriptors, $offset) as $descriptor) {
            if (PackageVersion::compare($descriptor->targetWebcoreVersion(), $package->targetWebcoreVersion()) > 0) {
                break;
            }
            if (!$descriptor->appliesTo($virtualVersion)) {
                return CoreMigrationPlan::rejected('A required migration is not applicable to the evolving legacy state.');
            }
            $planned[] = $descriptor;
            $virtualVersion = $descriptor->targetWebcoreVersion();
            $virtualSchema = $descriptor->targetSchemaIdentity();
        }

        if ($classification->classification() === LegacyClassification::CANONICAL_SCHEMA_BASELINE
            && PackageVersion::compare($sourceVersion, $canonicalCurrentVersion) !== 0) {
            return CoreMigrationPlan::rejected('Canonical schema baseline version is inconsistent with the authoritative current schema contract.');
        }
        if (!$package->migrationDeclaration()->declaresCoreMigrations() && $planned !== []) {
            return CoreMigrationPlan::rejected('Package omitted a required Core migration declaration.');
        }
        if (PackageVersion::compare($virtualVersion, $package->targetWebcoreVersion()) !== 0) {
            return CoreMigrationPlan::rejected('Migration registry cannot converge the proven legacy state to the package target.');
        }

        return CoreMigrationPlan::allow(
            $sourceVersion,
            $virtualVersion,
            $sourceSchema,
            $virtualSchema,
            $planned,
            $classification->classification() === LegacyClassification::CANONICAL_SCHEMA_BASELINE
        );
    }

    private function filesystemActions(PackageContract $package, LiveTreePathGuard $liveGuard): array
    {
        $actions = [];
        foreach ($package->inventory() as $entry) {
            if (!$entry instanceof PackageInventoryEntry || $entry->ownership() !== PackageOwnership::PACKAGE_OWNED) {
                throw new \RuntimeException('Trusted package inventory contains unsupported ownership.');
            }
            $path = $liveGuard->destination($entry->path());
            if (is_link($path) || (file_exists($path) && !is_file($path))) {
                throw new \RuntimeException('Package-owned target path is not a regular file.');
            }
            $action = FilesystemReconciliationAction::CREATE;
            $expectedLiveSha256 = null;
            if (is_file($path)) {
                $liveHash = @hash_file('sha256', $path);
                $action = ((int) @filesize($path) === $entry->byteSize() && $liveHash === $entry->sha256())
                    ? FilesystemReconciliationAction::UNCHANGED
                    : FilesystemReconciliationAction::REPLACE;
                $expectedLiveSha256 = $action === FilesystemReconciliationAction::UNCHANGED ? $entry->sha256() : $liveHash;
            }
            $actions[] = new FilesystemReconciliationAction($action, $entry->path(), $entry->byteSize(), $entry->sha256(), $expectedLiveSha256);
        }
        usort($actions, static fn (FilesystemReconciliationAction $left, FilesystemReconciliationAction $right): int => strcmp($left->path(), $right->path()));
        return $actions;
    }

    private static function hash(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
