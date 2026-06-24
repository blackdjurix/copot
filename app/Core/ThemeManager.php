<?php

namespace Copot\Core;

use JsonException;
use Throwable;

class ThemeManager
{
    public function __construct(
        private ThemeRepository $themes,
        private Database $database,
        private string $projectRoot
    ) {
        $this->projectRoot = rtrim($projectRoot, DIRECTORY_SEPARATOR);
    }

    public function register(ThemeDefinition $theme): void
    {
        $this->themes->register([
            'theme_id' => $theme->id(),
            'name' => $theme->name(),
            'version' => $theme->version(),
            'type' => $theme->type(),
            'path' => $this->relativeProjectPath($theme->path()),
            'metadata' => $theme->metadata(),
        ]);
    }

    public function activate(string $themeId): void
    {
        $theme = $this->themes->findByThemeId($themeId);

        if ($theme === null) {
            throw new ThemeException("Theme [{$themeId}] is not registered.");
        }

        if (($theme['type'] ?? null) !== 'frontend') {
            throw new ThemeException("Theme [{$themeId}] is not a frontend theme.");
        }

        $this->validateActiveThemeFiles($theme);

        $connection = $this->database->connection();

        try {
            $connection->beginTransaction();
            $this->themes->deactivateByType('frontend');
            $this->themes->activate($themeId);
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public function unregister(string $themeId): void
    {
        $theme = $this->themes->findByThemeId($themeId);

        if ($theme === null) {
            return;
        }

        if ((int) ($theme['is_active'] ?? 0) === 1) {
            throw new ThemeException("Active theme [{$themeId}] cannot be unregistered.");
        }

        $this->themes->unregister($themeId);
    }

    private function relativeProjectPath(string $path): string
    {
        $projectRoot = realpath($this->projectRoot);
        $themePath = realpath($path);

        if ($projectRoot === false || !is_dir($projectRoot)) {
            throw new ThemeException("Project root [{$this->projectRoot}] was not found.");
        }

        if ($themePath === false || !is_dir($themePath)) {
            throw new ThemeException("Theme path [{$path}] was not found.");
        }

        if (!$this->isInsideDirectory($themePath, $projectRoot)) {
            throw new ThemeException("Theme path [{$path}] is outside the project root.");
        }

        $relative = substr($themePath, strlen(rtrim($projectRoot, DIRECTORY_SEPARATOR)) + 1);

        return str_replace(DIRECTORY_SEPARATOR, '/', $relative);
    }

    private function resolveThemePath(array $theme): string
    {
        if (!isset($theme['path']) || !is_string($theme['path']) || trim($theme['path']) === '') {
            throw new ThemeException('Registered theme path is missing.');
        }

        $relativePath = trim($theme['path']);

        if (!$this->isSafeRelativePath($relativePath)) {
            throw new ThemeException("Registered theme path [{$relativePath}] must be a safe relative path.");
        }

        $path = $this->projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        $resolvedPath = realpath($path);
        $projectRoot = realpath($this->projectRoot);

        if ($projectRoot === false || !is_dir($projectRoot)) {
            throw new ThemeException("Project root [{$this->projectRoot}] was not found.");
        }

        if ($resolvedPath === false || !is_dir($resolvedPath)) {
            throw new ThemeException("Registered theme path [{$relativePath}] was not found.");
        }

        if (!$this->isInsideDirectory($resolvedPath, $projectRoot)) {
            throw new ThemeException("Registered theme path [{$relativePath}] is outside the project root.");
        }

        return $resolvedPath;
    }

    private function decodeMetadata(array $theme): array
    {
        if (!isset($theme['metadata']) || !is_string($theme['metadata']) || trim($theme['metadata']) === '') {
            throw new ThemeException('Registered theme metadata is missing.');
        }

        try {
            $metadata = json_decode($theme['metadata'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ThemeException('Registered theme metadata contains invalid JSON.');
        }

        if (!is_array($metadata)) {
            throw new ThemeException('Registered theme metadata must be a JSON object.');
        }

        return $metadata;
    }

    private function validateActiveThemeFiles(array $theme): void
    {
        $themeId = (string) ($theme['theme_id'] ?? 'unknown');
        $themePath = $this->resolveThemePath($theme);
        $metadata = $this->decodeMetadata($theme);

        if (!isset($metadata['entry']) || !is_array($metadata['entry'])) {
            throw new ThemeException("Registered theme [{$themeId}] metadata is missing entry.layout.");
        }

        if (!isset($metadata['entry']['layout']) || !is_string($metadata['entry']['layout']) || trim($metadata['entry']['layout']) === '') {
            throw new ThemeException("Registered theme [{$themeId}] metadata is missing entry.layout.");
        }

        $layout = trim($metadata['entry']['layout']);

        if (!$this->isSafeRelativePath($layout)) {
            throw new ThemeException("Registered theme [{$themeId}] layout path must be safe.");
        }

        $layoutPath = $themePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $layout);

        if (!is_file($layoutPath)) {
            throw new ThemeException("Registered theme [{$themeId}] layout [{$layout}] was not found.");
        }

        $resolvedLayoutPath = realpath($layoutPath);

        if ($resolvedLayoutPath === false || !$this->isInsideDirectory($resolvedLayoutPath, $themePath)) {
            throw new ThemeException("Registered theme [{$themeId}] layout [{$layout}] is outside the theme folder.");
        }
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
}
