<?php

namespace Copot\Core;

class InstallerEnvironmentWriter
{
    private const DATABASE_KEYS = [
        'DB_HOST' => 'host',
        'DB_PORT' => 'port',
        'DB_DATABASE' => 'database',
        'DB_USERNAME' => 'username',
        'DB_PASSWORD' => 'password',
    ];

    public function __construct(private string $environmentPath)
    {
    }

    public function persist(array $configuration): void
    {
        $values = $this->databaseValues($configuration);
        $directory = dirname($this->environmentPath);

        if (
            !is_dir($directory)
            || !is_writable($directory)
            || is_link($this->environmentPath)
            || (file_exists($this->environmentPath) && !is_file($this->environmentPath))
        ) {
            throw new InstallationException('Database configuration could not be saved.');
        }

        $existing = '';

        if (is_file($this->environmentPath)) {
            if (!is_readable($this->environmentPath) || !is_writable($this->environmentPath)) {
                throw new InstallationException('Database configuration could not be saved.');
            }

            $existing = @file_get_contents($this->environmentPath);

            if (!is_string($existing)) {
                throw new InstallationException('Database configuration could not be saved.');
            }
        }

        $contents = $this->merge($existing, $values);
        $temporaryPath = null;
        $backupPath = null;

        try {
            if (is_file($this->environmentPath)) {
                $backupPath = $this->temporaryPath($directory, '.env-backup-');
                @chmod($backupPath, 0600);
                $this->writeComplete($backupPath, $existing);
            }

            $temporaryPath = $this->temporaryPath($directory, '.env-write-');
            @chmod($temporaryPath, 0600);
            $this->writeComplete($temporaryPath, $contents);
            $permissions = is_file($this->environmentPath) ? @fileperms($this->environmentPath) : false;
            @chmod($temporaryPath, is_int($permissions) ? ($permissions & 0777) : 0600);

            if (!@rename($temporaryPath, $this->environmentPath)) {
                throw new InstallationException('Database configuration could not be saved.');
            }

            $temporaryPath = null;

            if (@file_get_contents($this->environmentPath) !== $contents) {
                $this->restoreBackup($backupPath, $existing);
                $backupPath = null;

                throw new InstallationException('Database configuration could not be verified.');
            }
        } finally {
            $this->removeTemporaryFile($temporaryPath);
            $this->removeTemporaryFile($backupPath);
        }
    }

    private function databaseValues(array $configuration): array
    {
        $values = [];

        foreach (self::DATABASE_KEYS as $environmentKey => $configurationKey) {
            if (!array_key_exists($configurationKey, $configuration)) {
                throw new InstallationException('Database configuration is incomplete.');
            }

            $value = $configuration[$configurationKey];

            if ($configurationKey === 'port') {
                if (!is_int($value) || $value < 1 || $value > 65535) {
                    throw new InstallationException('Database configuration is invalid.');
                }

                $value = (string) $value;
            }

            if (!is_string($value) || preg_match('/[\x00\r\n]/', $value)) {
                throw new InstallationException('Database configuration is invalid.');
            }

            $values[$environmentKey] = $this->serialize($value);
        }

        return $values;
    }

    private function merge(string $existing, array $values): string
    {
        $newLine = str_contains($existing, "\r\n") ? "\r\n" : "\n";
        $lines = $existing === '' ? [] : preg_split('/\r\n|\n|\r/', $existing);
        $lines = is_array($lines) ? $lines : [];

        if ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        $output = [];
        $written = [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*(DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD)\s*=/', $line, $matches)) {
                $key = $matches[1];

                if (!isset($written[$key])) {
                    $output[] = $key . '=' . $values[$key];
                    $written[$key] = true;
                }

                continue;
            }

            $output[] = $line;
        }

        foreach ($values as $key => $value) {
            if (!isset($written[$key])) {
                $output[] = $key . '=' . $value;
            }
        }

        return implode($newLine, $output) . $newLine;
    }

    private function serialize(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }

    private function temporaryPath(string $directory, string $prefix): string
    {
        $path = @tempnam($directory, $prefix);

        if (!is_string($path)) {
            throw new InstallationException('Database configuration could not be saved.');
        }

        return $path;
    }

    private function writeComplete(string $path, string $contents): void
    {
        $handle = @fopen($path, 'wb');

        if (!is_resource($handle)) {
            throw new InstallationException('Database configuration could not be saved.');
        }

        try {
            $length = strlen($contents);
            $offset = 0;

            while ($offset < $length) {
                $written = @fwrite($handle, substr($contents, $offset));

                if (!is_int($written) || $written < 1) {
                    throw new InstallationException('Database configuration could not be saved.');
                }

                $offset += $written;
            }

            if (!@fflush($handle)) {
                throw new InstallationException('Database configuration could not be saved.');
            }

            if (function_exists('fsync') && !@fsync($handle)) {
                throw new InstallationException('Database configuration could not be saved.');
            }
        } finally {
            fclose($handle);
        }
    }

    private function restoreBackup(?string $backupPath, string $existing): void
    {
        if ($backupPath !== null && is_file($backupPath) && @rename($backupPath, $this->environmentPath)) {
            return;
        }

        if ($existing === '') {
            @unlink($this->environmentPath);
        }
    }

    private function removeTemporaryFile(?string $path): void
    {
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }
}
