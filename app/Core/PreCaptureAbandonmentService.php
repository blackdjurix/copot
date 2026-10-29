<?php

namespace Copot\Core;

/** Canonical, Guard-free terminalization before recovery capture begins. */
final class PreCaptureAbandonmentService
{
    public function __construct(
        private MaintenanceCoordinator $maintenance,
        private InstallationMutex $mutex,
        private PreCaptureAbandonmentEvidenceStore $evidence
    ) {}

    public function abandon(PreCaptureAbandonmentRequest $request): LifecycleOperationRecord
    {
        $lock = $this->mutex->acquire();
        if (!$lock instanceof InstallationLock) throw new \RuntimeException('Pre-capture abandonment requires exclusive installation ownership.');
        try {
            $operation = $this->maintenance->record();
            $saved = $this->evidence->read($request->operationId());
            if ($saved !== null && $operation === null) {
                $historical = LifecycleOperationRecord::fromArray($saved['operation']);
                return $historical->advance(LifecycleOperationRecord::PRE_CAPTURE_ABANDONED, 0, null, null, 'Pre-capture abandonment evidence is durable and verified.');
            }
            if (!$operation instanceof LifecycleOperationRecord || $operation->operationId() !== $request->operationId()) throw new \RuntimeException('Requested lifecycle operation is not active.');
            if ($saved !== null && ($saved['request'] ?? null) !== $request->toArray()) throw new \RuntimeException('Abandonment request differs from immutable evidence.');
            if ($operation->phase() === LifecycleOperationRecord::PRE_CAPTURE_ABANDONED) {
                $this->maintenance->clear($operation);
                return $operation;
            }
            $this->assertEligible($operation, $request);
            if ($saved === null) {
                $saved = $this->evidence->publish([
                    'schema' => 'copot-pre-capture-abandonment-v1',
                    'request' => $request->toArray(),
                    'operation' => $operation->toArray(),
                    'staging' => ['path' => $operation->stagingPath(), 'available' => is_dir($operation->stagingPath())],
                    'disposition' => LifecycleOperationRecord::PRE_CAPTURE_ABANDONED,
                    'created_at' => gmdate(DATE_ATOM),
                ]);
            } elseif (($saved['request'] ?? null) !== $request->toArray()) {
                throw new \RuntimeException('Abandonment request differs from immutable evidence.');
            }
            $request->fail('after_evidence');
            $operation = $this->maintenance->record();
            if (!$operation instanceof LifecycleOperationRecord || $operation->operationId() !== $request->operationId()) throw new \RuntimeException('Lifecycle operation disappeared during abandonment.');
            $this->assertEligible($operation, $request);
            $request->assertFresh($operation);
            $terminal = $operation->advance(LifecycleOperationRecord::PRE_CAPTURE_ABANDONED, 0, null, null, 'Pre-capture abandonment evidence is durable and verified.');
            $this->maintenance->update($terminal);
            $request->fail('after_terminal_persist');
            $persisted = $this->maintenance->record();
            if (!$persisted instanceof LifecycleOperationRecord || $persisted->phase() !== LifecycleOperationRecord::PRE_CAPTURE_ABANDONED) throw new \RuntimeException('Pre-capture abandonment terminal state was not verified.');
            $this->maintenance->clear($persisted);
            $request->fail('after_clear');
            return $terminal;
        } finally { $lock->release(); }
    }

    /** Read-only classification for a historical orphan; it never creates evidence or mutates state. */
    public static function historicalCandidate(array $operation): array
    {
        $eligible = ($operation['phase'] ?? null) === LifecycleOperationRecord::PREPARING
            && (int) ($operation['file_cursor'] ?? -1) === 0
            && ($operation['last_verified_path'] ?? null) === null
            && ($operation['migration_outcome'] ?? null) === null
            && ($operation['recovery_identity'] ?? null) === null
            && ($operation['recovery_manifest_identity'] ?? null) === null
            && ($operation['recovery_state'] ?? null) === null;
        return ['eligible' => $eligible, 'attempt_identity_observed' => $operation['execution_attempt_identity'] ?? null, 'historical' => !array_key_exists('execution_attempt_identity', $operation)];
    }

    private function assertEligible(LifecycleOperationRecord $operation, PreCaptureAbandonmentRequest $request): void
    {
        if ($operation->phase() !== LifecycleOperationRecord::PREPARING || $operation->fileCursor() !== 0 || $operation->lastVerifiedPath() !== null || $operation->migrationOutcome() !== null || $operation->recoveryIdentity() !== null || $operation->recoveryManifestIdentity() !== null || $operation->recoveryState() !== null || $operation->isTerminal()) {
            throw new \RuntimeException('Operation is not eligible for pre-capture abandonment.');
        }
        $request->assertFresh($operation);
    }
}
