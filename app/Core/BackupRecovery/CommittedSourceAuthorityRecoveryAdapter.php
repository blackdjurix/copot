<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\CommittedSourceAuthorityPlan;
use Copot\Core\LifecycleOperationRecord;
use Copot\Core\LifecycleOperationStore;

/** Canonical capture adapter; capture itself remains owned by Backup & Recovery. */
final class CommittedSourceAuthorityRecoveryAdapter
{
    public function __construct(
        private NormalWebcoreRecoveryCaptureService $capture,
        private RecoveryArtifactStore $artifacts,
        private LifecycleOperationStore $operations,
        private RecoveryLifecycleStore $recoveryStore,
        private RecoveryLifecycleCoordinator $coordinator
    ) {}

    /**
     * Compatibility wrapper for callers that only need the immutable binding.
     * Canonical Package Lifecycle orchestration uses captureOrRehydrate() so
     * the returned process-local protection owner cannot be lost.
     */
    public function capture(
        CommittedSourceAuthorityPlan $plan,
        NormalWebcoreRecoveryCaptureRequest $request
    ): CommittedSourceAuthorityRecoveryBinding {
        $result = $this->captureOrRehydrate($plan, $request, true);
        if ($result['session'] instanceof NormalWebcoreRecoverySession) {
            $result['session']->close();
        } elseif ($result['owns']) {
            $this->coordinator->leaveMaintenance($result['binding']->crossReference()->recoveryIdentity());
        }
        return $result['binding'];
    }

    public function coordinator(): RecoveryLifecycleCoordinator { return $this->coordinator; }
    public function artifacts(): RecoveryArtifactStore { return $this->artifacts; }

    /**
     * Capture or rehydrate one exact operation-bound recovery and establish
     * process-local scoped protection.  No recovery or source authority is
     * created by the rehydrate paths.
     *
     * @return array{binding: CommittedSourceAuthorityRecoveryBinding, session: ?NormalWebcoreRecoverySession, owns: bool}
     */
    public function captureOrRehydrate(
        CommittedSourceAuthorityPlan $plan,
        NormalWebcoreRecoveryCaptureRequest $request,
        bool $allowFreshCapture = false
    ): array {
        self::assertRequest($plan, $request);
        $operation = $this->operations->read();
        if (!$operation instanceof LifecycleOperationRecord
            || $operation->operationId() !== $plan->operation()->operationId()
            || $operation->executionAttemptIdentity() !== $plan->operation()->executionAttemptIdentity()
            || $operation->classification() !== 'COMMITTED_SOURCE_AUTHORITY_RECONCILIATION'
            || !in_array($operation->phase(), [
                LifecycleOperationRecord::PREPARING,
                LifecycleOperationRecord::AWAITING_WU6,
                LifecycleOperationRecord::CLEANUP_PENDING,
            ], true)) {
            throw new RecoveryLifecycleException('Persisted authority operation is missing, stale, or in a conflicting phase.');
        }

        $session = null;
        $binding = null;
        $acquired = false;
        try {
            $binding = null;
            if ($operation->recoveryIdentity() !== null && $operation->recoveryManifestIdentity() !== null) {
                $recovery = new RecoveryIdentity($operation->recoveryIdentity());
                $decoded = $this->artifacts->readManifest($recovery);
                if ($decoded['manifest']->identity() !== $operation->recoveryManifestIdentity()) {
                    throw new RecoveryLifecycleException('Persisted authority recovery manifest binding is stale or conflicting.');
                }
                $record = $this->recoveryStore->read($recovery);
                $binding = CommittedSourceAuthorityRecoveryBinding::fromCaptured(
                    $plan,
                    $decoded['manifest'],
                    $record,
                    $request->applicationWriteProtectionEvidence(),
                    $request->databaseConsistencyEvidence()
                );
            } elseif ($operation->recoveryIdentity() !== null || $operation->recoveryManifestIdentity() !== null) {
                throw new RecoveryLifecycleException('Authority recovery binding is partial and cannot be resumed safely.');
            } else {
                $decoded = null;
                if ($allowFreshCapture) {
                    $expectedRecovery = NormalWebcoreRecoveryCaptureService::forOperation($operation->operationId(), $plan->applyPlan()->identity(), $operation->executionAttemptIdentity());
                    try {
                        $decoded = $this->artifacts->readManifest($expectedRecovery);
                    } catch (RecoveryStorageException $exception) {
                        if ($exception->getMessage() !== 'Recovery manifest is unavailable.') {
                            throw new RecoveryLifecycleException('Authority recovery is present but invalid or ambiguous; fresh capture is blocked.', 0, $exception);
                        }
                        $decoded = null;
                    }
                } else {
                    try {
                        $decoded = $this->artifacts->findManifestByOperationIdentity($operation->operationId(), $operation->executionAttemptIdentity());
                    } catch (RecoveryStorageException $exception) {
                        throw new RecoveryLifecycleException('Authority recovery is absent or ambiguous; canonical continuation is blocked.', 0, $exception);
                    }
                }

                if (is_array($decoded)) {
                    $recovery = $decoded['manifest']->recoveryIdentity();
                    $record = $this->recoveryStore->read($recovery);
                    $binding = CommittedSourceAuthorityRecoveryBinding::fromCaptured(
                        $plan,
                        $decoded['manifest'],
                        $record,
                        $request->applicationWriteProtectionEvidence(),
                        $request->databaseConsistencyEvidence()
                    );
                    $operation = $this->bindOperation($operation, $recovery, $decoded['manifest']->identity());
                } else {
                    $session = $this->capture->capture($request);
                    $decoded = $this->artifacts->readManifest($session->recoveryIdentity());
                    $binding = CommittedSourceAuthorityRecoveryBinding::fromCaptured(
                        $plan,
                        $decoded['manifest'],
                        $session->record(),
                        $request->applicationWriteProtectionEvidence(),
                        $request->databaseConsistencyEvidence()
                    );
                    $operation = $this->bindOperation($operation, $session->recoveryIdentity(), $decoded['manifest']->identity());
                }
            }

            if (!$binding instanceof CommittedSourceAuthorityRecoveryBinding) {
                throw new RecoveryLifecycleException('Authority recovery binding could not be established.');
            }

            $recovery = $binding->crossReference()->recoveryIdentity();
            if ($this->coordinator->maintenanceActive($recovery)) {
                if (!$this->coordinator->scopedProtectionMatchesCrossReference($binding->crossReference(), $binding->databaseConsistency())) {
                    throw new RecoveryLifecycleException('Existing authority protection is not owned by matching scoped evidence.');
                }
            } elseif ($this->coordinator->durableScopedMaintenanceActive($recovery)) {
                $this->coordinator->resumeDurableScopedMaintenance($recovery);
                $this->coordinator->adoptScopedMaintenance($recovery, $binding->applicationProtection(), $binding->databaseConsistency());
                $acquired = true;
            } else {
                $this->coordinator->resumeReadyScopedMaintenance($recovery, $binding->applicationProtection(), $binding->databaseConsistency());
                $acquired = true;
            }

            return ['binding' => $binding, 'session' => $session, 'owns' => $session instanceof NormalWebcoreRecoverySession || $acquired];
        } catch (\Throwable $exception) {
            if ($session instanceof NormalWebcoreRecoverySession) {
                $session->close();
            } elseif ($acquired && $binding instanceof CommittedSourceAuthorityRecoveryBinding) {
                $this->coordinator->abandonResumedScopedMaintenance($binding->crossReference()->recoveryIdentity());
            }
            throw $exception;
        }
    }

    private function bindOperation(LifecycleOperationRecord $operation, RecoveryIdentity $recovery, string $manifestIdentity): LifecycleOperationRecord
    {
        $bound = $operation->bindRecovery($recovery->value(), $manifestIdentity, RecoveryLifecycleState::READY);
        $this->operations->save($bound);
        $reread = $this->operations->read();
        if (!$reread instanceof LifecycleOperationRecord
            || $reread->operationId() !== $operation->operationId()
            || $reread->executionAttemptIdentity() !== $operation->executionAttemptIdentity()
            || $reread->recoveryIdentity() !== $recovery->value()
            || $reread->recoveryManifestIdentity() !== $manifestIdentity
            || $reread->recoveryState() !== RecoveryLifecycleState::READY) {
            throw new RecoveryLifecycleException('Authority operation recovery binding failed deterministic reread.');
        }
        return $reread;
    }

    public static function assertRequest(CommittedSourceAuthorityPlan $plan, NormalWebcoreRecoveryCaptureRequest $request): void
    {
        $recovery = NormalWebcoreRecoveryCaptureService::forOperation($plan->operation()->operationId(), $plan->applyPlan()->identity(), $plan->operation()->executionAttemptIdentity());
        if ($request->operationId() !== $plan->operation()->operationId()
            || $request->executionAttemptIdentity() !== $plan->operation()->executionAttemptIdentity()
            || $request->installationIdentity() !== $plan->databaseScope()->installationIdentity()
            || $request->namespaceIdentity() !== $plan->databaseScope()->namespaceIdentity()
            || $request->targetPackageIdentity() !== $plan->sourcePlan()->packageEvidenceIdentity()
            || $request->targetReleaseIdentity() !== $plan->operation()->releaseIdentity()
            || $request->archiveIdentity() !== $plan->operation()->archiveSha256()
            || $request->applyPlan()->identity() !== $plan->applyPlan()->identity()
            || $request->deploymentIdentity() !== $plan->committedLifecycleIdentity()
            || $request->preLifecycleIdentity() !== $plan->committedLifecycleIdentity()
            || $request->migrationLedgerIdentity() !== $plan->migrationStateIdentity()
            || $request->databaseScope()?->identity() !== $plan->databaseScope()->identity()
            || $request->databaseIdentity() !== $plan->databaseScope()->databaseIdentity()
            || $request->orderedV3Plan()?->identity() !== $plan->sourcePlan()->identity()
            || !$request->applicationWriteProtectionEvidence() instanceof ApplicationWriteProtectionEvidence
            || !$request->databaseConsistencyEvidence() instanceof ScopedDatabaseConsistencyEvidence
            || !$request->applicationWriteProtectionEvidence()->operationIdentity()->equals($recovery)
            || !$request->databaseConsistencyEvidence()->operationIdentity()->equals($recovery)) {
            throw new RecoveryLifecycleException('Authority recovery capture request is not bound to the exact plan and scoped evidence.');
        }
        if (!$request->applicationWriteProtectionEvidence()->isValidAt() || !$request->databaseConsistencyEvidence()->isValidAt()) {
            throw new RecoveryLifecycleException('Authority recovery capture request contains stale scoped evidence.');
        }
    }
}
