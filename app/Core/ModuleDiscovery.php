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
            listeners: isset($metadata['listeners']) ? (string) $metadata['listeners'] : null,
            requires: isset($metadata['requires']) && is_array($metadata['requires']) ? $metadata['requires'] : [],
            permissions: isset($metadata['permissions']) && is_array($metadata['permissions']) ? $metadata['permissions'] : []
        );
    }

    private function validateMetadata(string $folderName, array $metadata): ?string
    {
        foreach (['name', 'title', 'version'] as $field) {
            if (!isset($metadata[$field]) || !is_string($metadata[$field]) || trim($metadata[$field]) === '') {
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

        if (isset($metadata['routes']) && (!is_string($metadata['routes']) || !$this->isSafeRelativePath($metadata['routes']))) {
            return 'Module routes path must be a safe relative path inside the module folder.';
        }

        if (array_key_exists('listeners', $metadata)) {
            if (!is_string($metadata['listeners']) || trim($metadata['listeners']) === '') {
                return 'Module listeners path must be a non-empty string.';
            }

            if (!$this->isSafeListenerPath($metadata['listeners'])) {
                return 'Module listeners path must be a safe relative path inside the module folder.';
            }
        }

        if (array_key_exists('requires', $metadata)) {
            if (!is_array($metadata['requires'])) {
                return 'Module requires metadata must be an object.';
            }

            if (!array_key_exists('modules', $metadata['requires'])) {
                return 'Module requires.modules metadata is required.';
            }

            if (!is_array($metadata['requires']['modules'])) {
                return 'Module requires.modules metadata must be an array.';
            }

            if (array_diff(array_keys($metadata['requires']), ['modules']) !== []) {
                return 'Module requires metadata contains unsupported keys.';
            }
        }

        if (array_key_exists('permissions', $metadata)) {
            if (!is_array($metadata['permissions'])) {
                return 'Module permissions metadata must be an array.';
            }

            foreach ($metadata['permissions'] as $permission) {
                if (!is_array($permission)
                    || !isset($permission['slug'], $permission['name'])
                    || !is_string($permission['slug'])
                    || !is_string($permission['name'])
                    || trim($permission['slug']) === ''
                    || trim($permission['name']) === '') {
                    return 'Module permission metadata must contain string slug and name values.';
                }
            }
        }

        return null;
    }

    private function isSafeRelativePath(string $path): bool
    {
        $path = str_replace('\\', '/', $path);

        if (
            str_contains($path, "\0")
            || str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1
        ) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return $path !== '';
    }

    private function isSafeListenerPath(string $path): bool
    {
        if (
            str_contains($path, "\0")
            || str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1
        ) {
            return false;
        }

        $segments = explode('/', str_replace('\\', '/', $path));

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    private function addError(string $module, string $message): void
    {
        $this->errors[] = [
            'module' => $module,
            'error' => $message,
        ];
    }
}
