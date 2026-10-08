<?php

namespace Copot\Core;

use PDO;

final class PackageLifecycleService
{
    /** @var callable(): ExistingInstallEvidence */
    private $evidence;
    /** @var callable(): RuntimeCompatibilityContext */
    private $runtime;
    /** @var callable(): PDO */
    private $connection;
    /** @var callable(): array<string, callable> */
    private $runtimeChecks;

    public function __construct(
        private ZipIntakeService $intake,
        private PackageManifestReader $manifestReader,
        private PackageInventoryVerifier $inventoryVerifier,
        private InstalledStateInspector $installedInspector,
        private InstallationState $installationState,
        callable $evidence,
        private TransitionPlanner $transitionPlanner,
        private CoreMigrationPlanner $migrationPlanner,
        private CoreMigrationRegistry $migrationRegistry,
        private CoreMigrationLedger $ledger,
        callable $connection,
        private WebcoreApplyCoordinator $applyCoordinator,
        private HealthIntegrityCommitCoordinator $healthCoordinator,
        private LiveTreePathGuard $liveGuard,
        private MaintenanceCoordinator $maintenance,
        private InstallationMutex $mutex,
        callable $runtime,
        callable $runtimeChecks,
        private CanonicalSchemaBaselineVerifier $canonicalSchema,
        private string $canonicalSchemaPath,
        ?LegacyRuntimeClassifier $legacyClassifier = null,
        ?LegacyReconciliationPlanner $reconciliationPlanner = null,
        private ?LegacyReconciliationOperator $reconciliationOperator = null,
        private ?string $reconciliationUnavailableReason = null,
        ?CanonicalSchemaBaselineCatalog $baselineCatalog = null,
        private $recoveryEvidenceValidator = null,
        private ?NetZeroRetirementService $netZeroRetirement = null,
        private $awaitingWu6EvidenceValidator = null
    ) {
        $this->evidence = $evidence;
        $this->connection = $connection;
        $this->runtime = $runtime;
        $this->runtimeChecks = $runtimeChecks;
        $this->legacyClassifier = $legacyClassifier ?? new LegacyRuntimeClassifier($canonicalSchema, $baselineCatalog);
        $this->reconciliationPlanner = $reconciliationPlanner ?? new LegacyReconciliationPlanner();
        $this->databaseLifecycleClassifier = new DatabaseLifecycleClassifier();
    }

    private LegacyRuntimeClassifier $legacyClassifier;
    private LegacyReconciliationPlanner $reconciliationPlanner;
    private DatabaseLifecycleClassifier $databaseLifecycleClassifier;

    public function retireNetZero(string $operationId, WebcoreApplyPlan $plan, NetZeroRetirementContext $context): PackageLifecycleResult
    {
        if (!$this->netZeroRetirement instanceof NetZeroRetirementService) {
            return new PackageLifecycleResult(false, 'unavailable', 'Net-zero retirement is unavailable.');
        }
        return $this->netZeroRetirement->retire($operationId, $plan, $context);
    }

    public function plan(string $zip): PackageLifecycleResult
    {
        try {
            [$payload, $manifest, $transition, $migration] = $this->prepare($zip);
            $result = $transition->accepted() && $migration->isAccepted()
                ? new PackageLifecycleResult(true, 'planned', '', $transition, $migration)
                : new PackageLifecycleResult(false, 'rejected', $transition->reason() !== '' ? $transition->reason() : $migration->reason(), $transition, $migration);
            $payload->cleanup();
            return $result;
        } catch (\Throwable $exception) {
            return new PackageLifecycleResult(false, $this->isCapabilityFailure($exception) ? 'unavailable' : 'invalid_package', $exception->getMessage());
        }
    }

    public function apply(string $zip, bool $repairOnly = false): PackageLifecycleResult
    {
        try {
            [$payload, $manifest, $transition, $migration] = $this->prepare($zip);
            if ($repairOnly && $transition->classification() !== TransitionPlan::REPAIR) {
                $payload->cleanup();
                return new PackageLifecycleResult(false, 'rejected', 'Repair requires a same-version target.', $transition, $migration);
            }
            if (!$transition->accepted() || !$migration->isAccepted()) {
                $reason = $transition->reason() !== '' ? $transition->reason() : $migration->reason();
                $payload->cleanup();
                return new PackageLifecycleResult(false, 'rejected', $reason, $transition, $migration);
            }

            $applyPlan = WebcoreApplyPlan::fromPayload($manifest->payload());
            $apply = $this->applyCoordinator->execute($applyPlan, $transition, $migration);
            if ($apply->status() !== WebcoreApplyResult::AWAITING_WU6) {
                if ($apply->status() === WebcoreApplyResult::FAILED) { $payload->cleanup(); }
                return new PackageLifecycleResult(false, strtolower($apply->status()), $apply->reason(), $transition, $migration, $apply->operationId());
            }
            $connection = ($this->connection)();
            $final = $this->healthCoordinator->finalize($apply->operationId(), $manifest->contract(), $applyPlan, $migration, $this->liveGuard, $connection, ($this->runtimeChecks)());
            if ($final->status() === HealthIntegrityCommitResult::COMPLETED) {
                $payload->cleanup();
                return new PackageLifecycleResult(true, 'completed', '', $transition, $migration, $apply->operationId());
            }
            return new PackageLifecycleResult(false, $final->status(), $final->reason(), $transition, $migration, $apply->operationId());
        } catch (\Throwable $exception) {
            return new PackageLifecycleResult(false, $this->isCapabilityFailure($exception) ? 'unavailable' : 'invalid_package', $exception->getMessage());
        }
    }

    public function adopt(string $zip): PackageLifecycleResult
    {
        $payload = null;
        $operation = null;
        try {
            [$payload, $manifest, $installed] = $this->prepareAdoption($zip);
            $schemaGates = $this->canonicalSchema->verify(($this->connection)(), $this->canonicalSchemaPath);
            if (!$schemaGates->passed()) {
                $payload->cleanup();
                return new PackageLifecycleResult(false, 'rejected', $schemaGates->failureReason());
            }

            $schemaIdentity = $this->canonicalSchema->identity($this->canonicalSchemaPath);
            $migration = CoreMigrationPlan::allow($installed->snapshot()?->webcoreVersion() ?? '', $manifest->contract()->targetWebcoreVersion(), null, $schemaIdentity, [], true);
            $applyPlan = WebcoreApplyPlan::fromPayload($manifest->payload());
            $lock = $this->mutex->acquire();
            if (!$lock instanceof InstallationLock) {
                $payload->cleanup();
                return new PackageLifecycleResult(false, 'blocked', 'Another lifecycle operation is already running.');
            }

            try {
                if ($this->maintenance->record() !== null) {
                    $payload->cleanup();
                    return new PackageLifecycleResult(false, 'blocked', 'Another lifecycle operation or maintenance state is active.');
                }
                $now = gmdate(DATE_ATOM);
                $operation = new LifecycleOperationRecord(
                    bin2hex(random_bytes(16)), 'adopt', $manifest->contract()->targetWebcoreVersion(),
                    $manifest->contract()->releaseIdentity(), $payload->archiveSha256(), $payload->stagingPath(),
                    hash('sha256', implode(':', array_map(static fn (StagedFile $file): string => $file->path() . ':' . $file->sha256(), $manifest->payload()->files()))),
                    $applyPlan->identity(), LifecycleOperationRecord::AWAITING_WU6, 0, null,
                    hash('sha256', ''), MigrationRunResult::NOOP, $now, $now
                );
                $this->maintenance->enter($operation);
            } finally {
                $lock->release();
            }

            $final = $this->healthCoordinator->finalize($operation->operationId(), $manifest->contract(), $applyPlan, $migration, $this->liveGuard, ($this->connection)(), ($this->runtimeChecks)());
            if ($final->status() === HealthIntegrityCommitResult::COMPLETED) {
                $payload->cleanup();
                return new PackageLifecycleResult(true, 'completed', '', null, $migration, $operation->operationId());
            }
            return new PackageLifecycleResult(false, $final->status(), $final->reason(), null, $migration, $operation->operationId());
        } catch (\Throwable $exception) {
            if ($payload instanceof StagedPayload && !$operation instanceof LifecycleOperationRecord) {
                try { $payload->cleanup(); } catch (\Throwable) { }
            }
            return new PackageLifecycleResult(false, $this->isCapabilityFailure($exception) ? 'unavailable' : 'invalid_package', $exception->getMessage());
        }
    }

    public function reconcilePlan(string $zip): PackageLifecycleResult
    {
        $payload = null;
        try {
            $payload = $this->intake->intake($zip);
            $manifest = $this->manifestReader->read($payload);
            $target = TrustedWebcorePackageTarget::fromManifest($manifest, $this->inventoryVerifier);
            $installed = $this->installedInspector->inspect($this->installationState, ($this->evidence)());
            $classification = $this->legacyClassifier->classify(
                $installed,
                ($this->connection)(),
                $this->canonicalSchemaPath,
                $this->migrationRegistry
            );
            $plan = $this->reconciliationPlanner->plan(
                $target,
                $classification,
                ($this->runtime)(),
                $this->migrationRegistry,
                $this->liveGuard
            );
            $payload->cleanup();
            return new PackageLifecycleResult(true, 'planned', '', null, $plan->migrationPlan(), null, $plan);
        } catch (\Throwable $exception) {
            if ($payload instanceof StagedPayload) {
                try { $payload->cleanup(); } catch (\Throwable) { }
            }
            return new PackageLifecycleResult(false, $this->isCapabilityFailure($exception) ? 'unavailable' : 'rejected', $exception->getMessage());
        }
    }

    public function reconcile(string $zip, bool $confirmed): PackageLifecycleResult
    {
        if (!$this->reconciliationOperator instanceof LegacyReconciliationOperator) {
            return new PackageLifecycleResult(false, 'unavailable', 'Production reconciliation composition is unavailable.');
        }

        return $this->reconciliationOperator->reconcile($zip, $confirmed);
    }

    public function reconciliationAvailable(): bool
    {
        return $this->reconciliationOperator instanceof LegacyReconciliationOperator;
    }

    public function retryEvidence(string $operationId): bool
    {
        $record = $this->maintenance->record();
        if (!$record instanceof LifecycleOperationRecord || $record->operationId() !== $operationId
            || !in_array($record->phase(), [LifecycleOperationRecord::BLOCKED, LifecycleOperationRecord::INDETERMINATE, LifecycleOperationRecord::APPLYING, LifecycleOperationRecord::MIGRATING], true)
            || !is_dir($record->stagingPath())
            || !is_file($record->stagingPath() . DIRECTORY_SEPARATOR . 'source.zip')
            || !is_readable($record->stagingPath() . DIRECTORY_SEPARATOR . 'source.zip')
            || $record->recoveryIdentity() === null || $record->recoveryManifestIdentity() === null
            || $record->recoveryState() !== \Copot\Core\BackupRecovery\RecoveryLifecycleState::READY
            || !is_callable($this->recoveryEvidenceValidator)) return false;
        try { return (bool) ($this->recoveryEvidenceValidator)($record); } catch (\Throwable) { return false; }
    }

    /**
     * A blocked operation may bootstrap recovery only while its mutation
     * boundary is provably untouched. Once progress, migration, or recovery
     * state exists, the ordinary recovery-backed retry rules remain required.
     */
    private function preMutationRetryEligible(string $operationId): bool
    {
        $record = $this->maintenance->record();
        return $record instanceof LifecycleOperationRecord
            && $record->operationId() === $operationId
            && $record->phase() === LifecycleOperationRecord::BLOCKED
            && $record->fileCursor() === 0
            && $record->lastVerifiedPath() === null
            && $record->migrationOutcome() === null
            && $record->migrationPlanIdentity() !== null
            && $record->recoveryIdentity() === null
            && $record->recoveryManifestIdentity() === null
            && $record->recoveryState() === null
            && is_dir($record->stagingPath())
            && is_file($record->stagingPath() . DIRECTORY_SEPARATOR . 'source.zip')
            && is_readable($record->stagingPath() . DIRECTORY_SEPARATOR . 'source.zip');
    }

    public function retrySource(string $operationId): ?string
    {
        $record = $this->maintenance->record();
        if (!$record instanceof LifecycleOperationRecord || $record->operationId() !== $operationId || !$this->retryEvidence($operationId)) return null;
        $path = $record->stagingPath() . DIRECTORY_SEPARATOR . 'source.zip';
        return is_file($path) && is_readable($path) ? $path : null;
    }

    public function retry(string $operationId): PackageLifecycleResult
    {
        $record = $this->maintenance->record();
        $recoveryBacked = $record instanceof LifecycleOperationRecord && $this->retryEvidence($operationId);
        $preMutation = !$recoveryBacked && $this->preMutationRetryEligible($operationId);
        if (!$recoveryBacked && !$preMutation) return new PackageLifecycleResult(false, 'rejected', 'Retry evidence is unavailable or stale.');
        $retainedStagingPath = $record->stagingPath();
        $source = $preMutation
            ? $retainedStagingPath . DIRECTORY_SEPARATOR . 'source.zip'
            : $this->retrySource($operationId);
        if ($source === null) return new PackageLifecycleResult(false, 'rejected', 'Retained staged package evidence is unavailable.');
        $payload = null;
        try {
            [$payload, $manifest, $transition, $migration] = $this->prepare($source);
            $applyPlan = WebcoreApplyPlan::fromPayload($manifest->payload());
            $payloadIdentity = hash('sha256', implode(':', array_map(
                static fn (StagedFile $file): string => $file->path() . ':' . $file->sha256(),
                $manifest->payload()->files()
            )));
            $migrationIdentity = hash('sha256', implode("\n", array_map(
                static fn (CoreMigrationDescriptor $migration): string => $migration->id() . ':' . $migration->checksum(),
                array_filter($migration->migrations(), static fn ($migration): bool => $migration instanceof CoreMigrationDescriptor)
            )));
            if ($payload->archiveSha256() !== $record->archiveSha256()
                || $payloadIdentity !== $record->payloadIdentity()
                || $applyPlan->identity() !== $record->applyPlanIdentity()
                || $migrationIdentity !== $record->migrationPlanIdentity()
                || $manifest->contract()->releaseIdentity() !== $record->releaseIdentity()
                || $transition->classification() !== $record->classification()
                || $manifest->contract()->targetWebcoreVersion() !== $record->targetWebcoreVersion()) {
                throw new \RuntimeException('Retry target evidence does not match the persisted operation.');
            }
            if ($record->stagingPath() !== $payload->stagingPath()) {
                $record = $record->withStagingPath($payload->stagingPath());
                $this->maintenance->update($record);
            }
            $apply = $this->applyCoordinator->execute($applyPlan, $transition, $migration, $record);
            if ($apply->status() !== WebcoreApplyResult::AWAITING_WU6) return new PackageLifecycleResult(false, strtolower($apply->status()), $apply->reason(), $transition, $migration, $apply->operationId());
            $final = $this->healthCoordinator->finalize($apply->operationId(), $manifest->contract(), $applyPlan, $migration, $this->liveGuard, ($this->connection)(), ($this->runtimeChecks)());
            if ($final->status() === HealthIntegrityCommitResult::COMPLETED) {
                if ($retainedStagingPath !== $payload->stagingPath()) {
                    try { StagingSession::cleanupExisting($retainedStagingPath); } catch (\Throwable) {}
                }
                return new PackageLifecycleResult(true, 'completed', '', $transition, $migration, $apply->operationId());
            }
            return new PackageLifecycleResult(false, $final->status(), $final->reason(), $transition, $migration, $apply->operationId());
        } catch (\Throwable $e) { return new PackageLifecycleResult(false, 'blocked', 'Retry evidence or execution was rejected.', null, null, $record->operationId()); }
        finally { if ($payload instanceof StagedPayload) { try { $payload->cleanup(); } catch (\Throwable) {} } }
    }

    /**
     * Continue an operation whose package and migration stages completed and
     * which is waiting only for WU6 health/commit finalization.
     *
     * This path deliberately reconstructs and verifies the retained target,
     * then calls finalization directly. It never re-enters package apply or
     * Core migration execution.
     */
    public function continueAwaitingWu6(string $operationId): PackageLifecycleResult
    {
        $record = $this->maintenance->record();
        if (!$record instanceof LifecycleOperationRecord
            || $record->operationId() !== $operationId
            || $record->phase() !== LifecycleOperationRecord::AWAITING_WU6
            || $record->fileCursor() <= 0
            || $record->lastVerifiedPath() === null
            || !in_array($record->migrationOutcome(), [MigrationRunResult::COMPLETED, MigrationRunResult::NOOP], true)
            || $record->recoveryIdentity() === null
            || $record->recoveryManifestIdentity() === null
            || $record->recoveryState() !== \Copot\Core\BackupRecovery\RecoveryLifecycleState::READY
            || !is_callable($this->awaitingWu6EvidenceValidator)
            || !(($this->awaitingWu6EvidenceValidator)($record))) {
            return new PackageLifecycleResult(false, 'rejected', 'Awaiting-WU6 continuation evidence is unavailable or stale.', null, null, $operationId);
        }

        $retainedStagingPath = $record->stagingPath();
        $source = $retainedStagingPath . DIRECTORY_SEPARATOR . 'source.zip';
        if (!is_dir($retainedStagingPath) || !is_file($source) || !is_readable($source)) {
            return new PackageLifecycleResult(false, 'rejected', 'Retained staged package evidence is unavailable.', null, null, $operationId);
        }

        $payload = null;
        $stagingPersisted = false;
        try {
            [$payload, $manifest, $transition] = $this->prepare($source);
            $applyPlan = WebcoreApplyPlan::fromPayload($manifest->payload());
            if (!$transition->accepted()) {
                throw new \RuntimeException('Continuation transition is no longer accepted.');
            }
            $migration = $this->reconstructCompletedMigrationPlan($record, $manifest);
            $payloadIdentity = hash('sha256', implode(':', array_map(
                static fn (StagedFile $file): string => $file->path() . ':' . $file->sha256(),
                $manifest->payload()->files()
            )));
            $migrationIdentity = hash('sha256', implode("\n", array_map(
                static fn (CoreMigrationDescriptor $migration): string => $migration->id() . ':' . $migration->checksum(),
                array_filter($migration->migrations(), static fn ($migration): bool => $migration instanceof CoreMigrationDescriptor)
            )));
            if ($payload->archiveSha256() !== $record->archiveSha256()
                || $payloadIdentity !== $record->payloadIdentity()
                || $applyPlan->identity() !== $record->applyPlanIdentity()
                || $migrationIdentity !== $record->migrationPlanIdentity()
                || $manifest->contract()->releaseIdentity() !== $record->releaseIdentity()
                || $manifest->contract()->targetWebcoreVersion() !== $record->targetWebcoreVersion()) {
                throw new \RuntimeException('Continuation target evidence does not match the persisted operation.');
            }

            if ($record->stagingPath() !== $payload->stagingPath()) {
                $record = $record->withStagingPath($payload->stagingPath());
                $this->maintenance->update($record);
                $stagingPersisted = true;
            }

            $final = $this->healthCoordinator->finalize(
                $operationId,
                $manifest->contract(),
                $applyPlan,
                $migration,
                $this->liveGuard,
                ($this->connection)(),
                ($this->runtimeChecks)()
            );
            if ($final->status() === HealthIntegrityCommitResult::COMPLETED) {
                if ($retainedStagingPath !== $payload->stagingPath()) {
                    try { StagingSession::cleanupExisting($retainedStagingPath); } catch (\Throwable) {}
                }
                $stagingPersisted = false;
                return new PackageLifecycleResult(true, 'completed', '', $transition, $migration, $operationId);
            }

            return new PackageLifecycleResult(false, $final->status(), $final->reason(), $transition, $migration, $operationId);
        } catch (\Throwable) {
            return new PackageLifecycleResult(false, 'blocked', 'Awaiting-WU6 continuation was rejected.', null, null, $operationId);
        } finally {
            if ($payload instanceof StagedPayload && !$stagingPersisted) {
                try { $payload->cleanup(); } catch (\Throwable) {}
            }
        }
    }

    private function reconstructCompletedMigrationPlan(LifecycleOperationRecord $record, PackageManifest $manifest): CoreMigrationPlan
    {
        $descriptors = array_values(array_filter(
            $this->migrationRegistry->migrations(),
            static fn ($migration): bool => $migration instanceof CoreMigrationDescriptor
        ));
        $planned = [];
        $matched = false;
        foreach ($descriptors as $descriptor) {
            $planned[] = $descriptor;
            $identity = hash('sha256', implode("\n", array_map(
                static fn (CoreMigrationDescriptor $migration): string => $migration->id() . ':' . $migration->checksum(),
                $planned
            )));
            if ($identity === $record->migrationPlanIdentity()) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            throw new \RuntimeException('Completed migration-plan identity cannot be reconstructed.');
        }

        $records = $this->ledger->records(($this->connection)());
        if (count($records) !== count($planned)) {
            throw new \RuntimeException('Completed migration ledger length does not match the persisted plan.');
        }
        foreach ($records as $index => $recorded) {
            $descriptor = $planned[$index];
            if ($recorded->migrationId() !== $descriptor->id()
                || $recorded->sequence() !== $descriptor->sequence()
                || $recorded->targetWebcoreVersion() !== $descriptor->targetWebcoreVersion()
                || $recorded->targetSchemaIdentity() !== $descriptor->targetSchemaIdentity()
                || $recorded->checksum() !== $descriptor->checksum()) {
                throw new \RuntimeException('Completed migration ledger does not match the persisted plan.');
            }
        }

        $finalSchema = $planned === []
            ? 'canonical-current'
            : $planned[count($planned) - 1]->targetSchemaIdentity();
        return CoreMigrationPlan::allow(
            $record->targetWebcoreVersion(),
            $manifest->contract()->targetWebcoreVersion(),
            null,
            $finalSchema,
            $planned
        );
    }

    public function status(): array
    {
        try {
            $inspection = $this->installedInspector->inspect($this->installationState, ($this->evidence)());
            $record = $this->maintenance->record();
            $operationState = 'inactive';
            $operation = null;
            if ($record instanceof LifecycleOperationRecord) {
                $lock = $this->mutex->acquire();
                if ($lock instanceof InstallationLock) {
                    $operationState = 'interrupted';
                    $lock->release();
                } else {
                    $operationState = 'active';
                }
                $operation = [
                    'state' => $operationState,
                    'phase' => $record->phase(),
                    'operation_id' => $record->operationId(),
                    'classification' => $record->classification(),
                    'target_webcore_version' => $record->targetWebcoreVersion(),
                    'recovery_state' => $record->recoveryState(),
                ];
            }

            $status = PackageLifecycleStatus::describe($inspection, $record, $operationState);
            $status['reconciliation_available'] = $this->reconciliationAvailable();
            if (!$this->reconciliationAvailable() && $this->reconciliationUnavailableReason !== null) {
                $status['reconciliation_reason'] = $this->reconciliationUnavailableReason;
            }
            return $status;
        } catch (\Throwable $exception) {
            return ['accepted' => false, 'status' => 'invalid', 'reason' => $exception->getMessage()];
        }
    }

    private function prepare(string $zip): array
    {
        $payload = $this->intake->intake($zip);
        try {
            $manifest = $this->manifestReader->read($payload);
            $this->inventoryVerifier->verify($manifest->payload(), $manifest->contract()->inventory());
            $installed = $this->installedInspector->inspect($this->installationState, ($this->evidence)());
            $runtime = ($this->runtime)();
            $transition = $this->transitionPlanner->plan($installed, $manifest->contract(), $runtime);
            $migration = $this->migrationPlanner->plan($installed, $manifest->contract(), $this->migrationRegistry, $this->ledger, ($this->connection)());
            $transition = $this->databaseLifecycleClassifier->classify($transition, $migration);
            return [$payload, $manifest, $transition, $migration];
        } catch (\Throwable $exception) {
            $payload->cleanup();
            throw $exception;
        }
    }

    private function prepareAdoption(string $zip): array
    {
        $payload = $this->intake->intake($zip);
        try {
            $manifest = $this->manifestReader->read($payload);
            $this->inventoryVerifier->verify($manifest->payload(), $manifest->contract()->inventory());
            $installed = $this->installedInspector->inspect($this->installationState, ($this->evidence)());
            if ($installed->status() !== InstalledStateStatus::LEGACY || $installed->snapshot() === null) {
                throw new \RuntimeException('Exact-match adoption requires LEGACY installed state.');
            }
            if (PackageVersion::compare($manifest->contract()->targetWebcoreVersion(), $installed->snapshot()->webcoreVersion()) !== 0) {
                throw new \RuntimeException('Exact-match adoption requires package version equality with installed evidence.');
            }
            if ($manifest->contract()->migrationDeclaration()->declaresCoreMigrations()) {
                throw new \RuntimeException('Exact-match adoption cannot establish unknown historical migration state.');
            }
            if (!($this->runtime)()->supports($manifest->contract()->runtimeCompatibility())) {
                throw new \RuntimeException('Runtime requirements are not satisfied.');
            }
            return [$payload, $manifest, $installed];
        } catch (\Throwable $exception) {
            $payload->cleanup();
            throw $exception;
        }
    }

    private function isCapabilityFailure(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());
        return $exception instanceof \PDOException
            || str_contains($message, 'ziparchive')
            || str_contains($message, 'ext-zip')
            || str_contains($message, 'could not find driver')
            || str_contains($message, 'database connection');
    }
}
