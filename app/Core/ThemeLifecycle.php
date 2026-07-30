<?php

namespace Copot\Core;

use JsonException;
use Throwable;

class ThemeLifecycle
{
    public function __construct(
        private ThemeDiscovery $discovery,
        private ThemeManager $themes,
        private ThemeRepository $repository,
        private Database $database
    ) {
    }

    /**
     * Join fresh filesystem discovery with the persisted registry and active state.
     * Reads do not register, refresh, activate, deactivate, or delete themes.
     *
     * @return array{themes: list<array<string, mixed>>, diagnostics: list<array{theme: ?string, status: string, code: string, message: string}>}
     */
    public function inventory(): array
    {
        $catalog = $this->discovery->discoverCatalog();
        $registryRows = $this->repository->all();
        $registry = [];

        foreach ($registryRows as $row) {
            $themeId = is_string($row['theme_id'] ?? null) ? $row['theme_id'] : null;
            if ($themeId !== null && preg_match('/^[a-z0-9-]+$/', $themeId) === 1) {
                $registry[$themeId] = $row;
            }
        }

        $errorsByTheme = [];
        $diagnostics = [];
        foreach ($catalog['errors'] as $error) {
            $themeId = $error['theme'] ?? null;
            if (is_string($themeId)) {
                $errorsByTheme[$themeId][] = $error;
            } else {
                $diagnostics[] = $this->diagnostic($error);
            }
        }

        $items = [];
        $seen = [];
        foreach ($catalog['themes'] as $definition) {
            $themeId = $definition->id();
            $seen[$themeId] = true;
            $row = $registry[$themeId] ?? null;
            $blocked = isset($errorsByTheme[$themeId]);
            $items[] = $this->item(
                $themeId,
                $blocked ? null : $definition,
                $row,
                $blocked ? 'invalid' : 'healthy',
                $blocked ? $errorsByTheme[$themeId][0] : null
            );
        }

        foreach ($errorsByTheme as $themeId => $errors) {
            if (isset($seen[$themeId])) {
                continue;
            }

            $seen[$themeId] = true;
            $items[] = $this->item($themeId, null, $registry[$themeId] ?? null, $errors[0]['status'], $errors[0]);
        }

        $rootUnavailable = false;
        foreach ($catalog['errors'] as $error) {
            if (($error['code'] ?? null) === 'themes_path_unavailable' && ($error['theme'] ?? null) === null) {
                $rootUnavailable = true;
                break;
            }
        }

        foreach ($registry as $themeId => $row) {
            if (isset($seen[$themeId])) {
                continue;
            }

            $seen[$themeId] = true;
            $status = $rootUnavailable ? 'unavailable' : 'missing';
            $error = $rootUnavailable
                ? ($catalog['errors'][0] ?? null)
                : [
                    'theme' => $themeId,
                    'status' => 'missing',
                    'code' => 'registered_theme_missing',
                    'message' => 'Registered theme is missing from discovery.',
                ];
            $items[] = $this->item($themeId, null, $row, $status, $error);
        }

        usort($items, static fn (array $left, array $right): int => $left['theme_id'] <=> $right['theme_id']);
        usort($diagnostics, static fn (array $left, array $right): int => [$left['code'], (string) ($left['theme'] ?? '')] <=> [$right['code'], (string) ($right['theme'] ?? '')]);

        return ['themes' => $items, 'diagnostics' => $diagnostics];
    }

    public function activate(string $themeId): void
    {
        if (preg_match('/^[a-z0-9-]+$/', $themeId) !== 1) {
            throw new ThemeException('Theme activation target is invalid.');
        }

        $catalog = $this->discovery->discoverCatalog();
        $targetErrors = array_values(array_filter(
            $catalog['errors'],
            static fn (array $error): bool => ($error['theme'] ?? null) === $themeId
        ));
        if ($targetErrors !== []) {
            throw new ThemeException('Theme activation target is not healthy.');
        }

        $definitions = array_values(array_filter(
            $catalog['themes'],
            static fn (ThemeDefinition $definition): bool => $definition->id() === $themeId
        ));
        if (count($definitions) !== 1 || $definitions[0]->type() !== 'frontend') {
            throw new ThemeException('Theme activation target is unavailable.');
        }

        $connection = $this->database->connection();

        try {
            $connection->beginTransaction();
            $this->themes->register($definitions[0]);
            $this->repository->deactivateByType('frontend');
            $this->repository->activate($themeId);

            $active = $this->repository->activeFrontendRows();
            if (count($active) !== 1 || ($active[0]['theme_id'] ?? null) !== $themeId) {
                throw new ThemeException('Theme activation postcondition failed.');
            }

            $registered = $this->repository->findByThemeId($themeId);
            if (!$this->matchesDefinition($registered, $definitions[0])) {
                throw new ThemeException('Theme registry refresh postcondition failed.');
            }

            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception instanceof ThemeException
                ? $exception
                : new ThemeException('Theme activation failed.', 0, $exception);
        }
    }

    private function item(string $themeId, ?ThemeDefinition $definition, ?array $row, string $discoveryStatus, ?array $error): array
    {
        $registered = $row !== null;
        $active = $registered && (int) ($row['is_active'] ?? 0) === 1;
        $lifecycle = match ($discoveryStatus) {
            'healthy' => $active ? 'active' : ($registered ? 'inactive' : 'discovered'),
            'missing' => 'stale',
            'invalid' => 'invalid',
            'unavailable' => 'unavailable',
            default => 'unavailable',
        };

        return [
            'theme_id' => $themeId,
            'discovery_status' => $discoveryStatus,
            'registration_status' => $registered ? 'registered' : 'unregistered',
            'activation_status' => $active ? 'active' : 'inactive',
            'lifecycle_state' => $lifecycle,
            'definition' => $definition,
            'registry' => $this->safeRegistry($row),
            'diagnostic' => $error === null ? null : $this->diagnostic($error),
        ];
    }

    private function safeRegistry(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        $path = is_string($row['path'] ?? null) && $this->isSafeRelativePath($row['path'])
            ? str_replace('\\', '/', trim($row['path']))
            : null;

        return [
            'theme_id' => is_string($row['theme_id'] ?? null) ? $row['theme_id'] : null,
            'name' => is_string($row['name'] ?? null) ? $row['name'] : null,
            'version' => is_string($row['version'] ?? null) ? $row['version'] : null,
            'type' => is_string($row['type'] ?? null) ? $row['type'] : null,
            'path' => $path,
            'is_active' => (int) ($row['is_active'] ?? 0) === 1,
        ];
    }

    private function diagnostic(array $error): array
    {
        return [
            'theme' => isset($error['theme']) && is_string($error['theme']) ? $error['theme'] : null,
            'status' => is_string($error['status'] ?? null) ? $error['status'] : 'invalid',
            'code' => is_string($error['code'] ?? null) ? $error['code'] : 'theme_catalog_error',
            'message' => is_string($error['message'] ?? null) ? $error['message'] : 'Theme catalog inspection failed.',
        ];
    }

    private function matchesDefinition(?array $row, ThemeDefinition $definition): bool
    {
        if ($row === null || ($row['theme_id'] ?? null) !== $definition->id()
            || ($row['name'] ?? null) !== $definition->name()
            || ($row['version'] ?? null) !== $definition->version()
            || ($row['type'] ?? null) !== $definition->type()) {
            return false;
        }

        try {
            return json_decode((string) ($row['metadata'] ?? ''), true, 512, JSON_THROW_ON_ERROR) === $definition->metadata();
        } catch (JsonException) {
            return false;
        }
    }

    private function isSafeRelativePath(string $path): bool
    {
        $path = str_replace('\\', '/', trim($path));

        if ($path === '' || str_starts_with($path, '/') || str_contains($path, "\0") || preg_match('/^[A-Za-z]:\//', $path)) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }
}
