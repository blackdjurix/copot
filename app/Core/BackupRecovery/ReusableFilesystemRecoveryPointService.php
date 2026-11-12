<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\CanonicalSourceInventoryBuilder;
use Copot\Core\CanonicalTargetInventory;
use Copot\Core\CanonicalTargetInventoryEntry;
use Copot\Core\CommittedLifecycleState;
use Copot\Core\SourceInventoryArtifactStore;

/**
 * Bounded filesystem-only reusable recovery-point capability.
 *
 * Operation-bound recovery remains owned by NormalWebcoreRecoveryCaptureService
 * and RecoveryLifecycleCoordinator. This service only adds immutable committed
 * points and their durable restore-attempt lineage.
 */
final class ReusableFilesystemRecoveryPointService
{
    private const FILESYSTEM_DOMAIN = 'filesystem.package-owned';
    private const LIFECYCLE_DOMAIN = 'filesystem.lifecycle.committed';
    private const INSTALLED_LOCK_DOMAIN = 'filesystem.lifecycle.installed-lock';
    private const SOURCE_INVENTORY_DOMAIN = 'filesystem.source-inventory.committed';

    public function __construct(
        private RecoveryStorageRoot $root,
        private RecoveryArtifactStore $artifacts,
        private InstallationScopedMaintenanceGuard $maintenance,
        private FilesystemRecoveryDomain $filesystem,
        private LifecycleRecoveryDomain $lifecycle,
        private InstalledLockRecoveryDomain $installedLock,
        private SourceInventoryArtifactStore $sourceInventories,
        private CanonicalSourceInventoryBuilder $inventoryBuilder,
        private ?ReusableRecoveryPointAttemptStore $attempts = null,
        private mixed $stageObserver = null
    ) {
        $this->attempts ??= new ReusableRecoveryPointAttemptStore($root);
    }

    public function capture(ReusableRecoveryPointCaptureRequest $request): ReusableRecoveryPointResult
    {
        $admission = new RecoveryIdentity('reusable-point-capture-' . hash('sha256', $request->installationIdentity() . ':' . $request->creationIdentity()));
        $evidence = $this->maintenance->produceFilesystemEvidence($admission);
        if (!$this->maintenance->enterScoped($admission, $evidence)) throw new RecoveryLifecycleException('Reusable recovery-point maintenance admission could not be established.');
        try {
            $source = $this->sourceInventories->read($request->sourceInventoryReference());
            $actualInventory = $this->inventoryBuilder->capture();
            if ($actualInventory->fingerprint() !== $request->sourceInventoryReference()->fingerprint() || $source->inventory()->fingerprint() !== $actualInventory->fingerprint()) throw new RecoveryLifecycleException('Committed source inventory changed before reusable recovery-point capture.');

            $lifecycle = $this->lifecycle->capture();
            $state = $lifecycle->state();
            if (!$state instanceof CommittedLifecycleState || $state->sourceInventoryReference()?->toArray() !== $request->sourceInventoryReference()->toArray()) throw new RecoveryLifecycleException('Committed lifecycle state is not linked to the requested source inventory.');
            $marker = $this->installedLock->capture();
            $markerValues = $marker->marker();
            if ($markerValues['version'] !== $state->webcoreVersion() || $markerValues['installed_at'] !== $state->committedAt()->format(\DATE_ATOM)) throw new RecoveryLifecycleException('Committed lifecycle and installed.lock state are inconsistent.');

            $filesystem = $this->filesystem->capture($this->planFromInventory($actualInventory));
            $this->assertFilesystemCaptureMatchesInventory($filesystem, $actualInventory);
            $sourceBytes = $this->sourceInventoryBytes($request->sourceInventoryReference());
            $sourceRecord = new RecoveryArtifactRecord(self::SOURCE_INVENTORY_DOMAIN, hash('sha256', $sourceBytes), strlen($sourceBytes));

            $domains = [
                new RecoveryDomainIdentity(self::FILESYSTEM_DOMAIN, self::FILESYSTEM_DOMAIN, $actualInventory->fingerprint(), $filesystem->artifact()->artifactIdentity()),
                new RecoveryDomainIdentity(self::LIFECYCLE_DOMAIN, self::LIFECYCLE_DOMAIN, $state->releaseIdentity(), $lifecycle->identity()),
                new RecoveryDomainIdentity(self::INSTALLED_LOCK_DOMAIN, self::INSTALLED_LOCK_DOMAIN, $request->installationIdentity(), $marker->identity()),
                new RecoveryDomainIdentity(self::SOURCE_INVENTORY_DOMAIN, self::SOURCE_INVENTORY_DOMAIN, $request->sourceInventoryReference()->artifactIdentity(), $sourceRecord->artifactIdentity()),
            ];
            $pointIdentity = ReusableRecoveryPointIdentity::forCapture($this->root->projectIdentity(), $request->installationIdentity(), $request->originatingOperationIdentity(), $request->creationIdentity(), $request->createdAt(), $domains);
            $manifest = new ReusableRecoveryPointManifest($pointIdentity, $this->root->projectIdentity(), $request->installationIdentity(), $request->originatingOperationIdentity(), $request->creationIdentity(), $request->createdAt(), $request->sourceInventoryReference(), $lifecycle->identity(), $marker->identity(), $domains, [$filesystem->artifact(), $lifecycle->record(), $marker->record(), $sourceRecord]);
            $this->artifacts->publishReusablePoint($manifest, [
                ['record' => $filesystem->artifact(), 'bytes' => $filesystem->artifactBytes()],
                ['record' => $lifecycle->record(), 'bytes' => $lifecycle->bytes()],
                ['record' => $marker->record(), 'bytes' => $marker->bytes()],
                ['record' => $sourceRecord, 'bytes' => $sourceBytes],
            ]);
            $verified = $this->artifacts->readReusablePointManifest($pointIdentity);
            if ($verified['identity'] !== $manifest->identity()) throw new RecoveryLifecycleException('Reusable recovery-point manifest failed read-back verification.');
            return new ReusableRecoveryPointResult(ReusableRecoveryPointResult::CAPTURED, $pointIdentity, $manifest->identity());
        } finally {
            if (!$this->maintenance->leave($admission)) throw new RecoveryLifecycleException('Reusable recovery-point maintenance admission could not be released.');
        }
    }

    public function restore(ReusableRecoveryPointRestoreRequest $request): ReusableRecoveryPointResult
    {
        $loaded = $this->artifacts->readReusablePointManifest($request->pointIdentity());
        $manifest = $loaded['manifest'];
        if (!$manifest instanceof ReusableRecoveryPointManifest || $manifest->projectIdentity() !== $this->root->projectIdentity() || $manifest->installationIdentity() !== $request->installationIdentity()) throw new RecoveryLifecycleException('Reusable recovery-point installation or project binding is invalid.');
        if ($request->authorization()->capabilityReference() === '') throw new RecoveryLifecycleException('Reusable recovery-point restore authorization is unavailable.');
        $this->assertFilesystemOnlyManifest($manifest);
        $targetSource = $this->sourceInventories->read($manifest->sourceInventoryReference());
        $expectedCurrentSource = $this->sourceInventories->read($request->expectedCurrentSourceInventory());

        $attempt = $this->attempts->activeForInstallation($request->installationIdentity());
        $continued = $attempt instanceof ReusableRecoveryPointAttemptRecord;
        if ($attempt instanceof ReusableRecoveryPointAttemptRecord && $attempt->state() === ReusableRecoveryPointAttemptRecord::RESTORED) {
            $this->attempts->clearActive($attempt);
            $attempt = null;
            $continued = false;
        }
        if ($attempt instanceof ReusableRecoveryPointAttemptRecord) {
            if (!$attempt->pointIdentity()->equals($request->pointIdentity()) || ($request->attemptIdentity() !== null && !$attempt->attemptIdentity()->equals($request->attemptIdentity()))) throw new RecoveryLifecycleException('Another unresolved reusable recovery-point attempt is active.');
            if ($attempt->expectedCurrentSourceInventory()->toArray() !== $request->expectedCurrentSourceInventory()->toArray()) throw new RecoveryLifecycleException('Reusable recovery-point continuation precondition changed.');
        } else {
            if ($request->attemptIdentity() !== null) throw new RecoveryLifecycleException('Requested reusable recovery-point attempt is not the active durable attempt.');
            $actualCurrent = $this->inventoryBuilder->capture();
            if ($actualCurrent->fingerprint() !== $request->expectedCurrentSourceInventory()->fingerprint() || $expectedCurrentSource->inventory()->fingerprint() !== $actualCurrent->fingerprint()) throw new RecoveryLifecycleException('Current source inventory does not match the explicit restore precondition.');
            $currentLifecycle = $this->lifecycle->capture();
            $currentState = $currentLifecycle->state();
            if (!$currentState instanceof CommittedLifecycleState || $currentState->sourceInventoryReference()?->toArray() !== $request->expectedCurrentSourceInventory()->toArray()) throw new RecoveryLifecycleException('Current committed lifecycle state does not match the explicit restore precondition.');
            $currentMarker = $this->installedLock->capture();
            $attemptIdentity = ReusableRecoveryPointAttemptIdentity::forInvocation($request->pointIdentity(), $request->invocationIdentity(), $request->createdAt());
            $attempt = new ReusableRecoveryPointAttemptRecord($request->pointIdentity(), $attemptIdentity, $request->installationIdentity(), ReusableRecoveryPointAttemptRecord::CREATED, 'CREATED', $request->expectedCurrentSourceInventory(), $currentLifecycle->identity(), $currentMarker->identity(), $request->createdAt(), $request->createdAt());
            $this->attempts->create($attempt);
        }

        $guardIdentity = new RecoveryIdentity('reusable-point-restore-' . hash('sha256', $attempt->attemptIdentity()->value()));
        if ($continued && $this->maintenance->durableScopedActive($guardIdentity)) {
            $this->maintenance->revalidateFilesystemEvidence($guardIdentity);
            if (!$this->maintenance->enter($guardIdentity)) throw new RecoveryLifecycleException('Reusable recovery-point restore maintenance could not resume its durable admission.');
        } else {
            $evidence = $this->maintenance->produceFilesystemEvidence($guardIdentity);
            if (!$this->maintenance->enterScoped($guardIdentity, $evidence)) throw new RecoveryLifecycleException('Reusable recovery-point restore maintenance admission could not be established.');
        }
        $success = false;
        try {
            $filesystemArtifact = $manifest->artifact(self::FILESYSTEM_DOMAIN);
            $lifecycleArtifact = $manifest->artifact(self::LIFECYCLE_DOMAIN);
            $markerArtifact = $manifest->artifact(self::INSTALLED_LOCK_DOMAIN);
            if (!$filesystemArtifact instanceof RecoveryArtifactRecord || !$lifecycleArtifact instanceof RecoveryArtifactRecord || !$markerArtifact instanceof RecoveryArtifactRecord) throw new RecoveryLifecycleException('Reusable recovery-point restore domain set is incomplete.');

            if ($this->stageAtOrBefore($attempt->stage(), 'FILESYSTEM')) {
                $attempt = $this->progress($attempt, ReusableRecoveryPointAttemptRecord::RESTORING, 'FILESYSTEM');
                $result = $this->filesystem->restoreReusablePointArtifactBytes($this->artifacts->readReusablePointArtifact($request->pointIdentity(), $filesystemArtifact), $expectedCurrentSource->inventory());
                if ($result->status() !== FilesystemRecoveryResult::COMPLETED) throw new RecoveryLifecycleException($result->reason() !== '' ? $result->reason() : 'Reusable recovery-point filesystem restore failed.');
                $attempt = $this->progress($attempt, ReusableRecoveryPointAttemptRecord::RESTORING, 'SOURCE_INVENTORY');
            }
            if ($this->stageAtOrBefore($attempt->stage(), 'SOURCE_INVENTORY')) $attempt = $this->progress($attempt, ReusableRecoveryPointAttemptRecord::RESTORING, 'LIFECYCLE');
            if ($this->stageAtOrBefore($attempt->stage(), 'LIFECYCLE')) {
                $current = $this->lifecycle->capture();
                $lifecycleBytes = $this->artifacts->readReusablePointArtifact($request->pointIdentity(), $lifecycleArtifact);
                if ($current->identity() !== $manifest->capturedLifecycleIdentity()) $this->lifecycle->restore((new LifecycleRecoveryArtifactCodec())->decode($lifecycleBytes), $attempt->expectedCurrentLifecycleIdentity());
                if ($this->lifecycle->capture()->identity() !== $this->expectedArtifactIdentity($manifest, self::LIFECYCLE_DOMAIN)) throw new RecoveryLifecycleException('Reusable recovery-point committed lifecycle verification failed.');
                $attempt = $this->progress($attempt, ReusableRecoveryPointAttemptRecord::RESTORING, 'INSTALLED_LOCK');
            }
            if ($this->stageAtOrBefore($attempt->stage(), 'INSTALLED_LOCK')) {
                $current = $this->installedLock->capture();
                $markerBytes = $this->artifacts->readReusablePointArtifact($request->pointIdentity(), $markerArtifact);
                if ($current->identity() !== $manifest->capturedInstalledLockIdentity()) $this->installedLock->restore((new InstalledLockRecoveryArtifactCodec())->decode($markerBytes), $attempt->expectedCurrentInstalledLockIdentity());
                if ($this->installedLock->capture()->identity() !== $this->expectedArtifactIdentity($manifest, self::INSTALLED_LOCK_DOMAIN)) throw new RecoveryLifecycleException('Reusable recovery-point installed.lock verification failed.');
                $attempt = $this->progress($attempt, ReusableRecoveryPointAttemptRecord::VERIFYING, 'VERIFYING');
            }
            if ($this->stageAtOrBefore($attempt->stage(), 'VERIFYING')) {
                $actualRestored = $this->inventoryBuilder->capture();
                if ($actualRestored->fingerprint() !== $targetSource->inventory()->fingerprint()) throw new RecoveryLifecycleException('Reusable recovery-point restored source inventory fingerprint is inconsistent.');
                $restoredLifecycle = $this->lifecycle->capture();
                $restoredState = $restoredLifecycle->state();
                if (!$restoredState instanceof CommittedLifecycleState || $restoredState->sourceInventoryReference()?->toArray() !== $manifest->sourceInventoryReference()->toArray()) throw new RecoveryLifecycleException('Reusable recovery-point restored lifecycle authority is inconsistent.');
                $verifiedMarker = $this->installedLock->capture();
                if ($verifiedMarker->identity() !== $manifest->capturedInstalledLockIdentity()) throw new RecoveryLifecycleException('Reusable recovery-point restored installed.lock authority is inconsistent: expected ' . $manifest->capturedInstalledLockIdentity() . ', got ' . $verifiedMarker->identity() . '.');
                $attempt = $this->progress($attempt, ReusableRecoveryPointAttemptRecord::RESTORED, 'COMPLETE');
            }
            $success = true;
        } catch (\Throwable $exception) {
            try {
                $durable = $this->attempts->activeForInstallation($attempt->installationIdentity());
                if ($durable instanceof ReusableRecoveryPointAttemptRecord && !$this->stageAtOrBefore($durable->stage(), $attempt->stage())) $attempt = $durable;
                else $this->attempts->save($attempt->withProgress(ReusableRecoveryPointAttemptRecord::RESTORE_INDETERMINATE, $attempt->stage(), substr($exception->getMessage(), 0, 1024)));
            } catch (\Throwable) {}
            throw $exception;
        } finally {
            if (!$this->maintenance->leave($guardIdentity)) throw new RecoveryLifecycleException('Reusable recovery-point restore maintenance admission could not be released.');
        }
        if (!$success) throw new RecoveryLifecycleException('Reusable recovery-point restore did not reach a terminal success state.');
        $this->attempts->clearActive($attempt);
        return new ReusableRecoveryPointResult(ReusableRecoveryPointResult::RESTORED, $request->pointIdentity(), $manifest->identity(), $attempt->attemptIdentity(), $continued);
    }

    private function progress(ReusableRecoveryPointAttemptRecord $attempt, string $state, string $stage): ReusableRecoveryPointAttemptRecord
    {
        $next = $attempt->withProgress($state, $stage);
        $this->attempts->save($next);
        if ($this->stageObserver !== null) {
            try { ($this->stageObserver)($next); }
            catch (\Throwable $exception) {
                try { $this->attempts->save($next->withProgress(ReusableRecoveryPointAttemptRecord::RESTORE_INDETERMINATE, $stage, substr($exception->getMessage(), 0, 1024))); } catch (\Throwable) {}
                throw $exception;
            }
        }
        return $next;
    }

    private function stageAtOrBefore(string $current, string $target): bool
    {
        $order = ['CREATED' => 0, 'FILESYSTEM' => 1, 'SOURCE_INVENTORY' => 2, 'LIFECYCLE' => 3, 'INSTALLED_LOCK' => 4, 'VERIFYING' => 5, 'COMPLETE' => 6];
        return ($order[$current] ?? -1) <= ($order[$target] ?? 99);
    }

    private function assertFilesystemOnlyManifest(ReusableRecoveryPointManifest $manifest): void
    {
        $expected = [self::FILESYSTEM_DOMAIN, self::LIFECYCLE_DOMAIN, self::INSTALLED_LOCK_DOMAIN, self::SOURCE_INVENTORY_DOMAIN];
        $actual = array_map(static fn (RecoveryDomainIdentity $domain): string => $domain->identifier(), $manifest->domainIdentities());
        sort($expected, SORT_STRING); sort($actual, SORT_STRING);
        if ($actual !== $expected || $manifest->artifact('database.webcore') instanceof RecoveryArtifactRecord) throw new RecoveryLifecycleException('Reusable recovery-point manifest is not the bounded filesystem-only domain set.');
    }

    private function expectedArtifactIdentity(ReusableRecoveryPointManifest $manifest, string $domain): string
    {
        $artifact = $manifest->artifact($domain);
        if (!$artifact instanceof RecoveryArtifactRecord) throw new RecoveryLifecycleException('Reusable recovery-point domain artifact is unavailable.');
        return $artifact->artifactIdentity();
    }

    private function sourceInventoryBytes(\Copot\Core\SourceInventoryReference $reference): string
    {
        $path = $this->sourceInventories->pathFor($reference);
        $bytes = @file_get_contents($path);
        if (!is_string($bytes) || strlen($bytes) !== $reference->byteSize() || hash('sha256', $bytes) !== $reference->artifactIdentity()) throw new RecoveryLifecycleException('Committed source inventory artifact failed integrity verification.');
        return $bytes;
    }

    private function planFromInventory(CanonicalTargetInventory $inventory): FilesystemRecoveryPlan
    {
        $entries = [];
        foreach ($inventory->entries() as $entry) {
            $path = $entry->root() === CanonicalTargetInventoryEntry::PUBLIC_ROOT ? 'public/' . $entry->path() : $entry->path();
            $entries[] = new FilesystemRecoveryEntry($path, $entry->byteSize(), $entry->sha256());
        }
        return FilesystemRecoveryPlan::fromCapturedEntries(hash('sha256', 'reusable-point-filesystem-plan:' . $inventory->fingerprint()), $entries);
    }

    private function assertFilesystemCaptureMatchesInventory(FilesystemRecoveryCapture $capture, CanonicalTargetInventory $inventory): void
    {
        $expected = [];
        foreach ($inventory->entries() as $entry) $expected[$entry->root() === CanonicalTargetInventoryEntry::PUBLIC_ROOT ? 'public/' . $entry->path() : $entry->path()] = $entry;
        foreach ($capture->entries() as $entry) {
            $source = $expected[$entry->path()] ?? null;
            if (!$source instanceof CanonicalTargetInventoryEntry || $entry->preOperationState() !== FilesystemRecoveryEntry::EXISTING_FILE || $entry->preOperationSize() !== $source->byteSize() || $entry->preOperationHash() !== $source->sha256()) throw new RecoveryLifecycleException('Committed filesystem state does not match the authoritative source inventory.');
            unset($expected[$entry->path()]);
        }
        if ($expected !== []) throw new RecoveryLifecycleException('Committed filesystem capture omitted authoritative source inventory paths.');
    }
}

