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
        ?CanonicalSchemaBaselineCatalog $baselineCatalog = null
    ) {
        $this->evidence = $evidence;
        $this->connection = $connection;
        $this->runtime = $runtime;
        $this->runtimeChecks = $runtimeChecks;
        $this->legacyClassifier = $legacyClassifier ?? new LegacyRuntimeClassifier($canonicalSchema, $baselineCatalog);
        $this->reconciliationPlanner = $reconciliationPlanner ?? new LegacyReconciliationPlanner();
    }

    private LegacyRuntimeClassifier $legacyClassifier;
    private LegacyReconciliationPlanner $reconciliationPlanner;

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
        return $record instanceof LifecycleOperationRecord
            && $record->operationId() === $operationId
            && in_array($record->phase(), [LifecycleOperationRecord::BLOCKED, LifecycleOperationRecord::INDETERMINATE, LifecycleOperationRecord::APPLYING, LifecycleOperationRecord::MIGRATING], true)
            && is_dir($record->stagingPath());
    }

    public function retrySource(string $operationId): ?string
    {
        $record = $this->maintenance->record();
        if (!$record instanceof LifecycleOperationRecord || $record->operationId() !== $operationId || !$this->retryEvidence($operationId)) return null;
        $path = $record->stagingPath() . DIRECTORY_SEPARATOR . 'source.zip';
        return is_file($path) && is_readable($path) ? $path : null;
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
