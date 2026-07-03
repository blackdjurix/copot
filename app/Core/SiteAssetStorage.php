<?php

namespace Copot\Core;

use finfo;
use Throwable;

final class SiteAssetStorage
{
    private const SLOT_RULES = [
        'logo' => [
            'setting' => 'logo',
            'maximum_size' => 2097152,
            'maximum_width' => 4096,
            'maximum_height' => 4096,
            'mime_extensions' => [
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
            ],
        ],
        'favicon' => [
            'setting' => 'favicon',
            'maximum_size' => 524288,
            'maximum_width' => 512,
            'maximum_height' => 512,
            'mime_extensions' => [
                'image/png' => 'png',
                'image/x-icon' => 'ico',
                'image/vnd.microsoft.icon' => 'ico',
            ],
        ],
    ];

    private string $storageRoot;

    public function __construct(
        string $storageRoot,
        private SettingsService $settings
    ) {
        $this->storageRoot = rtrim($storageRoot, '/\\');
    }

    public function store(string $slot, string $sourcePath): array
    {
        $rules = $this->rules($slot);
        $source = $this->validateSource($sourcePath, $rules);
        $slotDirectory = $this->ensureSlotDirectory($slot);
        $filename = $slot . '-' . bin2hex(random_bytes(16)) . '.' . $source['extension'];
        $destination = $this->containedCandidate($slotDirectory, $filename);
        $temporary = $this->containedCandidate(
            $slotDirectory,
            '.tmp-' . $slot . '-' . bin2hex(random_bytes(16))
        );

        $previous = $this->activeDescriptor($slot);
        $activated = false;

        try {
            $this->copyFile($sourcePath, $temporary);
            $copied = $this->validateSource($temporary, $rules);

            if ($copied['mime_type'] !== $source['mime_type'] || $copied['size'] !== $source['size']) {
                throw new SiteAssetException('Site asset changed during validation.');
            }

            if (!rename($temporary, $destination)) {
                throw new SiteAssetException('Site asset could not be activated.');
            }

            $activated = true;
            @chmod($destination, 0644);

            $descriptor = [
                'filename' => $filename,
                'mime_type' => $copied['mime_type'],
                'size' => $copied['size'],
            ];

            $this->settings->set('site', $rules['setting'], $descriptor);
            $this->deleteDescriptorFile($slot, $previous, $filename);

            return $descriptor;
        } catch (Throwable $exception) {
            if (is_file($temporary) || is_link($temporary)) {
                @unlink($temporary);
            }

            if ($activated && (is_file($destination) || is_link($destination))) {
                @unlink($destination);
            }

            if ($exception instanceof SiteAssetException || $exception instanceof SettingsException) {
                throw $exception;
            }

            throw new SiteAssetException('Site asset storage failed.', 0, $exception);
        }
    }

    public function remove(string $slot): void
    {
        $rules = $this->rules($slot);
        $previous = $this->activeDescriptor($slot);

        try {
            $this->settings->set('site', $rules['setting'], null);
        } catch (Throwable $exception) {
            if ($exception instanceof SiteAssetException || $exception instanceof SettingsException) {
                throw $exception;
            }

            throw new SiteAssetException('Site asset removal failed.', 0, $exception);
        }

        $this->deleteDescriptorFile($slot, $previous);
    }

    public function url(string $slot): ?string
    {
        return $this->resolveActive($slot) === null ? null : '/site-assets/' . $slot;
    }

    public function serve(string $slot): Response
    {
        $active = $this->resolveActive($slot);

        if ($active === null) {
            return $this->notFound();
        }

        $content = @file_get_contents($active['path']);

        if (!is_string($content)) {
            return $this->notFound();
        }

        return Response::content($content, 200, [
            'Content-Type' => $active['descriptor']['mime_type'],
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function rules(string $slot): array
    {
        if (!isset(self::SLOT_RULES[$slot])) {
            throw new SiteAssetException('Unknown site asset slot.');
        }

        return self::SLOT_RULES[$slot];
    }

    private function validateSource(string $path, array $rules): array
    {
        if ($path === '' || str_contains($path, "\0") || !is_file($path) || is_link($path)) {
            throw new SiteAssetException('Site asset source is invalid.');
        }

        $size = filesize($path);

        if (!is_int($size) || $size < 1 || $size > $rules['maximum_size']) {
            throw new SiteAssetException('Site asset size is invalid.');
        }

        if (!class_exists(finfo::class)) {
            throw new SiteAssetException('Site asset validation is unavailable.');
        }

        $detector = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $detector->file($path);

        if (!is_string($mimeType) || !isset($rules['mime_extensions'][$mimeType])) {
            throw new SiteAssetException('Site asset type is not allowed.');
        }

        $dimensions = @getimagesize($path);

        if (!is_array($dimensions)) {
            throw new SiteAssetException('Site asset image structure is invalid.');
        }

        $width = $dimensions[0] ?? null;
        $height = $dimensions[1] ?? null;

        if (
            !is_int($width)
            || !is_int($height)
            || $width < 1
            || $height < 1
            || $width > $rules['maximum_width']
            || $height > $rules['maximum_height']
        ) {
            throw new SiteAssetException('Site asset dimensions are invalid.');
        }

        return [
            'mime_type' => $mimeType,
            'extension' => $rules['mime_extensions'][$mimeType],
            'size' => $size,
        ];
    }

    private function ensureSlotDirectory(string $slot): string
    {
        $storageParent = dirname($this->storageRoot);
        $resolvedParent = realpath($storageParent);

        if ($resolvedParent === false || !is_dir($resolvedParent) || is_link($storageParent)) {
            throw new SiteAssetException('Site asset storage is unavailable.');
        }

        if (!file_exists($this->storageRoot) && !mkdir($this->storageRoot, 0755, true) && !is_dir($this->storageRoot)) {
            throw new SiteAssetException('Site asset storage is unavailable.');
        }

        if (is_link($this->storageRoot)) {
            throw new SiteAssetException('Site asset storage is unavailable.');
        }

        $resolvedRoot = realpath($this->storageRoot);

        if ($resolvedRoot === false || !$this->isInside($resolvedRoot, $resolvedParent)) {
            throw new SiteAssetException('Site asset storage is unavailable.');
        }

        $slotDirectory = $resolvedRoot . DIRECTORY_SEPARATOR . $slot;

        if (!file_exists($slotDirectory) && !mkdir($slotDirectory, 0755) && !is_dir($slotDirectory)) {
            throw new SiteAssetException('Site asset slot storage is unavailable.');
        }

        if (is_link($slotDirectory)) {
            throw new SiteAssetException('Site asset slot storage is unavailable.');
        }

        $resolvedSlot = realpath($slotDirectory);

        if ($resolvedSlot === false || !$this->isInside($resolvedSlot, $resolvedRoot)) {
            throw new SiteAssetException('Site asset slot storage is unavailable.');
        }

        return $resolvedSlot;
    }

    private function containedCandidate(string $directory, string $filename): string
    {
        if (
            $filename === ''
            || str_contains($filename, "\0")
            || str_contains($filename, '/')
            || str_contains($filename, '\\')
            || $filename === '.'
            || $filename === '..'
        ) {
            throw new SiteAssetException('Site asset filename is invalid.');
        }

        return rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
    }

    private function copyFile(string $source, string $destination): void
    {
        $input = @fopen($source, 'rb');
        $output = @fopen($destination, 'xb');

        if ($input === false || $output === false) {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
            throw new SiteAssetException('Site asset could not be written.');
        }

        try {
            $copied = stream_copy_to_stream($input, $output);

            if (!is_int($copied) || $copied < 1 || !fflush($output)) {
                throw new SiteAssetException('Site asset could not be written.');
            }
        } finally {
            fclose($input);
            fclose($output);
        }
    }

    private function activeDescriptor(string $slot): ?array
    {
        $rules = $this->rules($slot);
        $descriptor = $this->settings->get('site', $rules['setting']);

        return is_array($descriptor) ? $descriptor : null;
    }

    private function resolveActive(string $slot): ?array
    {
        $descriptor = $this->activeDescriptor($slot);

        if ($descriptor === null) {
            return null;
        }

        try {
            $rules = $this->rules($slot);
            $this->settings->validate('site', $rules['setting'], $descriptor);
            $slotDirectory = $this->existingSlotDirectory($slot);

            if ($slotDirectory === null) {
                return null;
            }

            $path = $this->containedCandidate($slotDirectory, (string) ($descriptor['filename'] ?? ''));

            if (!is_file($path) || is_link($path)) {
                return null;
            }

            $resolved = realpath($path);

            if ($resolved === false || !$this->isInside($resolved, $slotDirectory)) {
                return null;
            }

            $validated = $this->validateSource($resolved, $rules);
            $extension = strtolower(pathinfo($descriptor['filename'], PATHINFO_EXTENSION));

            if (
                $validated['size'] !== $descriptor['size']
                || $validated['mime_type'] !== $descriptor['mime_type']
                || $validated['extension'] !== $extension
            ) {
                return null;
            }

            return ['descriptor' => $descriptor, 'path' => $resolved];
        } catch (SiteAssetException|SettingsException) {
            return null;
        }
    }

    private function existingSlotDirectory(string $slot): ?string
    {
        if (!is_dir($this->storageRoot) || is_link($this->storageRoot)) {
            return null;
        }

        $resolvedRoot = realpath($this->storageRoot);
        $resolvedParent = realpath(dirname($this->storageRoot));

        if ($resolvedRoot === false || $resolvedParent === false || !$this->isInside($resolvedRoot, $resolvedParent)) {
            return null;
        }

        $slotDirectory = $resolvedRoot . DIRECTORY_SEPARATOR . $slot;

        if (!is_dir($slotDirectory) || is_link($slotDirectory)) {
            return null;
        }

        $resolvedSlot = realpath($slotDirectory);

        return $resolvedSlot !== false && $this->isInside($resolvedSlot, $resolvedRoot)
            ? $resolvedSlot
            : null;
    }

    private function deleteDescriptorFile(string $slot, ?array $descriptor, ?string $exceptFilename = null): void
    {
        if ($descriptor === null) {
            return;
        }

        $filename = $descriptor['filename'] ?? null;

        if (!is_string($filename) || $filename === $exceptFilename) {
            return;
        }

        $slotDirectory = $this->existingSlotDirectory($slot);

        if ($slotDirectory === null) {
            return;
        }

        try {
            $path = $this->containedCandidate($slotDirectory, $filename);
        } catch (SiteAssetException) {
            return;
        }

        if ((is_file($path) || is_link($path)) && !is_dir($path)) {
            @unlink($path);
        }
    }

    private function isInside(string $path, string $directory): bool
    {
        $directory = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $directory);
    }

    private function notFound(): Response
    {
        return Response::content('404 Not Found', 404, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
