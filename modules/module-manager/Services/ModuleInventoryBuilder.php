<?php

use Copot\Core\ModuleDefinition;
use Copot\Core\ModuleDiscovery;
use Copot\Core\ModuleRepository;

final class ModuleInventoryBuilder
{
    public function __construct(
        private ModuleDiscovery $discovery,
        private ModuleRepository $repository,
        private ?ModuleActionPolicy $policy = null
    ) {
        $this->policy ??= new ModuleActionPolicy();
    }

    public function build(): array
    {
        $definitions = [];

        foreach ($this->discovery->discover() as $definition) {
            if ($definition instanceof ModuleDefinition) {
                $definitions[$definition->name()] = $definition;
            }
        }

        ksort($definitions, SORT_STRING);
        $discoveryErrors = $this->discovery->errors();
        $errorsByName = $this->indexDiscoveryErrors($discoveryErrors);
        $installed = [];

        foreach ($this->repository->all() as $row) {
            $name = (string) ($row['name'] ?? '');

            if ($name !== '') {
                $installed[$name] = $row;
            }
        }

        ksort($installed, SORT_STRING);
        $names = array_values(array_unique(array_merge(array_keys($definitions), array_keys($installed), array_keys($errorsByName))));
        sort($names, SORT_STRING);
        $items = [];

        foreach ($names as $name) {
            $definition = $definitions[$name] ?? null;
            $stored = $installed[$name] ?? null;
            $discoveryState = $this->discoveryState($definition, $errorsByName[$name] ?? []);
            $item = $this->baseItem($name, $definition, $stored, $discoveryState);
            $diagnostics = [];

            foreach ($errorsByName[$name] ?? [] as $error) {
                $diagnostics[] = [
                    'code' => $this->discoveryErrorCode((string) ($error['error'] ?? '')),
                    'severity' => 'error',
                    'message_key' => 'module.discovery.invalid',
                    'safe_parameters' => ['module' => $name],
                    'blocked_actions' => ['install', 'enable'],
                ];
            }

            if ($definition instanceof ModuleDefinition && is_array($stored)) {
                $this->addDriftDiagnostics($diagnostics, $definition, $stored);
            }

            if ($definition instanceof ModuleDefinition) {
                $item['contribution_files'] = $this->contributionFiles($definition, $diagnostics);
                $item['dependencies'] = $this->dependencies($definition, $diagnostics);
                $this->addDependencyDiagnostics(
                    $definition->name(),
                    $item['dependencies'],
                    $installed,
                    $definitions,
                    $diagnostics
                );
            }

            $item['permission_metadata_summary'] = $stored === null
                ? []
                : $this->safePermissionSummary($this->repository->permissionsFor($name));
            $item['discovered_permission_metadata_summary'] = $definition instanceof ModuleDefinition
                ? $this->safeManifestPermissionSummary($definition)
                : [];
            if (
                $definition instanceof ModuleDefinition
                && is_array($stored)
                && $item['permission_metadata_summary'] !== $item['discovered_permission_metadata_summary']
            ) {
                $diagnostics[] = [
                    'code' => 'metadata_drift',
                    'severity' => 'warning',
                    'message_key' => 'module.metadata.drift',
                    'safe_parameters' => ['field' => 'permissions'],
                    'blocked_actions' => [],
                ];
            }
            $item['stored_path_available'] = is_array($stored)
                && is_dir((string) ($stored['path'] ?? ''));
            $item['discovered_path_available'] = $definition instanceof ModuleDefinition
                && is_dir($definition->path());
            $this->addDependentDiagnostics($name, $installed, $definitions, $diagnostics);
            $item['diagnostics'] = $this->sortDiagnostics($diagnostics);
            $policy = $this->policy->evaluate($item);
            $item['available_actions'] = $policy['available_actions'];
            $item['denial_reasons'] = $policy['denial_reasons'];
            $items[] = $item;
        }

        return $items;
    }

    private function baseItem(string $name, ?ModuleDefinition $definition, ?array $stored, string $discoveryState): array
    {
        $lifecycle = 'not_installed';

        if (is_array($stored)) {
            $lifecycle = ($stored['status'] ?? null) === 'enabled'
                ? 'installed_enabled'
                : 'installed_disabled';
        }

        return [
            'name' => $name,
            'title' => $definition?->title() ?? (string) ($stored['title'] ?? $name),
            'discovered_title' => $definition?->title(),
            'stored_title' => is_array($stored) ? (string) ($stored['title'] ?? '') : null,
            'version' => $definition?->version() ?? (string) ($stored['version'] ?? ''),
            'discovered_version' => $definition?->version(),
            'stored_version' => is_array($stored) ? (string) ($stored['version'] ?? '') : null,
            'lifecycle_state' => $lifecycle,
            'discovery_state' => $discoveryState,
            'dependencies' => [],
            'permission_metadata_summary' => [],
            'discovered_permission_metadata_summary' => [],
            'stored_path_available' => false,
            'discovered_path_available' => false,
            'contribution_files' => [],
            'diagnostics' => [],
            'available_actions' => [],
            'denial_reasons' => [],
        ];
    }

    private function indexDiscoveryErrors(array $errors): array
    {
        $indexed = [];

        foreach ($errors as $error) {
            $name = (string) ($error['module'] ?? '');

            if ($name !== '') {
                $indexed[$name][] = $error;
            }
        }

        ksort($indexed, SORT_STRING);

        return $indexed;
    }

    private function discoveryState(?ModuleDefinition $definition, array $errors): string
    {
        if ($definition instanceof ModuleDefinition) {
            return 'valid';
        }

        if ($errors === []) {
            return 'missing';
        }

        $error = strtolower((string) ($errors[0]['error'] ?? ''));

        return str_contains($error, 'missing required field') || str_contains($error, 'must be')
            ? 'invalid_metadata'
            : 'malformed';
    }

    private function discoveryErrorCode(string $error): string
    {
        $error = strtolower($error);

        return str_contains($error, 'valid json') ? 'malformed_discovery' : 'invalid_metadata';
    }

    private function addDriftDiagnostics(array &$diagnostics, ModuleDefinition $definition, array $stored): void
    {
        foreach ([
            'title' => [$stored['title'] ?? '', $definition->title()],
            'version' => [$stored['version'] ?? '', $definition->version()],
            'path' => [$stored['path'] ?? '', $definition->path()],
        ] as $field => [$storedValue, $discoveredValue]) {
            if ((string) $storedValue !== (string) $discoveredValue) {
                $diagnostics[] = [
                    'code' => 'metadata_drift',
                    'severity' => 'warning',
                    'message_key' => 'module.metadata.drift',
                    'safe_parameters' => ['field' => $field],
                    'blocked_actions' => [],
                ];
            }
        }

        if (!is_dir((string) ($stored['path'] ?? ''))) {
            $diagnostics[] = [
                'code' => 'stored_path_unavailable',
                'severity' => 'warning',
                'message_key' => 'module.path.unavailable',
                'safe_parameters' => [],
                'blocked_actions' => ['enable'],
            ];
        }
    }

    private function contributionFiles(ModuleDefinition $definition, array &$diagnostics): array
    {
        $files = [];

        foreach (['routes' => 'route_file_missing', 'listeners' => 'listener_file_missing'] as $field => $code) {
            $relative = $definition->{$field}();
            $available = is_string($relative) && $relative !== ''
                ? is_file($definition->path() . DIRECTORY_SEPARATOR . $relative)
                : true;
            $files[$field] = [
                'declared' => is_string($relative) && $relative !== '',
                'available' => $available,
            ];

            if (is_string($relative) && $relative !== '' && !$available) {
                $diagnostics[] = [
                    'code' => $code,
                    'severity' => 'error',
                    'message_key' => 'module.contribution.missing',
                    'safe_parameters' => ['type' => $field],
                    'blocked_actions' => ['enable'],
                ];
            }
        }

        return $files;
    }

    private function dependencies(ModuleDefinition $definition, array &$diagnostics): array
    {
        $raw = $definition->requires()['modules'] ?? [];
        $dependencies = [];
        $seen = [];

        if (!is_array($raw)) {
            $diagnostics[] = $this->diagnostic('duplicate_dependency', 'error', ['enable']);

            return [];
        }

        foreach ($raw as $entry) {
            $name = is_array($entry) ? (string) ($entry['name'] ?? '') : (string) $entry;

            if (is_array($entry) && count($entry) > 1) {
                $diagnostics[] = $this->diagnostic('unsupported_version_constraint', 'error', ['enable']);
            }

            if ($name === $definition->name()) {
                $diagnostics[] = $this->diagnostic('self_dependency', 'error', ['enable']);
            }

            if ($name !== '' && isset($seen[$name])) {
                $diagnostics[] = $this->diagnostic('duplicate_dependency', 'error', ['enable']);
            }

            if ($name !== '') {
                $seen[$name] = true;
                $dependencies[] = ['name' => $name];
            }
        }

        usort($dependencies, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']));

        return $dependencies;
    }

    private function addDependencyDiagnostics(string $moduleName, array $dependencies, array $installed, array $definitions, array &$diagnostics): void
    {
        foreach ($dependencies as $dependency) {
            $name = $dependency['name'];

            if (!isset($installed[$name])) {
                $diagnostics[] = $this->diagnostic('dependency_missing', 'error', ['enable']);
            } elseif (($installed[$name]['status'] ?? null) !== 'enabled') {
                $diagnostics[] = $this->diagnostic('dependency_disabled', 'error', ['enable']);
            }
        }

        $graph = [];

        foreach ($definitions as $name => $definition) {
            $ignoredDiagnostics = [];
            $graph[$name] = array_map(
                static fn (array $dependency): string => $dependency['name'],
                $this->dependencies($definition, $ignoredDiagnostics)
            );
        }

        if ($this->hasCycle($moduleName, $graph, [], [])) {
            $diagnostics[] = $this->diagnostic('dependency_cycle', 'error', ['enable']);
        }
    }

    private function addDependentDiagnostics(string $name, array $installed, array $definitions, array &$diagnostics): void
    {
        foreach ($installed as $enabledName => $row) {
            if (($row['status'] ?? null) !== 'enabled' || $enabledName === $name) {
                continue;
            }

            if (!isset($definitions[$enabledName])) {
                $diagnostics[] = $this->diagnostic('dependent_safety_unknown', 'error', ['disable', 'uninstall']);
                continue;
            }

            $ignoredDiagnostics = [];
            $dependent = $this->dependencies($definitions[$enabledName], $ignoredDiagnostics);

            foreach ($dependent as $dependency) {
                if ($dependency['name'] === $name) {
                    $diagnostics[] = $this->diagnostic('enabled_dependent', 'error', ['disable', 'uninstall']);
                }
            }
        }
    }

    private function hasCycle(string $name, array $graph, array $visiting, array $visited): bool
    {
        if (isset($visiting[$name])) {
            return true;
        }

        if (isset($visited[$name]) || !isset($graph[$name])) {
            return false;
        }

        $visiting[$name] = true;

        foreach ($graph[$name] as $dependency) {
            if ($this->hasCycle($dependency, $graph, $visiting, $visited)) {
                return true;
            }
        }

        return false;
    }

    private function safePermissionSummary(array $permissions): array
    {
        $summary = [];

        foreach ($permissions as $permission) {
            $summary[] = [
                'slug' => (string) ($permission['permission_slug'] ?? ''),
                'name' => (string) ($permission['permission_name'] ?? ''),
            ];
        }

        usort($summary, static fn (array $left, array $right): int => strcmp($left['slug'], $right['slug']));

        return $summary;
    }

    private function safeManifestPermissionSummary(ModuleDefinition $definition): array
    {
        $summary = [];

        foreach ($definition->permissions() as $permission) {
            if (!is_array($permission) || !isset($permission['slug'], $permission['name'])) {
                continue;
            }

            $summary[] = [
                'slug' => (string) $permission['slug'],
                'name' => (string) $permission['name'],
            ];
        }

        usort($summary, static fn (array $left, array $right): int => strcmp($left['slug'], $right['slug']));

        return $summary;
    }

    private function diagnostic(string $code, string $severity, array $blockedActions): array
    {
        return [
            'code' => $code,
            'severity' => $severity,
            'message_key' => 'module.' . $code,
            'safe_parameters' => [],
            'blocked_actions' => $blockedActions,
        ];
    }

    private function sortDiagnostics(array $diagnostics): array
    {
        $priority = [
            'malformed_discovery' => 10,
            'invalid_metadata' => 10,
            'discovery_missing' => 10,
            'metadata_drift' => 20,
            'stored_path_unavailable' => 20,
            'route_file_missing' => 30,
            'listener_file_missing' => 30,
            'self_dependency' => 40,
            'duplicate_dependency' => 40,
            'unsupported_version_constraint' => 40,
            'dependency_missing' => 50,
            'dependency_disabled' => 50,
            'dependency_cycle' => 50,
            'enabled_dependent' => 60,
            'dependent_safety_unknown' => 60,
        ];

        usort($diagnostics, static function (array $left, array $right) use ($priority): int {
            $leftPriority = $priority[$left['code']] ?? 99;
            $rightPriority = $priority[$right['code']] ?? 99;

            return $leftPriority <=> $rightPriority ?: strcmp($left['code'], $right['code']);
        });

        return $diagnostics;
    }
}
