<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\DatabaseRestoreContext;
use Copot\Core\BackupRecovery\DatabaseRestoreAttemptContext;
use Copot\Core\BackupRecovery\FilesystemRecoveryBundleCodec;
use Copot\Core\BackupRecovery\FilesystemRecoveryPlan;
use Copot\Core\BackupRecovery\FilesystemRecoveryResult;
use Copot\Core\BackupRecovery\RecoveryArtifactRecord;
use Copot\Core\BackupRecovery\RecoveryLifecycleRecord;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryOrchestrator;
use Copot\Core\BackupRecovery\RecoveryIdentity;

/** Operator-authorized restore for a post-mutation normal package operation. */
final class NormalPackageLifecycleRestoreService
{
    public function __construct(
        private PackageLifecycleRecoveryComposition $recovery,
        private MaintenanceCoordinator $maintenance,
        private RestoredOperationTerminalizer $terminalizer
    ) {}

    public function restore(string $operationId, string $reason): PackageLifecycleResult
    {
        if ($reason === '') {
            return new PackageLifecycleResult(false, 'rejected', 'Explicit restore authorization reason is required.', operationId: $operationId);
        }

        $operation = $this->maintenance->record();
        if (!$operation instanceof LifecycleOperationRecord || $operation->operationId() !== $operationId) {
            return new PackageLifecycleResult(false, 'rejected', 'Requested lifecycle operation is not active.', operationId: $operationId);
        }
        try {
            $this->assertEligible($operation);
            $identity = new RecoveryIdentity((string) $operation->recoveryIdentity());
            $manifestData = $this->recovery->artifacts->readManifest($identity);
            $manifest = $manifestData['manifest'];
            if ($manifest->operationIdentity() !== $operationId
                || $manifest->archiveIdentity() !== $operation->archiveSha256()
                || $manifest->applyPlanIdentity() !== $operation->applyPlanIdentity()
                || $manifest->targetReleaseIdentity() !== $operation->releaseIdentity()
                || $manifest->identity() !== $operation->recoveryManifestIdentity()) {
                throw new \RuntimeException('Recovery manifest does not match the persisted lifecycle operation.');
            }

            $record = $this->recovery->store->read($identity);
            if ($record->operationIdentity() !== $operationId || $record->manifestIdentity() !== $operation->recoveryManifestIdentity()) {
                throw new \RuntimeException('Recovery lifecycle identity does not match the persisted operation.');
            }

            if ($record->state() !== RecoveryLifecycleState::RESTORED) {
                $this->restorePhysicalDomains($identity, $record, $manifestData['artifacts'], $operationId, $operation->applyPlanIdentity());
                $record = $this->recovery->store->read($identity);
            }
            if ($record->state() !== RecoveryLifecycleState::RESTORED) {
                throw new \RuntimeException('Recovery did not reach RESTORED.');
            }

            return $this->terminalizer->terminalize(
                $operationId,
                $record,
                $manifest->preOperationLifecycleIdentity(),
                $manifest->preOperationMigrationLedgerIdentity(),
                $reason
            );
        } catch (\Throwable $exception) {
            return new PackageLifecycleResult(false, 'blocked', $exception->getMessage(), operationId: $operationId);
        }
    }

    private function assertEligible(LifecycleOperationRecord $operation): void
    {
        if (!in_array($operation->phase(), [LifecycleOperationRecord::AWAITING_WU6, LifecycleOperationRecord::CLEANUP_PENDING, LifecycleOperationRecord::BLOCKED], true)) {
            throw new \RuntimeException('Lifecycle operation is not eligible for normal post-mutation restore.');
        }
        if ($operation->fileCursor() < 1 || $operation->migrationOutcome() === null || $operation->recoveryIdentity() === null || $operation->recoveryManifestIdentity() === null) {
            throw new \RuntimeException('Post-mutation recovery evidence is incomplete.');
        }
    }

    /** @param array<int, RecoveryArtifactRecord> $artifacts */
    private function restorePhysicalDomains(RecoveryIdentity $identity, RecoveryLifecycleRecord $record, array $artifacts, string $operationId, string $applyPlanIdentity): void
    {
        $byDomain = [];
        foreach ($artifacts as $artifact) { $byDomain[$artifact->domainIdentifier()] = $artifact; }
        foreach (['database.webcore', 'filesystem.package-owned', 'filesystem.lifecycle.committed', 'filesystem.lifecycle.installed-lock'] as $domain) {
            if (!isset($byDomain[$domain])) throw new \RuntimeException('Required recovery domain is missing: ' . $domain);
        }

        $dbArtifact = $this->recovery->database->artifactFromStore($identity, $byDomain['database.webcore'], $this->recovery->artifacts);
        if ($dbArtifact->databaseIdentity() !== $this->recovery->databaseIdentity) throw new \RuntimeException('Recovery database identity does not match the active database.');
        $attemptId = 'restore-' . substr(hash('sha256', $identity->value() . ':' . $operationId), 0, 24);
        $orchestrator = new RecoveryOrchestrator($this->recovery->store, $this->recovery->mutex, $this->recovery->quiescence, $this->recovery->maintenance);
        $orchestrator->restore(
            $identity,
            function () use ($identity, $byDomain, $attemptId): void {
                $probe = ($this->recovery->databaseConnection())();
                $currentState = $this->recovery->database->stateIdentity($probe, $this->recovery->databaseIdentity);
                $currentTables = $this->currentDatabaseTableNames($probe);
                $attempt = $this->recovery->coordinator->databaseRestoreAttempt($identity, $attemptId, $currentState, $this->recovery->database->tableSetIdentity($probe, $this->recovery->databaseIdentity), DatabaseRestoreAttemptContext::PREPARED, $currentTables);
                $context = new DatabaseRestoreContext(($this->recovery->databaseConnection())(), ($this->recovery->databaseConnection())(), $this->recovery->databaseIdentity, $attempt);
                $this->recovery->database->restoreFromStore($identity, $byDomain['database.webcore'], $this->recovery->artifacts, $context);
            },
            function () use ($identity, $byDomain, $applyPlanIdentity): void {
                $bundle = (new FilesystemRecoveryBundleCodec())->decode($this->recovery->artifacts->readArtifact($identity, $byDomain['filesystem.package-owned']));
                if ($bundle['plan_identity'] !== $applyPlanIdentity) throw new \RuntimeException('Filesystem recovery plan identity does not match the operation.');
                $result = $this->recovery->filesystem->restore($identity, $byDomain['filesystem.package-owned'], FilesystemRecoveryPlan::fromCapturedEntries($bundle['plan_identity'], $bundle['entries']));
                if ($result->status() !== FilesystemRecoveryResult::COMPLETED) throw new \RuntimeException($result->reason() ?: 'Package-owned filesystem restoration failed.');
            },
            function () use ($identity, $byDomain): void {
                $this->recovery->lifecycle->restoreFromStore($identity, $byDomain['filesystem.lifecycle.committed'], $this->recovery->artifacts, $this->recovery->lifecycle->currentIdentity());
            },
            function () use ($identity, $byDomain): void {
                $this->recovery->installedLock->restoreFromStore($identity, $byDomain['filesystem.lifecycle.installed-lock'], $this->recovery->artifacts, $this->recovery->installedLock->currentIdentity());
            },
            function () use ($dbArtifact): void {
                $context = new DatabaseRestoreContext(($this->recovery->databaseConnection())(), ($this->recovery->databaseConnection())(), $this->recovery->databaseIdentity);
                $verification = $this->recovery->database->verifyRestored($dbArtifact, $context);
                if (!$verification->isValid()) throw new \RuntimeException($verification->failure() ?: 'Restored database verification failed.');
            }
        );
    }

    /** @return array<int, string> */
    private function currentDatabaseTableNames(\PDO $connection): array
    {
        $rows = $connection->query("SELECT table_name, table_type FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY table_name")->fetchAll(\PDO::FETCH_ASSOC);
        $names = [];
        foreach ($rows as $row) {
            if (($row['table_type'] ?? null) !== 'BASE TABLE') throw new \RuntimeException('Unsupported non-table database object is present: ' . ($row['table_name'] ?? 'unknown'));
            $names[] = (string) $row['table_name'];
        }
        return $names;
    }
}
