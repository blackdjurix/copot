<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\CommittedLifecycleState;

final class LifecycleRecoveryArtifact
{
    public function __construct(private RecoveryArtifactRecord $record, private string $bytes, private string $stateKind, private ?CommittedLifecycleState $state)
    {
        if ($record->domainIdentifier() !== 'filesystem.lifecycle.committed' || strlen($bytes) !== $record->byteSize() || hash('sha256', $bytes) !== $record->artifactIdentity()) {
            throw new LifecycleRecoveryException('Lifecycle recovery artifact identity is invalid.');
        }
        if (!in_array($stateKind, ['PRESENT_COMMITTED_STATE', 'ABSENT_BEFORE_OPERATION'], true) || ($stateKind === 'PRESENT_COMMITTED_STATE') !== ($state instanceof CommittedLifecycleState)) {
            throw new LifecycleRecoveryException('Lifecycle recovery state kind is invalid.');
        }
    }

    public function record(): RecoveryArtifactRecord { return $this->record; }
    public function bytes(): string { return $this->bytes; }
    public function stateKind(): string { return $this->stateKind; }
    public function state(): ?CommittedLifecycleState { return $this->state; }
    public function identity(): string { return $this->record->artifactIdentity(); }
}
