<?php
namespace Copot\Core\BackupRecovery;

use Copot\Core\CommittedLifecycleState;

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

    public static function forOperation(string $operationId, string $applyPlanIdentity, ?string $attempt = null): RecoveryIdentity
    {
        return new RecoveryIdentity('webcore-' . hash('sha256', implode(':', array_filter([$operationId, $applyPlanIdentity, $attempt], static fn (?string $part): bool => $part !== null))));
    }

    public function capture(NormalWebcoreRecoveryCaptureRequest $request): NormalWebcoreRecoverySession
    {
        $recovery = self::forOperation($request->operationId(), $request->applyPlan()->identity(), $request->recoveryAttemptIdentity());
        $filesystemOnly = $request->filesystemOnly();
        $lease = null;
        $record = null;
        $scoped = $request->databaseScope() instanceof DatabaseScope
            && $request->databaseConsistencyEvidence() instanceof ScopedDatabaseConsistencyEvidence
            && $request->applicationWriteProtectionEvidence() instanceof ApplicationWriteProtectionEvidence;
        try {
            if ($filesystemOnly) {
                $this->assertFilesystemOnlyRequest($request);
                $this->coordinator->enterFilesystemOnlyMaintenance($recovery, $request->applicationWriteProtectionEvidence());
            } else {
                $hasScopedEvidence = $request->databaseScope() !== null
                    || $request->databaseConsistencyEvidence() !== null
                    || $request->applicationWriteProtectionEvidence() !== null;
                if ($hasScopedEvidence && !$scoped) throw new RecoveryLifecycleException('Scoped protection requires complete application and database evidence.');
                if ($scoped && $request->databaseScope()->identity() !== $request->databaseConsistencyEvidence()->scope()->identity()) throw new RecoveryLifecycleException('Scoped database request and consistency scope do not match.');
                if ($scoped) {
                    $this->coordinator->enterScopedMaintenance($recovery, $request->applicationWriteProtectionEvidence(), $request->databaseConsistencyEvidence());
                } else {
                    $lease = $this->quiescence->acquire();
                    if (!$lease instanceof DatabaseQuiescenceLease || !$lease->isActive()) throw new RecoveryLifecycleException('Database quiescence is unavailable.');
                }
            }

            $filesystemPlan = $request->filesystemPlan() ?? FilesystemRecoveryPlan::fromApplyPlan($request->applyPlan());
            $filesystem = $this->filesystem->capture($filesystemPlan);
            $actionLinkage = $request->orderedV3Plan() === null
                ? null
                : FilesystemRecoveryActionLinkageBuilder::build($request->orderedV3Plan(), $filesystem->entries(), $request->operationId(), $recovery, $filesystem->artifact()->artifactIdentity(), $request->applyPlan()->identity());
            $database = null;
            if (!$filesystemOnly) {
                $database = $this->database->capture(new DatabaseCaptureContext(($this->snapshotConnection)(), ($this->lockConnection)(), $request->databaseIdentity(), 67108864, $request->databaseScope(), $request->databaseConsistencyEvidence()));
            }
            $lifecycle = $this->lifecycle->capture();
            $marker = $this->installedLock->capture();
            $domains = [];
            if ($database instanceof DatabaseRecoveryArtifact) {
                $domains[] = new RecoveryDomainIdentity('database.webcore', 'database.webcore', $request->databaseScope()?->identity() ?? $request->namespaceIdentity(), $database->record()->artifactIdentity());
            }
            $domains[] = new RecoveryDomainIdentity('filesystem.package-owned', 'filesystem.package-owned', $request->applyPlan()->identity(), $filesystem->artifact()->artifactIdentity());
            $domains[] = new RecoveryDomainIdentity('filesystem.lifecycle.committed', 'filesystem.lifecycle.committed', $request->deploymentIdentity(), $lifecycle->record()->artifactIdentity());
            $domains[] = new RecoveryDomainIdentity('filesystem.lifecycle.installed-lock', 'filesystem.lifecycle.installed-lock', $request->installationIdentity(), $marker->record()->artifactIdentity());
            if ($actionLinkage !== null) $domains[] = new RecoveryDomainIdentity('filesystem.action-linkage', 'filesystem.action-linkage', $actionLinkage->applyPlanIdentity(), $actionLinkage->artifact()->artifactIdentity());
            $manifest = new RecoveryManifest($recovery, $request->operationId(), $request->targetPackageIdentity(), $request->targetReleaseIdentity(), $request->archiveIdentity(), $request->applyPlan()->identity(), $domains, $request->preLifecycleIdentity(), $request->migrationLedgerIdentity());
            $record = new RecoveryLifecycleRecord($recovery, $manifest->identity(), $request->operationId(), RecoveryLifecycleState::CREATED);
            $this->coordinator->create($record);
            if (!$scoped && !$filesystemOnly) $this->coordinator->enterMaintenance($recovery);
            $this->coordinator->transition($recovery, RecoveryLifecycleState::CAPTURING);
            $artifactRows = [
                ['record' => $filesystem->artifact(), 'bytes' => $filesystem->artifactBytes()],
                ['record' => $lifecycle->record(), 'bytes' => $lifecycle->bytes()],
                ['record' => $marker->record(), 'bytes' => $marker->bytes()],
            ];
            if ($database instanceof DatabaseRecoveryArtifact) array_unshift($artifactRows, ['record' => $database->record(), 'bytes' => $database->bytes()]);
            if ($actionLinkage !== null) $artifactRows[] = ['record' => $actionLinkage->artifact(), 'bytes' => $actionLinkage->artifactBytes()];
            $this->artifacts->publish($manifest, $artifactRows);
            $this->artifacts->readManifest($recovery);
            if ($actionLinkage !== null) {
                $persisted = (new FilesystemRecoveryActionLinkageCodec())->decode($this->artifacts->readArtifact($recovery, $actionLinkage->artifact()));
                if ($persisted->identity() !== $actionLinkage->identity()) throw new RecoveryLifecycleException('Persisted filesystem action linkage changed during reread.');
            }
            $this->coordinator->transition($recovery, RecoveryLifecycleState::CAPTURED);
            $this->coordinator->recordCaptureComplete($recovery);
            $this->coordinator->transition($recovery, RecoveryLifecycleState::READY);
            $this->coordinator->confirm($recovery, $manifest->identity(), $manifest->identity());
            $ready = $this->store->read($recovery);
            if ($ready->state() !== RecoveryLifecycleState::READY || !$ready->captureComplete()) throw new RecoveryLifecycleException('Recovery capture did not reach READY.');
            return $this->session($request, $recovery, $manifest->identity(), $ready, $lease, $filesystemOnly, $request->applicationWriteProtectionEvidence());
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
        $filesystemOnly = $request->filesystemOnly();
        $lease = null;
        try {
            if ($filesystemOnly) {
                $this->assertFilesystemOnlyRequest($request);
            } else {
                $hasScopedEvidence = $request->databaseScope() !== null || $request->databaseConsistencyEvidence() !== null || $request->applicationWriteProtectionEvidence() !== null;
                $scoped = $request->databaseScope() instanceof DatabaseScope && $request->databaseConsistencyEvidence() instanceof ScopedDatabaseConsistencyEvidence && $request->applicationWriteProtectionEvidence() instanceof ApplicationWriteProtectionEvidence;
                if ($hasScopedEvidence && !$scoped) throw new RecoveryLifecycleException('Scoped protection requires complete application and database evidence.');
                if (!$scoped) {
                    $lease = $this->quiescence->acquire();
                    if (!$lease instanceof DatabaseQuiescenceLease || !$lease->isActive()) throw new RecoveryLifecycleException('Database quiescence is unavailable.');
                }
            }
            $record = $this->store->read($identity);
            if ($record->operationIdentity() !== $request->operationId() || $record->manifestIdentity() !== $manifest || $record->state() !== RecoveryLifecycleState::READY || !$record->captureComplete() || (!$filesystemOnly && $record->mutationStarted())) throw new RecoveryLifecycleException('Persisted recovery evidence is not retry-eligible.');
            if ($filesystemOnly) $this->coordinator->resumeFilesystemOnlyMaintenance($identity, $request->applicationWriteProtectionEvidence());
            elseif ($request->databaseScope() instanceof DatabaseScope && $request->databaseConsistencyEvidence() instanceof ScopedDatabaseConsistencyEvidence) $this->coordinator->enterScopedMaintenance($identity, $request->applicationWriteProtectionEvidence(), $request->databaseConsistencyEvidence());
            else $this->coordinator->enterMaintenance($identity);
            return $this->session($request, $identity, $manifest, $record, $lease, $filesystemOnly, $request->applicationWriteProtectionEvidence());
        } catch (\Throwable $e) {
            if ($lease instanceof DatabaseQuiescenceLease && $lease->isActive()) $lease->release();
            throw $e;
        }
    }

    private function session(NormalWebcoreRecoveryCaptureRequest $request, RecoveryIdentity $identity, string $manifest, RecoveryLifecycleRecord $record, ?DatabaseQuiescenceLease $lease, bool $filesystemOnly, ?ApplicationWriteProtectionEvidence $application): NormalWebcoreRecoverySession
    {
        $restore = $filesystemOnly ? fn () => $this->restoreFilesystemCycle($request, $identity, $manifest) : null;
        $verify = $filesystemOnly ? fn () => $this->verifyFilesystemCycle($request, $identity, $manifest) : null;
        return new NormalWebcoreRecoverySession($this->coordinator, $identity, $manifest, $record, $lease, $filesystemOnly ? null : $request->databaseConsistencyEvidence(), $application, $filesystemOnly, $restore, $verify);
    }

    private function assertFilesystemOnlyRequest(NormalWebcoreRecoveryCaptureRequest $request): void
    {
        if (!$request->applicationWriteProtectionEvidence() instanceof ApplicationWriteProtectionEvidence
            || !$request->applicationWriteProtectionEvidence()->isValidAt()
            || $request->databaseScope() !== null
            || $request->databaseConsistencyEvidence() !== null) {
            throw new RecoveryLifecycleException('Filesystem-only recovery request is incomplete or contains database protection evidence.');
        }
    }

    private function restoreFilesystemCycle(NormalWebcoreRecoveryCaptureRequest $request, RecoveryIdentity $identity, string $manifestIdentity): void
    {
        $decoded = $this->artifacts->readManifest($identity);
        $manifest = $decoded['manifest'] ?? null;
        $artifactRecords = $decoded['artifacts'] ?? null;
        if (!$manifest instanceof RecoveryManifest || !is_array($artifactRecords) || $manifest->identity() !== $manifestIdentity || !$manifest->recoveryIdentity()->equals($identity) || $manifest->operationIdentity() !== $request->operationId() || $manifest->targetPackageIdentity() !== $request->targetPackageIdentity() || $manifest->targetReleaseIdentity() !== $request->targetReleaseIdentity() || $manifest->archiveIdentity() !== $request->archiveIdentity() || $manifest->applyPlanIdentity() !== $request->applyPlan()->identity()) throw new RecoveryLifecycleException('Filesystem-only recovery manifest binding is invalid.');
        $records = [];
        foreach ($artifactRecords as $record) if ($record instanceof RecoveryArtifactRecord) $records[$record->domainIdentifier()] = $record;
        foreach (['filesystem.package-owned', 'filesystem.lifecycle.committed', 'filesystem.lifecycle.installed-lock', 'filesystem.action-linkage'] as $domain) if (!isset($records[$domain])) throw new RecoveryLifecycleException('Filesystem-only recovery manifest is missing a required restore domain.');
        if (isset($records['database.webcore'])) throw new RecoveryLifecycleException('Filesystem-only recovery manifest contains a database domain.');
        $linkage = (new FilesystemRecoveryActionLinkageCodec())->decode($this->artifacts->readArtifact($identity, $records['filesystem.action-linkage']));
        if ($linkage->operationIdentity() !== $request->operationId() || $linkage->applyPlanIdentity() !== $request->applyPlan()->identity() || !$linkage->recoveryIdentity()->equals($identity)) throw new RecoveryLifecycleException('Filesystem action linkage binding is invalid.');
        $plan = $request->filesystemPlan();
        if (!$plan instanceof FilesystemRecoveryPlan) throw new RecoveryLifecycleException('Filesystem recovery plan is unavailable.');
        $filesystem = $this->filesystem->restore($identity, $records['filesystem.package-owned'], $plan);
        if ($filesystem->status() !== FilesystemRecoveryResult::COMPLETED) throw new RecoveryLifecycleException($filesystem->reason() !== '' ? $filesystem->reason() : 'Filesystem recovery failed.');
        $currentLifecycle = $this->lifecycle->capture();
        if ($currentLifecycle->identity() !== $records['filesystem.lifecycle.committed']->artifactIdentity()) $this->lifecycle->restoreFromStore($identity, $records['filesystem.lifecycle.committed'], $this->artifacts, $currentLifecycle->identity());
        $currentMarker = $this->installedLock->capture();
        if ($currentMarker->identity() !== $records['filesystem.lifecycle.installed-lock']->artifactIdentity()) $this->installedLock->restoreFromStore($identity, $records['filesystem.lifecycle.installed-lock'], $this->artifacts, $currentMarker->identity());
    }

    private function verifyFilesystemCycle(NormalWebcoreRecoveryCaptureRequest $request, RecoveryIdentity $identity, string $manifestIdentity): void
    {
        $decoded = $this->artifacts->readManifest($identity);
        $records = [];
        foreach (($decoded['artifacts'] ?? []) as $record) if ($record instanceof RecoveryArtifactRecord) $records[$record->domainIdentifier()] = $record;
        $plan = $request->filesystemPlan();
        if (!$plan instanceof FilesystemRecoveryPlan || !isset($records['filesystem.package-owned'], $records['filesystem.lifecycle.committed'], $records['filesystem.lifecycle.installed-lock'])) throw new RecoveryLifecycleException('Filesystem recovery verification evidence is unavailable.');
        if ($this->filesystem->capture($plan)->artifact()->artifactIdentity() !== $records['filesystem.package-owned']->artifactIdentity() || $this->lifecycle->capture()->identity() !== $records['filesystem.lifecycle.committed']->artifactIdentity() || $this->installedLock->capture()->identity() !== $records['filesystem.lifecycle.installed-lock']->artifactIdentity()) throw new RecoveryLifecycleException('Filesystem-only restored state does not match the original-state artifacts.');
        if (!isset($decoded['manifest']) || !$decoded['manifest'] instanceof RecoveryManifest || $decoded['manifest']->identity() !== $manifestIdentity) throw new RecoveryLifecycleException('Filesystem-only recovery manifest changed during restore verification.');
    }
}
