<?php

namespace Copot\Core;

class InstallationMutex
{
    private string $storagePath;

    public function __construct(string $storagePath)
    {
        $this->storagePath = rtrim($storagePath, '/\\');
    }

    public function acquire(): ?InstallationLock
    {
        if (!function_exists('flock')) {
            throw new InstallationException('Installer concurrency locking is unavailable.');
        }

        if (!is_dir($this->storagePath) || !is_writable($this->storagePath)) {
            throw new InstallationException('Installer storage is unavailable.');
        }

        $lockPath = $this->lockPath();

        if (is_link($lockPath) || (file_exists($lockPath) && !is_file($lockPath))) {
            throw new InstallationException('Installer concurrency lock is invalid.');
        }

        $handle = @fopen($lockPath, 'c');

        if (!is_resource($handle)) {
            throw new InstallationException('Installer concurrency lock is unavailable.');
        }

        if (!@flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return new InstallationLock($handle);
    }

    private function lockPath(): string
    {
        return $this->storagePath . DIRECTORY_SEPARATOR . '.install.lock';
    }
}
