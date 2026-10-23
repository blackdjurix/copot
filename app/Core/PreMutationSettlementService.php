<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\RecoveryLifecycleCoordinator;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;

/** Bounded terminal disposition for a proven pre-mutation operation. */
final class PreMutationSettlementService
{
    public function __construct(
        private MaintenanceCoordinator $maintenance,
        private RecoveryLifecycleStore $recoveryStore,
        private RecoveryArtifactStore $artifacts,
        private RecoveryLifecycleCoordinator $recovery,
        private PreMutationSettlementEvidenceStore $evidence
    ) {}

    public function settle(PreMutationSettlementRequest $request): LifecycleOperationRecord
    {
        $operation = $this->maintenance->record();
        $saved = $this->evidence->read($request->operationId());
        $this->assertContinuationState($operation, $saved, $request);

        // A fully completed prior settlement is naturally idempotent. A durable
        // Guard means an interrupted release instead, and must be resumed.
        if ($saved !== null && $operation === null && !$this->recovery->durableScopedMaintenanceActive($request->recoveryIdentity())) {
            return $this->settledRecord($saved);
        }

        $resumed = $this->recovery->resumeDurableScopedMaintenance($request->recoveryIdentity());
        try {
            $operation = $this->maintenance->record();
            $saved = $this->evidence->read($request->operationId());
            $this->assertContinuationState($operation, $saved, $request);
            if ($saved === null) {
                $record = $this->assertRecovery($request);
                $saved = $this->evidence->publish([
                    'schema' => 'copot-pre-mutation-settlement-v1', 'request' => $request->toArray(),
                    'operation' => $operation->toArray(), 'recovery' => $record->toArray(),
                    'disposition' => LifecycleOperationRecord::PRE_MUTATION_SETTLED,
                    'reason' => 'Operation settled before the durable mutation boundary.', 'created_at' => gmdate(DATE_ATOM),
                ]);
            }

            $request->fail('after_evidence');
            $operation = $this->maintenance->record();
            if ($operation instanceof LifecycleOperationRecord && $operation->operationId() === $request->operationId() && !$operation->isTerminal()) {
                $this->assertEligible($operation, $request);
                $request->assertFresh($operation);
                $this->assertRecovery($request);
                $terminal = $operation->advance(LifecycleOperationRecord::PRE_MUTATION_SETTLED, 0, null, null, 'Pre-mutation settlement evidence is durable and verified.');
                $this->maintenance->update($terminal);
            }
            $request->fail('after_terminal_persist');
            $operation = $this->maintenance->record();
            if ($operation instanceof LifecycleOperationRecord && $operation->operationId() === $request->operationId()) {
                if ($operation->phase() !== LifecycleOperationRecord::PRE_MUTATION_SETTLED) throw new \RuntimeException('Settlement terminal transition is invalid.');
                $this->maintenance->clear($operation);
            }
            $request->fail('after_clear_before_guard_release');
            if (!$this->recovery->maintenanceActive($request->recoveryIdentity())) {
                throw new \RuntimeException('Canonical settlement does not own the durable Guard.');
            }
            $this->recovery->leaveMaintenance($request->recoveryIdentity());
            return $operation instanceof LifecycleOperationRecord ? $operation : $this->settledRecord($saved);
        } catch (\Throwable $exception) {
            if ($resumed) {
                $this->recovery->abandonResumedScopedMaintenance($request->recoveryIdentity());
            }
            throw $exception;
        }
    }

    /** @param array<string,mixed>|null $saved */
    private function assertContinuationState(?LifecycleOperationRecord $operation, ?array $saved, PreMutationSettlementRequest $request): void
    {
        if ($saved !== null && ($saved['request'] ?? null) !== $request->toArray()) {
            throw new \RuntimeException('Settlement request differs from immutable evidence.');
        }
        $source = $operation;
        if ($saved !== null) {
            $savedOperation = isset($saved['operation']) && is_array($saved['operation']) ? LifecycleOperationRecord::fromArray($saved['operation']) : null;
            if (!$savedOperation instanceof LifecycleOperationRecord) throw new \RuntimeException('Settlement evidence operation is invalid.');
            $this->assertEligible($savedOperation, $request);
            $source = $savedOperation;
            if ($operation instanceof LifecycleOperationRecord
                && ($operation->operationId() !== $request->operationId()
                    || !in_array($operation->phase(), [LifecycleOperationRecord::PREPARING, LifecycleOperationRecord::PRE_MUTATION_SETTLED], true))) {
                throw new \RuntimeException('Settlement operation state conflicts with immutable evidence.');
            }
        }
        $this->assertEligible($source, $request);
        $request->assertFresh($source);
        $this->assertManifest($request);
        $this->assertRecovery($request);
    }

    private function assertManifest(PreMutationSettlementRequest $request): void
    {
        $decoded = $this->artifacts->readManifest($request->recoveryIdentity());
        $manifest = $decoded['manifest'] ?? null;
        if (!$manifest instanceof \Copot\Core\BackupRecovery\RecoveryManifest
            || $manifest->operationIdentity() !== $request->operationId()
            || $manifest->identity() !== $request->manifestIdentity()) {
            throw new \RuntimeException('Recovery manifest binding is invalid.');
        }
    }

    /** @param array<string,mixed> $saved */
    private function settledRecord(array $saved): LifecycleOperationRecord
    {
        $operation = isset($saved['operation']) && is_array($saved['operation']) ? LifecycleOperationRecord::fromArray($saved['operation']) : null;
        if (!$operation instanceof LifecycleOperationRecord) throw new \RuntimeException('Settlement evidence operation is invalid.');
        return $operation->advance(LifecycleOperationRecord::PRE_MUTATION_SETTLED, 0, null, null, 'Pre-mutation settlement evidence is durable and verified.');
    }

    private function assertRecovery(PreMutationSettlementRequest $request): \Copot\Core\BackupRecovery\RecoveryLifecycleRecord
    {
        $record = $this->recoveryStore->read($request->recoveryIdentity());
        if ($record->operationIdentity() !== $request->operationId()
            || $record->manifestIdentity() !== $request->manifestIdentity()
            || $record->state() !== RecoveryLifecycleState::READY
            || !$record->captureComplete() || $record->mutationStarted()
            || !$record->confirmationMatches($request->recoveryIdentity(), $request->manifestIdentity(), $request->manifestIdentity())) {
            throw new \RuntimeException('Recovery is not eligible for pre-mutation settlement.');
        }
        return $record;
    }

    private function assertEligible(?LifecycleOperationRecord $operation, PreMutationSettlementRequest $request): void
    {
        if (!$operation instanceof LifecycleOperationRecord || $operation->operationId() !== $request->operationId()) throw new \RuntimeException('Requested lifecycle operation is not active.');
        if ($operation->phase() !== LifecycleOperationRecord::PREPARING || $operation->fileCursor() !== 0 || $operation->lastVerifiedPath() !== null || $operation->migrationOutcome() !== null || $operation->recoveryIdentity() !== $request->recoveryIdentity()->value() || $operation->recoveryManifestIdentity() !== $request->manifestIdentity() || $operation->recoveryState() !== RecoveryLifecycleState::READY) throw new \RuntimeException('Operation is not eligible for pre-mutation settlement.');
        if ($operation->archiveSha256() !== $request->packageIdentity() || $operation->applyPlanIdentity() !== $request->planIdentity()) throw new \RuntimeException('Package or plan identity mismatch.');
    }
}
