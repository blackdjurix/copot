<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\CommittedLifecycleStateStore;

final class LifecycleRecoveryDomain
{
    private const DOMAIN = 'filesystem.lifecycle.committed';
    private const PATH = '.copot-lifecycle/committed-state.json';

    public function __construct(private CommittedLifecycleStateStore $store, private FilesystemRecoveryPathGuard $guard, private ?LifecycleRecoveryArtifactCodec $codec = null)
    {
        $this->codec ??= new LifecycleRecoveryArtifactCodec();
    }

    public function capture(): LifecycleRecoveryArtifact
    {
        try { $this->guard->resolve(self::PATH); return $this->codec->artifactFromState($this->store->read()); } catch (\Throwable $exception) { throw $exception instanceof LifecycleRecoveryException ? $exception : new LifecycleRecoveryException('Lifecycle recovery capture failed.', 0, $exception); }
    }

    public function restore(LifecycleRecoveryArtifact $artifact, ?string $expectedMutatedIdentity = null): void
    {
        try {
            $this->guard->resolve(self::PATH);
            $current = $this->store->read();
            $currentIdentity = $current === null ? null : $this->codec->artifactFromState($current)->identity();
            if ($artifact->stateKind() === 'ABSENT_BEFORE_OPERATION') {
                if ($current === null) { return; }
                if ($expectedMutatedIdentity === null || $currentIdentity !== $expectedMutatedIdentity) { throw new LifecycleRecoveryException('Unexpected committed lifecycle state drift.'); }
                $this->store->remove();
                if ($this->store->read() !== null) { throw new LifecycleRecoveryException('Committed lifecycle state remained after restoration.'); }
                return;
            }
            if (!$artifact->state() instanceof \Copot\Core\CommittedLifecycleState) { throw new LifecycleRecoveryException('Committed lifecycle recovery state is unavailable.'); }
            if ($currentIdentity === $artifact->identity()) { return; }
            if ($expectedMutatedIdentity === null || $currentIdentity !== $expectedMutatedIdentity) { throw new LifecycleRecoveryException('Unexpected committed lifecycle state drift.'); }
            $this->store->write($artifact->state());
            $restored = $this->store->read();
            if (!$restored instanceof \Copot\Core\CommittedLifecycleState || $this->codec->artifactFromState($restored)->identity() !== $artifact->identity()) { throw new LifecycleRecoveryException('Committed lifecycle state verification failed.'); }
        } catch (LifecycleRecoveryException $exception) { throw $exception; } catch (\Throwable $exception) { throw new LifecycleRecoveryException('Lifecycle recovery restore failed.', 0, $exception); }
    }

    public function restoreFromStore(RecoveryIdentity $identity, RecoveryArtifactRecord $record, RecoveryArtifactStore $store, ?string $expectedMutatedIdentity = null): void
    {
        $this->restore($this->codec->decode($store->readArtifact($identity, $record)), $expectedMutatedIdentity);
    }
}
