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
        private $awaitingWu6EvidenceValidator = null,
        private ?CommittedLifecycleStateStore $committedStore = null,
        private ?SourceInventoryArtifactStore $sourceInventoryArtifacts = null,
        private ?PackageV3SourceRelativePlanner $v3Planner = null,
        private ?V3ExplicitApplyExecutor $v3Executor = null,
        private ?PackageOwnedFileApplier $v3Applier = null,
        private ?DestructivePlanConfirmationAuthority $v3ConfirmationAuthority = null,
        private ?ProtectedWebcoreMutationBoundary $v3RecoveryBoundary = null,
        private ?LifecycleOperationStore $operationStore = null,
        private $v3MigrationRunner = null,
        private ?LegacyToV3BootstrapEntry $legacyBootstrap = null,
        private ?LifecycleTrace $lifecycleTrace = null,
        private $v3FilesystemOperationClassifier = null,
        private $v3BeforeActivation = null
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
    private ?string $activeRetryTraceRunId = null;

    /** Preparation only: no package apply, migration, recovery, or committed-state mutation. */
    public function prepareLegacyToV3Bootstrap(string $zip): LegacyBootstrapPreparation
    {
        if (!$this->legacyBootstrap instanceof LegacyToV3BootstrapEntry) {
            return new LegacyBootstrapPreparation(
                LegacyBootstrapPreparation::BLOCKED,
                'LEGACY_TO_V3_BOOTSTRAP composition is unavailable.',
                'legacy-to-v3-bootstrap-unavailable', hash('sha256', 'unavailable'), '',
                LegacyClassificationResult::unknown('Bootstrap composition is unavailable.'), null,
                [['action' => 'BLOCK', 'reason' => 'Bootstrap composition is unavailable.']], null
            );
        }
        try {
            $payload = $this->intake->intake($zip);
            try {
                $manifest = $this->manifestReader->read($payload);
                $this->inventoryVerifier->verify($payload, $manifest->contract()->inventory(), [PackageManifestReader::PATH]);
                return $this->legacyBootstrap->prepare($manifest);
            }
            finally { $payload->cleanup(); }
        } catch (\Throwable $exception) {
            return new LegacyBootstrapPreparation(
                LegacyBootstrapPreparation::BLOCKED, $exception->getMessage(),
                'legacy-to-v3-bootstrap-invalid-package', hash('sha256', $exception->getMessage()), '',
                LegacyClassificationResult::unknown($exception->getMessage()), null,
                [['action' => 'BLOCK', 'reason' => $exception->getMessage()]], null
            );
        }
    }

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

    public function apply(string $zip, bool $repairOnly = false, mixed $confirmation = null, ?string $authorizedActorIdentity = null): PackageLifecycleResult
    {
        $runId = $this->lifecycleTrace?->beginRun();
        try {
            [$payload, $manifest, $transition, $migration] = $this->prepare($zip);
            if ($manifest->contract()->manifestContractVersion() === PackageContract::V3_MANIFEST_CONTRACT_VERSION) {
                $result = $this->applyV3($payload, $manifest, $transition, $migration, $confirmation, $authorizedActorIdentity);
                $this->lifecycleTrace?->record($runId ?? '', $result->toArray()['operation_id'] ?? null, 'terminal', 'lifecycle', $result->accepted() ? 'SUCCESS' : 'FAILED', $result->accepted() ? 'SUCCESS' : $result->reason(), ['lifecycle_status' => $result->status()]);
                return $result;
            }
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
            $this->lifecycleTrace?->record($runId ?? '', null, 'terminal', 'lifecycle', 'FAILED', $exception->getMessage(), ['lifecycle_status' => 'blocked']);
            return new PackageLifecycleResult(false, $this->isCapabilityFailure($exception) ? 'unavailable' : 'invalid_package', $exception->getMessage());
        }
    }

    private function applyV3(StagedPayload $payload, PackageManifest $manifest, TransitionPlan $transition, CoreMigrationPlan $migration, mixed $confirmation, ?string $authorizedActorIdentity, ?string $recoveryAttemptIdentity = null, ?LifecycleOperationRecord $continuationEvidence = null): PackageLifecycleResult
    {
        if (!$this->committedStore instanceof CommittedLifecycleStateStore
            || !$this->sourceInventoryArtifacts instanceof SourceInventoryArtifactStore
            || !$this->v3Planner instanceof PackageV3SourceRelativePlanner
            || !$this->v3Executor instanceof V3ExplicitApplyExecutor
            || !$this->v3Applier instanceof PackageOwnedFileApplier
            || !$this->v3ConfirmationAuthority instanceof DestructivePlanConfirmationAuthority
            || !$this->v3RecoveryBoundary instanceof ProtectedWebcoreMutationBoundary) {
            $payload->cleanup();
            return new PackageLifecycleResult(false, 'unavailable', 'Manifest-v3 lifecycle composition is unavailable.', $transition, $migration);
        }
        if (!$transition->accepted() || !$migration->isAccepted()) {
            $reason = $transition->reason() !== '' ? $transition->reason() : $migration->reason();
            $payload->cleanup();
            return new PackageLifecycleResult(false, 'rejected', $reason, $transition, $migration);
        }
        if ($migration->migrations() !== []) {
            $payload->cleanup();
            return new PackageLifecycleResult(false, 'rejected', 'Normal V3 File Cycle rejects packages with database migrations.', $transition, $migration);
        }
        if ($this->v3RequiresDatabaseCompatibility($manifest->contract())) {
            $payload->cleanup();
            return new PackageLifecycleResult(false, 'rejected', 'Normal V3 File Cycle requires a package without database-version or database-target requirements.', $transition, $migration);
        }

        $committed = $this->committedStore->read();
        $reference = $committed?->sourceInventoryReference();
        if (!$committed instanceof CommittedLifecycleState || !$reference instanceof SourceInventoryReference) {
            $payload->cleanup();
            return new PackageLifecycleResult(false, 'blocked', 'Committed manifest-v3 source inventory evidence is unavailable.', $transition, $migration);
        }

        $source = $this->sourceInventoryArtifacts->read($reference);
        $plan = $this->v3Planner->plan($manifest, $source);
        $applyPlan = WebcoreApplyPlan::fromPayload($payload->withoutPaths([PackageManifestReader::PATH]));
        $consumedContinuation = $continuationEvidence instanceof LifecycleOperationRecord
            && $plan->removalActions() !== []
            && $this->v3ConfirmationAuthority->verifyConsumedContinuation($continuationEvidence, $plan);
        if ($continuationEvidence instanceof LifecycleOperationRecord && !$consumedContinuation) {
            $payload->cleanup();
            return new PackageLifecycleResult(false, 'rejected', 'Persisted destructive continuation evidence is unavailable or stale.', $transition, $migration, $plan->operationIdentity());
        }
        $migrationIdentity = $this->migrationPlanIdentity($migration);
        $now = gmdate(DATE_ATOM);
        $operation = new LifecycleOperationRecord(
            $plan->operationIdentity(), $transition->classification(), $manifest->contract()->targetWebcoreVersion(),
            $manifest->contract()->releaseIdentity(), $payload->archiveSha256(), $payload->stagingPath(),
            $this->v3PayloadIdentity($payload),
            $applyPlan->identity(), LifecycleOperationRecord::PREPARING, 0, null, $migrationIdentity, null, $now, $now
        );

        $lock = $this->mutex->acquire();
        if (!$lock instanceof InstallationLock) { $payload->cleanup(); return new PackageLifecycleResult(false, 'blocked', 'Another lifecycle operation is already running.', $transition, $migration); }
        $session = null;
        try {
            $this->maintenance->enter($operation);
            if ($plan->removalActions() !== []) {
                if ($consumedContinuation) {
                    $operation = $operation->withDestructiveConfirmation(
                        $continuationEvidence->destructiveConfirmationIdentity() ?? throw new \RuntimeException('Persisted destructive confirmation identity is unavailable.'),
                        $continuationEvidence->destructiveConfirmationActorIdentity() ?? throw new \RuntimeException('Persisted destructive actor identity is unavailable.'),
                        $continuationEvidence->destructiveConfirmationRemovalSetDigest() ?? throw new \RuntimeException('Persisted destructive removal digest is unavailable.'),
                        $continuationEvidence->destructiveConfirmationAcceptedAt() ?? throw new \RuntimeException('Persisted destructive confirmation timestamp is unavailable.')
                    )->withDestructiveConfirmationConsumed(gmdate(DATE_ATOM));
                    $this->maintenance->update($operation);
                } else {
                    if (is_array($confirmation)) $confirmation = DestructivePlanConfirmation::fromArray($confirmation);
                    if (!$confirmation instanceof DestructivePlanConfirmation) {
                    $this->maintenance->clear($operation->advance(LifecycleOperationRecord::COMPLETED, 0, null, null, 'Typed destructive confirmation is required.'));
                    return new PackageLifecycleResult(false, 'rejected', 'Typed destructive confirmation is required.', $transition, $migration, $plan->operationIdentity());
                    }
                    if ($authorizedActorIdentity !== null && $confirmation->actorAuthorizationEvidence()->actorIdentity() !== $authorizedActorIdentity) {
                    $this->maintenance->clear($operation->advance(LifecycleOperationRecord::COMPLETED, 0, null, null, 'Destructive actor identity is not the current authorized actor.'));
                    return new PackageLifecycleResult(false, 'rejected', 'Destructive actor identity is not authorized for this request.', $transition, $migration, $plan->operationIdentity());
                    }
                    $receipt = $this->v3ConfirmationAuthority->accept($confirmation, $plan);
                    $operation = $operation->withDestructiveConfirmation(
                    $receipt->bindingIdentity(),
                    $confirmation->actorAuthorizationEvidence()->actorIdentity(),
                    $confirmation->removalSetDigest(),
                    $receipt->acceptedAt()
                    );
                }
            }

            $context = new WebcoreMutationContext($operation, $applyPlan, $transition, $migration, $lock, \Copot\Core\BackupRecovery\FilesystemRecoveryPlan::fromV3Plan($plan), $plan, $recoveryAttemptIdentity);
            $session = $this->v3RecoveryBoundary->enter($context);
            $evidence = $session->evidence();
            if (!isset($evidence['identity'], $evidence['manifest'], $evidence['state'])) throw new \RuntimeException('V3 recovery evidence is incomplete.');
            $operation = $operation->bindRecovery((string) $evidence['identity'], (string) $evidence['manifest'], (string) $evidence['state']);
            $this->maintenance->update($operation);
            $traceExtra = ['recovery_identity' => (string) $evidence['identity'], 'recovery_state' => (string) $evidence['state']];
            if ($recoveryAttemptIdentity !== null) $traceExtra['recovery_attempt_identity'] = $recoveryAttemptIdentity;
            $this->recordRetryTrace($operation->operationId(), 'recovery', 'READY', 'V3 recovery evidence is ready for mutation.', $traceExtra);
            $session->authorize();

            $captured = false;
            $result = $this->v3Executor->execute(
                $plan, $payload->withoutPaths([PackageManifestReader::PATH]), $source, $operation, $this->operationStore ?? throw new \RuntimeException('Lifecycle operation store is unavailable.'), $this->v3Applier,
                $this->liveGuard,
                static function () use (&$captured): bool { $captured = true; return $captured; },
                $plan->removalActions() === [] ? null : $this->v3ConfirmationAuthority,
                $plan->removalActions() === [] || $consumedContinuation ? null : $confirmation,
                null,
                $this->v3BeforeActivation,
                $consumedContinuation
            );
            if ($result->status() !== WebcoreApplyResult::AWAITING_WU6) {
                $this->failAndRestoreV3Session($session, $result->reason());
                return new PackageLifecycleResult(false, strtolower($result->status()), $result->reason(), $transition, $migration, $plan->operationIdentity());
            }
            $latest = $this->maintenance->record();
            if (!$latest instanceof LifecycleOperationRecord) throw new \RuntimeException('V3 lifecycle operation disappeared before migration.');
            $latest = $latest->advance(LifecycleOperationRecord::MIGRATING, $latest->fileCursor(), $latest->lastVerifiedPath());
            $this->maintenance->update($latest);
            $migrationResult = $migration->migrations() === []
                ? new MigrationRunResult(MigrationRunResult::NOOP, [])
                : ($this->v3MigrationRunner)($migration, $plan->operationIdentity(), $transition->classification());
            if (!$migrationResult instanceof MigrationRunResult || $migrationResult->status() === MigrationRunResult::INDETERMINATE) {
                $reason = $migrationResult instanceof MigrationRunResult ? $migrationResult->reason() : 'V3 migration outcome is indeterminate.';
                $latest = $this->maintenance->record();
                if ($latest instanceof LifecycleOperationRecord) $this->maintenance->update($latest->advance(LifecycleOperationRecord::INDETERMINATE, $latest->fileCursor(), $latest->lastVerifiedPath(), MigrationRunResult::INDETERMINATE, $reason));
                $session->fail($reason);
                return new PackageLifecycleResult(false, 'indeterminate', $reason, $transition, $migration, $plan->operationIdentity());
            }
            if (!in_array($migrationResult->status(), [MigrationRunResult::COMPLETED, MigrationRunResult::NOOP], true)) {
                $reason = $migrationResult->reason();
                $latest = $this->maintenance->record();
                if ($latest instanceof LifecycleOperationRecord) $this->maintenance->update($latest->advance(LifecycleOperationRecord::BLOCKED, $latest->fileCursor(), $latest->lastVerifiedPath(), $migrationResult->status(), $reason));
                $session->fail($reason);
                return new PackageLifecycleResult(false, 'blocked', $reason, $transition, $migration, $plan->operationIdentity());
            }
            $latest = $this->maintenance->record();
            if (!$latest instanceof LifecycleOperationRecord) throw new \RuntimeException('V3 lifecycle operation disappeared before finalization.');
            $this->maintenance->update($latest->advance(LifecycleOperationRecord::AWAITING_WU6, $latest->fileCursor(), $latest->lastVerifiedPath(), $migrationResult->status()));
            // Keep the filesystem recovery session authoritative through the
            // commit-state finalization. The finalizer owns its own mutex
            // acquisition, so release only the orchestration lock here; the
            // Guard remains active until the session is explicitly closed.
            $session->settle();
            $lock->release();
            $final = $this->healthCoordinator->finalizeV3($plan->operationIdentity(), $manifest->contract(), $plan, $applyPlan, $migration, $this->liveGuard, null, $this->sourceInventoryArtifacts, [], true);
            if ($final->status() === HealthIntegrityCommitResult::COMPLETED) {
                $session->close();
                $payload->cleanup();
                return new PackageLifecycleResult(true, 'completed', '', $transition, $migration, $plan->operationIdentity());
            }
            if ($final->status() !== HealthIntegrityCommitResult::CLEANUP_PENDING) {
                $this->failAndRestoreV3Session($session, $final->reason());
            } else {
                $session->close();
            }
            return new PackageLifecycleResult(false, strtolower($final->status()), $final->reason(), $transition, $migration, $plan->operationIdentity());
        } catch (\Throwable $exception) {
            if ($session instanceof ProtectedWebcoreMutationSession) { try { $this->failAndRestoreV3Session($session, $exception->getMessage()); } catch (\Throwable) {} }
            try { if (($this->maintenance->record()?->phase() ?? null) !== LifecycleOperationRecord::INDETERMINATE) $this->maintenance->update(($this->maintenance->record() ?? $operation)->advance(LifecycleOperationRecord::BLOCKED, 0, null, null, $exception->getMessage())); } catch (\Throwable) {}
            return new PackageLifecycleResult(false, 'blocked', $exception->getMessage(), $transition, $migration, $plan->operationIdentity());
        } finally {
            try { if ($payload instanceof StagedPayload && $this->maintenance->record() === null) $payload->cleanup(); } catch (\Throwable) {}
            $lock->release();
        }
    }

    private function failAndRestoreV3Session(ProtectedWebcoreMutationSession $session, string $reason): void
    {
        $required = $session->fail($reason);
        if ($required->state() === \Copot\Core\BackupRecovery\RecoveryLifecycleState::RESTORE_REQUIRED) {
            $restored = $session->restore();
            if ($restored->state() !== \Copot\Core\BackupRecovery\RecoveryLifecycleState::RESTORED) {
                throw new \RuntimeException('Filesystem recovery did not reach RESTORED.');
            }
        }
    }

    private function migrationPlanIdentity(CoreMigrationPlan $migration): string
    {
        return hash('sha256', implode("\n", array_map(
            static fn (CoreMigrationDescriptor $descriptor): string => $descriptor->id() . ':' . $descriptor->checksum(),
            array_filter($migration->migrations(), static fn ($descriptor): bool => $descriptor instanceof CoreMigrationDescriptor)
        )));
    }

    private function v3PayloadIdentity(StagedPayload $payload): string
    {
        return hash('sha256', implode(':', array_map(
            static fn (StagedFile $file): string => $file->path() . ':' . $file->sha256(),
            $payload->withoutPaths([PackageManifestReader::PATH])->files()
        )));
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
        $runId = $this->lifecycleTrace?->beginRun();
        $this->activeRetryTraceRunId = $runId;
        $record = $this->maintenance->record();
        $this->recordRetryTrace(
            $operationId,
            'retry',
            'START',
            'Retry invocation started.',
            $record instanceof LifecycleOperationRecord ? [
                'recovery_identity' => $record->recoveryIdentity(),
                'recovery_state' => $record->recoveryState(),
            ] : []
        );
        try {
            $result = $this->retryInternal($operationId);
            $current = $this->maintenance->record();
            $this->recordRetryTrace(
                $result->toArray()['operation_id'] ?? $operationId,
                'terminal',
                $result->accepted() ? 'SUCCESS' : 'FAILED',
                $result->accepted() ? 'Retry completed.' : $result->reason(),
                [
                    'lifecycle_status' => $result->status(),
                    'recovery_identity' => $current?->recoveryIdentity() ?? $record?->recoveryIdentity(),
                    'recovery_state' => $current?->recoveryState(),
                ]
            );
            return $result;
        } catch (\Throwable $exception) {
            $this->recordRetryTrace($operationId, 'terminal', 'FAILED', $exception->getMessage(), ['lifecycle_status' => 'blocked']);
            throw $exception;
        } finally {
            $this->activeRetryTraceRunId = null;
        }
    }

    private function retryInternal(string $operationId): PackageLifecycleResult
    {
        $record = $this->maintenance->record();
        if ($record instanceof LifecycleOperationRecord
            && $record->operationId() === $operationId
            && is_callable($this->v3FilesystemOperationClassifier)
            && (bool) ($this->v3FilesystemOperationClassifier)($record)) {
            return $record->phase() === LifecycleOperationRecord::AWAITING_WU6
                && $record->recoveryState() === \Copot\Core\BackupRecovery\RecoveryLifecycleState::READY
                ? $this->continueAwaitingWu6V3($record)
                : $this->retryV3Filesystem($record);
        }
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

    private function retryV3Filesystem(LifecycleOperationRecord $record): PackageLifecycleResult
    {
        if (!in_array($record->phase(), [LifecycleOperationRecord::BLOCKED, LifecycleOperationRecord::INDETERMINATE, LifecycleOperationRecord::APPLYING, LifecycleOperationRecord::MIGRATING, LifecycleOperationRecord::AWAITING_WU6], true)
            || $record->recoveryIdentity() === null
            || $record->recoveryManifestIdentity() === null
            || !in_array($record->recoveryState(), [\Copot\Core\BackupRecovery\RecoveryLifecycleState::READY, \Copot\Core\BackupRecovery\RecoveryLifecycleState::RESTORED], true)) {
            return new PackageLifecycleResult(false, 'rejected', 'V3 filesystem retry requires a READY recovery record in a recoverable operation phase.', null, null, $record->operationId());
        }
        $source = $record->stagingPath() . DIRECTORY_SEPARATOR . 'source.zip';
        if (!is_file($source) || !is_readable($source)) {
            return new PackageLifecycleResult(false, 'rejected', 'Retained staged package evidence is unavailable.', null, null, $record->operationId());
        }

        $payload = null;
        $lock = null;
        $handedOff = false;
        try {
            [$payload, $manifest, $transition, $migration] = $this->prepare($source);
            if ($migration->migrations() !== []) {
                throw new \RuntimeException('V3 filesystem retry rejects a package with database migrations.');
            }
            $committed = $this->committedStore;
            $reference = $committed?->read()?->sourceInventoryReference();
            if (!$committed instanceof CommittedLifecycleStateStore || !$reference instanceof SourceInventoryReference || !$this->sourceInventoryArtifacts instanceof SourceInventoryArtifactStore || !$this->v3Planner instanceof PackageV3SourceRelativePlanner || !$this->v3RecoveryBoundary instanceof ProtectedWebcoreMutationBoundary) {
                throw new \RuntimeException('V3 filesystem retry composition is unavailable.');
            }
            $sourceInventory = $this->sourceInventoryArtifacts->read($reference);
            $plan = $this->v3Planner->plan($manifest, $sourceInventory);
            $applyPlan = WebcoreApplyPlan::fromPayload($payload->withoutPaths([PackageManifestReader::PATH]));
            $payloadIdentity = $this->v3PayloadIdentity($payload);
            $migrationIdentity = $this->migrationPlanIdentity($migration);
            if ($plan->operationIdentity() !== $record->operationId()
                || $payload->archiveSha256() !== $record->archiveSha256()
                || $payloadIdentity !== $record->payloadIdentity()
                || $applyPlan->identity() !== $record->applyPlanIdentity()
                || $migrationIdentity !== $record->migrationPlanIdentity()
                || $manifest->contract()->releaseIdentity() !== $record->releaseIdentity()
                || $transition->classification() !== $record->classification()
                || $manifest->contract()->targetWebcoreVersion() !== $record->targetWebcoreVersion()) {
                throw new \RuntimeException('V3 retry target evidence does not match the persisted operation.');
            }

            $lock = $this->mutex->acquire();
            if (!$lock instanceof InstallationLock) throw new \RuntimeException('Another lifecycle operation is already running.');
            if ($record->recoveryState() === \Copot\Core\BackupRecovery\RecoveryLifecycleState::READY) {
                $context = new WebcoreMutationContext($record, $applyPlan, $transition, $migration, $lock, \Copot\Core\BackupRecovery\FilesystemRecoveryPlan::fromV3Plan($plan), $plan);
                $session = $this->v3RecoveryBoundary->enterExisting($context, $record->recoveryIdentity(), $record->recoveryManifestIdentity());
                $recoveryState = $session->fail('Interrupted V3 filesystem operation is being restored before retry.');
                if ($recoveryState->state() === \Copot\Core\BackupRecovery\RecoveryLifecycleState::RESTORE_REQUIRED) {
                    $restored = $session->restore();
                    if ($restored->state() !== \Copot\Core\BackupRecovery\RecoveryLifecycleState::RESTORED) throw new \RuntimeException('Interrupted V3 filesystem recovery did not reach RESTORED.');
                } elseif ($recoveryState->state() !== \Copot\Core\BackupRecovery\RecoveryLifecycleState::FAILED_BEFORE_MUTATION) {
                    throw new \RuntimeException('V3 retry recovery did not reach a terminal pre-retry state.');
                }
            }
            $old = $this->maintenance->record();
            if (!$old instanceof LifecycleOperationRecord || $old->operationId() !== $record->operationId()) throw new \RuntimeException('V3 retry operation changed during recovery.');
            $this->recordRetryTrace($old->operationId(), 'recovery', 'SUCCESS', 'Interrupted filesystem state restored before retry.', [
                'recovery_identity' => $old->recoveryIdentity(),
                'recovery_state' => \Copot\Core\BackupRecovery\RecoveryLifecycleState::RESTORED,
            ]);
            $this->maintenance->clear($old->advance(LifecycleOperationRecord::COMPLETED, $old->fileCursor(), $old->lastVerifiedPath(), $old->migrationOutcome(), 'Interrupted filesystem state restored before retry.'));
            $lock->release();
            $lock = null;
            $handedOff = true;
            $continuationEvidence = $old->destructiveConfirmationIdentity() === null ? null : $old;
            $attemptIdentity = 'retry-' . bin2hex(random_bytes(16));
            $this->recordRetryTrace($old->operationId(), 'recovery', 'START', 'Filesystem retry recovery attempt started.', [
                'recovery_identity' => $old->recoveryIdentity(),
                'recovery_attempt_identity' => $attemptIdentity,
            ]);
            return $this->applyV3($payload, $manifest, $transition, $migration, null, null, $attemptIdentity, $continuationEvidence);
        } catch (\Throwable $exception) {
            return new PackageLifecycleResult(false, 'blocked', $exception->getMessage(), null, null, $record->operationId());
        } finally {
            if ($lock instanceof InstallationLock) $lock->release();
            if ($payload instanceof StagedPayload && !$handedOff) { try { $payload->cleanup(); } catch (\Throwable) {} }
        }
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
        if ($record instanceof LifecycleOperationRecord
            && $record->operationId() === $operationId
            && is_callable($this->v3FilesystemOperationClassifier)
            && (bool) ($this->v3FilesystemOperationClassifier)($record)) {
            return $this->continueAwaitingWu6V3($record);
        }
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

    private function continueAwaitingWu6V3(LifecycleOperationRecord $record): PackageLifecycleResult
    {
        if ($record->phase() !== LifecycleOperationRecord::AWAITING_WU6
            || $record->recoveryIdentity() === null
            || $record->recoveryManifestIdentity() === null
            || $record->recoveryState() !== \Copot\Core\BackupRecovery\RecoveryLifecycleState::READY
            || $record->migrationOutcome() !== MigrationRunResult::NOOP
            || $record->fileCursor() <= 0
            || $record->lastVerifiedPath() === null) {
            return new PackageLifecycleResult(false, 'rejected', 'V3 filesystem finalization continuation evidence is unavailable or stale.', null, null, $record->operationId());
        }
        $source = $record->stagingPath() . DIRECTORY_SEPARATOR . 'source.zip';
        if (!is_file($source) || !is_readable($source)) return new PackageLifecycleResult(false, 'rejected', 'Retained staged package evidence is unavailable.', null, null, $record->operationId());
        $payload = null;
        $lock = null;
        try {
            [$payload, $manifest, $transition, $migration] = $this->prepare($source);
            if ($migration->migrations() !== []) throw new \RuntimeException('V3 filesystem continuation rejects a package with database migrations.');
            if (!$this->committedStore instanceof CommittedLifecycleStateStore || !$this->sourceInventoryArtifacts instanceof SourceInventoryArtifactStore || !$this->v3Planner instanceof PackageV3SourceRelativePlanner || !$this->v3RecoveryBoundary instanceof ProtectedWebcoreMutationBoundary) throw new \RuntimeException('V3 filesystem continuation composition is unavailable.');
            $reference = $this->committedStore->read()?->sourceInventoryReference();
            if (!$reference instanceof SourceInventoryReference) throw new \RuntimeException('Committed V3 source inventory evidence is unavailable.');
            $sourceInventory = $this->sourceInventoryArtifacts->read($reference);
            $plan = $this->v3Planner->plan($manifest, $sourceInventory);
            $applyPlan = WebcoreApplyPlan::fromPayload($payload->withoutPaths([PackageManifestReader::PATH]));
            $payloadIdentity = $this->v3PayloadIdentity($payload);
            if ($plan->operationIdentity() !== $record->operationId()
                || $payload->archiveSha256() !== $record->archiveSha256()
                || $payloadIdentity !== $record->payloadIdentity()
                || $applyPlan->identity() !== $record->applyPlanIdentity()
                || $this->migrationPlanIdentity($migration) !== $record->migrationPlanIdentity()
                || $manifest->contract()->releaseIdentity() !== $record->releaseIdentity()
                || $manifest->contract()->targetWebcoreVersion() !== $record->targetWebcoreVersion()) throw new \RuntimeException('V3 filesystem continuation target evidence does not match the persisted operation.');

            $lock = $this->mutex->acquire();
            if (!$lock instanceof InstallationLock) throw new \RuntimeException('Another lifecycle operation is already running.');
            if ($record->stagingPath() !== $payload->stagingPath()) {
                $record = $record->withStagingPath($payload->stagingPath());
                $this->maintenance->update($record);
            }
            $this->recordRetryTrace($record->operationId(), 'finalization', 'START', 'V3 filesystem finalization continuation started.', [
                'recovery_identity' => $record->recoveryIdentity(),
                'recovery_state' => $record->recoveryState(),
            ]);
            $context = new WebcoreMutationContext($record, $applyPlan, $transition, $migration, $lock, \Copot\Core\BackupRecovery\FilesystemRecoveryPlan::fromV3Plan($plan), $plan);
            $session = $this->v3RecoveryBoundary->enterExisting($context, $record->recoveryIdentity(), $record->recoveryManifestIdentity());
            $lock->release();
            $lock = null;
            $final = $this->healthCoordinator->finalizeV3($record->operationId(), $manifest->contract(), $plan, $applyPlan, $migration, $this->liveGuard, null, $this->sourceInventoryArtifacts, [], true);
            if ($final->status() === HealthIntegrityCommitResult::COMPLETED || $final->status() === HealthIntegrityCommitResult::CLEANUP_PENDING) {
                $session->close();
                return new PackageLifecycleResult($final->status() === HealthIntegrityCommitResult::COMPLETED, strtolower($final->status()), $final->reason(), $transition, $migration, $record->operationId());
            }
            $this->failAndRestoreV3Session($session, $final->reason());
            return new PackageLifecycleResult(false, strtolower($final->status()), $final->reason(), $transition, $migration, $record->operationId());
        } catch (\Throwable $exception) {
            return new PackageLifecycleResult(false, 'blocked', $exception->getMessage(), null, null, $record->operationId());
        } finally {
            if ($lock instanceof InstallationLock) $lock->release();
            if ($payload instanceof StagedPayload) { try { $payload->cleanup(); } catch (\Throwable) {} }
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
            if ($manifest->contract()->manifestContractVersion() === PackageContract::V3_MANIFEST_CONTRACT_VERSION) {
                if ($manifest->contract()->migrationDeclaration()->declaresCoreMigrations()) {
                    return [
                        $payload,
                        $manifest,
                        TransitionPlan::rejected(TransitionPlan::REJECTED, 'Normal V3 File Cycle rejects packages declaring database migrations.', $manifest->contract()),
                        CoreMigrationPlan::rejected('Normal V3 File Cycle rejects packages declaring database migrations.'),
                    ];
                }
                if (!$this->v3RequiresDatabaseCompatibility($manifest->contract())) {
                    return $this->prepareV3FilesystemOnly($payload, $manifest);
                }
            }
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

    /**
     * V3 File Cycle preparation is deliberately independent from the
     * database-backed migration/runtime path. Any missing eligibility evidence
     * is rejected before a lifecycle operation, Guard, mutex mutation boundary,
     * or recovery capture can be entered.
     *
     * @return array{0:StagedPayload,1:PackageManifest,2:TransitionPlan,3:CoreMigrationPlan}
     */
    private function prepareV3FilesystemOnly(StagedPayload $payload, PackageManifest $manifest): array
    {
        $contract = $manifest->contract();
        if (!$this->committedStore instanceof CommittedLifecycleStateStore
            || !$this->sourceInventoryArtifacts instanceof SourceInventoryArtifactStore) {
            throw new \RuntimeException('Filesystem-only V3 eligibility composition is unavailable.');
        }

        $committed = $this->committedStore->read();
        $reference = $committed?->sourceInventoryReference();
        if (!$committed instanceof CommittedLifecycleState || !$reference instanceof SourceInventoryReference) {
            throw new \RuntimeException('Filesystem-only V3 requires committed lifecycle and source-inventory evidence.');
        }
        $marker = $this->installationState->readMarker();
        if (!is_array($marker)
            || ($marker['version'] ?? null) !== $committed->webcoreVersion()
            || ($marker['installed_at'] ?? null) !== $committed->committedAt()->format(DATE_ATOM)) {
            throw new \RuntimeException('Filesystem-only V3 requires a marker consistent with committed lifecycle state.');
        }
        $source = $this->sourceInventoryArtifacts->read($reference);
        if ($source->reference()->toArray() !== $reference->toArray()) {
            throw new \RuntimeException('Filesystem-only V3 source-inventory evidence is inconsistent.');
        }

        // A filesystem-only package explicitly declares no database runtime
        // minimum. Keep preparation database-free; never synthesize an
        // observed database version merely to satisfy a compatibility check.
        $installed = InstalledStateInspection::committed($committed->snapshot());
        $runtime = new RuntimeCompatibilityContext(PHP_VERSION, [], get_loaded_extensions());
        $transition = $this->transitionPlanner->plan($installed, $contract, $runtime);
        $migration = CoreMigrationPlan::allow(
            $committed->webcoreVersion(),
            $contract->targetWebcoreVersion(),
            $committed->schemaStateIdentity(),
            $committed->schemaStateIdentity(),
            []
        );
        return [$payload, $manifest, $transition, $migration];
    }

    private function v3RequiresDatabaseCompatibility(PackageContract $contract): bool
    {
        if ($contract->runtimeCompatibility()->minimumDatabaseVersions() !== []) {
            return true;
        }

        foreach ($contract->targetRequirements()->requirements() as $requirement) {
            if ($requirement instanceof PackageTargetRequirement && $requirement->kind() === PackageTargetRequirement::DATABASE) {
                return true;
            }
        }

        return false;
    }

    private function recordRetryTrace(string $operationId, string $stage, string $status, string $summary, array $extra = []): void
    {
        if ($this->activeRetryTraceRunId === null || !$this->lifecycleTrace instanceof LifecycleTrace) return;
        $safeExtra = array_filter($extra, static fn ($value): bool => is_scalar($value) && $value !== null);
        $this->lifecycleTrace->record($this->activeRetryTraceRunId, $operationId, $stage, 'retry', $status, $summary, $safeExtra);
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
