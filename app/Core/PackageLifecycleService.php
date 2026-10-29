<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\ApplicationWriteProtectionEvidence;
use Copot\Core\BackupRecovery\CommittedSourceAuthorityRecoveryBinding;
use Copot\Core\BackupRecovery\CommittedSourceAuthorityRecoveryAdapter;
use Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureRequest;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\RecoveryLifecycleCoordinator;
use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\BackupRecovery\InstallationScopedMaintenanceGuard;
use Copot\Core\BackupRecovery\MySqlRecoveryProvider;
use Copot\Core\BackupRecovery\ScopedDatabaseConsistencyEvidence;
use Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureService;
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
        private ?CommittedLifecycleMetadataReconciliationService $metadataReconciliation = null,
        private ?CommittedSourceAuthorityPlanner $sourceAuthorityPlanner = null,
        private ?CommittedLifecycleMetadataReconciliationOrchestrator $metadataOrchestrator = null,
        private ?PreMutationSettlementService $preMutationSettlement = null,
        private ?CommittedSourceAuthorityRecoveryAdapter $sourceAuthorityRecovery = null,
        private ?InstallationScopedMaintenanceGuard $scopedMaintenanceGuard = null,
        private ?DatabaseTableOwnershipCatalog $databaseOwnershipCatalog = null,
        private ?string $authorityStoragePath = null,
        private ?string $authorityDatabaseIdentity = null,
        private ?string $authorityNamespaceIdentity = null,
        private ?string $authorityDeploymentIdentity = null
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

    /**
     * Production preparation entrypoint for source authority. It creates no
     * execution authority: WU6/finalization remains a separate caller action.
     */
    public function prepareCommittedSourceAuthorityFromRuntime(
        PackageManifest $manifest,
        CanonicalTargetInventory $actualTarget,
        InstallerOwnershipProof $ownershipProof,
        ?CoreMigrationPlan $migrationPlan = null,
        ?CommittedSourceAuthorityPreservationPolicy $preservationPolicy = null
    ): CommittedSourceAuthorityPreparation {
        if (!$this->scopedMaintenanceGuard instanceof InstallationScopedMaintenanceGuard
            || !$this->databaseOwnershipCatalog instanceof DatabaseTableOwnershipCatalog
            || !is_string($this->authorityDatabaseIdentity)
            || !is_string($this->authorityNamespaceIdentity)
            || !is_string($this->authorityDeploymentIdentity)) {
            throw new \RuntimeException('Committed source-authority runtime composition is unavailable.');
        }
        if (!is_string($this->authorityStoragePath)) {
            throw new \RuntimeException('Committed source-authority storage composition is unavailable.');
        }
        $installation = (new InstallationIdentityStore($this->authorityStoragePath))->read();
        $committed = $this->committedStore?->read();
        if (!$installation instanceof InstallationIdentity || !$committed instanceof CommittedLifecycleState) {
            throw new \RuntimeException('Installation or committed lifecycle state is unavailable.');
        }
        if ($ownershipProof->installationId() !== $installation->value() || !$ownershipProof->isVerified()) {
            throw new \RuntimeException('Installer ownership proof is invalid for the current installation.');
        }
        $connection = ($this->connection)();
        $tables = new DatabaseTableNames($this->authorityNamespaceIdentity);
        $generationEvidence = (new CoreSchemaGenerationStore($tables))->readIfPresent($connection, $this->authorityNamespaceIdentity);
        $expectedGeneration = (new CoreSchemaMaterializer())->generationIdentity($this->canonicalSchemaPath);
        if (!is_array($generationEvidence)
            || ($generationEvidence['namespace'] ?? null) !== $this->authorityNamespaceIdentity
            || ($generationEvidence['compatibility_state'] ?? null) !== 'compatible'
            || ($generationEvidence['schema_generation'] ?? null) !== $expectedGeneration) {
            throw new \RuntimeException('Verified canonical schema-generation evidence is unavailable or mismatched.');
        }
        $scope = DatabaseScope::fromOwnershipCatalog(
            $installation->value(),
            $this->authorityDatabaseIdentity,
            $this->authorityNamespaceIdentity,
            $this->databaseOwnershipCatalog,
            [],
            $generationEvidence
        );
        $plan = $this->planCommittedSourceAuthority($committed, $installation, $manifest, $actualTarget, $scope, $ownershipProof, $migrationPlan, $preservationPolicy);
        $recovery = NormalWebcoreRecoveryCaptureService::forOperation($plan->operation()->operationId(), $plan->applyPlan()->identity());
        $guardEvidence = $this->scopedMaintenanceGuard->produceEvidence($recovery, $scope);
        $provider = new MySqlRecoveryProvider();
        $before = $provider->stateIdentity($connection, $scope->databaseIdentity(), $scope);
        $databaseEvidence = new ScopedDatabaseConsistencyEvidence(
            $scope,
            $recovery,
            $guardEvidence->writerExclusionIdentity(),
            hash('sha256', json_encode([
                'scope' => $scope->identity(),
                'state_identity' => $before,
                'tables' => $scope->tablesetFingerprint(),
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            $guardEvidence->capturedAt(),
            $guardEvidence->validUntil()
        );
        $after = $provider->stateIdentity($connection, $scope->databaseIdentity(), $scope);
        if (!hash_equals($before, $after)) {
            throw new \RuntimeException('Scoped database state changed while composing source-authority evidence.');
        }
        $request = new NormalWebcoreRecoveryCaptureRequest(
            $plan->operation()->operationId(),
            $installation->value(),
            $scope->namespaceIdentity(),
            $plan->committedLifecycleIdentity(),
            $plan->sourcePlan()->packageEvidenceIdentity(),
            $plan->operation()->releaseIdentity(),
            $plan->operation()->archiveSha256(),
            $plan->applyPlan(),
            $plan->committedLifecycleIdentity(),
            $plan->migrationStateIdentity(),
            $scope->databaseIdentity(),
            null,
            $scope,
            $databaseEvidence,
            $guardEvidence,
            $plan->sourcePlan()
        );
        return $this->prepareCommittedSourceAuthority($committed, $installation, $manifest, $actualTarget, $scope, $ownershipProof, $request, $migrationPlan, $preservationPolicy);
    }

    /** Settles only a proven PREPARING operation before its mutation boundary. */
    public function settlePreMutation(PreMutationSettlementRequest $request): LifecycleOperationRecord
    {
        if (!$this->preMutationSettlement instanceof PreMutationSettlementService) {
            throw new \RuntimeException('Pre-mutation settlement is unavailable.');
        }
        return $this->preMutationSettlement->settle($request);
    }

    /** Non-mutating canonical planning entrypoint. */
    public function planCommittedSourceAuthority(
        CommittedLifecycleState $committed,
        InstallationIdentity $installation,
        PackageManifest $manifest,
        CanonicalTargetInventory $actualTarget,
        \Copot\Core\BackupRecovery\DatabaseScope $databaseScope,
        InstallerOwnershipProof $ownershipProof,
        ?CoreMigrationPlan $migrationPlan = null,
        ?CommittedSourceAuthorityPreservationPolicy $preservationPolicy = null
    ): CommittedSourceAuthorityPlan {
        if (!$this->sourceAuthorityPlanner instanceof CommittedSourceAuthorityPlanner) {
            throw new \RuntimeException('Committed source-authority planning is unavailable.');
        }
        $current = $this->sourceAuthorityMetadataGate()->assert($committed);
        return $this->sourceAuthorityPlanner->plan($current, $installation, $manifest, $actualTarget, $databaseScope, $ownershipProof, $migrationPlan, $preservationPolicy);
    }

    /**
     * Canonical AUTHORITY_ONLY preparation.  Operation creation is the
     * durable exclusion boundary; recovery capture then establishes the
     * scoped Guard/mutex owner through Backup & Recovery.
     */
    public function prepareCommittedSourceAuthority(
        CommittedLifecycleState $committed,
        InstallationIdentity $installation,
        PackageManifest $manifest,
        CanonicalTargetInventory $actualTarget,
        \Copot\Core\BackupRecovery\DatabaseScope $databaseScope,
        InstallerOwnershipProof $ownershipProof,
        NormalWebcoreRecoveryCaptureRequest $recoveryRequest,
        ?CoreMigrationPlan $migrationPlan = null,
        ?CommittedSourceAuthorityPreservationPolicy $preservationPolicy = null
    ): CommittedSourceAuthorityPreparation {
        $plan = $this->planCommittedSourceAuthority($committed, $installation, $manifest, $actualTarget, $databaseScope, $ownershipProof, $migrationPlan, $preservationPolicy);
        return $this->prepareSourceAuthorityPlan($plan, $recoveryRequest, true);
    }

    /** Rehydrates one exact immutable plan without creating a replacement operation. */
    public function resumeCommittedSourceAuthority(
        CommittedSourceAuthorityPlan $plan,
        NormalWebcoreRecoveryCaptureRequest $recoveryRequest
    ): CommittedSourceAuthorityPreparation {
        return $this->prepareSourceAuthorityPlan($plan, $recoveryRequest, false);
    }

    /**
     * Completes an authority-only handoff and WU6 finalization while the
     * preparation-owned recovery session remains open.
     */
    public function executePreparedCommittedSourceAuthority(
        CommittedSourceAuthorityPreparation $preparation
    ): HealthIntegrityCommitResult {
        if (!$preparation->plan()->authorityOnly()) {
            throw new \RuntimeException('Mutation-required source-authority execution is outside R3-A.');
        }
        try {
            $admission = $preparation->admission();
            $current = $this->operationStore?->read();
            if (!$current instanceof LifecycleOperationRecord || $current->operationId() !== $admission->plan()->operation()->operationId()) {
                throw new \RuntimeException('Authority operation disappeared before canonical execution.');
            }
            if ($current->phase() === LifecycleOperationRecord::PREPARING) {
                $admission->advanceToAwaitingWu6(
                    $this->sourceAuthorityRecovery?->coordinator() ?? throw new \RuntimeException('Authority recovery composition is unavailable.'),
                    $this->operationStore,
                    $this->sourceAuthorityRecovery->artifacts(),
                    $preparation->binding()->applicationProtection(),
                    $preparation->binding()->databaseConsistency()
                );
            } elseif (!in_array($current->phase(), [LifecycleOperationRecord::AWAITING_WU6, LifecycleOperationRecord::CLEANUP_PENDING], true)) {
                throw new \RuntimeException('Authority operation is not in a canonical continuation phase.');
            }
            $result = $this->healthCoordinator->finalizeCommittedSourceAuthority(
                $admission,
                $this->sourceAuthorityRecovery?->coordinator() ?? throw new \RuntimeException('Authority recovery composition is unavailable.'),
                $this->operationStore,
                $this->sourceAuthorityRecovery->artifacts(),
                $this->liveGuard,
                ($this->connection)(),
                $this->sourceInventoryArtifacts ?? throw new \RuntimeException('Source inventory composition is unavailable.'),
                ($this->runtimeChecks)(),
                $preparation->activeLock()
            );
            return $result;
        } finally {
            $preparation->close();
        }
    }

    private function prepareSourceAuthorityPlan(
        CommittedSourceAuthorityPlan $plan,
        NormalWebcoreRecoveryCaptureRequest $recoveryRequest,
        bool $allowFreshCapture
    ): CommittedSourceAuthorityPreparation {
        if (!$this->sourceAuthorityRecovery instanceof CommittedSourceAuthorityRecoveryAdapter
            || !$this->operationStore instanceof LifecycleOperationStore) {
            throw new \RuntimeException('Committed source-authority recovery composition is unavailable.');
        }
        $current = $this->operationStore->read();
        $created = false;
        if ($current === null) {
            if (!$allowFreshCapture) {
                throw new \RuntimeException('Cannot resume committed source authority without a persisted operation.');
            }
            $this->operationStore->create($plan->operation());
            $current = $this->operationStore->read();
            $created = true;
        }
        if (!$current instanceof LifecycleOperationRecord
            || $current->operationId() !== $plan->operation()->operationId()
            || $current->classification() !== $plan->operation()->classification()
            || $current->applyPlanIdentity() !== $plan->applyPlan()->identity()
            || $current->migrationPlanIdentity() !== $plan->migrationPlanIdentity()
            || !in_array($current->phase(), [LifecycleOperationRecord::PREPARING, LifecycleOperationRecord::AWAITING_WU6, LifecycleOperationRecord::CLEANUP_PENDING], true)) {
            throw new \RuntimeException('Committed source-authority operation is conflicting or stale.');
        }
        $captured = $this->sourceAuthorityRecovery->captureOrRehydrate($plan, $recoveryRequest, $created);
        $binding = $captured['binding'];
        try {
            $admission = $current->phase() === LifecycleOperationRecord::PREPARING
                ? $this->admitCommittedSourceAuthority(
                    $plan,
                    $binding,
                    $this->sourceAuthorityRecovery->coordinator(),
                    $this->operationStore,
                    $this->sourceAuthorityRecovery->artifacts(),
                    $binding->applicationProtection(),
                    $binding->databaseConsistency()
                )
                : CommittedSourceAuthorityExecutionAdmission::rehydrate(
                    $plan,
                    $binding,
                    $this->sourceAuthorityRecovery->coordinator(),
                    $this->operationStore,
                    $this->sourceAuthorityRecovery->artifacts(),
                    $binding->applicationProtection(),
                    $binding->databaseConsistency(),
                    $this->committedStore?->read() ?? throw new \RuntimeException('Committed lifecycle state is unavailable for authority continuation.')
                );
        } catch (\Throwable $exception) {
            if ($captured['session'] instanceof \Copot\Core\BackupRecovery\NormalWebcoreRecoverySession) {
                $captured['session']->close();
            } elseif ($captured['owns']) {
                $this->sourceAuthorityRecovery->coordinator()->leaveMaintenance($binding->crossReference()->recoveryIdentity());
            }
            throw $exception;
        }
        return new CommittedSourceAuthorityPreparation(
            $plan,
            $binding,
            $admission,
            $this->sourceAuthorityRecovery->coordinator(),
            $captured['session'],
            $captured['owns']
        );
    }

    /** Routes a prepared plan through canonical, non-authorizing admission. */
    public function admitCommittedSourceAuthority(
        CommittedSourceAuthorityPlan $plan,
        CommittedSourceAuthorityRecoveryBinding $binding,
        RecoveryLifecycleCoordinator $recoveryCoordinator,
        LifecycleOperationStore $operations,
        RecoveryArtifactStore $artifacts,
        ApplicationWriteProtectionEvidence $application,
        ScopedDatabaseConsistencyEvidence $database
    ): CommittedSourceAuthorityExecutionAdmission {
        return CommittedSourceAuthorityExecutionAdmission::issue($plan, $binding, $recoveryCoordinator, $operations, $artifacts, $application, $database, $this->sourceAuthorityMetadataGate());
    }

    /** Plans canonical metadata-only reconciliation without persisting an operation. */
    public function planCommittedLifecycleMetadataReconciliation(
        CommittedLifecycleMetadataReconciliationEvidence $evidence
    ): CommittedLifecycleMetadataReconciliationPlan {
        if (!$this->metadataReconciliation instanceof CommittedLifecycleMetadataReconciliationService) {
            throw new \RuntimeException('Committed lifecycle metadata reconciliation is unavailable.');
        }
        return $this->metadataReconciliation->plan($evidence);
    }

    /** Executes only a previously evidenced metadata reconciliation plan. */
    public function executeCommittedLifecycleMetadataReconciliation(
        CommittedLifecycleMetadataReconciliationPlan $plan,
        RecoveryLifecycleCoordinator $recovery,
        \Copot\Core\BackupRecovery\RecoveryLifecycleStore $recoveryStore,
        RecoveryArtifactStore $artifacts
    ): LifecycleOperationRecord {
        if (!$this->metadataReconciliation instanceof CommittedLifecycleMetadataReconciliationService) {
            throw new \RuntimeException('Committed lifecycle metadata reconciliation is unavailable.');
        }
        return $this->metadataReconciliation->execute($plan, $recovery, $recoveryStore, $artifacts);
    }

    /** Canonical target-bound preparation; caller supplies no identities or evidence. */
    public function prepareCommittedLifecycleMetadataReconciliation(): CommittedLifecycleMetadataReconciliationPreparation
    {
        if (!$this->metadataOrchestrator instanceof CommittedLifecycleMetadataReconciliationOrchestrator) {
            throw new \RuntimeException('Committed lifecycle metadata orchestration is unavailable.');
        }
        return $this->metadataOrchestrator->prepare();
    }

    /** Rehydrates a persisted recovery-ready metadata preparation without recapture. */
    public function resumeCommittedLifecycleMetadataReconciliation(string $operationIdentity): CommittedLifecycleMetadataReconciliationPreparation
    {
        if (!$this->metadataOrchestrator instanceof CommittedLifecycleMetadataReconciliationOrchestrator) {
            throw new \RuntimeException('Committed lifecycle metadata orchestration is unavailable.');
        }
        return $this->metadataOrchestrator->resume($operationIdentity);
    }

    /** Completes only an already-verified post-mutation reconciliation cleanup. */
    public function continueCommittedLifecycleMetadataCleanup(string $operationIdentity): \Copot\Core\BackupRecovery\RecoveryLifecycleRecord
    {
        if (!$this->metadataOrchestrator instanceof CommittedLifecycleMetadataReconciliationOrchestrator) {
            throw new \RuntimeException('Committed lifecycle metadata orchestration is unavailable.');
        }
        return $this->metadataOrchestrator->continueCleanup($operationIdentity);
    }

    /** Execute only a recovery-ready preparation produced by the canonical orchestrator. */
    public function executePreparedCommittedLifecycleMetadataReconciliation(CommittedLifecycleMetadataReconciliationPreparation $preparation): LifecycleOperationRecord
    {
        if (!$this->metadataOrchestrator instanceof CommittedLifecycleMetadataReconciliationOrchestrator) {
            throw new \RuntimeException('Committed lifecycle metadata orchestration is unavailable.');
        }
        return $this->metadataOrchestrator->execute($preparation);
    }

    /**
     * Executes a mutation-required source-authority plan through the existing
     * V3 executor and recovery coordinator. This method does not publish
     * committed source authority or perform WU6 finalization.
     */
    public function executeCommittedSourceAuthorityMutation(
        CommittedSourceAuthorityExecutionAdmission $admission,
        StagedPayload $payload,
        ?SourceInventoryArtifact $source,
        CanonicalTargetInventory $actualTarget,
        RecoveryLifecycleCoordinator $recoveryCoordinator,
        LifecycleOperationStore $operations,
        ?callable $journal = null,
        ?callable $beforeActivation = null
    ): WebcoreApplyResult {
        try {
            $this->sourceAuthorityMetadataGate()->assert(null, $admission->plan());
        } catch (\Throwable $exception) {
            return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, [], $exception->getMessage(), $admission->plan()->operation()->operationId());
        }
        if (!$admission->mutationRequired() || $admission->mutationAuthorized()) {
            return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, [], 'Only an admitted mutation-required plan may enter canonical mutation execution.');
        }
        if (!$this->v3Executor instanceof V3ExplicitApplyExecutor
            || !$this->v3Applier instanceof PackageOwnedFileApplier
            || !$this->operationStore instanceof LifecycleOperationStore) {
            return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, [], 'Canonical mutation execution composition is unavailable.');
        }

        $plan = $admission->plan();
        $binding = $admission->binding();
        try {
            $plan->assertTargetFingerprint($actualTarget);
            $this->v3Executor->assertMutationPreconditions($plan->sourcePlan(), $payload, $source, $plan->operation(), $this->liveGuard, $plan->operation()->operationId());
        } catch (\Throwable $exception) {
            return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, [], $exception->getMessage(), $plan->operation()->operationId());
        }
        $persisted = $operations->read();
        if (!$persisted instanceof LifecycleOperationRecord
            || $persisted->operationId() !== $plan->operation()->operationId()
            || $persisted->classification() !== $plan->operation()->classification()) {
            return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, [], 'Canonical mutation operation identity is stale or conflicting.');
        }
        $recovery = $binding->crossReference()->recoveryIdentity();
        $permit = null;
        try {
            $recoveryCoordinator->confirm($recovery, $binding->manifest()->identity(), $plan->identity());
            $permit = $recoveryCoordinator->authorizeMutation(
                $recovery,
                $binding->manifest()->identity(),
                $plan->identity(),
                null,
                $binding->databaseConsistency()
            );
            if (!$permit->isValid()) {
                return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, [], 'Canonical recovery mutation permit is invalid.');
            }

            $result = $this->v3Executor->execute(
                $plan->sourcePlan(),
                $payload,
                $source,
                $plan->operation(),
                $operations,
                $this->v3Applier,
                $this->liveGuard,
                static fn (V3SourceRelativePlan $_plan, LifecycleOperationRecord $_operation): bool => true,
                null,
                null,
                $journal,
                $beforeActivation,
                $plan->operation()->operationId(),
                true
            );
            if ($result->status() !== WebcoreApplyResult::AWAITING_WU6) {
                return $result;
            }
            $recoveryCoordinator->recordPostReconciliationVerification($recovery, $binding->manifest()->identity(), $plan->identity());
            return $result;
        } catch (\Throwable $exception) {
            return new WebcoreApplyResult(WebcoreApplyResult::INDETERMINATE, [], $exception->getMessage(), $plan->operation()->operationId());
        } finally {
            $permit?->release();
        }
    }

    private function sourceAuthorityMetadataGate(): CommittedSourceAuthorityMetadataGate
    {
        return new CommittedSourceAuthorityMetadataGate(
            $this->committedStore,
            $this->installationState,
            $this->ledger,
            $this->connection,
            $this->canonicalSchema,
            $this->canonicalSchemaPath,
            $this->operationStore
        );
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
        try {
            [$payload, $manifest, $transition, $migration] = $this->prepare($zip);
            if ($manifest->contract()->manifestContractVersion() === PackageContract::V3_MANIFEST_CONTRACT_VERSION) {
                return $this->applyV3($payload, $manifest, $transition, $migration, $confirmation, $authorizedActorIdentity);
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
            return new PackageLifecycleResult(false, $this->isCapabilityFailure($exception) ? 'unavailable' : 'invalid_package', $exception->getMessage());
        }
    }

    private function applyV3(StagedPayload $payload, PackageManifest $manifest, TransitionPlan $transition, CoreMigrationPlan $migration, mixed $confirmation, ?string $authorizedActorIdentity): PackageLifecycleResult
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
        if ($migration->migrations() !== [] && !is_callable($this->v3MigrationRunner)) {
            $payload->cleanup();
            return new PackageLifecycleResult(false, 'unavailable', 'Manifest-v3 migration runner is unavailable.', $transition, $migration);
        }

        $committed = $this->committedStore->read();
        $reference = $committed?->sourceInventoryReference();
        if (!$committed instanceof CommittedLifecycleState || !$reference instanceof SourceInventoryReference) {
            $payload->cleanup();
            return new PackageLifecycleResult(false, 'blocked', 'Committed manifest-v3 source inventory evidence is unavailable.', $transition, $migration);
        }

        $source = $this->sourceInventoryArtifacts->read($reference);
        $plan = $this->v3Planner->plan($manifest, $source);
        $applyPlan = WebcoreApplyPlan::fromPayload($payload);
        $migrationIdentity = $this->migrationPlanIdentity($migration);
        $now = gmdate(DATE_ATOM);
        $operation = new LifecycleOperationRecord(
            $plan->operationIdentity(), $transition->classification(), $manifest->contract()->targetWebcoreVersion(),
            $manifest->contract()->releaseIdentity(), $payload->archiveSha256(), $payload->stagingPath(),
            hash('sha256', implode(':', array_map(static fn (StagedFile $file): string => $file->path() . ':' . $file->sha256(), $payload->files()))),
            $applyPlan->identity(), LifecycleOperationRecord::PREPARING, 0, null, $migrationIdentity, null, $now, $now
        );

        $lock = $this->mutex->acquire();
        if (!$lock instanceof InstallationLock) { $payload->cleanup(); return new PackageLifecycleResult(false, 'blocked', 'Another lifecycle operation is already running.', $transition, $migration); }
        $session = null;
        try {
            $this->maintenance->enter($operation);
            if ($plan->removalActions() !== []) {
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

            $context = new WebcoreMutationContext($operation, $applyPlan, $transition, $migration, $lock, \Copot\Core\BackupRecovery\FilesystemRecoveryPlan::fromV3Plan($plan));
            $session = $this->v3RecoveryBoundary->enter($context);
            $evidence = $session->evidence();
            if (!isset($evidence['identity'], $evidence['manifest'], $evidence['state'])) throw new \RuntimeException('V3 recovery evidence is incomplete.');
            $operation = $operation->bindRecovery((string) $evidence['identity'], (string) $evidence['manifest'], (string) $evidence['state']);
            $this->maintenance->update($operation);
            $session->authorize();

            $captured = false;
            $result = $this->v3Executor->execute(
                $plan, $payload->withoutPaths([PackageManifestReader::PATH]), $source, $operation, $this->operationStore ?? throw new \RuntimeException('Lifecycle operation store is unavailable.'), $this->v3Applier,
                $this->liveGuard,
                static function () use (&$captured): bool { $captured = true; return $captured; },
                $plan->removalActions() === [] ? null : $this->v3ConfirmationAuthority,
                $plan->removalActions() === [] ? null : $confirmation
            );
            if ($result->status() !== WebcoreApplyResult::AWAITING_WU6) {
                $session->fail($result->reason());
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
            $session->complete();
            $final = $this->healthCoordinator->finalizeV3($plan->operationIdentity(), $manifest->contract(), $plan, $applyPlan, $migration, $this->liveGuard, ($this->connection)(), $this->sourceInventoryArtifacts, ($this->runtimeChecks)());
            if ($final->status() === HealthIntegrityCommitResult::COMPLETED) {
                $payload->cleanup();
                return new PackageLifecycleResult(true, 'completed', '', $transition, $migration, $plan->operationIdentity());
            }
            return new PackageLifecycleResult(false, strtolower($final->status()), $final->reason(), $transition, $migration, $plan->operationIdentity());
        } catch (\Throwable $exception) {
            if ($session instanceof ProtectedWebcoreMutationSession) { try { $session->fail($exception->getMessage()); } catch (\Throwable) {} }
            try { if (($this->maintenance->record()?->phase() ?? null) !== LifecycleOperationRecord::INDETERMINATE) $this->maintenance->update(($this->maintenance->record() ?? $operation)->advance(LifecycleOperationRecord::BLOCKED, 0, null, null, $exception->getMessage())); } catch (\Throwable) {}
            return new PackageLifecycleResult(false, 'blocked', $exception->getMessage(), $transition, $migration, $plan->operationIdentity());
        } finally {
            try { if ($payload instanceof StagedPayload && $this->maintenance->record() === null) $payload->cleanup(); } catch (\Throwable) {}
            $lock->release();
        }
    }

    private function migrationPlanIdentity(CoreMigrationPlan $migration): string
    {
        return hash('sha256', implode("\n", array_map(
            static fn (CoreMigrationDescriptor $descriptor): string => $descriptor->id() . ':' . $descriptor->checksum(),
            array_filter($migration->migrations(), static fn ($descriptor): bool => $descriptor instanceof CoreMigrationDescriptor)
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

    private function supportsOrdinaryRetryClassification(LifecycleOperationRecord $record): bool
    {
        return in_array($record->classification(), [
            TransitionPlan::PATCH,
            TransitionPlan::UPDATE,
            TransitionPlan::UPGRADE,
            TransitionPlan::REPAIR,
            TransitionPlan::DATABASE_UPDATE,
        ], true);
    }

    public function retryEvidence(string $operationId): bool
    {
        $record = $this->maintenance->record();
        if (!$record instanceof LifecycleOperationRecord || $record->operationId() !== $operationId
            || !$this->supportsOrdinaryRetryClassification($record)
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
            && $this->supportsOrdinaryRetryClassification($record)
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
        if (!$record instanceof LifecycleOperationRecord
            || $record->operationId() !== $operationId
            || !$this->supportsOrdinaryRetryClassification($record)) {
            return new PackageLifecycleResult(false, 'rejected', 'Retry is unavailable for this lifecycle classification.');
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
        if (!$record instanceof LifecycleOperationRecord || $record->operationId() !== $operationId) {
            return new PackageLifecycleResult(false, 'rejected', 'Awaiting-WU6 continuation evidence is unavailable or stale.', null, null, $operationId);
        }
        if ($record->classification() === LegacyBootstrapPreparation::OPERATION_TYPE) {
            return new PackageLifecycleResult(false, 'rejected', 'Legacy bootstrap WU6 continuation is retired.', null, null, $operationId);
        }
        if ($record->phase() !== LifecycleOperationRecord::AWAITING_WU6
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

            $final = $this->finalizeAwaitingWu6($operationId, $manifest, $applyPlan, $migration);
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

    /** Finalize an ordinary V3 operation through the canonical WU6 boundary. */
    private function finalizeAwaitingWu6(
        string $operationId,
        PackageManifest $manifest,
        WebcoreApplyPlan $applyPlan,
        CoreMigrationPlan $migration
    ): HealthIntegrityCommitResult {
        return $this->healthCoordinator->finalize(
            $operationId,
            $manifest->contract(),
            $applyPlan,
            $migration,
            $this->liveGuard,
            ($this->connection)(),
            ($this->runtimeChecks)()
        );
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
