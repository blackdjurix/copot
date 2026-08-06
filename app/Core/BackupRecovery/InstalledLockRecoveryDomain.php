<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\InstallationState;

final class InstalledLockRecoveryDomain
{
    private const PATH = 'installed.lock';

    public function __construct(private InstallationState $state, private FilesystemRecoveryPathGuard $guard, private ?InstalledLockRecoveryArtifactCodec $codec = null)
    {
        $this->codec ??= new InstalledLockRecoveryArtifactCodec();
    }

    public function capture(): InstalledLockRecoveryArtifact
    {
        try { $this->guard->resolve(self::PATH); $marker = $this->state->readMarker(); if (!is_array($marker)) { throw new LifecycleRecoveryException('Required installed.lock marker is missing.'); } return $this->codec->artifactFromMarker(['version' => $marker['version'], 'installed_at' => $marker['installed_at']]); } catch (LifecycleRecoveryException $exception) { throw $exception; } catch (\Throwable $exception) { throw new LifecycleRecoveryException('Installed-lock recovery capture failed.', 0, $exception); }
    }

    public function restore(InstalledLockRecoveryArtifact $artifact, ?string $expectedMutatedIdentity = null): void
    {
        try {
            $this->guard->resolve(self::PATH);
            $current = $this->state->readMarker();
            if (!is_array($current)) { throw new LifecycleRecoveryException('Required installed.lock marker is missing or invalid.'); }
            $currentArtifact = $this->codec->artifactFromMarker(['version' => $current['version'], 'installed_at' => $current['installed_at']]);
            if ($currentArtifact->identity() === $artifact->identity()) { return; }
            if ($expectedMutatedIdentity === null || $currentArtifact->identity() !== $expectedMutatedIdentity) { throw new LifecycleRecoveryException('Unexpected installed.lock drift.'); }
            $marker = $artifact->marker();
            $this->state->replaceMarker($marker['version'], $marker['installed_at']);
            $restored = $this->state->readMarker();
            if (!is_array($restored) || $this->codec->artifactFromMarker(['version' => $restored['version'], 'installed_at' => $restored['installed_at']])->identity() !== $artifact->identity()) { throw new LifecycleRecoveryException('Installed.lock verification failed.'); }
        } catch (LifecycleRecoveryException $exception) { throw $exception; } catch (\Throwable $exception) { throw new LifecycleRecoveryException('Installed-lock recovery restore failed.', 0, $exception); }
    }

    public function restoreFromStore(RecoveryIdentity $identity, RecoveryArtifactRecord $record, RecoveryArtifactStore $store, ?string $expectedMutatedIdentity = null): void
    {
        $this->restore($this->codec->decode($store->readArtifact($identity, $record)), $expectedMutatedIdentity);
    }
}
