<?php

namespace Copot\Core;

class ModuleDiscovery
{
    private array $errors = [];

    public function __construct(private string $modulesPath)
    {
    }

    public function discover(): array
    {
        $this->errors = [];

        if (!is_dir($this->modulesPath)) {
            return [];
        }

        $modules = [];
        $directories = glob(rtrim($this->modulesPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];

        foreach ($directories as $directory) {
            $moduleJson = $directory . DIRECTORY_SEPARATOR . 'module.json';

            if (!is_file($moduleJson)) {
                continue;
            }

            $module = $this->loadModule($directory, $moduleJson);

            if ($module instanceof ModuleDefinition) {
                $modules[$module->name()] = $module;
            }
        }

        ksort($modules);

        return array_values($modules);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private function loadModule(string $directory, string $moduleJson): ?ModuleDefinition
    {
        $folderName = basename($directory);
        $contents = file_get_contents($moduleJson);

        if ($contents === false) {
            $this->addError($folderName, 'Unable to read module.json.');

            return null;
        }

        $metadata = json_decode($contents, true);

        if (!is_array($metadata)) {
            $this->addError($folderName, 'module.json must contain valid JSON object metadata.');

            return null;
        }

        $error = $this->validateMetadata($folderName, $metadata);

        if ($error !== null) {
            $this->addError($folderName, $error);

            return null;
        }

        return new ModuleDefinition(
            name: (string) $metadata['name'],
            title: (string) $metadata['title'],
            version: (string) $metadata['version'],
            path: $directory,
            description: isset($metadata['description']) ? (string) $metadata['description'] : null,
            author: isset($metadata['author']) ? (string) $metadata['author'] : null,
            routes: isset($metadata['routes']) ? (string) $metadata['routes'] : null,
            requires: isset($metadata['requires']) && is_array($metadata['requires']) ? $metadata['requires'] : [],
            permissions: isset($metadata['permissions']) && is_array($metadata['permissions']) ? $metadata['permissions'] : []
        );
    }

    private function validateMetadata(string $folderName, array $metadata): ?string
    {
        foreach (['name', 'title', 'version'] as $field) {
            if (!isset($metadata[$field]) || trim((string) $metadata[$field]) === '') {
                return "Missing required field [{$field}].";
            }
        }

        $name = (string) $metadata['name'];

        if (!preg_match('/^[a-z0-9_-]+$/', $name)) {
            return 'Module name must be a lowercase slug using only letters, numbers, underscores, or hyphens.';
        }

        if ($folderName !== $name) {
            return "Module folder [{$folderName}] must match module name [{$name}].";
        }

        if (isset($metadata['routes']) && !$this->isSafeRelativePath((string) $metadata['routes'])) {
            return 'Module routes path must be a safe relative path inside the module folder.';
        }

        return null;
    }

    private function isSafeRelativePath(string $path): bool
    {
        $path = str_replace('\\', '/', $path);

        return $path !== ''
            && !str_starts_with($path, '/')
            && !str_contains($path, '../')
            && !str_contains($path, '..\\')
            && $path !== '..';
    }

    private function addError(string $module, string $message): void
    {
        $this->errors[] = [
            'module' => $module,
            'error' => $message,
        ];
    }
}
