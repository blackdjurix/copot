<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\BackupRecovery\MetadataRecoveryCaptureRequest;
use Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureService;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\RecoveryLifecycleCoordinator;
use Copot\Core\BackupRecovery\RecoveryLifecycleRecord;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;
use Copot\Core\BackupRecovery\ScopedDatabaseConsistencyEvidence;
use Copot\Core\BackupRecovery\InstallationScopedMaintenanceGuard;
use PDO;

/** Canonical target-bound preparation and execution boundary for metadata reconciliation. */
final class CommittedLifecycleMetadataReconciliationOrchestrator
{
    public function __construct(
        private InstallationIdentityStore $installationIdentities,
        private InstallationState $installationState,
        private CommittedLifecycleStateStore $committedStore,
        private LifecycleOperationStore $operations,
        private CoreMigrationRegistry $registry,
        private CoreMigrationLedger $ledger,
        private DatabaseHealthVerifier $databaseHealth,
        private CoreMigrationHealthVerifier $migrationHealth,
        private DatabaseTableOwnershipCatalog $ownershipCatalog,
        private InstallationScopedMaintenanceGuard $guard,
        private NormalWebcoreRecoveryCaptureService $capture,
        private RecoveryLifecycleCoordinator $recovery,
        private RecoveryLifecycleStore $recoveryStore,
        private RecoveryArtifactStore $artifacts,
        private CommittedLifecycleMetadataReconciliationService $metadata,
        private $connection,
        private string $databaseIdentity,
        private string $namespaceIdentity,
        private string $deploymentIdentity,
        private CanonicalSchemaBaselineVerifier $canonicalSchema,
        private string $canonicalSchemaPath
    ) {}

    public function prepare(): CommittedLifecycleMetadataReconciliationPreparation
    {
        $installation = $this->installationIdentities->read();
        if (!$installation instanceof InstallationIdentity) throw new \RuntimeException('Installation identity is unavailable.');
        if ($this->operations->read() !== null) throw new \RuntimeException('A lifecycle operation is already active.');
        $committed = $this->committedStore->read();
        if (!$committed instanceof CommittedLifecycleState) throw new \RuntimeException('A committed lifecycle state is required.');
        $marker = $this->installationState->readMarker();
        if (!is_array($marker)) throw new \RuntimeException('Installed marker is unavailable.');

        $connection = ($this->connection)();
        if (!$connection instanceof PDO) throw new \RuntimeException('Canonical database connection is unavailable.');
        $databaseGate = $this->databaseHealth->verify($connection);
        if (!$databaseGate->passed()) throw new \RuntimeException($databaseGate->failureReason());
        $migrationGate = $this->migrationHealth->verify($connection, $this->registry, null);
        if (!$migrationGate->passed()) throw new \RuntimeException($migrationGate->failureReason());
        $records = $this->ledger->records($connection);
        if ($records === []) throw new \RuntimeException('Applied migration history is required for metadata reconciliation.');
        $last = $records[array_key_last($records)];
        $scope = DatabaseScope::fromOwnershipCatalog($installation->value(), $this->databaseIdentity, $this->namespaceIdentity, $this->ownershipCatalog);
        $this->assertScope($connection, $scope);
        $observedMigration = $this->migrationHealth->identity($connection);
        $observedSchema = $last->targetSchemaIdentity();
        $beforeIdentity = CommittedLifecycleMetadataReconciliationService::stateIdentity($committed);
        $markerIdentity = CommittedLifecycleMetadataReconciliationService::markerIdentity($marker);
        $operationId = self::operationIdentity(
            $installation->value(),
            $beforeIdentity,
            $markerIdentity,
            $scope->identity(),
            $observedSchema,
            $observedMigration
        );
        $recoveryPlanIdentity = self::recoveryPlanIdentity($operationId, $scope->identity(), $beforeIdentity, $markerIdentity);
        $recovery = NormalWebcoreRecoveryCaptureService::forOperation($operationId, $recoveryPlanIdentity);
        $application = $this->guard->produceEvidence($recovery, $scope);
        $provider = new \Copot\Core\BackupRecovery\MySqlRecoveryProvider();
        $beforeDatabaseIdentity = $provider->stateIdentity($connection, $this->databaseIdentity, $scope);
        $database = new ScopedDatabaseConsistencyEvidence(
            $scope,
            $recovery,
            $application->writerExclusionIdentity(),
            $beforeDatabaseIdentity,
            $application->capturedAt(),
            $application->validUntil()
        );
        $afterDatabaseIdentity = $provider->stateIdentity($connection, $this->databaseIdentity, $scope);
        if (!hash_equals($beforeDatabaseIdentity, $afterDatabaseIdentity)) {
            throw new \RuntimeException('Scoped database state changed while composing metadata recovery evidence.');
        }
        $request = new MetadataRecoveryCaptureRequest(
            $operationId,
            $installation->value(),
            $this->namespaceIdentity,
            $this->deploymentIdentity,
            hash('sha256', 'metadata-reconciliation-package:' . $operationId),
            $committed->releaseIdentity(),
            hash('sha256', 'metadata-reconciliation-archive:' . $operationId),
            $recoveryPlanIdentity,
            $beforeIdentity,
            $observedMigration,
            $this->databaseIdentity,
            $scope,
            $database,
            $application
        );
        $session = $this->capture->captureMetadata($request);
        try {
            $evidence = new CommittedLifecycleMetadataReconciliationEvidence(
                $installation,
                $beforeIdentity,
                $markerIdentity,
                $scope,
                $session->recoveryIdentity(),
                $session->manifestIdentity(),
                $application,
                $database,
                $application->capturedAt(),
                $application->validUntil(),
                $operationId,
                $recoveryPlanIdentity
            );
            $plan = $this->metadata->plan($evidence);
            return new CommittedLifecycleMetadataReconciliationPreparation($plan, $session);
        } catch (\Throwable $exception) {
            $session->close();
            throw $exception;
        }
    }

    /** Rehydrate a persisted, recovery-ready preparation without recapturing. */
    public function resume(string $operationIdentity): CommittedLifecycleMetadataReconciliationPreparation
    {
        if ($operationIdentity === '' || preg_match('/[\x00-\x1F\x7F]/', $operationIdentity) === 1) {
            throw new \RuntimeException('Metadata reconciliation operation locator is invalid.');
        }
        if ($this->operations->read() !== null) {
            throw new \RuntimeException('A lifecycle operation is already active.');
        }
        $decoded = $this->artifacts->findManifestByOperationIdentity($operationIdentity);
        $manifest = $decoded['manifest'];
        $recoveryIdentity = $manifest->recoveryIdentity();
        $record = $this->recoveryStore->read($recoveryIdentity);
        if ($record->operationIdentity() !== $operationIdentity
            || $record->manifestIdentity() !== $manifest->identity()
            || $record->state() !== \Copot\Core\BackupRecovery\RecoveryLifecycleState::READY
            || !$record->captureComplete()
            || $record->mutationStarted()
            || !$record->confirmationMatches($recoveryIdentity, $manifest->identity(), $manifest->identity())) {
            throw new \RuntimeException('Persisted metadata recovery is not resume-eligible.');
        }

        $installation = $this->installationIdentities->read();
        if (!$installation instanceof InstallationIdentity) throw new \RuntimeException('Installation identity is unavailable.');
        $committed = $this->committedStore->read();
        if (!$committed instanceof CommittedLifecycleState) throw new \RuntimeException('A committed lifecycle state is required.');
        if (CommittedLifecycleMetadataReconciliationService::stateIdentity($committed) !== $manifest->preOperationLifecycleIdentity()) {
            throw new \RuntimeException('Committed lifecycle state drifted after recovery capture.');
        }
        $marker = $this->installationState->readMarker();
        if (!is_array($marker)) throw new \RuntimeException('Installed marker is unavailable.');

        $records = $decoded['artifacts'];
        $databaseRecord = $this->artifactRecord($records, 'database.webcore');
        $lifecycleRecord = $this->artifactRecord($records, 'filesystem.lifecycle.committed');
        $markerRecord = $this->artifactRecord($records, 'filesystem.lifecycle.installed-lock');
        $databaseBytes = $this->artifacts->readArtifact($recoveryIdentity, $databaseRecord);
        $lifecycleBytes = $this->artifacts->readArtifact($recoveryIdentity, $lifecycleRecord);
        $markerBytes = $this->artifacts->readArtifact($recoveryIdentity, $markerRecord);
        $databaseBundle = (new \Copot\Core\BackupRecovery\MySqlDatabaseArtifactCodec())->decode($databaseBytes);
        $lifecycleArtifact = (new \Copot\Core\BackupRecovery\LifecycleRecoveryArtifactCodec())->decode($lifecycleBytes);
        $markerArtifact = (new \Copot\Core\BackupRecovery\InstalledLockRecoveryArtifactCodec())->decode($markerBytes);
        if (!$lifecycleArtifact->state() instanceof CommittedLifecycleState
            || CommittedLifecycleMetadataReconciliationService::stateIdentity($lifecycleArtifact->state()) !== $manifest->preOperationLifecycleIdentity()
            || CommittedLifecycleMetadataReconciliationService::markerIdentity($markerArtifact->marker()) !== CommittedLifecycleMetadataReconciliationService::markerIdentity($marker)) {
            throw new \RuntimeException('Persisted lifecycle recovery artifacts do not match the current target.');
        }

        $scope = DatabaseScope::fromOwnershipCatalog(
            $installation->value(), $this->databaseIdentity, $this->namespaceIdentity, $this->ownershipCatalog
        );
        if (!isset($databaseBundle['scope']['scope_identity']) || $databaseBundle['scope']['scope_identity'] !== $scope->identity()) {
            throw new \RuntimeException('Persisted database recovery scope does not match the current target.');
        }
        $connection = ($this->connection)();
        if (!$connection instanceof PDO) throw new \RuntimeException('Canonical database connection is unavailable.');
        $databaseGate = $this->databaseHealth->verify($connection);
        $migrationGate = $this->migrationHealth->verify($connection, $this->registry, null);
        if (!$databaseGate->passed() || !$migrationGate->passed()) throw new \RuntimeException('Current database evidence is not healthy for resume.');
        $ledgerRecords = $this->ledger->records($connection);
        if ($ledgerRecords === []) throw new \RuntimeException('Applied migration history is required for resume.');
        $schema = $ledgerRecords[array_key_last($ledgerRecords)]->targetSchemaIdentity();
        $migration = $this->migrationHealth->identity($connection);
        $currentDatabaseIdentity = hash('sha256', json_encode([
            'database_identity' => $databaseBundle['database_identity'],
            'schema' => $databaseBundle['identities']['schema'],
            'data' => $databaseBundle['identities']['data'],
            'migration_ledger' => $databaseBundle['identities']['migration_ledger'],
            'scope' => $scope->identity(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $provider = new \Copot\Core\BackupRecovery\MySqlRecoveryProvider();
        if ($provider->stateIdentity($connection, $this->databaseIdentity, $scope) !== $currentDatabaseIdentity
            || $databaseBundle['database_identity'] !== $this->databaseIdentity
            || $manifest->preOperationMigrationLedgerIdentity() !== $migration) {
            throw new \RuntimeException('Persisted database recovery evidence is stale.');
        }

        if (!$this->guard instanceof InstallationScopedMaintenanceGuard) throw new \RuntimeException('Scoped maintenance guard is unavailable.');
        $durableGuardActive = $this->recovery->durableScopedMaintenanceActive($recoveryIdentity);
        $application = $durableGuardActive
            ? $this->guard->revalidateActiveEvidence($recoveryIdentity, $scope)
            : $this->guard->produceEvidence($recoveryIdentity, $scope);
        $database = new ScopedDatabaseConsistencyEvidence(
            $scope, $recoveryIdentity, $application->writerExclusionIdentity(),
            $provider->stateIdentity($connection, $this->databaseIdentity, $scope),
            $application->capturedAt(), $application->validUntil()
        );
        $scopedMaintenanceEstablished = false;
        try {
            if ($durableGuardActive) {
                $lock = $this->recovery->mutex();
                if (!$lock instanceof \Copot\Core\InstallationLock) throw new \RuntimeException('Lifecycle mutex is unavailable for recovery resume.');
                $this->guard->adopt($lock);
                if (!$this->guard->enter($recoveryIdentity)) {
                    $this->guard->detach();
                    $lock->release();
                    throw new \RuntimeException('Durable recovery Guard could not adopt the lifecycle mutex.');
                }
                $scopedMaintenanceEstablished = true;
                $this->recovery->adoptScopedMaintenance($recoveryIdentity, $application, $database);
            } else {
                $this->recovery->resumeReadyScopedMaintenance($recoveryIdentity, $application, $database);
                $scopedMaintenanceEstablished = true;
            }
            $evidence = new CommittedLifecycleMetadataReconciliationEvidence(
                $installation, CommittedLifecycleMetadataReconciliationService::stateIdentity($committed), CommittedLifecycleMetadataReconciliationService::markerIdentity($marker), $scope,
                $recoveryIdentity, $manifest->identity(), $application, $database,
                $application->capturedAt(), $application->validUntil(), $operationIdentity, $manifest->applyPlanIdentity()
            );
            $plan = $this->metadata->plan($evidence);
            if ($plan->operation()->operationId() !== $operationIdentity) throw new \RuntimeException('Resumed operation identity changed.');
            return new CommittedLifecycleMetadataReconciliationPreparation(
                $plan, new \Copot\Core\BackupRecovery\NormalWebcoreRecoverySession($this->recovery, $recoveryIdentity, $manifest->identity(), $record, null, $database)
            );
        } catch (\Throwable $exception) {
            if ($scopedMaintenanceEstablished) {
                try { $this->recovery->leaveMaintenance($recoveryIdentity); } catch (\Throwable) {}
            }
            throw $exception;
        }
    }

    /**
     * Finish a post-mutation reconciliation whose durable cleanup boundary was
     * reached but whose process died before the recovery/Guard session closed.
     *
     * This path never reconstructs a preparation and never invokes metadata
     * execution. It only validates the immutable recovery binding, re-adopts
     * the durable scoped Guard when necessary, and completes cleanup.
     */
    public function continueCleanup(string $operationIdentity): RecoveryLifecycleRecord
    {
        if ($operationIdentity === '' || preg_match('/[\x00-\x1F\x7F]/', $operationIdentity) === 1) {
            throw new \RuntimeException('Metadata reconciliation cleanup operation locator is invalid.');
        }
        if ($this->operations->read() !== null) {
            throw new \RuntimeException('A lifecycle operation is already active.');
        }

        $decoded = $this->artifacts->findManifestByOperationIdentity($operationIdentity);
        $manifest = $decoded['manifest'];
        $recoveryIdentity = $manifest->recoveryIdentity();
        $record = $this->recoveryStore->read($recoveryIdentity);
        $cleanupState = in_array($record->state(), [RecoveryLifecycleState::CLEANUP_PENDING, RecoveryLifecycleState::CLEANED], true);
        if (!$cleanupState
            || $record->operationIdentity() !== $operationIdentity
            || $record->manifestIdentity() !== $manifest->identity()
            || !$record->captureComplete()
            || !$record->mutationStarted()
            || !$record->postReconciliationVerified()
            || !$record->confirmationMatches($recoveryIdentity, $manifest->identity(), $manifest->identity())) {
            throw new \RuntimeException('Persisted metadata reconciliation cleanup is not eligible.');
        }

        $owned = false;
        try {
            if ($this->recovery->maintenanceActive($recoveryIdentity)) {
                if (!($this->recovery->activeLifecycleLock() instanceof \Copot\Core\InstallationLock)) {
                    throw new \RuntimeException('Process-local recovery cleanup ownership is unavailable.');
                }
                $owned = true;
            } elseif ($this->recovery->durableScopedMaintenanceActive($recoveryIdentity)) {
                if (!$this->recovery->resumeDurableScopedMaintenance($recoveryIdentity)) {
                    throw new \RuntimeException('Durable recovery cleanup ownership could not be re-adopted.');
                }
                $owned = true;
            } elseif ($record->state() === RecoveryLifecycleState::CLEANUP_PENDING) {
                throw new \RuntimeException('Durable recovery cleanup Guard is unavailable.');
            }

            // A pending cleanup still has a live protection boundary. Acquire
            // or re-adopt it before rereading committed metadata and the ledger.
            // CLEANED is terminal; its idempotent no-Guard replay remains a
            // read-only verification path.
            $this->assertPostMutationTarget($manifest);

            if ($record->state() === RecoveryLifecycleState::CLEANUP_PENDING) {
                $record = $this->recovery->markCleaned($recoveryIdentity);
            }

            return $this->recoveryStore->read($recoveryIdentity);
        } finally {
            if ($owned && $this->recovery->maintenanceActive($recoveryIdentity)) {
                $this->recovery->leaveMaintenance($recoveryIdentity);
            }
        }
    }

    public function execute(CommittedLifecycleMetadataReconciliationPreparation $preparation): LifecycleOperationRecord
    {
        if (!$preparation->ready()) throw new \RuntimeException('Metadata reconciliation preparation is not recovery-ready.');
        try {
            $result = $this->metadata->execute($preparation->plan(), $this->recovery, $this->recoveryStore, $this->artifacts);
            (new CommittedSourceAuthorityMetadataGate(
                $this->committedStore,
                $this->installationState,
                $this->ledger,
                $this->connection,
                $this->canonicalSchema,
                $this->canonicalSchemaPath,
                $this->operations
            ))->assert();
            $preparation->close();
            return $result;
        } catch (\Throwable $exception) {
            try {
                $record = $this->recoveryStore->read($preparation->recoveryIdentity());
                if (!$record->mutationStarted()) $preparation->close();
            } catch (\Throwable) {}
            throw $exception;
        }
    }

    private function assertScope(PDO $connection, DatabaseScope $scope): void
    {
        $statement = $connection->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'");
        $scope->assertPhysicalTables(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }

    private function assertPostMutationTarget(\Copot\Core\BackupRecovery\RecoveryManifest $manifest): void
    {
        $installation = $this->installationIdentities->read();
        if (!$installation instanceof InstallationIdentity) {
            throw new \RuntimeException('Installation identity is unavailable for metadata reconciliation cleanup.');
        }

        $connection = ($this->connection)();
        if (!$connection instanceof PDO) {
            throw new \RuntimeException('Canonical database connection is unavailable for metadata reconciliation cleanup.');
        }

        (new CommittedSourceAuthorityMetadataGate(
            $this->committedStore,
            $this->installationState,
            $this->ledger,
            $this->connection,
            $this->canonicalSchema,
            $this->canonicalSchemaPath,
            $this->operations
        ))->assert();

        $records = $this->ledger->records($connection);
        if ($records === []) {
            throw new \RuntimeException('Applied migration history is required for metadata reconciliation cleanup.');
        }
        $current = $this->committedStore->read();
        if (!$current instanceof CommittedLifecycleState
            || $manifest->preOperationLifecycleIdentity() === CommittedLifecycleMetadataReconciliationService::stateIdentity($current)) {
            throw new \RuntimeException('Post-reconciliation metadata transition is not proven.');
        }
        if ($installation->value() === '') {
            throw new \RuntimeException('Installation identity is invalid for metadata reconciliation cleanup.');
        }
    }

    /** @param array<int, \Copot\Core\BackupRecovery\RecoveryArtifactRecord> $records */
    private function artifactRecord(array $records, string $domain): \Copot\Core\BackupRecovery\RecoveryArtifactRecord
    {
        foreach ($records as $record) if ($record->domainIdentifier() === $domain) return $record;
        throw new \RuntimeException('Required persisted recovery artifact is missing: ' . $domain);
    }

    public static function operationIdentity(string $installation, string $committed, string $marker, string $scope, string $schema, string $migration): string
    {
        return 'metadata-reconciliation-sha256:' . hash('sha256', json_encode([
            'scheme' => 'committed-lifecycle-metadata-reconciliation-v2',
            'operation' => CommittedLifecycleMetadataReconciliationService::classify(),
            'installation' => $installation,
            'committed_state' => $committed,
            'installed_marker' => $marker,
            'scope' => $scope,
            'observed_schema' => $schema,
            'observed_migration' => $migration,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public static function recoveryPlanIdentity(string $operation, string $scope, string $committed, string $marker): string
    {
        return hash('sha256', json_encode([
            'scheme' => 'metadata-reconciliation-recovery-v1',
            'operation' => $operation,
            'surface' => ['committed-state', 'installed-marker', 'database.webcore'],
            'scope' => $scope,
            'committed_state' => $committed,
            'installed_marker' => $marker,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
