<?php

namespace Copot\Core;

use JsonException;
use stdClass;

class ThemeDiscovery
{
    public function __construct(private string $themesPath)
    {
    }

    public function discover(): array
    {
        $themesPath = realpath($this->themesPath);

        if ($themesPath === false || !is_dir($themesPath)) {
            throw new ThemeException("Themes directory [{$this->themesPath}] was not found.");
        }

        $themes = [];
        $directories = glob(rtrim($themesPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];

        foreach ($directories as $directory) {
            $themePath = realpath($directory);

            if ($themePath === false || !is_dir($themePath)) {
                throw new ThemeException("Theme directory [{$directory}] was not found.");
            }

            if (!$this->isInsideDirectory($themePath, $themesPath)) {
                throw new ThemeException("Theme directory [{$directory}] is outside the themes directory.");
            }

            $themeJson = $themePath . DIRECTORY_SEPARATOR . 'theme.json';

            if (!is_file($themeJson)) {
                continue;
            }

            $theme = $this->loadTheme($themePath, $themeJson);

            if (isset($themes[$theme->id()])) {
                throw new ThemeException("Duplicate theme ID [{$theme->id()}] was found.");
            }

            $themes[$theme->id()] = $theme;
        }

        ksort($themes);

        return array_values($themes);
    }

    private function loadTheme(string $themePath, string $themeJson): ThemeDefinition
    {
        $contents = file_get_contents($themeJson);

        if ($contents === false) {
            throw new ThemeException('Unable to read theme.json.');
        }

        try {
            $metadataObject = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
            $metadata = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ThemeException('theme.json contains invalid JSON.');
        }

        if (!$metadataObject instanceof stdClass || !is_array($metadata)) {
            throw new ThemeException('theme.json must contain valid JSON object metadata.');
        }

        $metadata = $this->normalizeMetadata($metadata, $metadataObject);

        $this->validateLayout($themePath, $metadata['entry']['layout'], $metadata['id']);

        return new ThemeDefinition(
            id: $metadata['id'],
            name: $metadata['name'],
            version: $metadata['version'],
            type: $metadata['type'],
            path: $themePath,
            layout: $metadata['entry']['layout'],
            description: $metadata['description'],
            author: $metadata['author'],
            supports: $metadata['supports'],
            metadata: $metadata
        );
    }

    private function normalizeMetadata(array $metadata, stdClass $metadataObject): array
    {
        foreach (['id', 'name', 'version', 'type'] as $field) {
            if (!$this->hasRequiredString($metadata, $field)) {
                throw new ThemeException("Missing required field [{$field}].");
            }
        }

        if (!isset($metadataObject->entry) || !$metadataObject->entry instanceof stdClass || !isset($metadata['entry']) || !is_array($metadata['entry'])) {
            throw new ThemeException('Missing required field [entry.layout].');
        }

        if (!$this->hasRequiredString($metadata['entry'], 'layout')) {
            throw new ThemeException('Missing required field [entry.layout].');
        }

        $this->validateOptionalString($metadata, 'description');
        $this->validateOptionalString($metadata, 'author');
        $this->validateSupports($metadataObject, $metadata);

        $metadata['id'] = trim($metadata['id']);
        $metadata['name'] = trim($metadata['name']);
        $metadata['version'] = trim($metadata['version']);
        $metadata['type'] = trim($metadata['type']);
        $metadata['entry']['layout'] = trim($metadata['entry']['layout']);
        $metadata['description'] = $this->optionalString($metadata, 'description');
        $metadata['author'] = $this->optionalString($metadata, 'author');
        $metadata['supports'] = isset($metadata['supports']) ? $metadata['supports'] : [];

        if (!preg_match('/^[a-z0-9-]+$/', $metadata['id'])) {
            throw new ThemeException('Theme ID must be a lowercase slug using only letters, numbers, and hyphens.');
        }

        if ($metadata['type'] !== 'frontend') {
            throw new ThemeException("Unsupported theme type [{$metadata['type']}].");
        }

        if (!$this->isSafeRelativePath($metadata['entry']['layout'])) {
            throw new ThemeException('Theme layout path must be a safe relative path inside the theme folder.');
        }

        return $metadata;
    }

    private function validateLayout(string $themePath, string $layout, string $themeId): void
    {
        $layoutPath = $themePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $layout);

        if (!is_file($layoutPath)) {
            throw new ThemeException("Default layout [{$layout}] for theme [{$themeId}] was not found.");
        }

        $resolvedLayout = realpath($layoutPath);

        if ($resolvedLayout === false || !$this->isInsideDirectory($resolvedLayout, $themePath)) {
            throw new ThemeException("Default layout [{$layout}] for theme [{$themeId}] is outside the theme folder.");
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

    private function hasRequiredString(array $metadata, string $field): bool
    {
        return isset($metadata[$field])
            && is_string($metadata[$field])
            && trim($metadata[$field]) !== '';
    }

    private function validateOptionalString(array $metadata, string $field): void
    {
        if (array_key_exists($field, $metadata) && !is_string($metadata[$field])) {
            throw new ThemeException("Optional field [{$field}] must be a string.");
        }
    }

    private function optionalString(array $metadata, string $field): ?string
    {
        if (!array_key_exists($field, $metadata)) {
            return null;
        }

        $value = trim($metadata[$field]);

        return $value === '' ? null : $value;
    }

    private function validateSupports(stdClass $metadataObject, array $metadata): void
    {
        if (!property_exists($metadataObject, 'supports')) {
            return;
        }

        if (!$metadataObject->supports instanceof stdClass || !isset($metadata['supports']) || !is_array($metadata['supports'])) {
            throw new ThemeException('Optional field [supports] must be a JSON object.');
        }
    }

    private function isInsideDirectory(string $path, string $directory): bool
    {
        $directory = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $directory);
    }
}
