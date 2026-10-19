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
        $recovery = self::forOperation($request->operationId(), $request->applyPlan()->identity());
        $lease = null; $record = null; $hasScopedEvidence = $request->databaseScope() !== null
            || $request->databaseConsistencyEvidence() !== null
            || $request->applicationWriteProtectionEvidence() !== null;
        $scoped = $request->databaseScope() instanceof DatabaseScope
            && $request->databaseConsistencyEvidence() instanceof ScopedDatabaseConsistencyEvidence
            && $request->applicationWriteProtectionEvidence() instanceof ApplicationWriteProtectionEvidence;
        try {
            if ($hasScopedEvidence && !$scoped) throw new RecoveryLifecycleException('Scoped protection requires complete application and database evidence.');
            if ($scoped && $request->databaseScope()->identity() !== $request->databaseConsistencyEvidence()->scope()->identity()) throw new RecoveryLifecycleException('Scoped database request and consistency scope do not match.');
            if ($scoped) {
                $this->coordinator->enterScopedMaintenance($recovery, $request->applicationWriteProtectionEvidence(), $request->databaseConsistencyEvidence());
            } else {
                $lease = $this->quiescence->acquire();
                if (!$lease instanceof DatabaseQuiescenceLease || !$lease->isActive()) throw new RecoveryLifecycleException('Database quiescence is unavailable.');
            }
            $filesystem = $this->filesystem->capture($request->filesystemPlan() ?? FilesystemRecoveryPlan::fromApplyPlan($request->applyPlan()));
            $actionLinkage = $request->orderedV3Plan() === null ? null : FilesystemRecoveryActionLinkageBuilder::build($request->orderedV3Plan(), $filesystem->entries(), $request->operationId(), $recovery, $filesystem->artifact()->artifactIdentity());
            $database = $this->database->capture(new DatabaseCaptureContext(($this->snapshotConnection)(), ($this->lockConnection)(), $request->databaseIdentity(), 67108864, $request->databaseScope(), $request->databaseConsistencyEvidence()));
            $lifecycle = $this->lifecycle->capture();
            $marker = $this->installedLock->capture();
            $domains = [
                new RecoveryDomainIdentity('database.webcore', 'database.webcore', $request->databaseScope()?->identity() ?? $request->namespaceIdentity(), $database->record()->artifactIdentity()),
                new RecoveryDomainIdentity('filesystem.package-owned', 'filesystem.package-owned', $request->applyPlan()->identity(), $filesystem->artifact()->artifactIdentity()),
                new RecoveryDomainIdentity('filesystem.lifecycle.committed', 'filesystem.lifecycle.committed', $request->deploymentIdentity(), $lifecycle->record()->artifactIdentity()),
                new RecoveryDomainIdentity('filesystem.lifecycle.installed-lock', 'filesystem.lifecycle.installed-lock', $request->installationIdentity(), $marker->record()->artifactIdentity()),
            ];
            if ($actionLinkage !== null) $domains[] = new RecoveryDomainIdentity('filesystem.action-linkage', 'filesystem.action-linkage', $actionLinkage->applyPlanIdentity(), $actionLinkage->artifact()->artifactIdentity());
            $manifest = new RecoveryManifest($recovery, $request->operationId(), $request->targetPackageIdentity(), $request->targetReleaseIdentity(), $request->archiveIdentity(), $request->applyPlan()->identity(), $domains, $request->preLifecycleIdentity(), $request->migrationLedgerIdentity());
            $record = new RecoveryLifecycleRecord($recovery, $manifest->identity(), $request->operationId(), RecoveryLifecycleState::CREATED);
            $this->coordinator->create($record);
            if (!$scoped) { $this->coordinator->enterMaintenance($recovery); }
            $this->coordinator->transition($recovery, RecoveryLifecycleState::CAPTURING);
            $artifactRows = [
                ['record'=>$database->record(), 'bytes'=>$database->bytes()], ['record'=>$filesystem->artifact(), 'bytes'=>$filesystem->artifactBytes()],
                ['record'=>$lifecycle->record(), 'bytes'=>$lifecycle->bytes()], ['record'=>$marker->record(), 'bytes'=>$marker->bytes()],
            ];
            if ($actionLinkage !== null) $artifactRows[] = ['record' => $actionLinkage->artifact(), 'bytes' => $actionLinkage->artifactBytes()];
            $this->artifacts->publish($manifest, $artifactRows);
            $this->artifacts->readManifest($recovery);
            if ($actionLinkage !== null) {
                $linked = $this->artifacts->readArtifact($recovery, $actionLinkage->artifact());
                if ((new FilesystemRecoveryActionLinkageCodec())->decode($linked)->identity() !== $actionLinkage->identity()) throw new RecoveryLifecycleException('Persisted filesystem action linkage changed during reread.');
            }
            $this->coordinator->transition($recovery, RecoveryLifecycleState::CAPTURED);
            $this->coordinator->recordCaptureComplete($recovery);
            $this->coordinator->transition($recovery, RecoveryLifecycleState::READY);
            $this->coordinator->confirm($recovery, $manifest->identity(), $manifest->identity());
            $ready = $this->store->read($recovery);
            if ($ready->state() !== RecoveryLifecycleState::READY || !$ready->captureComplete()) throw new RecoveryLifecycleException('Recovery capture did not reach READY.');
            return new NormalWebcoreRecoverySession($this->coordinator, $recovery, $manifest->identity(), $ready, $lease, $scoped ? $request->databaseConsistencyEvidence() : null);
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

    public static function forOperation(string $operationIdentity, string $applyPlanIdentity): RecoveryIdentity
    {
        return new RecoveryIdentity('webcore-' . hash('sha256', $operationIdentity . ':' . $applyPlanIdentity));
    }

    public function resume(NormalWebcoreRecoveryCaptureRequest $request, RecoveryIdentity $identity, string $manifest): NormalWebcoreRecoverySession
    {
        $hasScopedEvidence = $request->databaseScope() !== null
            || $request->databaseConsistencyEvidence() !== null
            || $request->applicationWriteProtectionEvidence() !== null;
        $scoped = $request->databaseScope() instanceof DatabaseScope
            && $request->databaseConsistencyEvidence() instanceof ScopedDatabaseConsistencyEvidence
            && $request->applicationWriteProtectionEvidence() instanceof ApplicationWriteProtectionEvidence;
        $lease = null;
        try {
            if ($hasScopedEvidence && !$scoped) throw new RecoveryLifecycleException('Scoped protection requires complete application and database evidence.');
            if ($scoped && $request->databaseScope()->identity() !== $request->databaseConsistencyEvidence()->scope()->identity()) throw new RecoveryLifecycleException('Scoped database request and consistency scope do not match.');
            if (!$scoped) {
                $lease = $this->quiescence->acquire();
                if (!$lease instanceof DatabaseQuiescenceLease || !$lease->isActive()) throw new RecoveryLifecycleException('Database quiescence is unavailable.');
            }
            $record = $this->store->read($identity);
            if ($record->operationIdentity() !== $request->operationId() || $record->manifestIdentity() !== $manifest || $record->state() !== RecoveryLifecycleState::READY || !$record->captureComplete() || $record->mutationStarted()) throw new RecoveryLifecycleException('Persisted recovery evidence is not retry-eligible.');
            if ($scoped) { $this->coordinator->enterScopedMaintenance($identity, $request->applicationWriteProtectionEvidence(), $request->databaseConsistencyEvidence()); }
            else { $this->coordinator->enterMaintenance($identity); }
            return new NormalWebcoreRecoverySession($this->coordinator, $identity, $manifest, $record, $lease, $scoped ? $request->databaseConsistencyEvidence() : null);
        } catch (\Throwable $e) { if ($lease instanceof DatabaseQuiescenceLease && $lease->isActive()) $lease->release(); throw $e; }
    }
}
