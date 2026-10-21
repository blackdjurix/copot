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
        private LifecycleOperationStore $operations
    ) {}

    public function capture(
        CommittedSourceAuthorityPlan $plan,
        NormalWebcoreRecoveryCaptureRequest $request
    ): CommittedSourceAuthorityRecoveryBinding {
        self::assertRequest($plan, $request);
        $operation = $this->operations->read();
        if (!$operation instanceof LifecycleOperationRecord
            || $operation->operationId() !== $plan->operation()->operationId()
            || $operation->classification() !== 'COMMITTED_SOURCE_AUTHORITY_RECONCILIATION'
            || $operation->phase() !== LifecycleOperationRecord::PREPARING) {
            throw new RecoveryLifecycleException('Persisted authority operation is missing, stale, or in a conflicting phase.');
        }
        $session = $this->capture->capture($request);
        try {
            $decoded = $this->artifacts->readManifest($session->recoveryIdentity());
            $binding = CommittedSourceAuthorityRecoveryBinding::fromCaptured(
                $plan,
                $decoded['manifest'],
                $session->record(),
                $request->applicationWriteProtectionEvidence(),
                $request->databaseConsistencyEvidence()
            );
            $bound = $operation->bindRecovery(
                $session->recoveryIdentity()->value(),
                $decoded['manifest']->identity(),
                RecoveryLifecycleState::READY
            );
            $this->operations->save($bound);
            $reread = $this->operations->read();
            if (!$reread instanceof LifecycleOperationRecord
                || $reread->recoveryIdentity() !== $session->recoveryIdentity()->value()
                || $reread->recoveryManifestIdentity() !== $decoded['manifest']->identity()
                || $reread->recoveryState() !== RecoveryLifecycleState::READY) {
                throw new RecoveryLifecycleException('Authority operation recovery binding failed deterministic reread.');
            }
            return $binding;
        } catch (\Throwable $exception) {
            $session->close();
            throw $exception;
        }
    }

    public static function assertRequest(CommittedSourceAuthorityPlan $plan, NormalWebcoreRecoveryCaptureRequest $request): void
    {
        $recovery = NormalWebcoreRecoveryCaptureService::forOperation($plan->operation()->operationId(), $plan->applyPlan()->identity());
        if ($request->operationId() !== $plan->operation()->operationId()
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
