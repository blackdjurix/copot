<?php

namespace Copot\Core\BackupRecovery;

final class RecoveryAtomicFileWriter
{
    private bool $requireFsync;

    public function __construct(?bool $requireFsync = null, private mixed $fsync = null)
    {
        // Match the repository's existing primitive: use fsync when the
        // runtime exposes it, while keeping the capability injectable for
        // fail-closed tests and supported runtimes that require it.
        $this->requireFsync = $requireFsync ?? function_exists('fsync');
    }

    public function write(string $path, string $contents, int $mode = 0600): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) || is_link($directory) || !is_writable($directory)) {
            throw new RecoveryStorageException('Recovery write directory is unavailable.');
        }
        if (file_exists($path) || is_link($path)) {
            throw new RecoveryStorageException('Recovery artifact would overwrite an existing file.');
        }

        $temporary = $directory . DIRECTORY_SEPARATOR . '.recovery-' . bin2hex(random_bytes(16)) . '.tmp';
        $handle = @fopen($temporary, 'xb');
        if (!is_resource($handle)) {
            throw new RecoveryStorageException('Recovery temporary file could not be created.');
        }
        try {
            $offset = 0;
            $length = strlen($contents);
            while ($offset < $length) {
                $written = @fwrite($handle, substr($contents, $offset));
                if (!is_int($written) || $written < 1) {
                    throw new RecoveryStorageException('Recovery file could not be written.');
                }
                $offset += $written;
            }
            if (!@fflush($handle)) {
                throw new RecoveryStorageException('Recovery file could not be flushed.');
            }
            if ($this->requireFsync) {
                $sync = $this->fsync ?? (function_exists('fsync') ? static fn ($resource): bool => @fsync($resource) : null);
                if ($sync === null || !$sync($handle)) {
                    throw new RecoveryStorageException('Recovery file could not be durably synchronized.');
                }
            }
        } catch (\Throwable $exception) {
            @unlink($temporary);
            throw $exception;
        } finally {
            fclose($handle);
        }

        @chmod($temporary, $mode);
        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            throw new RecoveryStorageException('Recovery file could not be published.');
        }

        $size = @filesize($path);
        $hash = @hash_file('sha256', $path);
        if ($size !== $length || $hash !== hash('sha256', $contents)) {
            @unlink($path);
            throw new RecoveryStorageException('Recovery file failed read-after-write verification.');
        }
    }
}
