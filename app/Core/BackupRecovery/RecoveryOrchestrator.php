<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\InstallationMutex;

/** Coordinates the WU6 boundaries; domain policy remains in caller-supplied seams. */
final class RecoveryOrchestrator
{
    public function __construct(
        private RecoveryLifecycleStore $store,
        private InstallationMutex $mutex,
        private DatabaseQuiescenceCapability $quiescence,
        private RecoveryMaintenanceBoundary $maintenance
    ) {}

    /**
     * @param callable():void $captureDomains
     * @param callable():void $publishManifest
     * @param callable():void $verifyReady
     * @param callable(RecoveryMutationPermit):void $mutate
     * @param callable():void $verifyMutation
     */
    public function captureAndAuthorize(RecoveryLifecycleRecord $record, callable $captureDomains, callable $publishManifest, callable $verifyReady, string $targetIdentity, callable $mutate, callable $verifyMutation): RecoveryLifecycleRecord
    {
        $lock = $this->mutex->acquire();
        if ($lock === null) throw new RecoveryLifecycleException('Another installation or recovery operation owns the mutex.');
        $identity = $record->recoveryIdentity();
        try {
            $this->store->create($record);
            if (!$this->maintenance->enter($identity)) throw new RecoveryLifecycleException('Recovery maintenance could not be established.');
            $this->store->transition($identity, RecoveryLifecycleState::CAPTURING);
            $captureDomains();
            $publishManifest();
            $verifyReady();
            $captured = $this->store->transition($identity, RecoveryLifecycleState::CAPTURED);
            $captured = $captured->withCaptureComplete(); $this->store->save($captured);
            $this->store->transition($identity, RecoveryLifecycleState::READY);
            $this->store->read($identity);
            $ready = $this->store->read($identity);
            if (!$ready->confirmationMatches($identity, $ready->manifestIdentity(), $targetIdentity)) throw new RecoveryLifecycleException('Explicit recovery confirmation is required before mutation.');
            $lease = $this->quiescence->acquire();
            if (!$lease instanceof DatabaseQuiescenceLease || !$lease->isActive()) throw new RecoveryLifecycleException('Database quiescence is unavailable; mutation is blocked.');
            try {
                $this->store->markMutationStarting($identity);
                $mutate(new RecoveryMutationPermit($lease));
                $verifyMutation();
                $verified = $this->store->read($identity);
                if (!$verified->confirmationMatches($identity, $verified->manifestIdentity(), $targetIdentity)) throw new RecoveryLifecycleException('Recovery confirmation changed during mutation.');
                $this->store->save($verified->withPostReconciliationVerified());
                $verified = $this->store->read($identity);
                if (!$verified->postReconciliationVerified()) throw new RecoveryLifecycleException('Post-reconciliation verification was not durably recorded.');
                if ($verified->state() !== RecoveryLifecycleState::READY || !$verified->mutationStarted() || !$verified->confirmationMatches($identity, $verified->manifestIdentity(), $targetIdentity)) throw new RecoveryLifecycleException('Forward-reconciliation cleanup prerequisites are not satisfied.');
                $this->store->transition($identity, RecoveryLifecycleState::CLEANUP_PENDING);
                $this->store->transition($identity, RecoveryLifecycleState::CLEANED);
            } finally { if ($lease->isActive()) $lease->release(); }
            $this->maintenance->leave($identity);
            return $this->store->read($identity);
        } catch (\Throwable $e) {
            try { $current = $this->store->read($identity); if (!$current->mutationStarted() && in_array($current->state(), [RecoveryLifecycleState::CREATED, RecoveryLifecycleState::CAPTURING, RecoveryLifecycleState::CAPTURED, RecoveryLifecycleState::READY], true)) $this->store->transition($identity, RecoveryLifecycleState::FAILED_BEFORE_MUTATION, $e->getMessage()); else if ($current->mutationStarted() && in_array($current->state(), [RecoveryLifecycleState::READY, RecoveryLifecycleState::RESTORING, RecoveryLifecycleState::VERIFYING], true)) $this->store->transition($identity, RecoveryLifecycleState::RESTORE_REQUIRED, $e->getMessage()); } catch (\Throwable) { /* preserve the original failure; unreadable state is indeterminate */ }
            try { $this->maintenance->leave($identity); } catch (\Throwable) {}
            throw $e;
        }
    }

    /** @param callable():void $restoreDatabase @param callable():void $restoreFilesystem @param callable():void $restoreLifecycle @param callable():void $restoreMarker @param callable():void $verify */
    public function restore(RecoveryIdentity $identity, callable $restoreDatabase, callable $restoreFilesystem, callable $restoreLifecycle, callable $restoreMarker, callable $verify): RecoveryLifecycleRecord
    {
        $lock=$this->mutex->acquire(); if ($lock===null) throw new RecoveryLifecycleException('Another installation or recovery operation owns the mutex.');
        try { if (!$this->maintenance->enter($identity)) throw new RecoveryLifecycleException('Recovery maintenance could not be established.'); $this->store->transition($identity, RecoveryLifecycleState::RESTORING); $lease=$this->quiescence->acquire(); if (!$lease instanceof DatabaseQuiescenceLease || !$lease->isActive()) throw new RecoveryLifecycleException('Database quiescence is unavailable; restore is blocked.'); try { $restoreDatabase(); $restoreFilesystem(); $restoreLifecycle(); $restoreMarker(); $this->store->transition($identity, RecoveryLifecycleState::VERIFYING); $verify(); $record=$this->store->read($identity); if (!$record->mutationStarted()) throw new RecoveryLifecycleException('RESTORED requires a known mutation boundary.'); $this->store->transition($identity, RecoveryLifecycleState::RESTORED); return $this->store->read($identity); } finally { if ($lease->isActive()) $lease->release(); } } catch (\Throwable $e) { try { $current=$this->store->read($identity); if ($current->mutationStarted() && in_array($current->state(), [RecoveryLifecycleState::RESTORING, RecoveryLifecycleState::VERIFYING], true)) $this->store->transition($identity, RecoveryLifecycleState::RESTORE_REQUIRED, $e->getMessage()); } catch (\Throwable) {} throw $e; } finally { try { $this->maintenance->leave($identity); } catch (\Throwable) {} }
    }
}
