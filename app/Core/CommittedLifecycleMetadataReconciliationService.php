<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\RecoveryLifecycleCoordinator;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;
use PDO;

/** Canonical metadata-only reconciliation over the existing lifecycle stores. */
final class CommittedLifecycleMetadataReconciliationService
{
    public function __construct(
        private InstallationState $installationState,
        private CommittedLifecycleStateStore $committedStore,
        private LifecycleOperationStore $operations,
        private InstallationMutex $mutex,
        private CoreMigrationRegistry $registry,
        private CoreMigrationLedger $ledger,
        private DatabaseHealthVerifier $databaseHealth,
        private CoreMigrationHealthVerifier $migrationHealth,
        private $connection,
        private $faultInjector = null
    ) {}

    public function plan(CommittedLifecycleMetadataReconciliationEvidence $evidence): CommittedLifecycleMetadataReconciliationPlan
    {
        $current = $this->committedStore->read();
        if (!$current instanceof CommittedLifecycleState) throw new \RuntimeException('A committed lifecycle state is required.');
        if ($this->operations->read() !== null) throw new \RuntimeException('A lifecycle operation is already active.');
        if (!$evidence->isValidAt()) throw new \RuntimeException('Metadata reconciliation evidence is stale.');
        if ($evidence->installation()->value() === '') throw new \RuntimeException('Installation identity is unavailable.');
        $currentIdentity = self::stateIdentity($current);
        if (!hash_equals($currentIdentity, $evidence->committedStateIdentity())) throw new \RuntimeException('Committed lifecycle pre-state evidence is stale.');
        $marker = $this->installationState->readMarker();
        if (!is_array($marker) || self::markerIdentity($marker) !== $evidence->installedMarkerIdentity()) throw new \RuntimeException('Installed marker evidence is stale.');
        if ($evidence->databaseScope()->installationIdentity() !== $evidence->installation()->value()) throw new \RuntimeException('Database scope is bound to another installation.');

        $db = $this->databaseHealth->verify($this->connection());
        if (!$db->passed()) throw new \RuntimeException($db->failureReason());
        $this->assertScopeTables($evidence->databaseScope());
        $migration = $this->migrationHealth->verify($this->connection(), $this->registry, null);
        if (!$migration->passed()) throw new \RuntimeException($migration->failureReason());
        $records = $this->ledger->records($this->connection());
        if ($records === []) throw new \RuntimeException('Applied migration history is required for metadata reconciliation.');
        $schema = $records[array_key_last($records)]->targetSchemaIdentity();
        $migrationIdentity = $this->migrationHealth->identity($this->connection());
        if ($schema === '') throw new \RuntimeException('Schema evidence is unavailable.');

        $after = new CommittedLifecycleState(
            $current->webcoreVersion(), $current->releaseIdentity(), $current->sourceTreeIdentity(),
            $current->manifestContractVersion(), $schema, $migrationIdentity, $current->committedAt(),
            $current->packageIntegrityIdentity(), $current->sourceInventoryReference()
        );
        $operationId = $evidence->operationIdentity() ?? ('metadata-reconciliation-sha256:' . hash('sha256', json_encode([
            'installation' => $evidence->installation()->value(), 'before' => $currentIdentity,
            'after' => self::stateIdentity($after), 'scope' => $evidence->databaseScope()->identity(),
            'recovery' => $evidence->recoveryIdentity()->value(), 'manifest' => $evidence->recoveryManifestIdentity(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)));
        if ($evidence->recoveryPlanIdentity() !== null) {
            $expectedRecovery = \Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureService::forOperation(
                $operationId,
                $evidence->recoveryPlanIdentity()
            );
            if (!$expectedRecovery->equals($evidence->recoveryIdentity())) {
                throw new \RuntimeException('Metadata recovery identity is not derived from the canonical operation preparation.');
            }
        }
        $planIdentity = hash('sha256', json_encode([
            'scheme' => 'committed-lifecycle-metadata-reconciliation-v1', 'operation' => $operationId,
            'before' => $currentIdentity, 'after' => self::stateIdentity($after),
            'schema' => $schema, 'migration' => $migrationIdentity, 'evidence' => $evidence->identity(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $now = gmdate(DATE_ATOM);
        $operation = new LifecycleOperationRecord(
            $operationId, self::classify(), $current->webcoreVersion(), $current->releaseIdentity(),
            $currentIdentity, 'metadata-reconciliation://' . $operationId, self::stateIdentity($after),
            $planIdentity, LifecycleOperationRecord::PREPARING, 0, null, $migrationIdentity, null, $now, $now,
            'Metadata-only reconciliation is planned; canonical recovery and guarded write are pending.'
        );
        return new CommittedLifecycleMetadataReconciliationPlan($planIdentity, $operation, $current, $after, $schema, $migrationIdentity, $evidence);
    }

    public function execute(
        CommittedLifecycleMetadataReconciliationPlan $plan,
        RecoveryLifecycleCoordinator $recovery,
        RecoveryLifecycleStore $recoveryStore,
        RecoveryArtifactStore $artifacts
    ): LifecycleOperationRecord {
        $evidence = $plan->evidence();
        if (!$evidence->isValidAt()) throw new \RuntimeException('Metadata reconciliation evidence is stale.');
        $recoveryRecord = $recoveryStore->read($evidence->recoveryIdentity());
        if ($recoveryRecord->operationIdentity() !== $plan->operation()->operationId()
            || $recoveryRecord->manifestIdentity() !== $evidence->recoveryManifestIdentity()
            || $recoveryRecord->state() !== RecoveryLifecycleState::READY
            || !$recoveryRecord->captureComplete() || $recoveryRecord->mutationStarted()
            || !$recoveryRecord->confirmationMatches($evidence->recoveryIdentity(), $evidence->recoveryManifestIdentity(), $evidence->recoveryManifestIdentity())) {
            throw new \RuntimeException('Canonical recovery evidence is not ready for metadata reconciliation.');
        }
        if (!$recovery->scopedProtectionMatches($evidence->recoveryIdentity(), $evidence->databaseEvidence())) {
            throw new \RuntimeException('Guard and scoped database evidence are not operation-bound.');
        }
        $artifacts->readManifest($evidence->recoveryIdentity());
        $lock = $recovery->activeLifecycleLock();
        $ownsLock = false;
        if (!$lock instanceof InstallationLock) {
            $lock = $this->mutex->acquire();
            $ownsLock = true;
        }
        if (!$lock instanceof InstallationLock) throw new \RuntimeException('Installation mutex is unavailable.');
        $permit = null;
        $journaled = false;
        $metadataWriteAttempted = false;
        $originalMarker = $this->installationState->readMarker();
        try {
            if ($this->operations->read() !== null) throw new \RuntimeException('A lifecycle operation is already active.');
            $current = $this->committedStore->read();
            if (!$current instanceof CommittedLifecycleState || !hash_equals(self::stateIdentity($current), self::stateIdentity($plan->before()))) throw new \RuntimeException('Committed lifecycle state changed after planning.');
            if (!hash_equals(self::stateIdentity($current), $evidence->committedStateIdentity())) throw new \RuntimeException('Committed lifecycle evidence no longer matches.');
            $bound = $plan->operation()->bindRecovery($evidence->recoveryIdentity()->value(), $evidence->recoveryManifestIdentity(), RecoveryLifecycleState::READY);
            $this->operations->create($bound);
            $awaiting = $bound->advance(LifecycleOperationRecord::AWAITING_WU6, 0, null, null, 'Metadata preconditions passed; guarded metadata write is authorized by canonical evidence.');
            $this->operations->save($awaiting);
            $journaled = true;
            $this->injectFault('after_journal_before_mutation');

            $recovery->confirm($evidence->recoveryIdentity(), $evidence->recoveryManifestIdentity(), $evidence->recoveryManifestIdentity());
            $permit = $recovery->authorizeMutation(
                $evidence->recoveryIdentity(),
                $evidence->recoveryManifestIdentity(),
                $evidence->recoveryManifestIdentity(),
                null,
                $evidence->databaseEvidence()
            );
            if (!$permit->isValid()) throw new \RuntimeException('Metadata reconciliation recovery mutation permit is invalid.');

            $this->injectFault('before_metadata_write');
            $metadataWriteAttempted = true;
            $this->committedStore->commit($this->installationState, $plan->after());
            $this->injectFault('after_metadata_write');
            $written = $this->committedStore->read();
            if (!$written instanceof CommittedLifecycleState || self::stateIdentity($written) !== self::stateIdentity($plan->after())) throw new \RuntimeException('Committed lifecycle metadata reread failed.');
            $postDatabase = $this->databaseHealth->verify($this->connection());
            $postMigration = $this->migrationHealth->verify($this->connection(), $this->registry, $plan->migrationIdentity());
            if (!$postDatabase->passed() || !$postMigration->passed()) throw new \RuntimeException('Post-write lifecycle health verification failed.');
            $completed = $awaiting->advance(LifecycleOperationRecord::COMPLETED, 0, null, null, 'Committed lifecycle schema and migration identities reconciled.');
            $this->operations->save($completed);
            $this->operations->clear($completed);
            return $completed;
        } catch (\Throwable $exception) {
            $rollbackSucceeded = !$metadataWriteAttempted;
            $rollbackReason = '';
            if ($metadataWriteAttempted) {
                try {
                    $this->injectFault('before_metadata_rollback');
                    $this->committedStore->commit($this->installationState, $plan->before());
                    if (!is_array($originalMarker)) {
                        throw new \RuntimeException('Original installed marker evidence is unavailable for rollback.');
                    }
                    $this->installationState->replaceMarker($originalMarker['version'], $originalMarker['installed_at']);
                    $restored = $this->committedStore->read();
                    $restoredMarker = $this->installationState->readMarker();
                    $markerMatches = is_array($originalMarker)
                        ? is_array($restoredMarker) && self::markerIdentity($restoredMarker) === self::markerIdentity($originalMarker)
                        : $restoredMarker === null;
                    if (!$restored instanceof CommittedLifecycleState
                        || self::stateIdentity($restored) !== self::stateIdentity($plan->before())
                        || !$markerMatches) {
                        throw new \RuntimeException('Committed lifecycle original state reread failed after rollback.');
                    }
                    $rollbackSucceeded = true;
                } catch (\Throwable $rollbackException) {
                    $rollbackReason = $rollbackException->getMessage();
                    $rollbackSucceeded = false;
                }
            }

            if ($journaled) {
                try {
                    $persisted = $this->operations->read();
                    if ($persisted instanceof LifecycleOperationRecord
                        && $persisted->operationId() === $plan->operation()->operationId()) {
                        $phase = $rollbackSucceeded ? LifecycleOperationRecord::BLOCKED : LifecycleOperationRecord::INDETERMINATE;
                        $reason = $rollbackSucceeded
                            ? 'Metadata reconciliation failed; original committed state was restored and requires explicit reconciliation retry.'
                            : 'Metadata reconciliation outcome is indeterminate; rollback failed and recovery evidence must be reconciled before retry.';
                        if ($rollbackReason !== '') $reason .= ' Rollback: ' . $rollbackReason;
                        $this->operations->save($persisted->advance($phase, $persisted->fileCursor(), $persisted->lastVerifiedPath(), null, $reason));
                    }
                } catch (\Throwable) {
                    // Preserve the active operation and recovery evidence when
                    // even disposition persistence is unavailable.
                }
            }
            throw $exception;
        } finally {
            $permit?->release();
            if ($ownsLock) $lock->release();
        }
    }

    public static function classify(): string { return 'COMMITTED_LIFECYCLE_METADATA_RECONCILIATION'; }
    public static function stateIdentity(CommittedLifecycleState $state): string { return hash('sha256', json_encode($state->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)); }
    public static function markerIdentity(array $marker): string { return hash('sha256', json_encode($marker, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)); }
    private function injectFault(string $stage): void
    {
        if ($this->faultInjector !== null) {
            ($this->faultInjector)($stage);
        }
    }
    private function connection(): PDO
    {
        $connection = ($this->connection)();
        if (!$connection instanceof PDO) throw new \RuntimeException('Canonical database connection is unavailable.');
        return $connection;
    }

    private function assertScopeTables(\Copot\Core\BackupRecovery\DatabaseScope $scope): void
    {
        try {
            $statement = $this->connection()->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'");
        } catch (\Throwable) {
            $statement = $this->connection()->query("SELECT name FROM sqlite_master WHERE type = 'table'");
        }
        $tables = array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
        $scope->assertPhysicalTables($tables);
    }

}
