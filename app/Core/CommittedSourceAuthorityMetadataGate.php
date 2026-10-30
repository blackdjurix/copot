<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\RecoveryLifecycleState;

/** Shared persisted-evidence gate for every committed source-authority admission path. */
final class CommittedSourceAuthorityMetadataGate
{
    public function __construct(
        private ?CommittedLifecycleStateStore $committedStore,
        private ?InstallationState $installationState,
        private ?CoreMigrationLedger $ledger,
        private $connection,
        private ?CanonicalSchemaBaselineVerifier $canonicalSchema,
        private ?string $canonicalSchemaPath,
        private ?LifecycleOperationStore $operationStore
    ) {}

    public function assert(?CommittedLifecycleState $planned = null, ?CommittedSourceAuthorityPlan $plan = null): CommittedLifecycleState
    {
        $this->assertConfigured();

        $activeOperation = $this->operationStore->read();
        if ($plan === null && $activeOperation instanceof LifecycleOperationRecord) {
            throw new \RuntimeException('A lifecycle operation is active; metadata reconciliation and recovery disposition must settle before source-authority planning.');
        }
        if ($plan instanceof CommittedSourceAuthorityPlan
            && $activeOperation instanceof LifecycleOperationRecord
            && $activeOperation->operationId() !== $plan->operation()->operationId()) {
            throw new \RuntimeException('A conflicting lifecycle operation is active; source-authority admission is blocked.');
        }
        if ($plan instanceof CommittedSourceAuthorityPlan
            && $activeOperation instanceof LifecycleOperationRecord
            && ($activeOperation->phase() !== LifecycleOperationRecord::PREPARING
                || $activeOperation->recoveryState() !== RecoveryLifecycleState::READY)) {
            throw new \RuntimeException('Source-authority operation is not in a fresh recovery-ready phase; retry requires canonical reconciliation.');
        }

        return $this->assertPersistedMetadata($planned, $plan);
    }

    /**
     * Narrow gate for the only state allowed to create the first recovery
     * binding for an already-persisted attempt.  This does not relax the
     * general planning/admission gate: it requires the exact operation and
     * an otherwise untouched PREPARING journal before capture.
     */
    public function assertFirstCapture(
        ?CommittedLifecycleState $planned,
        CommittedSourceAuthorityPlan $plan,
        LifecycleOperationRecord $activeOperation
    ): CommittedLifecycleState {
        $this->assertConfigured();

        $persistedOperation = $this->operationStore->read();
        if (!$persistedOperation instanceof LifecycleOperationRecord
            || $persistedOperation->toArray() !== $activeOperation->toArray()) {
            throw new \RuntimeException('Persisted operation changed while entering first recovery capture.');
        }

        $expected = $plan->operation();
        if ($activeOperation->operationId() !== $expected->operationId()
            || $activeOperation->classification() !== $expected->classification()
            || $activeOperation->targetWebcoreVersion() !== $expected->targetWebcoreVersion()
            || $activeOperation->releaseIdentity() !== $expected->releaseIdentity()
            || $activeOperation->archiveSha256() !== $expected->archiveSha256()
            || $activeOperation->stagingPath() !== $expected->stagingPath()
            || $activeOperation->payloadIdentity() !== $expected->payloadIdentity()
            || $activeOperation->applyPlanIdentity() !== $plan->applyPlan()->identity()
            || $activeOperation->phase() !== LifecycleOperationRecord::PREPARING
            || $activeOperation->fileCursor() !== 0
            || $activeOperation->lastVerifiedPath() !== null
            || $activeOperation->migrationPlanIdentity() !== $plan->migrationPlanIdentity()
            || $activeOperation->migrationOutcome() !== null
            || !$activeOperation->isAttemptAware()
            || $expected->executionAttemptIdentity() === null
            || $activeOperation->executionAttemptIdentity() !== $expected->executionAttemptIdentity()
            || $activeOperation->recoveryIdentity() !== null
            || $activeOperation->recoveryManifestIdentity() !== null
            || $activeOperation->recoveryState() !== null) {
            throw new \RuntimeException('Persisted operation is not eligible for canonical first recovery capture.');
        }

        return $this->assertPersistedMetadata($planned, $plan);
    }

    private function assertConfigured(): void
    {
        if (!$this->committedStore instanceof CommittedLifecycleStateStore
            || !$this->installationState instanceof InstallationState
            || !$this->ledger instanceof CoreMigrationLedger
            || !is_callable($this->connection)
            || !$this->canonicalSchema instanceof CanonicalSchemaBaselineVerifier
            || !is_string($this->canonicalSchemaPath)
            || !$this->operationStore instanceof LifecycleOperationStore) {
            throw new \RuntimeException('Committed source-authority metadata gate is unavailable.');
        }
    }

    private function assertPersistedMetadata(?CommittedLifecycleState $planned, ?CommittedSourceAuthorityPlan $plan): CommittedLifecycleState
    {
        $current = $this->committedStore->read();
        if (!$current instanceof CommittedLifecycleState || $current->sourceInventoryReference() !== null) {
            throw new \RuntimeException('Committed source-authority metadata is unavailable or source authority is already published.');
        }
        if ($planned instanceof CommittedLifecycleState
            && !hash_equals(CommittedLifecycleMetadataReconciliationService::stateIdentity($current), CommittedLifecycleMetadataReconciliationService::stateIdentity($planned))) {
            throw new \RuntimeException('Committed metadata changed after source-authority planning input; reconciliation and replan are required.');
        }
        if ($plan instanceof CommittedSourceAuthorityPlan
            && !hash_equals(CommittedLifecycleMetadataReconciliationService::stateIdentity($current), $plan->committedLifecycleIdentity())) {
            throw new \RuntimeException('Committed metadata is stale for the source-authority plan; reconciliation and replan are required.');
        }

        $marker = $this->installationState->readMarker();
        if (!is_array($marker)
            || $marker['version'] !== $current->webcoreVersion()
            || $marker['installed_at'] !== $current->committedAt()->format(DATE_ATOM)) {
            throw new \RuntimeException('Committed metadata and installed marker are inconsistent; metadata reconciliation is required.');
        }

        $connection = ($this->connection)();
        $records = $this->ledger->records($connection);
        if (CoreMigrationStateIdentity::fromRecords($records) !== $current->migrationStateIdentity()) {
            throw new \RuntimeException('Committed migration identity is stale; metadata reconciliation and replan are required.');
        }
        if ($records !== []) {
            $last = $records[array_key_last($records)];
            if ($last->targetSchemaIdentity() !== $current->schemaStateIdentity()
                || $last->targetWebcoreVersion() !== $current->webcoreVersion()) {
                throw new \RuntimeException('Committed schema metadata is stale; metadata reconciliation and replan are required.');
            }
        } else {
            $schemaEvidence = $this->canonicalSchema->verify($connection, $this->canonicalSchemaPath, true);
            if (!$schemaEvidence->passed()) {
                throw new \RuntimeException('Empty migration ledger requires passing authoritative current-schema evidence.');
            }
            $canonicalSchemaIdentity = $this->canonicalSchema->identity($this->canonicalSchemaPath);
            if (!in_array($current->schemaStateIdentity(), ['canonical-current', $canonicalSchemaIdentity], true)) {
                throw new \RuntimeException('Empty migration ledger does not prove the committed schema identity.');
            }
        }
        return $current;
    }
}
