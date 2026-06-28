<?php

namespace Copot\Core;

use DateTimeImmutable;
use JsonException;

class InstallationState
{
    private const MAX_MARKER_BYTES = 4096;
    private const VERSION_PATTERN = '/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?$/';

    private string $storagePath;

    public function __construct(string $storagePath)
    {
        $this->storagePath = rtrim($storagePath, '/\\');
    }

    public function isInstalled(): bool
    {
        return $this->readMarker() !== null;
    }

    public function readMarker(): ?array
    {
        $markerPath = $this->markerPath();

        if (!file_exists($markerPath)) {
            return null;
        }

        if (is_link($markerPath) || !is_file($markerPath) || !is_readable($markerPath)) {
            throw new InstallationException('Installation marker is invalid.');
        }

        $storagePath = realpath($this->storagePath);
        $resolvedMarkerPath = realpath($markerPath);

        if (
            $storagePath === false
            || $resolvedMarkerPath === false
            || !str_starts_with($resolvedMarkerPath, rtrim($storagePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
        ) {
            throw new InstallationException('Installation marker is invalid.');
        }

        $size = @filesize($resolvedMarkerPath);

        if (!is_int($size) || $size < 1 || $size > self::MAX_MARKER_BYTES) {
            throw new InstallationException('Installation marker is invalid.');
        }

        $contents = @file_get_contents($resolvedMarkerPath);

        if (!is_string($contents)) {
            throw new InstallationException('Installation marker could not be read.');
        }

        try {
            $marker = json_decode($contents, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InstallationException('Installation marker is invalid.');
        }

        if (!is_array($marker)) {
            throw new InstallationException('Installation marker is invalid.');
        }

        $version = $marker['version'] ?? null;
        $installedAt = $marker['installed_at'] ?? null;

        if (
            count($marker) !== 2
            || !array_key_exists('version', $marker)
            || !array_key_exists('installed_at', $marker)
            || !is_string($version)
            || !preg_match(self::VERSION_PATTERN, $version)
            || !is_string($installedAt)
            || DateTimeImmutable::createFromFormat(DATE_ATOM, $installedAt) === false
        ) {
            throw new InstallationException('Installation marker is invalid.');
        }

        return [
            'version' => $version,
            'installed_at' => $installedAt,
        ];
    }

    public function storageIsWritable(): bool
    {
        return is_dir($this->storagePath) && is_writable($this->storagePath);
    }

    public function createMarker(string $version): void
    {
        if (!preg_match(self::VERSION_PATTERN, $version)) {
            throw new InstallationException('Installation marker version is invalid.');
        }

        if (!$this->storageIsWritable()) {
            throw new InstallationException('Installer storage is unavailable.');
        }

        if (file_exists($this->markerPath())) {
            throw new InstallationException('Installation marker already exists.');
        }

        try {
            $contents = json_encode([
                'installed_at' => gmdate(DATE_ATOM),
                'version' => $version,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        } catch (JsonException) {
            throw new InstallationException('Installation marker could not be prepared.');
        }

        $temporaryPath = @tempnam($this->storagePath, '.installed-');

        if (!is_string($temporaryPath)) {
            throw new InstallationException('Installation marker could not be prepared.');
        }

        try {
            @chmod($temporaryPath, 0644);
            $this->writeComplete($temporaryPath, $contents);

            if (file_exists($this->markerPath()) || !@rename($temporaryPath, $this->markerPath())) {
                throw new InstallationException('Installation marker could not be finalized.');
            }

            $temporaryPath = '';
            $this->readMarker();
        } finally {
            if ($temporaryPath !== '' && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function markerPath(): string
    {
        return $this->storagePath . DIRECTORY_SEPARATOR . 'installed.lock';
    }

    private function writeComplete(string $path, string $contents): void
    {
        $handle = @fopen($path, 'wb');

        if (!is_resource($handle)) {
            throw new InstallationException('Installation marker could not be written.');
        }

        try {
            $length = strlen($contents);
            $offset = 0;

            while ($offset < $length) {
                $written = @fwrite($handle, substr($contents, $offset));

                if (!is_int($written) || $written < 1) {
                    throw new InstallationException('Installation marker could not be written.');
                }

                $offset += $written;
            }

            if (!@fflush($handle)) {
                throw new InstallationException('Installation marker could not be written.');
            }

            if (function_exists('fsync') && !@fsync($handle)) {
                throw new InstallationException('Installation marker could not be written.');
            }
        } finally {
            fclose($handle);
        }
    }
}
