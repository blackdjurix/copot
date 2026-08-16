<?php
namespace Copot\Core\BackupRecovery;

final class NormalWebcoreRecoveryCaptureService
{
    public function __construct(
        private RecoveryLifecycleCoordinator $coordinator,
        private RecoveryLifecycleStore $store,
        private RecoveryArtifactStore $artifacts,
        private FilesystemRecoveryDomain $filesystem,
        private LifecycleRecoveryDomain $lifecycle,
        private InstalledLockRecoveryDomain $installedLock,
        private DatabaseRecoveryProvider $database,
        private DatabaseQuiescenceCapability $quiescence,
        private $snapshotConnection,
        private $lockConnection
    ) {}

    public function capture(NormalWebcoreRecoveryCaptureRequest $request): NormalWebcoreRecoverySession
    {
        $recovery = new RecoveryIdentity('webcore-' . hash('sha256', $request->operationId() . ':' . $request->applyPlan()->identity()));
        $lease = null; $record = null;
        try {
            $lease = $this->quiescence->acquire();
            if (!$lease instanceof DatabaseQuiescenceLease || !$lease->isActive()) throw new RecoveryLifecycleException('Database quiescence is unavailable.');
            $filesystem = $this->filesystem->capture(FilesystemRecoveryPlan::fromApplyPlan($request->applyPlan()));
            $database = $this->database->capture(new DatabaseCaptureContext(($this->snapshotConnection)(), ($this->lockConnection)(), $request->databaseIdentity()));
            $lifecycle = $this->lifecycle->capture();
            $marker = $this->installedLock->capture();
            $manifest = new RecoveryManifest($recovery, $request->operationId(), $request->targetPackageIdentity(), $request->targetReleaseIdentity(), $request->archiveIdentity(), $request->applyPlan()->identity(), [
                new RecoveryDomainIdentity('database.webcore', 'database.webcore', $request->namespaceIdentity(), $database->record()->artifactIdentity()),
                new RecoveryDomainIdentity('filesystem.package-owned', 'filesystem.package-owned', $request->applyPlan()->identity(), $filesystem->artifact()->artifactIdentity()),
                new RecoveryDomainIdentity('filesystem.lifecycle.committed', 'filesystem.lifecycle.committed', $request->deploymentIdentity(), $lifecycle->record()->artifactIdentity()),
                new RecoveryDomainIdentity('filesystem.lifecycle.installed-lock', 'filesystem.lifecycle.installed-lock', $request->installationIdentity(), $marker->record()->artifactIdentity()),
            ], $request->preLifecycleIdentity(), $request->migrationLedgerIdentity());
            $record = new RecoveryLifecycleRecord($recovery, $manifest->identity(), $request->operationId(), RecoveryLifecycleState::CREATED);
            $this->coordinator->create($record);
            $this->coordinator->enterMaintenance($recovery);
            $this->coordinator->transition($recovery, RecoveryLifecycleState::CAPTURING);
            $this->artifacts->publish($manifest, [
                ['record'=>$database->record(), 'bytes'=>$database->bytes()], ['record'=>$filesystem->artifact(), 'bytes'=>$filesystem->artifactBytes()],
                ['record'=>$lifecycle->record(), 'bytes'=>$lifecycle->bytes()], ['record'=>$marker->record(), 'bytes'=>$marker->bytes()],
            ]);
            $this->artifacts->readManifest($recovery);
            $this->coordinator->transition($recovery, RecoveryLifecycleState::CAPTURED);
            $this->coordinator->recordCaptureComplete($recovery);
            $this->coordinator->transition($recovery, RecoveryLifecycleState::READY);
            $this->coordinator->confirm($recovery, $manifest->identity(), $manifest->identity());
            $ready = $this->store->read($recovery);
            if ($ready->state() !== RecoveryLifecycleState::READY || !$ready->captureComplete()) throw new RecoveryLifecycleException('Recovery capture did not reach READY.');
            return new NormalWebcoreRecoverySession($this->coordinator, $recovery, $manifest->identity(), $ready, $lease);
        } catch (\Throwable $exception) {
            if ($record instanceof RecoveryLifecycleRecord) { try { $this->coordinator->transition($recovery, RecoveryLifecycleState::FAILED_BEFORE_MUTATION, 'Recovery capture failed.'); } catch (\Throwable) {} }
            throw $exception;
        } finally {
            if (!isset($ready) || !isset($manifest)) {
                if ($lease instanceof DatabaseQuiescenceLease && $lease->isActive()) $lease->release();
                try { $this->coordinator->leaveMaintenance($recovery); } catch (\Throwable) {}
            }
        }
    }

    public function resume(NormalWebcoreRecoveryCaptureRequest $request, RecoveryIdentity $identity, string $manifest): NormalWebcoreRecoverySession
    {
        $lease = $this->quiescence->acquire();
        try {
            if (!$lease instanceof DatabaseQuiescenceLease || !$lease->isActive()) throw new RecoveryLifecycleException('Database quiescence is unavailable.');
            $record = $this->store->read($identity);
            if ($record->operationIdentity() !== $request->operationId() || $record->manifestIdentity() !== $manifest || $record->state() !== RecoveryLifecycleState::READY || !$record->captureComplete() || $record->mutationStarted()) throw new RecoveryLifecycleException('Persisted recovery evidence is not retry-eligible.');
            $this->coordinator->enterMaintenance($identity);
            return new NormalWebcoreRecoverySession($this->coordinator, $identity, $manifest, $record, $lease);
        } catch (\Throwable $e) { if ($lease instanceof DatabaseQuiescenceLease && $lease->isActive()) $lease->release(); throw $e; }
    }
}
