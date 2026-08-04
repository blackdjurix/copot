<?php

namespace Copot\Core;

use PDO;

final class HealthIntegrityCommitCoordinator
{
    public function __construct(
        private InstallationMutex $mutex,
        private MaintenanceCoordinator $maintenance,
        private InstallationState $installationState,
        private CommittedLifecycleStateStore $committedStore,
        private TargetPackageIntegrityVerifier $integrity,
        private DatabaseHealthVerifier $databaseHealth,
        private CoreMigrationHealthVerifier $migrationHealth,
        private RuntimeHealthVerifier $runtimeHealth,
        private CoreMigrationRegistry $migrationRegistry
    ) {
    }

    public function finalize(
        string $operationId,
        PackageContract $package,
        WebcoreApplyPlan $applyPlan,
        CoreMigrationPlan $migrationPlan,
        LiveTreePathGuard $liveGuard,
        PDO $connection,
        array $runtimeChecks = []
    ): HealthIntegrityCommitResult {
        $lock = $this->mutex->acquire();
        if (!$lock instanceof InstallationLock) {
            return new HealthIntegrityCommitResult(HealthIntegrityCommitResult::FAILED, new HealthGateMatrix([HealthGateResult::fail('mutex', 'Another lifecycle operation is already running.')]), 'Another lifecycle operation is already running.');
        }

        try {
            $record = $this->maintenance->record();
            if (!$record instanceof LifecycleOperationRecord || $record->operationId() !== $operationId) {
                return $this->failure('operation', 'Lifecycle operation identity is unavailable.', $operationId);
            }

            if ($record->phase() === LifecycleOperationRecord::CLEANUP_PENDING) {
                return $this->retryCleanup($record, $package, $applyPlan, $migrationPlan, $connection, $operationId);
            }
            if ($record->phase() !== LifecycleOperationRecord::AWAITING_WU6) {
                return $this->failure('operation', 'Lifecycle operation is not awaiting WU6.', $operationId);
            }

            $identityGates = $this->identityGates($record, $package, $applyPlan, $migrationPlan);
            if (!$identityGates->passed()) { return new HealthIntegrityCommitResult(HealthIntegrityCommitResult::FAILED, $identityGates, $identityGates->failureReason(), $operationId); }

            $gates = array_merge(
                $identityGates->gates(),
                $this->integrity->verify($package, $liveGuard)->gates(),
                $this->databaseHealth->verify($connection)->gates(),
                $this->migrationHealth->verify($connection, $this->migrationRegistry, null)->gates(),
                $this->runtimeHealth->verify($runtimeChecks)->gates()
            );
            $matrix = new HealthGateMatrix($gates);
            if (!$matrix->passed()) { return new HealthIntegrityCommitResult(HealthIntegrityCommitResult::FAILED, $matrix, $matrix->failureReason(), $operationId); }

            $latest = $this->maintenance->record();
            if (!$latest instanceof LifecycleOperationRecord || $latest->operationId() !== $operationId || $latest->phase() !== LifecycleOperationRecord::AWAITING_WU6) {
                return $this->failure('operation', 'Lifecycle operation changed before commit.', $operationId, $matrix);
            }

            $migrationIdentity = $this->migrationHealth->identity($connection);
            $committedAt = new \DateTimeImmutable('now');
            $state = new CommittedLifecycleState(
                $package->targetWebcoreVersion(),
                $package->releaseIdentity(),
                $package->sourceTreeIdentity(),
                $package->manifestContractVersion(),
                $migrationPlan->virtualFinalSchemaIdentity() ?? 'canonical-current',
                $migrationIdentity,
                $committedAt
            );
            $this->committedStore->commit($this->installationState, $state);

            $completed = $latest->advance(LifecycleOperationRecord::COMPLETED, $latest->fileCursor(), $latest->lastVerifiedPath());
            try {
                $this->maintenance->clear($completed);
            } catch (\Throwable $exception) {
                $pending = $latest->advance(LifecycleOperationRecord::CLEANUP_PENDING, $latest->fileCursor(), $latest->lastVerifiedPath(), $latest->migrationOutcome(), 'Committed state is complete; lifecycle cleanup is pending.');
                try { $this->maintenance->update($pending); } catch (\Throwable) { }
                return new HealthIntegrityCommitResult(HealthIntegrityCommitResult::CLEANUP_PENDING, $matrix, $exception->getMessage(), $operationId);
            }

            return new HealthIntegrityCommitResult(HealthIntegrityCommitResult::COMPLETED, $matrix, '', $operationId);
        } catch (\Throwable $exception) {
            return $this->failure('finalization', $exception->getMessage(), $operationId);
        } finally {
            $lock->release();
        }
    }

    private function retryCleanup(
        LifecycleOperationRecord $record,
        PackageContract $package,
        WebcoreApplyPlan $applyPlan,
        CoreMigrationPlan $migrationPlan,
        PDO $connection,
        string $operationId
    ): HealthIntegrityCommitResult
    {
        try {
            $identityGates = $this->identityGates($record, $package, $applyPlan, $migrationPlan);
            $migrationGates = $this->migrationHealth->verify($connection, $this->migrationRegistry, null);
            $gates = new HealthGateMatrix(array_merge($identityGates->gates(), $migrationGates->gates()));
            if (!$gates->passed()) {
                return $this->failure('cleanup', $gates->failureReason(), $operationId, $gates);
            }

            $state = $this->committedStore->read();
            $migrationIdentity = $this->migrationHealth->identity($connection);
            $expectedSchema = $migrationPlan->virtualFinalSchemaIdentity() ?? 'canonical-current';
            if (!$state instanceof CommittedLifecycleState
                || $state->webcoreVersion() !== $package->targetWebcoreVersion()
                || $state->releaseIdentity() !== $package->releaseIdentity()
                || $state->sourceTreeIdentity() !== $package->sourceTreeIdentity()
                || $state->manifestContractVersion() !== $package->manifestContractVersion()
                || $state->schemaStateIdentity() !== $expectedSchema
                || $state->migrationStateIdentity() !== $migrationIdentity) {
                return $this->failure('cleanup', 'Committed target does not exactly match the cleanup-pending operation.', $operationId, new HealthGateMatrix([HealthGateResult::fail('committed-target', 'Committed target identity mismatch.')]));
            }

            $this->maintenance->clear($record->advance(LifecycleOperationRecord::COMPLETED, $record->fileCursor(), $record->lastVerifiedPath()));
            return new HealthIntegrityCommitResult(HealthIntegrityCommitResult::COMPLETED, $gates, '', $operationId);
        } catch (\Throwable $exception) {
            return $this->failure('cleanup', $exception->getMessage(), $operationId);
        }
    }

    private function identityGates(LifecycleOperationRecord $record, PackageContract $package, WebcoreApplyPlan $applyPlan, CoreMigrationPlan $migrationPlan): HealthGateMatrix
    {
        $migrationPlanIdentity = hash('sha256', implode("\n", array_map(
            static fn (CoreMigrationDescriptor $migration): string => $migration->id() . ':' . $migration->checksum(),
            array_filter($migrationPlan->migrations(), static fn ($migration): bool => $migration instanceof CoreMigrationDescriptor)
        )));
        $payloadIdentity = hash('sha256', implode(':', array_map(
            static fn (StagedFile $file): string => $file->path() . ':' . $file->sha256(),
            $applyPlan->files()
        )));
        $gates = [
            $record->targetWebcoreVersion() === $package->targetWebcoreVersion() ? HealthGateResult::pass('target-version') : HealthGateResult::fail('target-version', 'Operation target version does not match package.'),
            $record->releaseIdentity() === $package->releaseIdentity() ? HealthGateResult::pass('release-identity') : HealthGateResult::fail('release-identity', 'Operation release identity does not match package.'),
            $record->archiveSha256() === $applyPlan->payload()->archiveSha256() ? HealthGateResult::pass('archive-identity') : HealthGateResult::fail('archive-identity', 'Archive identity does not match operation.'),
            $record->stagingPath() === $applyPlan->payload()->stagingPath() ? HealthGateResult::pass('staging-identity') : HealthGateResult::fail('staging-identity', 'Staging identity does not match operation.'),
            $record->payloadIdentity() === $payloadIdentity ? HealthGateResult::pass('payload-identity') : HealthGateResult::fail('payload-identity', 'Payload identity does not match operation.'),
            $record->applyPlanIdentity() === $applyPlan->identity() ? HealthGateResult::pass('apply-plan-identity') : HealthGateResult::fail('apply-plan-identity', 'Apply-plan identity does not match operation.'),
            $record->migrationPlanIdentity() === $migrationPlanIdentity ? HealthGateResult::pass('migration-plan-identity') : HealthGateResult::fail('migration-plan-identity', 'Migration-plan identity does not match operation.'),
            $record->migrationOutcome() !== MigrationRunResult::INDETERMINATE ? HealthGateResult::pass('migration-outcome') : HealthGateResult::fail('migration-outcome', 'Migration outcome is indeterminate.'),
            $migrationPlan->isAccepted() ? HealthGateResult::pass('migration-plan') : HealthGateResult::fail('migration-plan', 'Migration plan is not accepted.'),
        ];
        return new HealthGateMatrix($gates);
    }

    private function failure(string $name, string $reason, string $operationId, ?HealthGateMatrix $matrix = null): HealthIntegrityCommitResult
    {
        return new HealthIntegrityCommitResult(HealthIntegrityCommitResult::FAILED, $matrix ?? new HealthGateMatrix([HealthGateResult::fail($name, $reason)]), $reason, $operationId);
    }
}
