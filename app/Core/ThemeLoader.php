<?php

namespace Copot\Core;

use JsonException;

class ThemeLoader
{
    public function __construct(
        private ThemeRepository $themes,
        private string $projectRoot
    ) {
        $this->projectRoot = rtrim($projectRoot, DIRECTORY_SEPARATOR);
    }

    public function activeTheme(): array
    {
        $theme = $this->themes->activeFrontend();

        if ($theme === null) {
            throw new ThemeException('No active frontend theme is registered.');
        }

        $themePath = $this->resolveThemePath($theme);
        $metadata = $this->decodeMetadata($theme);
        $this->defaultLayout($metadata);

        return [
            'theme_id' => (string) $theme['theme_id'],
            'name' => (string) $theme['name'],
            'version' => (string) $theme['version'],
            'type' => (string) $theme['type'],
            'path' => (string) $theme['path'],
            'metadata' => $metadata,
            'theme_path' => $themePath,
        ];
    }

    public function themePath(): string
    {
        return $this->activeTheme()['theme_path'];
    }

    public function layoutPath(?string $layout = null): string
    {
        $theme = $this->activeTheme();
        $layout = $layout === null ? $this->defaultLayout($theme['metadata']) : trim($layout);

        return $this->resolveLayoutPath($theme['theme_path'], $layout, $theme['theme_id']);
    }

    private function resolveThemePath(array $theme): string
    {
        if (!isset($theme['path']) || !is_string($theme['path']) || trim($theme['path']) === '') {
            throw new ThemeException('Active theme path is missing.');
        }

        $relativePath = trim($theme['path']);

        if (!$this->isSafeRelativePath($relativePath)) {
            throw new ThemeException("Active theme path [{$relativePath}] must be a safe relative path.");
        }

        $projectRoot = realpath($this->projectRoot);

        if ($projectRoot === false || !is_dir($projectRoot)) {
            throw new ThemeException("Project root [{$this->projectRoot}] was not found.");
        }

        $path = $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        $resolvedPath = realpath($path);

        if ($resolvedPath === false || !is_dir($resolvedPath)) {
            throw new ThemeException("Active theme path [{$relativePath}] was not found.");
        }

        if (!$this->isInsideDirectory($resolvedPath, $projectRoot)) {
            throw new ThemeException("Active theme path [{$relativePath}] is outside the project root.");
        }

        return $resolvedPath;
    }

    private function decodeMetadata(array $theme): array
    {
        if (!isset($theme['metadata']) || !is_string($theme['metadata']) || trim($theme['metadata']) === '') {
            throw new ThemeException('Active theme metadata is missing.');
        }

        try {
            $metadata = json_decode($theme['metadata'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ThemeException('Active theme metadata contains invalid JSON.');
        }

        if (!is_array($metadata) || $this->isListArray($metadata)) {
            throw new ThemeException('Active theme metadata must be a JSON object.');
        }

        return $metadata;
    }

    private function defaultLayout(array $metadata): string
    {
        if (!isset($metadata['entry']) || !is_array($metadata['entry']) || $this->isListArray($metadata['entry'])) {
            throw new ThemeException('Active theme metadata is missing entry.layout.');
        }

        if (!isset($metadata['entry']['layout']) || !is_string($metadata['entry']['layout']) || trim($metadata['entry']['layout']) === '') {
            throw new ThemeException('Active theme metadata is missing entry.layout.');
        }

        return trim($metadata['entry']['layout']);
    }

    private function resolveLayoutPath(string $themePath, string $layout, string $themeId): string
    {
        if (!$this->isSafeRelativePath($layout)) {
            throw new ThemeException("Theme [{$themeId}] layout path must be safe.");
        }

        $layoutPath = $themePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $layout);

        if (!is_file($layoutPath)) {
            throw new ThemeException("Theme [{$themeId}] layout [{$layout}] was not found.");
        }

        $resolvedLayoutPath = realpath($layoutPath);

        if ($resolvedLayoutPath === false || !$this->isInsideDirectory($resolvedLayoutPath, $themePath)) {
            throw new ThemeException("Theme [{$themeId}] layout [{$layout}] is outside the theme folder.");
        }

        return $resolvedLayoutPath;
    }

    private function isSafeRelativePath(string $path): bool
    {
        $path = str_replace('\\', '/', $path);

        if ($path === '' || str_starts_with($path, '/') || str_contains($path, "\0") || preg_match('/^[A-Za-z]:\//', $path)) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                return false;
            }
        }

        return true;
    }

    private function isInsideDirectory(string $path, string $directory): bool
    {
        $directory = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $directory);
    }

    private function isListArray(array $value): bool
    {
        return array_keys($value) === range(0, count($value) - 1);
    }
}
