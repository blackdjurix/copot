<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\CanonicalTargetInventory;
use Copot\Core\CanonicalTargetInventoryEntry;

final class FilesystemRecoveryDomain
{
    private const DOMAIN = 'filesystem.package-owned';

    public function __construct(private FilesystemRecoveryPathGuard $guard, private RecoveryArtifactStore $artifactStore, private mixed $fsync = null)
    {
    }

    public function capture(FilesystemRecoveryPlan $plan): FilesystemRecoveryCapture
    {
        $captured = [];
        try {
            foreach ($plan->entries() as $entry) {
                $path = $this->guard->resolve($entry->path());
                if (!file_exists($path)) {
                    $captured[] = FilesystemRecoveryEntry::absent($entry->path(), $entry->targetSize(), $entry->targetHash());
                    continue;
                }
                $bytes = @file_get_contents($path);
                if (!is_string($bytes)) { throw new FilesystemRecoveryException('Filesystem recovery file could not be read.'); }
                $size = @filesize($path);
                $hash = hash('sha256', $bytes);
                // The pre-image is allowed to differ from the target; only
                // the captured byte stream must be internally consistent.
                if (!is_int($size) || $size !== strlen($bytes)) { throw new FilesystemRecoveryException('Filesystem recovery file changed during capture.'); }
                $mode = DIRECTORY_SEPARATOR === '/' ? ((int) @fileperms($path) & 0777) : null;
                $captured[] = FilesystemRecoveryEntry::existing($entry->path(), $entry->targetSize(), $entry->targetHash(), $bytes, $mode);
                $this->assertCurrentIdentity($path, strlen($bytes), $hash);
            }
        } catch (\Throwable $exception) {
            if ($exception instanceof FilesystemRecoveryException) { throw $exception; }
            throw new FilesystemRecoveryException('Filesystem recovery capture failed.', 0, $exception);
        }
        $capturedPlan = FilesystemRecoveryPlan::fromCapturedEntries($plan->applyPlanIdentity(), $captured, $plan->postOperationAbsentPaths());
        $bytes = (new FilesystemRecoveryBundleCodec())->encode($capturedPlan, $captured);
        $artifactIdentity = hash('sha256', $bytes);
        return new FilesystemRecoveryCapture($capturedPlan, $captured, $bytes, new RecoveryArtifactRecord(self::DOMAIN, $artifactIdentity, strlen($bytes)), new RecoveryDomainIdentity(self::DOMAIN, self::DOMAIN, $plan->applyPlanIdentity(), $artifactIdentity));
    }

    public function restore(RecoveryIdentity $recoveryIdentity, RecoveryArtifactRecord $artifact, FilesystemRecoveryPlan $plan, ?callable $progress = null): FilesystemRecoveryResult
    {
        return $this->restoreArtifactBytes($this->artifactStore->readArtifact($recoveryIdentity, $artifact), $plan, $progress);
    }

    public function restoreArtifactBytes(string $artifactBytes, FilesystemRecoveryPlan $plan, ?callable $progress = null): FilesystemRecoveryResult
    {
        $completed = [];
        try {
            $decoded = (new FilesystemRecoveryBundleCodec())->decode($artifactBytes);
            if ($decoded['plan_identity'] !== $plan->applyPlanIdentity()) { throw new FilesystemRecoveryException('Filesystem recovery apply-plan identity changed.'); }
            $expectedAbsent = $plan->postOperationAbsentPaths();
            sort($expectedAbsent, SORT_STRING);
            $actualAbsent = $decoded['post_operation_absent_paths'];
            sort($actualAbsent, SORT_STRING);
            if ($actualAbsent !== $expectedAbsent) { throw new FilesystemRecoveryException('Filesystem recovery post-operation state changed.'); }
            $expected = [];
            foreach ($plan->entries() as $planned) { $expected[$planned->path()] = $planned; }
            if (count($decoded['entries']) !== count($expected)) { throw new FilesystemRecoveryException('Filesystem recovery scope changed.'); }
            foreach ($decoded['entries'] as $entry) {
                if (!isset($expected[$entry->path()]) || $expected[$entry->path()]->targetSize() !== $entry->targetSize() || $expected[$entry->path()]->targetHash() !== $entry->targetHash()) {
                    throw new FilesystemRecoveryException('Filesystem recovery target identity changed.');
                }
                $path = $this->guard->resolve($entry->path());
                if ($entry->preOperationState() === FilesystemRecoveryEntry::ABSENT_BEFORE_OPERATION) {
                    $this->restoreAbsent($path, $entry);
                } else {
                    $this->restoreExisting($path, $entry, in_array($entry->path(), $expectedAbsent, true));
                }
                $completed[] = $entry->path();
                if ($progress !== null) { $progress(count($completed), $entry->path()); }
            }
        } catch (\Throwable $exception) {
            return new FilesystemRecoveryResult(FilesystemRecoveryResult::FAILED, $completed, $exception->getMessage());
        }
        return new FilesystemRecoveryResult(FilesystemRecoveryResult::COMPLETED, $completed);
    }

    /**
     * Restore a committed filesystem snapshot while binding the current
     * state to an explicit source inventory. This is deliberately separate
     * from operation-bound restoreArtifactBytes(): a reusable point has no
     * package-apply target identity to use as a safe mutation precondition.
     */
    public function restoreReusablePointArtifactBytes(string $artifactBytes, CanonicalTargetInventory $expectedCurrentInventory, ?callable $progress = null): FilesystemRecoveryResult
    {
        $completed = [];
        try {
            $decoded = (new FilesystemRecoveryBundleCodec())->decode($artifactBytes);
            $expected = [];
            foreach ($expectedCurrentInventory->entries() as $entry) {
                if (!$entry instanceof CanonicalTargetInventoryEntry || $entry->ownership() !== CanonicalTargetInventoryEntry::PACKAGE_OWNED) throw new FilesystemRecoveryException('Reusable recovery-point source inventory ownership is invalid.');
                $path = self::logicalPath($entry);
                if (isset($expected[$path])) throw new FilesystemRecoveryException('Reusable recovery-point source inventory contains duplicate paths.');
                $expected[$path] = $entry;
            }
            $desired = [];
            foreach ($decoded['entries'] as $entry) {
                if (!$entry instanceof FilesystemRecoveryEntry || isset($desired[$entry->path()])) throw new FilesystemRecoveryException('Reusable recovery-point filesystem snapshot is invalid.');
                $desired[$entry->path()] = $entry;
            }

            foreach ($desired as $path => $entry) {
                $physical = $this->guard->resolve($path);
                $current = $expected[$path] ?? null;
                if ($entry->preOperationState() === FilesystemRecoveryEntry::ABSENT_BEFORE_OPERATION) {
                    if (!file_exists($physical)) { $completed[] = $path; continue; }
                    $this->assertExpectedCurrent($physical, $current, $path);
                    if (!@unlink($physical) || file_exists($physical) || is_link($physical)) throw new FilesystemRecoveryException('Reusable recovery-point filesystem absence could not be restored.');
                    $completed[] = $path;
                    if ($progress !== null) $progress(count($completed), $path);
                    continue;
                }
                $bytes = $entry->preImageBytes();
                if (!is_string($bytes) || $entry->preOperationSize() === null || $entry->preOperationHash() === null) throw new FilesystemRecoveryException('Reusable recovery-point filesystem pre-image is unavailable.');
                if (file_exists($physical) && @filesize($physical) === $entry->preOperationSize() && @hash_file('sha256', $physical) === $entry->preOperationHash()) { $completed[] = $path; continue; }
                $this->assertExpectedCurrent($physical, $current, $path);
                $this->writeSnapshot($physical, $path, $bytes, $entry->preOperationMode());
                $completed[] = $path;
                if ($progress !== null) $progress(count($completed), $path);
            }

            foreach ($expected as $path => $entry) {
                if (isset($desired[$path])) continue;
                $physical = $this->guard->resolve($path);
                if (!file_exists($physical)) continue;
                $this->assertExpectedCurrent($physical, $entry, $path);
                if (!@unlink($physical) || file_exists($physical) || is_link($physical)) throw new FilesystemRecoveryException('Reusable recovery-point extra filesystem path could not be removed.');
                $completed[] = $path;
                if ($progress !== null) $progress(count($completed), $path);
            }
        } catch (\Throwable $exception) {
            return new FilesystemRecoveryResult(FilesystemRecoveryResult::FAILED, $completed, $exception->getMessage());
        }
        return new FilesystemRecoveryResult(FilesystemRecoveryResult::COMPLETED, $completed);
    }

    private static function logicalPath(CanonicalTargetInventoryEntry $entry): string
    {
        return $entry->root() === CanonicalTargetInventoryEntry::PUBLIC_ROOT ? 'public/' . $entry->path() : $entry->path();
    }

    private function assertExpectedCurrent(string $physical, ?CanonicalTargetInventoryEntry $entry, string $path): void
    {
        if (!$entry instanceof CanonicalTargetInventoryEntry) throw new FilesystemRecoveryException('Reusable recovery-point current source inventory does not cover ' . $path . '.');
        if (!file_exists($physical) || is_link($physical) || !is_file($physical) || @filesize($physical) !== $entry->byteSize() || @hash_file('sha256', $physical) !== $entry->sha256()) throw new FilesystemRecoveryException('Reusable recovery-point current filesystem identity changed for ' . $path . '.');
    }

    private function writeSnapshot(string $path, string $relativePath, string $bytes, ?int $mode): void
    {
        $this->guard->ensureParent($relativePath);
        $temporary = dirname($path) . DIRECTORY_SEPARATOR . '.recovery-point-restore-' . bin2hex(random_bytes(16)) . '.tmp';
        $handle = @fopen($temporary, 'xb');
        if (!is_resource($handle)) throw new FilesystemRecoveryException('Reusable recovery-point filesystem temporary file could not be created.');
        try {
            $offset = 0;
            while ($offset < strlen($bytes)) { $written = @fwrite($handle, substr($bytes, $offset)); if (!is_int($written) || $written < 1) throw new FilesystemRecoveryException('Reusable recovery-point filesystem temporary file could not be written.'); $offset += $written; }
            if (!@fflush($handle)) throw new FilesystemRecoveryException('Reusable recovery-point filesystem temporary file could not be flushed.');
            $sync = $this->fsync ?? (function_exists('fsync') ? static fn ($resource): bool => @fsync($resource) : null);
            if ($sync === null || !$sync($handle)) throw new FilesystemRecoveryException('Reusable recovery-point filesystem temporary file could not be synchronized.');
        } catch (\Throwable $exception) { @unlink($temporary); throw $exception; } finally { fclose($handle); }
        try {
            if (file_exists($path)) $this->assertRegular($path);
            if (!@rename($temporary, $path)) throw new FilesystemRecoveryException('Reusable recovery-point filesystem activation failed.');
            if (!is_file($path) || @filesize($path) !== strlen($bytes) || @hash_file('sha256', $path) !== hash('sha256', $bytes)) throw new FilesystemRecoveryException('Reusable recovery-point filesystem identity verification failed.');
            if ($mode !== null && DIRECTORY_SEPARATOR === '/') { @chmod($path, $mode); if ((((int) @fileperms($path)) & 0777) !== $mode) throw new FilesystemRecoveryException('Reusable recovery-point filesystem mode verification failed.'); }
        } finally { if (file_exists($temporary)) @unlink($temporary); }
    }

    private function restoreAbsent(string $path, FilesystemRecoveryEntry $entry): void
    {
        if (!file_exists($path)) { return; }
        $this->assertRegular($path);
        $this->assertCurrentIdentity($path, $entry->targetSize(), $entry->targetHash());
        if (!@unlink($path)) { throw new FilesystemRecoveryException('Operation-created filesystem path could not be removed.'); }
        if (file_exists($path) || is_link($path)) { throw new FilesystemRecoveryException('Operation-created filesystem path remained after removal.'); }
    }

    private function restoreExisting(string $path, FilesystemRecoveryEntry $entry, bool $postOperationAbsent = false): void
    {
        if (!file_exists($path)) {
            if (!$postOperationAbsent) { throw new FilesystemRecoveryException('Expected existing filesystem path disappeared.'); }
            $this->writePreImage($path, $entry, false);
            return;
        }
        $this->assertRegular($path);
        $currentSize = @filesize($path);
        $currentHash = @hash_file('sha256', $path);
        if ($currentSize === $entry->preOperationSize() && $currentHash === $entry->preOperationHash()) { return; }
        if ($postOperationAbsent) { throw new FilesystemRecoveryException('Unexpected filesystem drift detected.'); }
        if ($currentSize !== $entry->targetSize() || $currentHash !== $entry->targetHash()) { throw new FilesystemRecoveryException('Unexpected filesystem drift detected.'); }

        $this->writePreImage($path, $entry, true);
    }

    private function writePreImage(string $path, FilesystemRecoveryEntry $entry, bool $replaceExisting): void
    {
        $this->guard->ensureParent($entry->path());
        $temporary = dirname($path) . DIRECTORY_SEPARATOR . '.recovery-restore-' . bin2hex(random_bytes(16)) . '.tmp';
        $handle = @fopen($temporary, 'xb');
        if (!is_resource($handle)) { throw new FilesystemRecoveryException('Filesystem restore temporary file could not be created.'); }
        try {
            $bytes = $entry->preImageBytes();
            if (!is_string($bytes)) { throw new FilesystemRecoveryException('Filesystem restore pre-image is unavailable.'); }
            $offset = 0;
            while ($offset < strlen($bytes)) {
                $written = @fwrite($handle, substr($bytes, $offset));
                if (!is_int($written) || $written < 1) { throw new FilesystemRecoveryException('Filesystem restore temporary file could not be written.'); }
                $offset += $written;
            }
            if (!@fflush($handle)) { throw new FilesystemRecoveryException('Filesystem restore temporary file could not be flushed.'); }
            $sync = $this->fsync ?? (function_exists('fsync') ? static fn ($resource): bool => @fsync($resource) : null);
            if ($sync === null || !$sync($handle)) { throw new FilesystemRecoveryException('Filesystem restore temporary file could not be synchronized.'); }
        } catch (\Throwable $exception) {
            @unlink($temporary);
            throw $exception;
        } finally { fclose($handle); }

        try {
            if ($replaceExisting) {
                $this->assertCurrentIdentity($path, $entry->targetSize(), $entry->targetHash());
            } elseif (file_exists($path)) {
                throw new FilesystemRecoveryException('Filesystem restore destination changed before activation.');
            }
            if (!@rename($temporary, $path)) { throw new FilesystemRecoveryException('Filesystem restore activation failed.'); }
            if (!is_file($path) || @filesize($path) !== $entry->preOperationSize() || @hash_file('sha256', $path) !== $entry->preOperationHash()) {
                throw new FilesystemRecoveryException('Filesystem restore identity verification failed.');
            }
            if ($entry->preOperationMode() !== null && DIRECTORY_SEPARATOR === '/') {
                @chmod($path, $entry->preOperationMode());
                if ((((int) @fileperms($path)) & 0777) !== $entry->preOperationMode()) { throw new FilesystemRecoveryException('Filesystem restore mode verification failed.'); }
            }
        } finally { if (file_exists($temporary)) { @unlink($temporary); } }
    }

    private function assertRegular(string $path): void
    {
        if (is_link($path) || !is_file($path) || @filetype($path) !== 'file') { throw new FilesystemRecoveryException('Filesystem restore path is not a regular file.'); }
    }

    private function assertCurrentIdentity(string $path, int $size, string $hash): void
    {
        if (@filesize($path) !== $size || @hash_file('sha256', $path) !== $hash) { throw new FilesystemRecoveryException('Filesystem identity changed during recovery.'); }
    }
}
