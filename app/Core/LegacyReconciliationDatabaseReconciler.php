<?php

namespace Copot\Core;

use PDO;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;
use Copot\Core\BackupRecovery\RecoveryManifest;

final class LegacyReconciliationDatabaseReconciler
{
    public function __construct(
        private CoreMigrationLedger $ledger,
        private CoreMigrationRunner $runner,
        private CanonicalSchemaBaselineVerifier $canonicalSchema,
        private CoreMigrationHealthVerifier $health
    ) {}

    /**
     * @param callable(PDO):string $schemaIdentityResolver
     */
    public function reconcile(
        PDO $connection,
        LegacyReconciliationPlan $plan,
        RecoveryManifest $manifest,
        ReconciliationMutationEligibility $eligibility,
        CoreMigrationRegistry $registry,
        string $canonicalSchemaPath,
        callable $schemaIdentityResolver,
        RecoveryLifecycleStore $recoveryStore
    ): LegacyReconciliationDatabaseResult {
        $this->assertBinding($plan, $manifest, $eligibility, $recoveryStore);
        $records = $this->verifySource($connection, $plan, $registry, $canonicalSchemaPath, $schemaIdentityResolver);
        $migrationPlan = $plan->migrationPlan();

        if (!$migrationPlan->isAccepted()) {
            throw new \RuntimeException('WU1 migration plan is not accepted.');
        }

        $needsMutation = $migrationPlan->migrations() !== [];
        if ($needsMutation && !$eligibility->isValid()) {
            throw new \RuntimeException('Database reconciliation requires an active quiescence lease.');
        }

        $record = $recoveryStore->read($eligibility->recoveryIdentity());
        if ($needsMutation && !$record->mutationStarted()) {
            $record = $recoveryStore->markMutationStarting($eligibility->recoveryIdentity());
        }

        $run = $this->runner->run($connection, $migrationPlan, function () use ($eligibility): void {
            if (!$eligibility->isValid()) {
                throw new \RuntimeException('Database quiescence lease is no longer active.');
            }
        });

        if ($run->status() === MigrationRunResult::FAILED || $run->status() === MigrationRunResult::INDETERMINATE) {
            $this->markRecoveryRequired($recoveryStore, $eligibility, $run->reason());
            return new LegacyReconciliationDatabaseResult(
                $run->status() === MigrationRunResult::INDETERMINATE ? LegacyReconciliationDatabaseResult::INDETERMINATE : LegacyReconciliationDatabaseResult::FAILED,
                '',
                '',
                $run->appliedMigrationIds(),
                $run->reason()
            );
        }

        if ($needsMutation && !$eligibility->isValid()) {
            $this->markRecoveryRequired($recoveryStore, $eligibility, 'Database quiescence lease expired after migration execution.');
            return new LegacyReconciliationDatabaseResult(LegacyReconciliationDatabaseResult::INDETERMINATE, '', '', $run->appliedMigrationIds(), 'Database quiescence lease expired after migration execution.');
        }

        try {
            $finalSchemaIdentity = (string) $schemaIdentityResolver($connection);
            $finalMigrationIdentity = $this->health->identity($connection);
        } catch (\Throwable $exception) {
            $this->markRecoveryRequired($recoveryStore, $eligibility, $exception->getMessage());
            return new LegacyReconciliationDatabaseResult(LegacyReconciliationDatabaseResult::FAILED, '', '', $run->appliedMigrationIds(), $exception->getMessage());
        }

        $expectedSchemaIdentity = $migrationPlan->virtualFinalSchemaIdentity();
        if ($expectedSchemaIdentity === null || $finalSchemaIdentity !== $expectedSchemaIdentity || $finalMigrationIdentity !== $plan->expectedMigrationStateIdentity()) {
            $reason = 'Verified database schema or migration identity does not match the immutable reconciliation plan.';
            if ($needsMutation) {
                $this->markRecoveryRequired($recoveryStore, $eligibility, $reason);
            }
            return new LegacyReconciliationDatabaseResult(LegacyReconciliationDatabaseResult::FAILED, $finalSchemaIdentity, $finalMigrationIdentity, $run->appliedMigrationIds(), $reason);
        }

        return new LegacyReconciliationDatabaseResult(LegacyReconciliationDatabaseResult::COMPLETED, $finalSchemaIdentity, $finalMigrationIdentity, $run->appliedMigrationIds());
    }

    private function assertBinding(LegacyReconciliationPlan $plan, RecoveryManifest $manifest, ReconciliationMutationEligibility $eligibility, RecoveryLifecycleStore $store): void
    {
        if (!$eligibility->isValid()
            || !$eligibility->recoveryIdentity()->equals($manifest->recoveryIdentity())
            || $eligibility->operationIdentity() !== $plan->operationIdentity()
            || $eligibility->planIdentity() !== $plan->identity()
            || $eligibility->manifestIdentity() !== $manifest->identity()
            || $eligibility->targetIdentity() !== $plan->target()->packageIdentity()
            || $manifest->operationIdentity() !== $plan->operationIdentity()
            || $manifest->targetPackageIdentity() !== $plan->target()->packageIdentity()
            || $manifest->targetReleaseIdentity() !== $plan->target()->contract()->releaseIdentity()
            || $manifest->archiveIdentity() !== $plan->target()->archiveIdentity()
            || $manifest->applyPlanIdentity() !== $plan->target()->payloadIdentity()) {
            throw new \RuntimeException('Database reconciliation requires an exact active WU2 eligibility binding.');
        }

        $record = $store->read($eligibility->recoveryIdentity());
        if (!in_array($record->state(), [RecoveryLifecycleState::READY], true) || !$record->captureComplete()
            || !$record->confirmationMatches($eligibility->recoveryIdentity(), $manifest->identity(), $eligibility->confirmationIdentity())) {
            throw new \RuntimeException('Database reconciliation recovery state is not mutation-eligible.');
        }
    }

    /** @return array<int, AppliedMigrationRecord> */
    private function verifySource(PDO $connection, LegacyReconciliationPlan $plan, CoreMigrationRegistry $registry, string $canonicalSchemaPath, callable $schemaIdentityResolver): array
    {
        $classification = $plan->classification();
        if (!in_array($classification->classification(), [LegacyClassification::CANONICAL_SCHEMA_BASELINE, LegacyClassification::KNOWN_MIGRATION_PREFIX], true)) {
            throw new \RuntimeException('Database reconciliation source classification is not a supported legacy candidate.');
        }

        $records = $this->ledger->records($connection);
        $expectedRecords = $classification->records();
        if (CoreMigrationStateIdentity::fromRecords($records) !== CoreMigrationStateIdentity::fromRecords($expectedRecords)) {
            throw new \RuntimeException('Live migration history no longer matches the immutable legacy classification.');
        }

        if ($classification->classification() === LegacyClassification::CANONICAL_SCHEMA_BASELINE) {
            if ($records !== [] || $classification->sourceSchemaIdentity() !== $this->canonicalSchema->identity($canonicalSchemaPath) || !$this->canonicalSchema->verify($connection, $canonicalSchemaPath)->passed()) {
                throw new \RuntimeException('Authoritative canonical schema baseline is not proven by the current database.');
            }
        } else {
            $this->verifyPrefix($records, $registry);
            if ($records === [] || $classification->sourceWebcoreVersion() !== $records[count($records) - 1]->targetWebcoreVersion() || $classification->sourceSchemaIdentity() !== $records[count($records) - 1]->targetSchemaIdentity() || (string) $schemaIdentityResolver($connection) !== $classification->sourceSchemaIdentity()) {
                throw new \RuntimeException('Known migration prefix schema or version identity contradicts the current database.');
            }
        }

        $this->verifyForwardPlan($plan, $registry, count($records));
        return $records;
    }

    private function verifyPrefix(array $records, CoreMigrationRegistry $registry): void
    {
        $descriptors = $registry->migrations();
        foreach ($records as $index => $record) {
            $descriptor = $descriptors[$index] ?? null;
            if (!$descriptor instanceof CoreMigrationDescriptor || $record->migrationId() !== $descriptor->id() || $record->sequence() !== $descriptor->sequence() || $record->targetWebcoreVersion() !== $descriptor->targetWebcoreVersion() || $record->targetSchemaIdentity() !== $descriptor->targetSchemaIdentity() || $record->checksum() !== $descriptor->checksum()) {
                throw new \RuntimeException('Applied migration history is not an exact authoritative registry prefix.');
            }
        }
    }

    private function verifyForwardPlan(LegacyReconciliationPlan $plan, CoreMigrationRegistry $registry, int $offset): void
    {
        $planned = $plan->migrationPlan()->migrations();
        $descriptors = $registry->migrations();
        $classification = $plan->classification();
        try {
            $declared = $registry->resolve($plan->target()->contract()->migrationDeclaration());
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Trusted package migration declaration is not authoritative.', 0, $exception);
        }
        if ($planned !== [] && $declared === []) {
            throw new \RuntimeException('Trusted package omitted the required Core migration declaration.');
        }
        if ($plan->migrationPlan()->initialWebcoreVersion() !== $classification->sourceWebcoreVersion()
            || $plan->migrationPlan()->initialSchemaIdentity() !== $classification->sourceSchemaIdentity()) {
            throw new \RuntimeException('Migration plan source identity contradicts the immutable legacy classification.');
        }
        $previousVersion = $plan->migrationPlan()->initialWebcoreVersion();
        foreach ($planned as $index => $migration) {
            $authoritative = $descriptors[$offset + $index] ?? null;
            if (!$migration instanceof CoreMigrationDescriptor || !$authoritative instanceof CoreMigrationDescriptor || $migration->id() !== $authoritative->id() || $migration->sequence() !== $authoritative->sequence() || $migration->checksum() !== $authoritative->checksum() || !$migration->appliesTo($previousVersion) || PackageVersion::compare($migration->targetWebcoreVersion(), $plan->target()->contract()->targetWebcoreVersion()) > 0) {
                throw new \RuntimeException('Migration plan is not an ordered forward authoritative suffix.');
            }
            $previousVersion = $migration->targetWebcoreVersion();
        }
        if (PackageVersion::compare($plan->migrationPlan()->virtualFinalWebcoreVersion(), $plan->migrationPlan()->initialWebcoreVersion()) < 0) {
            throw new \RuntimeException('Downgrade and reverse migration are unsupported.');
        }
    }

    private function markRecoveryRequired(RecoveryLifecycleStore $store, ReconciliationMutationEligibility $eligibility, string $reason): void
    {
        try {
            $record = $store->read($eligibility->recoveryIdentity());
            if ($record->mutationStarted() && $record->state() === RecoveryLifecycleState::READY) {
                $store->transition($eligibility->recoveryIdentity(), RecoveryLifecycleState::RESTORE_REQUIRED, $reason);
            }
        } catch (\Throwable) {
            // Preserve the migration result; unreadable durable state remains a B&R concern.
        }
    }
}
