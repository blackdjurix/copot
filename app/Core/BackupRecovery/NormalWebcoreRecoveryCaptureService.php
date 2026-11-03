<?php
namespace Copot\Core\BackupRecovery;

use Copot\Core\InstallationLock;

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
        private $lockConnection,
        private $faultInjector = null,
        private ?RecoveryOrchestrator $restore = null
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
            $filesystem = $request->metadataOnly() ? null : $this->filesystem->capture($request->filesystemPlan() ?? FilesystemRecoveryPlan::fromApplyPlan($request->applyPlan()));
            $actionLinkage = $request->metadataOnly() || $request->orderedV3Plan() === null ? null : FilesystemRecoveryActionLinkageBuilder::build($request->orderedV3Plan(), $filesystem->entries(), $request->operationId(), $recovery, $filesystem->artifact()->artifactIdentity());
            $database = $this->database->capture(new DatabaseCaptureContext(($this->snapshotConnection)(), ($this->lockConnection)(), $request->databaseIdentity(), 67108864, $request->databaseScope(), $request->databaseConsistencyEvidence()));
            $lifecycle = $this->lifecycle->capture();
            $marker = $this->installedLock->capture();
            $domains = [
                new RecoveryDomainIdentity('database.webcore', 'database.webcore', $request->databaseScope()?->identity() ?? $request->namespaceIdentity(), $database->record()->artifactIdentity()),
                new RecoveryDomainIdentity('filesystem.lifecycle.committed', 'filesystem.lifecycle.committed', $request->deploymentIdentity(), $lifecycle->record()->artifactIdentity()),
                new RecoveryDomainIdentity('filesystem.lifecycle.installed-lock', 'filesystem.lifecycle.installed-lock', $request->installationIdentity(), $marker->record()->artifactIdentity()),
            ];
            if ($filesystem !== null) $domains[] = new RecoveryDomainIdentity('filesystem.package-owned', 'filesystem.package-owned', $request->applyPlan()->identity(), $filesystem->artifact()->artifactIdentity());
            if ($actionLinkage !== null) $domains[] = new RecoveryDomainIdentity('filesystem.action-linkage', 'filesystem.action-linkage', $actionLinkage->applyPlanIdentity(), $actionLinkage->artifact()->artifactIdentity());
            $manifest = new RecoveryManifest($recovery, $request->operationId(), $request->targetPackageIdentity(), $request->targetReleaseIdentity(), $request->archiveIdentity(), $request->applyPlan()->identity(), $domains, $request->preLifecycleIdentity(), $request->migrationLedgerIdentity());
            $record = new RecoveryLifecycleRecord($recovery, $manifest->identity(), $request->operationId(), RecoveryLifecycleState::CREATED);
            $this->coordinator->create($record);
            if (!$scoped) { $this->coordinator->enterMaintenance($recovery); }
            $this->coordinator->transition($recovery, RecoveryLifecycleState::CAPTURING);
            $artifactRows = [
                ['record'=>$database->record(), 'bytes'=>$database->bytes()],
                ['record'=>$lifecycle->record(), 'bytes'=>$lifecycle->bytes()], ['record'=>$marker->record(), 'bytes'=>$marker->bytes()],
            ];
            if ($filesystem !== null) $artifactRows[] = ['record' => $filesystem->artifact(), 'bytes' => $filesystem->artifactBytes()];
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
            $restore = $this->restore === null ? null : fn (): RecoveryLifecycleRecord => $this->restorePackage($request, $recovery, $manifest->identity(), $this->coordinator->activeLifecycleLock());
            return new NormalWebcoreRecoverySession($this->coordinator, $recovery, $manifest->identity(), $ready, $lease, $scoped ? $request->databaseConsistencyEvidence() : null, $restore);
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

    /**
     * Capture only the recovery domains touched by committed metadata
     * reconciliation. This reuses the canonical coordinator, stores and
     * database provider without manufacturing a Webcore package apply plan.
     */
    public function captureMetadata(MetadataRecoveryCaptureRequest $request): NormalWebcoreRecoverySession
    {
        return $this->captureMetadataRun($request, false);
    }

    public function resumeMetadata(MetadataRecoveryCaptureRequest $request): NormalWebcoreRecoverySession
    {
        return $this->captureMetadataRun($request, true);
    }

    private function captureMetadataRun(MetadataRecoveryCaptureRequest $request, bool $resume): NormalWebcoreRecoverySession
    {
        $recovery = self::forOperation($request->operationId(), $request->recoveryPlanIdentity());
        $record = null;
        try {
            if (!$request->applicationWriteProtectionEvidence()->isValidAt()
                || !$request->databaseConsistencyEvidence()->isValidAt()
                || !$request->applicationWriteProtectionEvidence()->operationIdentity()->equals($recovery)
                || !$request->databaseConsistencyEvidence()->operationIdentity()->equals($recovery)
                || $request->databaseScope()->identity() !== $request->databaseConsistencyEvidence()->scope()->identity()) {
                throw new RecoveryLifecycleException('Metadata recovery protection evidence is stale or mismatched.');
            }
            $this->coordinator->enterScopedMaintenanceFresh($recovery, $request->applicationWriteProtectionEvidence(), $request->databaseConsistencyEvidence());
            $database = $this->database->capture(new DatabaseCaptureContext(
                ($this->snapshotConnection)(),
                ($this->lockConnection)(),
                $request->databaseIdentity(),
                67108864,
                $request->databaseScope(),
                $request->databaseConsistencyEvidence()
            ));
            $lifecycle = $this->lifecycle->capture();
            $marker = $this->installedLock->capture();
            $domains = [
                new RecoveryDomainIdentity('database.webcore', 'database.webcore', $request->databaseScope()->identity(), $database->record()->artifactIdentity()),
                new RecoveryDomainIdentity('filesystem.lifecycle.committed', 'filesystem.lifecycle.committed', $request->deploymentIdentity(), $lifecycle->record()->artifactIdentity()),
                new RecoveryDomainIdentity('filesystem.lifecycle.installed-lock', 'filesystem.lifecycle.installed-lock', $request->installationIdentity(), $marker->record()->artifactIdentity()),
            ];
            $manifest = new RecoveryManifest(
                $recovery,
                $request->operationId(),
                $request->targetPackageIdentity(),
                $request->targetReleaseIdentity(),
                $request->archiveIdentity(),
                $request->recoveryPlanIdentity(),
                $domains,
                $request->preLifecycleIdentity(),
                $request->migrationLedgerIdentity()
            );
            if ($resume) {
                $record = $this->store->read($recovery);
                if ($record->operationIdentity() !== $request->operationId()
                    || $record->manifestIdentity() !== $manifest->identity()
                    || $record->state() !== RecoveryLifecycleState::FAILED_BEFORE_MUTATION
                    || $record->mutationStarted()) {
                    throw new RecoveryLifecycleException('Metadata recovery evidence is not retry-eligible.');
                }
            } else {
                $record = new RecoveryLifecycleRecord($recovery, $manifest->identity(), $request->operationId(), RecoveryLifecycleState::CREATED);
                $this->coordinator->create($record);
            }
            $this->coordinator->transition($recovery, RecoveryLifecycleState::CAPTURING);
            $this->injectFault('after_recovery_record_persisted');
            $this->artifacts->publish($manifest, [
                ['record' => $database->record(), 'bytes' => $database->bytes()],
                ['record' => $lifecycle->record(), 'bytes' => $lifecycle->bytes()],
                ['record' => $marker->record(), 'bytes' => $marker->bytes()],
            ]);
            $this->artifacts->readManifest($recovery);
            $this->coordinator->transition($recovery, RecoveryLifecycleState::CAPTURED);
            $this->coordinator->recordCaptureComplete($recovery);
            $this->coordinator->transition($recovery, RecoveryLifecycleState::READY);
            $this->coordinator->confirm($recovery, $manifest->identity(), $manifest->identity());
            $ready = $this->store->read($recovery);
            if ($ready->state() !== RecoveryLifecycleState::READY || !$ready->captureComplete()) {
                throw new RecoveryLifecycleException('Metadata recovery capture did not reach READY.');
            }
            return new NormalWebcoreRecoverySession($this->coordinator, $recovery, $manifest->identity(), $ready, null, $request->databaseConsistencyEvidence());
        } catch (\Throwable $exception) {
            if ($record instanceof RecoveryLifecycleRecord) {
                try { $this->coordinator->transition($recovery, RecoveryLifecycleState::FAILED_BEFORE_MUTATION, 'Metadata recovery capture failed.'); } catch (\Throwable) {}
            }
            try { $this->coordinator->leaveMaintenance($recovery); } catch (\Throwable) {}
            throw $exception;
        }
    }

    private function injectFault(string $stage): void
    {
        if ($this->faultInjector !== null) {
            ($this->faultInjector)($stage);
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
            $restoreCallback = $this->restore === null ? null : fn (): RecoveryLifecycleRecord => $this->restorePackage($request, $identity, $manifest, $this->coordinator->activeLifecycleLock());
            return new NormalWebcoreRecoverySession($this->coordinator, $identity, $manifest, $record, $lease, $scoped ? $request->databaseConsistencyEvidence() : null, $restoreCallback);
        } catch (\Throwable $e) { if ($lease instanceof DatabaseQuiescenceLease && $lease->isActive()) $lease->release(); throw $e; }
    }

    private function restorePackage(NormalWebcoreRecoveryCaptureRequest $request, RecoveryIdentity $identity, string $manifestIdentity, ?InstallationLock $sessionLock = null): RecoveryLifecycleRecord
    {
        if (!$this->restore instanceof RecoveryOrchestrator || !$request->filesystemPlan() instanceof FilesystemRecoveryPlan || !$request->orderedV3Plan() instanceof \Copot\Core\V3SourceRelativePlan) {
            throw new RecoveryLifecycleException('V3 automatic recovery composition is unavailable.');
        }
        $decoded = $this->artifacts->readManifest($identity);
        $manifest = $decoded['manifest'] ?? null;
        $artifactRecords = $decoded['artifacts'] ?? null;
        if (!$manifest instanceof RecoveryManifest || !is_array($artifactRecords)
            || $manifest->identity() !== $manifestIdentity
            || !$manifest->recoveryIdentity()->equals($identity)
            || $manifest->operationIdentity() !== $request->operationId()
            || $manifest->targetPackageIdentity() !== $request->targetPackageIdentity()
            || $manifest->targetReleaseIdentity() !== $request->targetReleaseIdentity()
            || $manifest->archiveIdentity() !== $request->archiveIdentity()
            || $manifest->applyPlanIdentity() !== $request->applyPlan()->identity()) {
            throw new RecoveryLifecycleException('V3 recovery manifest binding is invalid.');
        }
        $records = [];
        foreach ($artifactRecords as $record) {
            if ($record instanceof RecoveryArtifactRecord) $records[$record->domainIdentifier()] = $record;
        }
        foreach (['database.webcore', 'filesystem.lifecycle.committed', 'filesystem.lifecycle.installed-lock', 'filesystem.package-owned'] as $domain) {
            if (!isset($records[$domain])) throw new RecoveryLifecycleException('V3 recovery manifest is missing a required restore domain.');
        }
        $linkage = $records['filesystem.action-linkage'] ?? null;
        $this->assertActionLinkage($identity, $manifest, $linkage, $request);
        $databaseRecord = $records['database.webcore'];
        $filesystemRecord = $records['filesystem.package-owned'];
        $lifecycleRecord = $records['filesystem.lifecycle.committed'];
        $markerRecord = $records['filesystem.lifecycle.installed-lock'];
        $restoreDatabase = function () use ($request, $identity, $databaseRecord): void {
            $current = $this->database->capture(new DatabaseCaptureContext(($this->snapshotConnection)(), ($this->lockConnection)(), $request->databaseIdentity(), 67108864, $request->databaseScope(), $request->databaseConsistencyEvidence()));
            if ($current->record()->artifactIdentity() !== $databaseRecord->artifactIdentity()) {
                throw new RecoveryLifecycleException('V3 File Cycle restore detected unexpected database mutation.');
            }
        };
        $restoreFilesystem = function () use ($identity, $filesystemRecord, $request): void {
            $result = $this->filesystem->restore($identity, $filesystemRecord, $request->filesystemPlan());
            if ($result->status() !== FilesystemRecoveryResult::COMPLETED) throw new RecoveryLifecycleException($result->reason() !== '' ? $result->reason() : 'V3 filesystem restore failed.');
        };
        $restoreLifecycle = function () use ($identity, $lifecycleRecord, $request): void {
            $current = $this->lifecycle->capture();
            if ($current->identity() === $lifecycleRecord->artifactIdentity()) return;
            $state = $current->state();
            $plan = $request->orderedV3Plan();
            if (!$state instanceof \Copot\Core\CommittedLifecycleState
                || $state->releaseIdentity() !== $request->targetReleaseIdentity()
                || $state->sourceTreeIdentity() !== $plan->package()->sourceTreeIdentity()
                || $state->manifestContractVersion() !== 3
                || $state->sourceInventoryReference()?->fingerprint() !== $plan->targetFingerprint()) {
                throw new RecoveryLifecycleException('V3 committed lifecycle state drift is not bound to the target package.');
            }
            $this->lifecycle->restoreFromStore($identity, $lifecycleRecord, $this->artifacts, $current->identity());
        };
        $restoreMarker = function () use ($identity, $markerRecord, $request): void {
            $current = $this->installedLock->capture();
            if ($current->identity() === $markerRecord->artifactIdentity()) return;
            $targetVersion = $request->orderedV3Plan()->package()->targetWebcoreVersion();
            if ($current->marker()['version'] !== $targetVersion) throw new RecoveryLifecycleException('V3 installed marker drift is not bound to the target package.');
            $this->installedLock->restoreFromStore($identity, $markerRecord, $this->artifacts, $current->identity());
        };
        $verify = function () use ($request, $identity, $databaseRecord, $lifecycleRecord, $markerRecord): void {
            $currentDatabase = $this->database->capture(new DatabaseCaptureContext(($this->snapshotConnection)(), ($this->lockConnection)(), $request->databaseIdentity(), 67108864, $request->databaseScope(), $request->databaseConsistencyEvidence()));
            if ($currentDatabase->record()->artifactIdentity() !== $databaseRecord->artifactIdentity()) throw new RecoveryLifecycleException('V3 restored database identity does not match the captured pre-image.');
            if ($this->lifecycle->capture()->identity() !== $lifecycleRecord->artifactIdentity()) throw new RecoveryLifecycleException('V3 restored committed lifecycle identity does not match the captured pre-image.');
            if ($this->installedLock->capture()->identity() !== $markerRecord->artifactIdentity()) throw new RecoveryLifecycleException('V3 restored installed marker identity does not match the captured pre-image.');
        };
        return $this->restore->restore($identity, $restoreDatabase, $restoreFilesystem, $restoreLifecycle, $restoreMarker, $verify, null, null, $sessionLock);
    }

    private function assertActionLinkage(RecoveryIdentity $identity, RecoveryManifest $manifest, ?RecoveryArtifactRecord $record, NormalWebcoreRecoveryCaptureRequest $request): void
    {
        if (!$record instanceof RecoveryArtifactRecord || $request->orderedV3Plan() === null) throw new RecoveryLifecycleException('V3 recovery action linkage is unavailable.');
        $linkage = (new FilesystemRecoveryActionLinkageCodec())->decode($this->artifacts->readArtifact($identity, $record));
        if ($linkage->operationIdentity() !== $request->operationId()
            || $linkage->applyPlanIdentity() !== $request->applyPlan()->identity()
            || !$linkage->recoveryIdentity()->equals($identity)) {
            throw new RecoveryLifecycleException('V3 recovery action linkage binding is invalid.');
        }
    }
}
