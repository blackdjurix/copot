<?php

namespace Copot\Core\BackupRecovery;

final class ReusableRecoveryPointAttemptStore
{
    public function __construct(private RecoveryStorageRoot $root, private ?RecoveryAtomicFileWriter $writer = null)
    {
        $this->writer ??= new RecoveryAtomicFileWriter();
    }

    public function read(ReusableRecoveryPointIdentity $point, ReusableRecoveryPointAttemptIdentity $attempt): ReusableRecoveryPointAttemptRecord
    {
        $path = (new RecoveryStoragePathPolicy($this->root))->reusableRecoveryPointAttemptPath($point, $attempt);
        if (is_link($path) || !is_file($path) || !is_readable($path)) throw new RecoveryLifecycleException('Reusable recovery-point attempt is unavailable.');
        try {
            $data = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($data)) throw new \RuntimeException();
            $record = ReusableRecoveryPointAttemptRecord::fromArray($data);
            if (!$record->pointIdentity()->equals($point) || !$record->attemptIdentity()->equals($attempt)) throw new RecoveryLifecycleException('Reusable recovery-point attempt namespace identity changed.');
            return $record;
        } catch (\Throwable $exception) {
            if ($exception instanceof RecoveryLifecycleException) throw $exception;
            throw new RecoveryLifecycleException('Reusable recovery-point attempt is malformed.', 0, $exception);
        }
    }

    public function activeForInstallation(string $installationIdentity): ?ReusableRecoveryPointAttemptRecord
    {
        $path = (new RecoveryStoragePathPolicy($this->root))->reusableRecoveryPointActiveAttemptPath($installationIdentity);
        if (!file_exists($path) && !is_link($path)) return null;
        if (is_link($path) || !is_file($path) || !is_readable($path)) throw new RecoveryLifecycleException('Reusable recovery-point active-attempt pointer is invalid.');
        try {
            $data = json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($data) || array_keys($data) !== ['point_identity','attempt_identity','installation_identity'] || $data['installation_identity'] !== $installationIdentity) throw new \RuntimeException();
            return $this->read(new ReusableRecoveryPointIdentity($data['point_identity']), new ReusableRecoveryPointAttemptIdentity($data['attempt_identity']));
        } catch (\Throwable $exception) {
            if ($exception instanceof RecoveryLifecycleException) throw $exception;
            throw new RecoveryLifecycleException('Reusable recovery-point active-attempt pointer is malformed.', 0, $exception);
        }
    }

    public function create(ReusableRecoveryPointAttemptRecord $record): void
    {
        if ($this->activeForInstallation($record->installationIdentity()) !== null) throw new RecoveryLifecycleException('Another unresolved reusable recovery-point attempt is active for this installation.');
        $policy = new RecoveryStoragePathPolicy($this->root);
        $path = $policy->reusableRecoveryPointAttemptPath($record->pointIdentity(), $record->attemptIdentity());
        $this->ensureDirectory(dirname($path));
        if (file_exists($path) || is_link($path)) throw new RecoveryLifecycleException('Reusable recovery-point attempt already exists.');
        $this->writer->write($path, $this->json($record->toArray()));
        $active = $policy->reusableRecoveryPointActiveAttemptPath($record->installationIdentity());
        $this->ensureDirectory(dirname($active));
        if (file_exists($active) || is_link($active)) throw new RecoveryLifecycleException('Reusable recovery-point active-attempt pointer already exists.');
        $this->writer->write($active, $this->json(['point_identity' => $record->pointIdentity()->value(), 'attempt_identity' => $record->attemptIdentity()->value(), 'installation_identity' => $record->installationIdentity()]));
    }

    public function save(ReusableRecoveryPointAttemptRecord $record): void
    {
        $path = (new RecoveryStoragePathPolicy($this->root))->reusableRecoveryPointAttemptPath($record->pointIdentity(), $record->attemptIdentity());
        if (!is_file($path) || is_link($path)) throw new RecoveryLifecycleException('Reusable recovery-point attempt cannot be updated because it is unavailable.');
        $this->replace($path, $this->json($record->toArray()));
    }

    public function clearActive(ReusableRecoveryPointAttemptRecord $record): void
    {
        $path = (new RecoveryStoragePathPolicy($this->root))->reusableRecoveryPointActiveAttemptPath($record->installationIdentity());
        if (!file_exists($path)) return;
        $active = $this->activeForInstallation($record->installationIdentity());
        if (!$active instanceof ReusableRecoveryPointAttemptRecord || !$active->pointIdentity()->equals($record->pointIdentity()) || !$active->attemptIdentity()->equals($record->attemptIdentity())) throw new RecoveryLifecycleException('Reusable recovery-point active-attempt identity changed.');
        if (!@unlink($path) || file_exists($path)) throw new RecoveryLifecycleException('Reusable recovery-point active-attempt pointer could not be cleared.');
    }

    private function json(array $data): string { return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); }
    private function ensureDirectory(string $path): void { if (!is_dir($path) && (!mkdir($path, 0700, true) || !is_dir($path))) throw new RecoveryLifecycleException('Reusable recovery-point attempt storage is unavailable.'); if (is_link($path) || !is_writable($path)) throw new RecoveryLifecycleException('Reusable recovery-point attempt storage is unsafe.'); @chmod($path, 0700); }
    private function replace(string $path, string $contents): void
    {
        $temporary = dirname($path) . DIRECTORY_SEPARATOR . '.recovery-attempt-' . bin2hex(random_bytes(16)) . '.tmp';
        $handle = @fopen($temporary, 'xb');
        if (!is_resource($handle)) throw new RecoveryLifecycleException('Reusable recovery-point attempt temporary file could not be created.');
        try {
            $offset = 0;
            while ($offset < strlen($contents)) { $written = @fwrite($handle, substr($contents, $offset)); if (!is_int($written) || $written < 1) throw new RecoveryLifecycleException('Reusable recovery-point attempt could not be written.'); $offset += $written; }
            if (!@fflush($handle) || !function_exists('fsync') || !@fsync($handle)) throw new RecoveryLifecycleException('Reusable recovery-point attempt durability is unavailable.');
        } catch (\Throwable $exception) { @unlink($temporary); throw $exception; } finally { fclose($handle); }
        @chmod($temporary, 0600);
        if (!@rename($temporary, $path)) { @unlink($temporary); throw new RecoveryLifecycleException('Reusable recovery-point attempt publication failed.'); }
        if (@filesize($path) !== strlen($contents) || @hash_file('sha256', $path) !== hash('sha256', $contents)) throw new RecoveryLifecycleException('Reusable recovery-point attempt read-back verification failed.');
    }
}

