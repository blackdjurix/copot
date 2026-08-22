<?php

namespace Copot\Core;

final class ModuleManifestValidator
{
    public static function validate(string $folderName, array $metadata): ?string
    {
        foreach (['name', 'title', 'version'] as $field) {
            if (!isset($metadata[$field]) || !is_string($metadata[$field]) || trim($metadata[$field]) === '') {
                return "Missing required field [{$field}].";
            }
        }

        $name = $metadata['name'];
        if (!preg_match('/^[a-z0-9_-]+$/', $name)) {
            return 'Module name must be a lowercase slug using only letters, numbers, underscores, or hyphens.';
        }
        if ($folderName !== $name) {
            return "Module folder [{$folderName}] must match module name [{$name}].";
        }

        foreach (['routes' => false, 'listeners' => true, 'frontend_context' => true, 'resolver' => true, 'schema' => false] as $field => $listenerPath) {
            if (array_key_exists($field, $metadata)
                && (!is_string($metadata[$field]) || !self::isSafePath($metadata[$field], $listenerPath))) {
                return "Module {$field} path must be a safe relative path inside the module folder.";
            }
        }

        if (array_key_exists('requires', $metadata)) {
            if (!is_array($metadata['requires']) || !array_key_exists('modules', $metadata['requires'])
                || !is_array($metadata['requires']['modules'])
                || array_diff(array_keys($metadata['requires']), ['modules']) !== []) {
                return 'Module requires metadata is invalid.';
            }
        }

        if (array_key_exists('permissions', $metadata)) {
            if (!is_array($metadata['permissions'])) {
                return 'Module permissions metadata must be an array.';
            }
            foreach ($metadata['permissions'] as $permission) {
                if (!is_array($permission) || !isset($permission['slug'], $permission['name'])
                    || !is_string($permission['slug']) || !is_string($permission['name'])
                    || trim($permission['slug']) === '' || trim($permission['name']) === '') {
                    return 'Module permission metadata must contain string slug and name values.';
                }
            }
        }

        return null;
    }

    private static function isSafePath(string $path, bool $listenerPath): bool
    {
        $path = str_replace('\\', '/', $path);
        if ($path === '' || str_contains($path, "\0") || str_starts_with($path, '/')
            || str_starts_with($path, '\\') || preg_match('/^[A-Za-z]:[\\\/]/', $path) === 1) {
            return false;
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }
        return $listenerPath ? true : $path !== '';
    }
}
