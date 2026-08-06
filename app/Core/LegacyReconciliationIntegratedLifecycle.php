<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\RecoveryLifecycleCoordinator;
use Copot\Core\BackupRecovery\RecoveryLifecycleRecord;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;
use Copot\Core\BackupRecovery\RecoveryManifest;
use Copot\Core\BackupRecovery\RecoveryOrchestrator;

/** Composes the accepted IU2 WU1-WU5 boundaries without owning a second state machine. */
final class LegacyReconciliationIntegratedLifecycle
{
    private const DOMAINS = [
        'database' => 'database.webcore',
        'filesystem.package-owned' => 'filesystem.package-owned',
        'lifecycle.committed' => 'filesystem.lifecycle.committed',
        'lifecycle.installed-lock' => 'filesystem.lifecycle.installed-lock',
    ];
    public function __construct(
        private RecoveryLifecycleStore $store,
        private RecoveryLifecycleCoordinator $lifecycle,
        private RecoveryOrchestrator $restore
    ) {}

    /**
     * @param callable():WebcoreApplyResult $filesystem
     * @param callable():LegacyReconciliationDatabaseResult $database
     * @param callable():LegacyReconciliationFinalizationResult $finalize
     */
    public function reconcile(
        LegacyReconciliationPlan $plan,
        RecoveryManifest $manifest,
        ReconciliationMutationEligibility $eligibility,
        callable $filesystem,
        callable $database,
        callable $finalize
    ): LegacyReconciliationIntegratedResult {
        $this->assertReadyBinding($plan, $manifest, $eligibility);

        try {
            $filesystemResult = $filesystem();
            if (!$filesystemResult instanceof WebcoreApplyResult || $filesystemResult->status() !== WebcoreApplyResult::COMPLETED) {
                throw new \RuntimeException('Integrated reconciliation filesystem phase did not complete.');
            }
            $databaseResult = $database();
            if (!$databaseResult instanceof LegacyReconciliationDatabaseResult || $databaseResult->status() !== LegacyReconciliationDatabaseResult::COMPLETED) {
                throw new \RuntimeException('Integrated reconciliation database phase did not complete.');
            }
            $finalization = $finalize();
            if (!$finalization instanceof LegacyReconciliationFinalizationResult || !in_array($finalization->status(), [LegacyReconciliationFinalizationResult::COMPLETED, LegacyReconciliationFinalizationResult::ALREADY_FINALIZED], true)) {
                throw new \RuntimeException('Integrated reconciliation finalization phase did not complete.');
            }

            $record = $this->store->read($eligibility->recoveryIdentity());
            if ($record->state() !== RecoveryLifecycleState::READY || !$record->mutationStarted()) {
                throw new \RuntimeException('Integrated reconciliation did not leave a verified mutation boundary.');
            }
            $this->lifecycle->recordPostReconciliationVerification($eligibility->recoveryIdentity(), $manifest->identity(), $eligibility->confirmationIdentity());
            $this->lifecycle->markReconciliationCleanupPending($eligibility->recoveryIdentity(), $manifest->identity(), $eligibility->confirmationIdentity());
            $this->lifecycle->markCleaned($eligibility->recoveryIdentity());
            return new LegacyReconciliationIntegratedResult(LegacyReconciliationIntegratedResult::COMPLETED, $this->store->read($eligibility->recoveryIdentity())->state());
        } catch (\Throwable $exception) {
            $record = $this->store->read($eligibility->recoveryIdentity());
            $postMutation = $record->mutationStarted();
            if ($postMutation && $record->state() === RecoveryLifecycleState::READY) {
                $this->store->transition($eligibility->recoveryIdentity(), RecoveryLifecycleState::RESTORE_REQUIRED, $exception->getMessage());
            }
            return new LegacyReconciliationIntegratedResult(
                $postMutation ? LegacyReconciliationIntegratedResult::RESTORE_REQUIRED : LegacyReconciliationIntegratedResult::FAILED_BEFORE_MUTATION,
                $this->store->read($eligibility->recoveryIdentity())->state(),
                $exception->getMessage()
            );
        }
    }

    /** Restore uses the exact original recovery identity and all four accepted domains. */
    public function restore(
        LegacyReconciliationPlan $plan,
        RecoveryManifest $manifest,
        ReconciliationMutationEligibility $eligibility,
        callable $restoreDatabase,
        callable $restoreFilesystem,
        callable $restoreLifecycle,
        callable $restoreMarker,
        callable $verifyRestored
    ): RecoveryLifecycleRecord {
        $this->assertManifestBinding($plan, $manifest, $eligibility);
        $record = $this->store->read($eligibility->recoveryIdentity());
        if (!$record->mutationStarted() || !in_array($record->state(), [RecoveryLifecycleState::RESTORE_REQUIRED, RecoveryLifecycleState::VERIFICATION_FAILED], true)) {
            throw new \RuntimeException('Restore requires the exact mutation-started recovery identity in a recovery-required state.');
        }
        return $this->restore->restore($eligibility->recoveryIdentity(), $restoreDatabase, $restoreFilesystem, $restoreLifecycle, $restoreMarker, $verifyRestored);
    }

    /**
     * Retry is a fresh recovery attempt only after the original set is RESTORED.
     * The operation, immutable plan, and trusted target lineage must remain exact.
     * @param callable():LegacyReconciliationIntegratedResult $retry
     */
    public function retryAfterRestore(
        LegacyReconciliationPlan $plan,
        RecoveryManifest $originalManifest,
        RecoveryLifecycleRecord $restored,
        RecoveryManifest $retryManifest,
        ReconciliationMutationEligibility $retryEligibility,
        callable $retry
    ): LegacyReconciliationIntegratedResult {
        if ($restored->state() !== RecoveryLifecycleState::RESTORED || !$restored->mutationStarted() || $restored->operationIdentity() !== $plan->operationIdentity()) {
            throw new \RuntimeException('Retry requires an independently verified RESTORED recovery lineage.');
        }
        $this->assertManifestBinding($plan, $retryManifest, $retryEligibility);
        if (!$retryEligibility->isValid() || $retryManifest->recoveryIdentity()->equals($originalManifest->recoveryIdentity())
            || $retryManifest->operationIdentity() !== $plan->operationIdentity()
            || $retryManifest->targetPackageIdentity() !== $plan->target()->packageIdentity()
            || $retryEligibility->operationIdentity() !== $plan->operationIdentity()
            || $retryEligibility->planIdentity() !== $plan->identity()
            || $retryEligibility->manifestIdentity() !== $retryManifest->identity()
            || $retryEligibility->targetIdentity() !== $plan->target()->packageIdentity()) {
            throw new \RuntimeException('Retry authorization is not a fresh exact binding to the original plan lineage.');
        }
        $result = $retry();
        if (!$result instanceof LegacyReconciliationIntegratedResult) {
            throw new \RuntimeException('Retry did not return an integrated reconciliation result.');
        }
        return $result;
    }

    private function assertReadyBinding(LegacyReconciliationPlan $plan, RecoveryManifest $manifest, ReconciliationMutationEligibility $eligibility): void
    {
        $this->assertManifestBinding($plan, $manifest, $eligibility);
        $record = $this->store->read($eligibility->recoveryIdentity());
        if ($record->state() !== RecoveryLifecycleState::READY || !$record->captureComplete() || $record->mutationStarted()) {
            throw new \RuntimeException('Integrated reconciliation requires an unused READY recovery boundary.');
        }
        if (!$record->confirmationMatches($eligibility->recoveryIdentity(), $manifest->identity(), $eligibility->confirmationIdentity())) {
            throw new \RuntimeException('Integrated reconciliation confirmation does not match the exact recovery and target identities.');
        }
    }

    private function assertManifestBinding(LegacyReconciliationPlan $plan, RecoveryManifest $manifest, ReconciliationMutationEligibility $eligibility): void
    {
        if (!$eligibility->recoveryIdentity()->equals($manifest->recoveryIdentity())
            || $eligibility->operationIdentity() !== $plan->operationIdentity()
            || $eligibility->planIdentity() !== $plan->identity()
            || $eligibility->manifestIdentity() !== $manifest->identity()
            || $eligibility->targetIdentity() !== $plan->target()->packageIdentity()
            || $manifest->operationIdentity() !== $plan->operationIdentity()
            || $manifest->targetPackageIdentity() !== $plan->target()->packageIdentity()
            || $manifest->targetReleaseIdentity() !== $plan->target()->contract()->releaseIdentity()
            || $manifest->archiveIdentity() !== $plan->target()->archiveIdentity()
            || $manifest->applyPlanIdentity() !== $plan->target()->payloadIdentity()) {
            throw new \RuntimeException('Integrated reconciliation identity binding is invalid.');
        }
        $domains = [];
        foreach ($manifest->domainIdentities() as $domain) {
            $domains[$domain->identifier()] = $domain->ownershipKey();
        }
        if ($domains !== self::DOMAINS) {
            throw new \RuntimeException('Integrated reconciliation requires the exact four accepted recovery domains.');
        }
    }
}
