<?php

namespace Copot\Core;

/** Deterministic, non-executable metadata reconciliation plan. */
final class CommittedLifecycleMetadataReconciliationPlan
{
    public const OPERATION = 'COMMITTED_LIFECYCLE_METADATA_RECONCILIATION';

    public function __construct(
        private string $identity,
        private LifecycleOperationRecord $operation,
        private CommittedLifecycleState $before,
        private CommittedLifecycleState $after,
        private string $schemaIdentity,
        private string $migrationIdentity,
        private CommittedLifecycleMetadataReconciliationEvidence $evidence
    ) {
        if (preg_match('/^[a-f0-9]{64}$/D', $identity) !== 1
            || $operation->classification() !== self::OPERATION
            || $operation->phase() !== LifecycleOperationRecord::PREPARING
            || $operation->migrationPlanIdentity() !== $migrationIdentity
            || $before->sourceInventoryReference()?->toArray() !== $after->sourceInventoryReference()?->toArray()
            || $before->webcoreVersion() !== $after->webcoreVersion()
            || $before->releaseIdentity() !== $after->releaseIdentity()
            || $before->sourceTreeIdentity() !== $after->sourceTreeIdentity()
            || $before->manifestContractVersion() !== $after->manifestContractVersion()
            || $before->packageIntegrityIdentity() !== $after->packageIntegrityIdentity()) {
            throw new \InvalidArgumentException('Metadata reconciliation plan changes protected lifecycle fields.');
        }
    }

    public function identity(): string { return $this->identity; }
    public function operation(): LifecycleOperationRecord { return $this->operation; }
    public function before(): CommittedLifecycleState { return $this->before; }
    public function after(): CommittedLifecycleState { return $this->after; }
    public function schemaIdentity(): string { return $this->schemaIdentity; }
    public function migrationIdentity(): string { return $this->migrationIdentity; }
    public function evidence(): CommittedLifecycleMetadataReconciliationEvidence { return $this->evidence; }
    public function toArray(): array
    {
        return [
            'identity' => $this->identity,
            'operation' => $this->operation->toArray(),
            'before' => $this->before->toArray(),
            'after' => $this->after->toArray(),
            'schema_identity' => $this->schemaIdentity,
            'migration_identity' => $this->migrationIdentity,
            'evidence_identity' => $this->evidence->identity(),
        ];
    }
}
