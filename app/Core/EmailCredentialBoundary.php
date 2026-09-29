<?php

namespace Copot\Core;

class EmailCredentialBoundary
{
    private const ENVIRONMENT_KEY = 'COPOT_EMAIL_CREDENTIAL';

    /**
     * @param (callable(string, string): bool)|null $rename
     */
    public function __construct(
        private string $environmentPath,
        private $rename = null,
    ) {
    }

    public function replace(string $secret): void
    {
        if ($secret === '' || preg_match('/[\x00\r\n]/', $secret)) {
            throw new EmailCredentialException('Email credential could not be saved.');
        }

        $directory = dirname($this->environmentPath);
        $this->assertTarget($directory);

        $existing = '';
        if (is_file($this->environmentPath)) {
            if (!is_readable($this->environmentPath) || !is_writable($this->environmentPath)) {
                throw new EmailCredentialException('Email credential could not be saved.');
            }

            $existing = @file_get_contents($this->environmentPath);
            if (!is_string($existing)) {
                throw new EmailCredentialException('Email credential could not be saved.');
            }
        }

        $contents = $this->merge($existing, $secret);
        $temporaryPath = null;
        $backupPath = null;

        try {
            if (is_file($this->environmentPath)) {
                $backupPath = $this->temporaryPath($directory, '.copot-email-credential-backup-');
                @chmod($backupPath, 0600);
                $this->writeComplete($backupPath, $existing);
            }

            $temporaryPath = $this->temporaryPath($directory, '.copot-email-credential-write-');
            @chmod($temporaryPath, 0600);
            $this->writeComplete($temporaryPath, $contents);
            $permissions = is_file($this->environmentPath) ? @fileperms($this->environmentPath) : false;
            @chmod($temporaryPath, is_int($permissions) ? ($permissions & 0777) : 0600);

            if (!$this->rename($temporaryPath, $this->environmentPath)) {
                throw new EmailCredentialException('Email credential could not be saved.');
            }

            $temporaryPath = null;
            if (@file_get_contents($this->environmentPath) !== $contents) {
                $this->restore($backupPath, $existing);
                $backupPath = null;
                throw new EmailCredentialException('Email credential could not be verified.');
            }
        } finally {
            $this->removeTemporaryFile($temporaryPath);
            $this->removeTemporaryFile($backupPath);
        }
    }

    /** @return array{configured: bool} */
    public function state(): array
    {
        if (!is_file($this->environmentPath) || !is_readable($this->environmentPath)) {
            return ['configured' => false];
        }

        $contents = @file_get_contents($this->environmentPath);
        if (!is_string($contents)) {
            return ['configured' => false];
        }

        foreach (preg_split('/\r\n|\n|\r/', $contents) ?: [] as $line) {
            if (!preg_match('/^\s*' . preg_quote(self::ENVIRONMENT_KEY, '/') . '\s*=\s*(.*)$/', $line, $matches)) {
                continue;
            }

            $value = trim($matches[1]);
            if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                $value = str_replace(['\\"', '\\\\'], ['"', '\\'], substr($value, 1, -1));
            } elseif (str_starts_with($value, "'") && str_ends_with($value, "'")) {
                $value = substr($value, 1, -1);
            }

            return ['configured' => $value !== ''];
        }

        return ['configured' => false];
    }

    /**
     * Internal transport-only access. The public state() method remains redacted.
     */
    public function readForTransport(): ?string
    {
        if (!is_file($this->environmentPath) || !is_readable($this->environmentPath)) {
            return null;
        }

        $contents = @file_get_contents($this->environmentPath);
        if (!is_string($contents)) {
            return null;
        }

        foreach (preg_split('/\r\n|\n|\r/', $contents) ?: [] as $line) {
            if (!preg_match('/^\s*' . preg_quote(self::ENVIRONMENT_KEY, '/') . '\s*=\s*(.*)$/', $line, $matches)) {
                continue;
            }

            $value = trim($matches[1]);
            if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                $value = str_replace(['\\"', '\\\\'], ['"', '\\'], substr($value, 1, -1));
            } elseif (str_starts_with($value, "'") && str_ends_with($value, "'")) {
                $value = substr($value, 1, -1);
            }

            return $value === '' ? null : $value;
        }

        return null;
    }

    private function assertTarget(string $directory): void
    {
        if (
            !is_dir($directory)
            || !is_writable($directory)
            || is_link($this->environmentPath)
            || (file_exists($this->environmentPath) && !is_file($this->environmentPath))
        ) {
            throw new EmailCredentialException('Email credential could not be saved.');
        }
    }

    private function merge(string $existing, string $secret): string
    {
        $newLine = str_contains($existing, "\r\n") ? "\r\n" : "\n";
        $hasTrailingNewLine = preg_match('/(?:\r\n|\n|\r)$/', $existing) === 1;
        $lines = $existing === '' ? [] : preg_split('/\r\n|\n|\r/', $existing);
        $lines = is_array($lines) ? $lines : [];
        if ($hasTrailingNewLine && $lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        $output = [];
        $written = false;
        foreach ($lines as $line) {
            if (preg_match('/^(\s*)' . preg_quote(self::ENVIRONMENT_KEY, '/') . '\s*=/', $line, $matches)) {
                if (!$written) {
                    $output[] = $matches[1] . self::ENVIRONMENT_KEY . '=' . $this->serialize($secret);
                    $written = true;
                }
                continue;
            }
            $output[] = $line;
        }

        if (!$written) {
            $output[] = self::ENVIRONMENT_KEY . '=' . $this->serialize($secret);
        }

        return implode($newLine, $output) . ($hasTrailingNewLine || $existing === '' || !$written ? $newLine : '');
    }

    private function serialize(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }

    private function temporaryPath(string $directory, string $prefix): string
    {
        $path = @tempnam($directory, $prefix);
        if (!is_string($path)) {
            throw new EmailCredentialException('Email credential could not be saved.');
        }
        return $path;
    }

    private function writeComplete(string $path, string $contents): void
    {
        $handle = @fopen($path, 'wb');
        if (!is_resource($handle)) {
            throw new EmailCredentialException('Email credential could not be saved.');
        }

        try {
            $offset = 0;
            $length = strlen($contents);
            while ($offset < $length) {
                $written = @fwrite($handle, substr($contents, $offset));
                if (!is_int($written) || $written < 1) {
                    throw new EmailCredentialException('Email credential could not be saved.');
                }
                $offset += $written;
            }
            if (!@fflush($handle) || (function_exists('fsync') && !@fsync($handle))) {
                throw new EmailCredentialException('Email credential could not be saved.');
            }
        } finally {
            fclose($handle);
        }
    }

    private function rename(string $temporaryPath, string $targetPath): bool
    {
        return $this->rename !== null
            ? (bool) ($this->rename)($temporaryPath, $targetPath)
            : @rename($temporaryPath, $targetPath);
    }

    private function restore(?string $backupPath, string $existing): void
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
