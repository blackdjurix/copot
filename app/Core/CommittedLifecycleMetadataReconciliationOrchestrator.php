<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\BackupRecovery\MetadataRecoveryCaptureRequest;
use Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureService;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\RecoveryLifecycleCoordinator;
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
        $database = new ScopedDatabaseConsistencyEvidence(
            $scope,
            $recovery,
            $application->writerExclusionIdentity(),
            hash('sha256', json_encode([
                'scope' => $scope->identity(), 'schema' => $observedSchema,
                'migration' => $observedMigration, 'tables' => $scope->tablesetFingerprint(),
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            $application->capturedAt(),
            $application->validUntil()
        );
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
