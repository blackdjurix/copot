<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\DatabaseQuiescenceCapability;
use Copot\Core\BackupRecovery\DatabaseQuiescenceLease;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryLifecycleCoordinator;
use Copot\Core\BackupRecovery\RecoveryLifecycleException;
use Copot\Core\BackupRecovery\RecoveryLifecycleRecord;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;
use Copot\Core\BackupRecovery\RecoveryManifest;

final class LegacyReconciliationRecoveryOrchestrator
{
    private const DOMAINS = [
        'database' => 'database.webcore',
        'filesystem.package-owned' => 'filesystem.package-owned',
        'lifecycle.committed' => 'filesystem.lifecycle.committed',
        'lifecycle.installed-lock' => 'filesystem.lifecycle.installed-lock',
    ];

    public function __construct(
        private RecoveryLifecycleCoordinator $recovery,
        private RecoveryLifecycleStore $store,
        private DatabaseQuiescenceCapability $quiescence
    ) {}

    /**
     * Capture/verify is supplied by the accepted Backup & Recovery domain layer.
     * This method never marks mutation as started and never invokes downstream apply.
     *
     * @param callable(RecoveryManifest):void $captureAndPublish
     * @param callable(RecoveryManifest):void $verifyReady
     */
    public function prepare(
        LegacyReconciliationPlan $plan,
        RecoveryManifest $manifest,
        callable $captureAndPublish,
        callable $verifyReady,
        ReconciliationConfirmation $confirmation
    ): ReconciliationMutationEligibility {
        $identity = self::recoveryIdentity($plan);
        self::assertManifest($plan, $identity, $manifest);

        if (!$confirmation->matches($plan, $identity)) {
            throw new \RuntimeException('Reconciliation confirmation does not match the exact operation, plan, recovery, and target identities.');
        }

        $record = $this->existingOrCreate($plan, $identity, $manifest);
        $ready = $record->state() === RecoveryLifecycleState::READY && $record->captureComplete();
        $maintenanceActive = false;
        $lease = null;

        try {
            if (!$ready) {
                if ($record->state() !== RecoveryLifecycleState::CREATED) {
                    $record = $this->recovery->transition($identity, RecoveryLifecycleState::CAPTURING);
                } else {
                    $this->recovery->enterMaintenance($identity);
                    $maintenanceActive = true;
                    $record = $this->recovery->transition($identity, RecoveryLifecycleState::CAPTURING);
                }

                if (!$maintenanceActive) {
                    $this->recovery->enterMaintenance($identity);
                    $maintenanceActive = true;
                }

                $lease = $this->acquireQuiescence();
                $captureAndPublish($manifest);
                $verifyReady($manifest);
                $this->recovery->transition($identity, RecoveryLifecycleState::CAPTURED);
                $this->recovery->recordCaptureComplete($identity);
                $this->recovery->transition($identity, RecoveryLifecycleState::READY);
            } else {
                $this->recovery->enterMaintenance($identity);
                $maintenanceActive = true;
                $lease = $this->acquireQuiescence();
            }

            $this->recovery->confirm($identity, $manifest->identity(), $confirmation->bindingIdentity());
            $record = $this->store->read($identity);
            if ($record->state() !== RecoveryLifecycleState::READY
                || !$record->captureComplete()
                || !$record->confirmationMatches($identity, $manifest->identity(), $confirmation->bindingIdentity())) {
                throw new \RuntimeException('Recovery readiness or exact confirmation binding could not be verified.');
            }

            return new ReconciliationMutationEligibility(
                $identity,
                $plan->operationIdentity(),
                $plan->identity(),
                $manifest->identity(),
                $plan->target()->packageIdentity(),
                $lease,
                function () use ($identity, $maintenanceActive): void {
                    if ($maintenanceActive) {
                        $this->recovery->leaveMaintenance($identity);
                    }
                }
            );
        } catch (\Throwable $exception) {
            if ($lease instanceof DatabaseQuiescenceLease) {
                $lease->release();
            }
            if ($maintenanceActive) {
                try { $this->recovery->leaveMaintenance($identity); } catch (\Throwable) {}
            }

            if ($this->store->read($identity)->state() === RecoveryLifecycleState::CAPTURING) {
                try { $this->recovery->transition($identity, RecoveryLifecycleState::FAILED_BEFORE_MUTATION, $exception->getMessage()); } catch (\Throwable) {}
            }

            throw $exception;
        }
    }

    private function acquireQuiescence(): DatabaseQuiescenceLease
    {
        if (!$this->quiescence->isAvailable()) {
            throw new \RuntimeException('Database quiescence is unavailable; reconciliation is blocked.');
        }

        $lease = $this->quiescence->acquire();
        if (!$lease instanceof DatabaseQuiescenceLease || !$lease->isActive()) {
            throw new \RuntimeException('Database quiescence could not be acquired; reconciliation is blocked.');
        }

        return $lease;
    }

    public static function recoveryIdentity(LegacyReconciliationPlan $plan): RecoveryIdentity
    {
        return new RecoveryIdentity('reconciliation-' . hash('sha256', json_encode([
            'operation_identity' => $plan->operationIdentity(),
            'plan_identity' => $plan->identity(),
            'target_identity' => $plan->target()->packageIdentity(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)));
    }

    private function existingOrCreate(LegacyReconciliationPlan $plan, RecoveryIdentity $identity, RecoveryManifest $manifest): RecoveryLifecycleRecord
    {
        try {
            $record = $this->store->read($identity);
            if ($record->operationIdentity() !== $plan->operationIdentity() || $record->manifestIdentity() !== $manifest->identity() || $record->mutationStarted()) {
                throw new \RuntimeException('Recovery identity is already bound to incompatible or post-mutation state.');
            }
            return $record;
        } catch (RecoveryLifecycleException) {
            return $this->recovery->create(new RecoveryLifecycleRecord($identity, $manifest->identity(), $plan->operationIdentity(), RecoveryLifecycleState::CREATED));
        }
    }

    private static function assertManifest(LegacyReconciliationPlan $plan, RecoveryIdentity $identity, RecoveryManifest $manifest): void
    {
        if (!$manifest->recoveryIdentity()->equals($identity)
            || $manifest->operationIdentity() !== $plan->operationIdentity()
            || $manifest->targetPackageIdentity() !== $plan->target()->packageIdentity()
            || $manifest->targetReleaseIdentity() !== $plan->target()->contract()->releaseIdentity()
            || $manifest->archiveIdentity() !== $plan->target()->archiveIdentity()
            || $manifest->applyPlanIdentity() !== $plan->target()->payloadIdentity()
            || $manifest->preOperationLifecycleIdentity() !== $plan->preStateIdentity()
            || $manifest->preOperationMigrationLedgerIdentity() !== CoreMigrationStateIdentity::fromRecords($plan->classification()->records())) {
            throw new \RuntimeException('Recovery manifest is not bound to the exact reconciliation plan and trusted target.');
        }

        $domains = [];
        foreach ($manifest->domainIdentities() as $domain) {
            $domains[$domain->identifier()] = $domain->ownershipKey();
        }
        if ($domains !== self::DOMAINS) {
            throw new \RuntimeException('Recovery manifest does not contain the exact four accepted recovery domains.');
        }
    }
}
