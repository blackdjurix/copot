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
        if ($saved === null) {
            $this->assertEligible($operation, $request);
            $request->assertFresh($operation);
            $decoded = $this->artifacts->readManifest($request->recoveryIdentity());
            $manifest = $decoded['manifest'] ?? null;
            if (!$manifest instanceof \Copot\Core\BackupRecovery\RecoveryManifest
                || $manifest->operationIdentity() !== $request->operationId()
                || $manifest->identity() !== $request->manifestIdentity()) throw new \RuntimeException('Recovery manifest binding is invalid.');
            $record = $this->assertRecovery($request);
            $saved = $this->evidence->publish([
                'schema' => 'copot-pre-mutation-settlement-v1', 'request' => $request->toArray(),
                'operation' => $operation->toArray(), 'recovery' => $record->toArray(),
                'disposition' => LifecycleOperationRecord::PRE_MUTATION_SETTLED,
                'reason' => 'Operation settled before the durable mutation boundary.', 'created_at' => gmdate(DATE_ATOM),
            ]);
        } elseif (($saved['request'] ?? null) !== $request->toArray()) {
            throw new \RuntimeException('Settlement request differs from immutable evidence.');
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
        if ($this->recovery->maintenanceActive($request->recoveryIdentity())) {
            $this->recovery->leaveMaintenance($request->recoveryIdentity());
        }
        return $operation instanceof LifecycleOperationRecord ? $operation : LifecycleOperationRecord::fromArray($saved['operation'])->advance(LifecycleOperationRecord::PRE_MUTATION_SETTLED, 0, null, null, 'Pre-mutation settlement evidence is durable and verified.');
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
