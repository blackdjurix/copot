<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\ApplicationWriteProtectionEvidence;
use Copot\Core\BackupRecovery\CommittedSourceAuthorityRecoveryBinding;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\RecoveryLifecycleCoordinator;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\ScopedDatabaseConsistencyEvidence;

/**
 * Canonical admission proof for committed source-authority reconciliation.
 *
 * This proof never grants package, migration, or WU6 finalization authority.
 */
final class CommittedSourceAuthorityExecutionAdmission
{
    private function __construct(
        private CommittedSourceAuthorityPlan $plan,
        private CommittedSourceAuthorityRecoveryBinding $binding,
        private string $identity
    ) {}

    public static function issue(
        CommittedSourceAuthorityPlan $plan,
        CommittedSourceAuthorityRecoveryBinding $binding,
        RecoveryLifecycleCoordinator $recoveryCoordinator,
        LifecycleOperationStore $operations,
        RecoveryArtifactStore $artifacts,
        ApplicationWriteProtectionEvidence $application,
        ScopedDatabaseConsistencyEvidence $database
    ): self {
        self::assertPrerequisites($plan, $binding, $recoveryCoordinator, $operations, $artifacts, $application, $database);
        $identity = hash('sha256', json_encode([
            'admission_scheme' => 'committed-source-authority-admission-v1',
            'plan_identity' => $plan->identity(),
            'binding_identity' => $binding->crossReference()->identity(),
            'operation_identity' => $plan->operation()->operationId(),
            'recovery_identity' => $binding->crossReference()->recoveryIdentity()->value(),
            'manifest_identity' => $binding->manifest()->identity(),
            'database_scope_identity' => $plan->databaseScope()->identity(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return new self($plan, $binding, $identity);
    }

    public function plan(): CommittedSourceAuthorityPlan { return $this->plan; }
    public function binding(): CommittedSourceAuthorityRecoveryBinding { return $this->binding; }
    public function identity(): string { return $this->identity; }
    public function mutationAuthorized(): bool { return false; }
    public function mutationRequired(): bool { return $this->plan->mutationRequired(); }
    public function wu6Required(): bool { return true; }

    /**
     * Persists the canonical authority-only handoff without claiming that
     * package application or migration occurred.
     */
    public function advanceToAwaitingWu6(
        RecoveryLifecycleCoordinator $recoveryCoordinator,
        LifecycleOperationStore $operations,
        RecoveryArtifactStore $artifacts,
        ApplicationWriteProtectionEvidence $application,
        ScopedDatabaseConsistencyEvidence $database
    ): LifecycleOperationRecord {
        if ($this->plan->mutationRequired()) {
            throw new \RuntimeException('Mutation-required source-authority plans require canonical mutation and recovery execution before WU6 handoff.');
        }
        self::assertPrerequisites($this->plan, $this->binding, $recoveryCoordinator, $operations, $artifacts, $application, $database);
        $current = $operations->read();
        if (!$current instanceof LifecycleOperationRecord) {
            throw new \RuntimeException('Committed source-authority operation disappeared before WU6 handoff.');
        }
        $next = $current->advance(
            LifecycleOperationRecord::AWAITING_WU6,
            0,
            null,
            null,
            'Authority evidence is complete; WU6 finalization is required.'
        );
        $operations->save($next);
        $reread = $operations->read();
        if (!$reread instanceof LifecycleOperationRecord
            || $reread->operationId() !== $this->plan->operation()->operationId()
            || $reread->phase() !== LifecycleOperationRecord::AWAITING_WU6
            || $reread->fileCursor() !== 0
            || $reread->lastVerifiedPath() !== null
            || $reread->migrationOutcome() !== null
            || $reread->recoveryIdentity() !== $this->binding->crossReference()->recoveryIdentity()->value()
            || $reread->recoveryManifestIdentity() !== $this->binding->manifest()->identity()
            || $reread->recoveryState() !== RecoveryLifecycleState::READY) {
            throw new \RuntimeException('Committed source-authority WU6 handoff failed deterministic reread.');
        }
        return $reread;
    }

    /** Revalidates the durable handoff for WU6, including cleanup retry. */
    public function revalidateForWu6(
        RecoveryLifecycleCoordinator $recoveryCoordinator,
        LifecycleOperationStore $operations,
        RecoveryArtifactStore $artifacts,
        ApplicationWriteProtectionEvidence $application,
        ScopedDatabaseConsistencyEvidence $database
    ): LifecycleOperationRecord {
        if ($this->plan->mutationRequired()) {
            throw new \RuntimeException('Mutation-required source-authority plans cannot enter WU6 continuation before canonical execution.');
        }
        $current = $operations->read();
        if (!$current instanceof LifecycleOperationRecord
            || !in_array($current->phase(), [LifecycleOperationRecord::AWAITING_WU6, LifecycleOperationRecord::CLEANUP_PENDING], true)) {
            throw new \RuntimeException('Committed source-authority operation is not awaiting WU6.');
        }
        self::assertPrerequisites($this->plan, $this->binding, $recoveryCoordinator, $operations, $artifacts, $application, $database, [LifecycleOperationRecord::AWAITING_WU6, LifecycleOperationRecord::CLEANUP_PENDING], false);
        return $current;
    }

    private static function assertPrerequisites(
        CommittedSourceAuthorityPlan $plan,
        CommittedSourceAuthorityRecoveryBinding $binding,
        RecoveryLifecycleCoordinator $recoveryCoordinator,
        LifecycleOperationStore $operations,
        RecoveryArtifactStore $artifacts,
        ApplicationWriteProtectionEvidence $application,
        ScopedDatabaseConsistencyEvidence $database,
        array $allowedPhases = [LifecycleOperationRecord::PREPARING],
        bool $requirePlanPhase = true
    ): void {
        $operation = $plan->operation();
        if ($operation->classification() !== 'COMMITTED_SOURCE_AUTHORITY_RECONCILIATION'
            || ($requirePlanPhase && !in_array($operation->phase(), $allowedPhases, true))
            || $operation->fileCursor() !== 0
            || $operation->lastVerifiedPath() !== null
            || $operation->migrationOutcome() !== null
            || $binding->plan()->identity() !== $plan->identity()
            || $binding->mutationAuthorized()
            || !$binding->record()->captureComplete()
            || $binding->record()->state() !== RecoveryLifecycleState::READY
            || $binding->record()->mutationStarted()) {
            throw new \RuntimeException('Committed source-authority admission prerequisites are incomplete.');
        }
        $persisted = $operations->read();
        if (!$persisted instanceof LifecycleOperationRecord
            || $persisted->operationId() !== $operation->operationId()
            || $persisted->classification() !== $operation->classification()
            || !in_array($persisted->phase(), $allowedPhases, true)
            || $persisted->applyPlanIdentity() !== $plan->applyPlan()->identity()
            || $persisted->migrationPlanIdentity() !== $plan->migrationPlanIdentity()
            || $persisted->recoveryIdentity() !== $binding->crossReference()->recoveryIdentity()->value()
            || $persisted->recoveryManifestIdentity() !== $binding->manifest()->identity()
            || $persisted->recoveryState() !== RecoveryLifecycleState::READY
            || $persisted->fileCursor() !== 0
            || $persisted->lastVerifiedPath() !== null
            || $persisted->migrationOutcome() !== null) {
            throw new \RuntimeException('Persisted committed source-authority operation is stale or conflicting.');
        }
        $decoded = $artifacts->readManifest($binding->crossReference()->recoveryIdentity());
        $manifest = $decoded['manifest'];
        if ($manifest->identity() !== $binding->manifest()->identity()
            || $manifest->operationIdentity() !== $operation->operationId()
            || $manifest->applyPlanIdentity() !== $plan->applyPlan()->identity()
            || $manifest->preOperationLifecycleIdentity() !== $plan->committedLifecycleIdentity()
            || $manifest->preOperationMigrationLedgerIdentity() !== $plan->migrationStateIdentity()) {
            throw new \RuntimeException('Committed source-authority recovery manifest reread is mismatched.');
        }
        if (!$application->isValidAt()
            || !$database->isValidAt()
            || !$binding->crossReference()->matchesEvidence($application, $database)
            || !$recoveryCoordinator->scopedProtectionMatchesCrossReference($binding->crossReference(), $database)) {
            throw new \RuntimeException('Committed source-authority Guard or database evidence is stale, unscoped, or inactive.');
        }
    }
}
