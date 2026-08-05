<?php

namespace Copot\Core\BackupRecovery;

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
        $capturedPlan = FilesystemRecoveryPlan::fromCapturedEntries($plan->applyPlanIdentity(), $captured);
        $bytes = (new FilesystemRecoveryBundleCodec())->encode($capturedPlan, $captured);
        $artifactIdentity = hash('sha256', $bytes);
        return new FilesystemRecoveryCapture($capturedPlan, $captured, $bytes, new RecoveryArtifactRecord(self::DOMAIN, $artifactIdentity, strlen($bytes)), new RecoveryDomainIdentity(self::DOMAIN, self::DOMAIN, $plan->applyPlanIdentity(), $artifactIdentity));
    }

    public function restore(RecoveryIdentity $recoveryIdentity, RecoveryArtifactRecord $artifact, FilesystemRecoveryPlan $plan, ?callable $progress = null): FilesystemRecoveryResult
    {
        $completed = [];
        try {
            $decoded = (new FilesystemRecoveryBundleCodec())->decode($this->artifactStore->readArtifact($recoveryIdentity, $artifact));
            if ($decoded['plan_identity'] !== $plan->applyPlanIdentity()) { throw new FilesystemRecoveryException('Filesystem recovery apply-plan identity changed.'); }
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
                    $this->restoreExisting($path, $entry);
                }
                $completed[] = $entry->path();
                if ($progress !== null) { $progress(count($completed), $entry->path()); }
            }
        } catch (\Throwable $exception) {
            return new FilesystemRecoveryResult(FilesystemRecoveryResult::FAILED, $completed, $exception->getMessage());
        }
        return new FilesystemRecoveryResult(FilesystemRecoveryResult::COMPLETED, $completed);
    }

    private function restoreAbsent(string $path, FilesystemRecoveryEntry $entry): void
    {
        if (!file_exists($path)) { return; }
        $this->assertRegular($path);
        $this->assertCurrentIdentity($path, $entry->targetSize(), $entry->targetHash());
        if (!@unlink($path)) { throw new FilesystemRecoveryException('Operation-created filesystem path could not be removed.'); }
        if (file_exists($path) || is_link($path)) { throw new FilesystemRecoveryException('Operation-created filesystem path remained after removal.'); }
    }

    private function restoreExisting(string $path, FilesystemRecoveryEntry $entry): void
    {
        if (!file_exists($path)) { throw new FilesystemRecoveryException('Expected existing filesystem path disappeared.'); }
        $this->assertRegular($path);
        $currentSize = @filesize($path);
        $currentHash = @hash_file('sha256', $path);
        if ($currentSize === $entry->preOperationSize() && $currentHash === $entry->preOperationHash()) { return; }
        if ($currentSize !== $entry->targetSize() || $currentHash !== $entry->targetHash()) { throw new FilesystemRecoveryException('Unexpected filesystem drift detected.'); }

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
            $this->assertCurrentIdentity($path, $entry->targetSize(), $entry->targetHash());
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
