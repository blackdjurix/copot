<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\DatabaseCaptureContext;
use Copot\Core\BackupRecovery\DatabaseQuiescenceCapability;
use Copot\Core\BackupRecovery\FilesystemRecoveryDomain;
use Copot\Core\BackupRecovery\FilesystemRecoveryPlan;
use Copot\Core\BackupRecovery\InstalledLockRecoveryDomain;
use Copot\Core\BackupRecovery\LifecycleRecoveryDomain;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\RecoveryDomainIdentity;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryLifecycleCoordinator;
use Copot\Core\BackupRecovery\RecoveryLifecycleRecord;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;
use Copot\Core\BackupRecovery\RecoveryManifest;
use Copot\Core\BackupRecovery\RecoveryRootResolver;
use Copot\Core\BackupRecovery\DatabaseRecoveryProvider;

/** Production composition for the already accepted IU2 WU1-WU6 boundaries. */
final class LegacyReconciliationOperator
{
    /**
     * @param callable():ExistingInstallEvidence $evidence
     * @param callable():\PDO $connection
     * @param callable():\PDO $freshConnection
     * @param callable():RuntimeCompatibilityContext $runtime
     * @param callable():array<string, callable> $runtimeChecks
     */
    public function __construct(
        private ZipIntakeService $intake,
        private PackageManifestReader $manifestReader,
        private PackageInventoryVerifier $inventoryVerifier,
        private InstalledStateInspector $installedInspector,
        private InstallationState $installation,
        private $evidence,
        private LegacyRuntimeClassifier $classifier,
        private LegacyReconciliationPlanner $planner,
        private CoreMigrationRegistry $registry,
        private CoreMigrationLedger $ledger,
        private $connection,
        private $reconciliationConnection,
        private $freshConnection,
        private $runtime,
        private $runtimeChecks,
        private string $databaseName,
        private string $canonicalSchemaPath,
        private CanonicalSchemaBaselineVerifier $canonicalSchema,
        private LiveTreePathGuard $liveGuard,
        private PackageOwnedFileApplier $applier,
        private LegacyReconciliationDatabaseReconciler $databaseReconciler,
        private LegacyReconciliationFinalizer $finalizer,
        private LegacyReconciliationRecoveryOrchestrator $recoveryOrchestrator,
        private LegacyReconciliationIntegratedLifecycle $integratedLifecycle,
        private RecoveryLifecycleCoordinator $recoveryCoordinator,
        private RecoveryLifecycleStore $recoveryStore,
        private RecoveryArtifactStore $artifactStore,
        private FilesystemRecoveryDomain $filesystemRecovery,
        private LifecycleRecoveryDomain $lifecycleRecovery,
        private InstalledLockRecoveryDomain $installedLockRecovery,
        private DatabaseRecoveryProvider $databaseRecovery,
        private DatabaseQuiescenceCapability $quiescence,
        private RecoveryRootResolver $recoveryRoot,
        private CommittedLifecycleStateStore $committedStore,
        private InstallationMutex $mutex
    ) {}

    public function reconcile(string $zip, bool $confirmed): PackageLifecycleResult
    {
        $payload = null;
        $eligibility = null;
        try {
            $payload = $this->intake->intake($zip);
            $manifest = $this->manifestReader->read($payload);
            $this->inventoryVerifier->verify($manifest->payload(), $manifest->contract()->inventory());
            $target = TrustedWebcorePackageTarget::fromManifest($manifest, $this->inventoryVerifier);
            $installed = $this->installedInspector->inspect($this->installation, ($this->evidence)());
            $classification = $this->classifier->classify($installed, ($this->connection)(), $this->canonicalSchemaPath, $this->registry);
            $plan = $this->planner->plan($target, $classification, ($this->runtime)(), $this->registry, $this->liveGuard);

            if (!$confirmed) {
                return $this->result(false, 'blocked', 'Explicit reconciliation confirmation is required.', $plan);
            }

            $root = $this->recoveryRoot->resolve();
            $recoveryIdentity = LegacyReconciliationRecoveryOrchestrator::recoveryIdentity($plan);
            $captured = $this->capture($payload, $plan);
            $manifest = $captured['manifest'];
            $record = new RecoveryLifecycleRecord($recoveryIdentity, $manifest->identity(), $plan->operationIdentity(), RecoveryLifecycleState::CREATED);
            $this->recoveryStore->create($record);
            $confirmation = ReconciliationConfirmation::forPlan($plan, $recoveryIdentity);

            $eligibility = $this->recoveryOrchestrator->prepare(
                $plan,
                $manifest,
                function (RecoveryManifest $expected) use ($payload, $plan, $captured): void {
                    $current = $this->capture($payload, $plan, false);
                    if ($current['manifest']->identity() !== $expected->identity()) {
                        throw new \RuntimeException('Recovery capture identity changed before readiness.');
                    }
                    $this->artifactStore->publish($expected, $current['artifacts']);
                },
                function (RecoveryManifest $expected): void {
                    $decoded = $this->artifactStore->readManifest($expected->recoveryIdentity());
                    if ($decoded['identity'] !== $expected->identity() || !$decoded['complete']) {
                        throw new \RuntimeException('Recovery manifest is not complete and integrity-verified.');
                    }
                },
                $confirmation
            );

            $filesystemResult = null;
            $databaseResult = null;
            $result = $this->integratedLifecycle->reconcile(
                $plan,
                $manifest,
                $eligibility,
                function () use (&$filesystemResult, $plan, $manifest, $eligibility, $payload): WebcoreApplyResult {
                    return $filesystemResult = (new LegacyReconciliationFilesystemConverger($this->recoveryStore, $this->applier, $this->liveGuard))->converge($plan, $manifest, $eligibility, $payload);
                },
                function () use (&$databaseResult, $plan, $manifest, $eligibility): LegacyReconciliationDatabaseResult {
                    return $databaseResult = $this->databaseReconciler->reconcile(
                    ($this->reconciliationConnection)(),
                    $plan,
                    $manifest,
                    $eligibility,
                    $this->registry,
                    $this->canonicalSchemaPath,
                    fn (\PDO $connection): string => $this->schemaIdentity($connection),
                    $this->recoveryStore
                    );
                },
                function () use (&$filesystemResult, &$databaseResult, $plan, $manifest, $eligibility): LegacyReconciliationFinalizationResult {
                    if (!$filesystemResult instanceof WebcoreApplyResult || !$databaseResult instanceof LegacyReconciliationDatabaseResult) {
                        throw new \RuntimeException('Reconciliation finalization received incomplete phase results.');
                    }
                    return $this->finalizer->finalize(
                    $plan,
                    $manifest,
                    $eligibility,
                    $filesystemResult,
                    $databaseResult,
                    $this->installation,
                    $this->committedStore,
                    $this->liveGuard,
                    ($this->reconciliationConnection)(),
                    $this->registry,
                    ($this->runtimeChecks)()
                    );
                }
            );

            return $this->result($result->status() === LegacyReconciliationIntegratedResult::COMPLETED, $result->status(), $result->reason(), $plan);
        } catch (\Throwable $exception) {
            return $this->result(false, $this->capabilityFailure($exception) ? 'unavailable' : 'rejected', $exception->getMessage(), $plan ?? null);
        } finally {
            $eligibility?->release();
            if ($payload instanceof StagedPayload) {
                try { $payload->cleanup(); } catch (\Throwable) {}
            }
        }
    }

    /** @return array{manifest: RecoveryManifest, artifacts: array<int, array{record: mixed, bytes: string}>} */
    private function capture(StagedPayload $payload, LegacyReconciliationPlan $plan, bool $guard = true): array
    {
        $lock = $guard ? $this->mutex->acquire() : null;
        if ($guard && $lock === null) {
            throw new \RuntimeException('Another installation or recovery operation owns the mutex.');
        }

        $lease = $guard ? $this->quiescence->acquire() : null;
        if ($guard && ($lease === null || !$lease->isActive())) {
            $lock?->release();
            throw new \RuntimeException('Database quiescence is unavailable; recovery capture is blocked.');
        }

        try {
            $applyPlan = WebcoreApplyPlan::fromPayload($payload);
            $filesystem = $this->filesystemRecovery->capture(FilesystemRecoveryPlan::fromApplyPlan($applyPlan));
            $database = $this->databaseRecovery->capture(new DatabaseCaptureContext(($this->reconciliationConnection)(), ($this->reconciliationConnection)(), $this->databaseName));
            $lifecycle = $this->lifecycleRecovery->capture();
            $marker = $this->installedLockRecovery->capture();
            $artifacts = [
                ['record' => $database->record(), 'bytes' => $database->bytes()],
                ['record' => $filesystem->artifact(), 'bytes' => $filesystem->artifactBytes()],
                ['record' => $lifecycle->record(), 'bytes' => $lifecycle->bytes()],
                ['record' => $marker->record(), 'bytes' => $marker->bytes()],
            ];
            $domains = [
                new RecoveryDomainIdentity('database', 'database.webcore', 'configured-webcore-database', $database->record()->artifactIdentity()),
                new RecoveryDomainIdentity('filesystem.package-owned', 'filesystem.package-owned', $plan->identity(), $filesystem->artifact()->artifactIdentity()),
                new RecoveryDomainIdentity('lifecycle.committed', 'filesystem.lifecycle.committed', 'committed-state', $lifecycle->record()->artifactIdentity()),
                new RecoveryDomainIdentity('lifecycle.installed-lock', 'filesystem.lifecycle.installed-lock', 'installed-lock', $marker->record()->artifactIdentity()),
            ];
            $manifest = new RecoveryManifest(
                LegacyReconciliationRecoveryOrchestrator::recoveryIdentity($plan),
                $plan->operationIdentity(),
                $plan->target()->packageIdentity(),
                $plan->target()->contract()->releaseIdentity(),
                $plan->target()->archiveIdentity(),
                $plan->target()->payloadIdentity(),
                $domains,
                $plan->preStateIdentity(),
                CoreMigrationStateIdentity::fromRecords($plan->classification()->records())
            );
            return ['manifest' => $manifest, 'artifacts' => $artifacts];
        } finally {
            $lock?->release();
            $lease?->release();
        }
    }

    private function schemaIdentity(\PDO $connection): string
    {
        $records = $this->ledger->records($connection);
        if ($records === []) {
            return $this->canonicalSchema->identity($this->canonicalSchemaPath);
        }
        return $records[count($records) - 1]->targetSchemaIdentity();
    }

    private function result(bool $accepted, string $status, string $reason, ?LegacyReconciliationPlan $plan): PackageLifecycleResult
    {
        return new PackageLifecycleResult($accepted, $status, $reason, null, $plan?->migrationPlan(), null, $plan);
    }

    private function capabilityFailure(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());
        return $exception instanceof \PDOException || str_contains($message, 'database') || str_contains($message, 'pdo') || str_contains($message, 'ziparchive');
    }
}
