<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\InstallationLock;
use Copot\Core\InstallationMutex;

final class RecoveryLifecycleCoordinator
{
    public function __construct(
        private RecoveryLifecycleStore $store,
        private InstallationMutex $mutex,
        private DatabaseQuiescenceCapability $quiescence,
        private ?RecoveryMaintenanceBoundary $maintenance = null
    ) {}

    public function create(RecoveryLifecycleRecord $record): RecoveryLifecycleRecord
    { $this->store->create($record); return $record; }

    public function enterMaintenance(RecoveryIdentity $identity): void
    { if ($this->maintenance === null || !$this->maintenance->enter($identity)) throw new RecoveryLifecycleException('Recovery maintenance could not be established.'); }

    public function leaveMaintenance(RecoveryIdentity $identity): void
    { if ($this->maintenance === null || !$this->maintenance->leave($identity)) throw new RecoveryLifecycleException('Recovery maintenance could not be released.'); }

    public function transition(RecoveryIdentity $identity, string $state, string $reason = ''): RecoveryLifecycleRecord
    { return $this->store->transition($identity, $state, $reason); }

    public function recordCaptureComplete(RecoveryIdentity $identity): RecoveryLifecycleRecord
    { $record = $this->store->read($identity); if ($record->state() !== RecoveryLifecycleState::CAPTURED) throw new RecoveryLifecycleException('Capture must be CAPTURED before completion is recorded.'); $next = $record->withCaptureComplete(); $this->store->save($next); return $next; }

    public function confirm(RecoveryIdentity $identity, string $manifestIdentity, string $targetIdentity): RecoveryLifecycleRecord
    { $record = $this->store->read($identity); if (!in_array($record->state(), [RecoveryLifecycleState::CAPTURED, RecoveryLifecycleState::READY], true)) throw new RecoveryLifecycleException('Recovery confirmation is not allowed in the current state.'); $next = $record->withConfirmation($identity, $manifestIdentity, $targetIdentity); $this->store->save($next); return $next; }

    /** The returned permit must be held for the complete caller-controlled mutation interval. */
    public function authorizeMutation(RecoveryIdentity $identity, string $manifestIdentity, string $targetIdentity): RecoveryMutationPermit
    {
        $record = $this->store->read($identity);
        if ($record->state() !== RecoveryLifecycleState::READY || !$record->captureComplete() || !$record->confirmationMatches($identity, $manifestIdentity, $targetIdentity)) throw new RecoveryLifecycleException('Recovery mutation prerequisites are not satisfied.');
        if ($this->maintenance === null || !$this->maintenance->isActive($identity)) throw new RecoveryLifecycleException('Recovery maintenance is unavailable; mutation is blocked.');
        $lease = $this->quiescence->acquire();
        if (!$lease instanceof DatabaseQuiescenceLease || !$lease->isActive()) throw new RecoveryLifecycleException('Database quiescence is unavailable; mutation is blocked.');
        try { $this->store->markMutationStarting($identity); } catch (\Throwable $e) { $lease->release(); throw $e; }
        return new RecoveryMutationPermit($lease);
    }

    public function beginRestore(RecoveryIdentity $identity): RecoveryLifecycleRecord
    { $record = $this->store->read($identity); if (!in_array($record->state(), [RecoveryLifecycleState::READY, RecoveryLifecycleState::RESTORE_REQUIRED, RecoveryLifecycleState::VERIFICATION_FAILED], true) || !$record->mutationStarted()) throw new RecoveryLifecycleException('Restore requires a durably crossed mutation boundary.'); return $this->store->transition($identity, RecoveryLifecycleState::RESTORING); }

    public function beginVerification(RecoveryIdentity $identity): RecoveryLifecycleRecord
    { return $this->store->transition($identity, RecoveryLifecycleState::VERIFYING); }
    public function markRestored(RecoveryIdentity $identity): RecoveryLifecycleRecord
    { $record = $this->store->read($identity); if ($record->state() !== RecoveryLifecycleState::VERIFYING || !$record->mutationStarted()) throw new RecoveryLifecycleException('RESTORED requires a completed restore boundary.'); return $this->store->transition($identity, RecoveryLifecycleState::RESTORED); }
    public function recordPostReconciliationVerification(RecoveryIdentity $identity, string $manifestIdentity, string $targetIdentity): RecoveryLifecycleRecord
    { $record = $this->store->read($identity); if ($record->state() !== RecoveryLifecycleState::READY || !$record->mutationStarted() || !$record->confirmationMatches($identity, $manifestIdentity, $targetIdentity)) throw new RecoveryLifecycleException('Post-reconciliation verification prerequisites are not satisfied.'); $next = $record->withPostReconciliationVerified(); $this->store->save($next); return $next; }
    public function markCleanupPending(RecoveryIdentity $identity, string $targetIdentity): RecoveryLifecycleRecord
    { $record = $this->store->read($identity); if ($record->state() !== RecoveryLifecycleState::RESTORED || !$record->confirmationMatches($identity, $record->manifestIdentity(), $targetIdentity)) throw new RecoveryLifecycleException('Restore cleanup requires RESTORED state and matching confirmation.'); return $this->store->transition($identity, RecoveryLifecycleState::CLEANUP_PENDING); }
    public function markReconciliationCleanupPending(RecoveryIdentity $identity, string $manifestIdentity, string $targetIdentity): RecoveryLifecycleRecord
    { $record = $this->store->read($identity); if ($record->state() !== RecoveryLifecycleState::READY || !$record->mutationStarted() || !$record->postReconciliationVerified() || !$record->confirmationMatches($identity, $manifestIdentity, $targetIdentity)) throw new RecoveryLifecycleException('Forward-reconciliation cleanup prerequisites are not satisfied.'); return $this->store->transition($identity, RecoveryLifecycleState::CLEANUP_PENDING); }
    public function markCleaned(RecoveryIdentity $identity): RecoveryLifecycleRecord
    { return $this->store->transition($identity, RecoveryLifecycleState::CLEANED); }
    public function recordRestoreStage(RecoveryIdentity $identity, string $attempt, string $stage): RecoveryLifecycleRecord
    { $record=$this->store->read($identity); if ($record->state() !== RecoveryLifecycleState::RESTORING) throw new RecoveryLifecycleException('Restore stage is not valid outside RESTORING.'); $next=$record->withRestoreStage($attempt,$stage); $this->store->save($next); return $next; }

    /** @param array<int, string> $providerCreatedObjectScope */
    public function databaseRestoreAttempt(RecoveryIdentity $identity, string $attempt, string $expectedDatabaseIdentity, string $expectedTableSetIdentity, string $stage, array $providerCreatedObjectScope): DatabaseRestoreAttemptContext
    {
        if ($this->store->read($identity)->state() !== RecoveryLifecycleState::RESTORING) throw new RecoveryLifecycleException('Database restore attempt is not allowed outside RESTORING.');
        $this->recordRestoreStage($identity, $attempt, $stage);
        return new DatabaseRestoreAttemptContext($identity, $attempt, $expectedDatabaseIdentity, $expectedTableSetIdentity, $stage, $providerCreatedObjectScope, function (string $nextStage) use ($identity, $attempt): void {
            $this->recordRestoreStage($identity, $attempt, $nextStage);
        });
    }
    public function mutex(): ?InstallationLock { return $this->mutex->acquire(); }
}
