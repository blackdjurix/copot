<?php

namespace Copot\Core;

/**
 * Canonical System Manager Module inventory and lifecycle eligibility
 * projection. It consumes Core discovery/repository evidence and delegates
 * package planning to the Core-owned ModulePackageOperator.
 */
final class SystemManagerModuleFallback
{
    public function __construct(
        private ModuleDiscovery $discovery,
        private ModuleRepository $repository,
        private ?ModulePackageOperator $packages = null
    ) {
    }

    public function inventory(): array
    {
        $definitions = [];
        foreach ($this->discovery->discover() as $definition) if ($definition instanceof ModuleDefinition) $definitions[$definition->name()] = $definition;
        $errors = [];
        foreach ($this->discovery->errors() as $error) { $name = (string) ($error['module'] ?? ''); if ($name !== '') $errors[$name][] = $error; }
        $installed = [];
        foreach ($this->repository->all() as $row) if (isset($row['name']) && is_string($row['name'])) $installed[$row['name']] = $row;
        $names = array_values(array_unique(array_merge(array_keys($definitions), array_keys($installed), array_keys($errors)))); sort($names, SORT_STRING);
        $items = [];
        foreach ($names as $name) {
            $definition = $definitions[$name] ?? null; $row = $installed[$name] ?? null;
            $discovery = $definition instanceof ModuleDefinition ? 'valid' : (($errors[$name] ?? []) === [] ? 'missing' : 'invalid_metadata');
            $lifecycle = is_array($row) ? (($row['status'] ?? null) === 'enabled' ? 'installed_enabled' : (($row['status'] ?? null) === 'disabled' ? 'installed_disabled' : 'invalid')) : 'not_installed';
            $diagnostics = [];
            if ($lifecycle === 'invalid') $diagnostics[] = $this->diagnostic('invalid_stored_status', 'error', ['enable', 'disable', 'uninstall']);
            if ($definition instanceof ModuleDefinition) {
                foreach (($definition->requires()['modules'] ?? []) as $dependency) {
                    $dependencyName = is_array($dependency) ? (string) ($dependency['name'] ?? '') : (string) $dependency;
                    if ($dependencyName === '') continue;
                    if (!isset($installed[$dependencyName])) $diagnostics[] = $this->diagnostic('dependency_missing', 'error', ['enable']);
                    elseif (($installed[$dependencyName]['status'] ?? null) !== 'enabled') $diagnostics[] = $this->diagnostic('dependency_disabled', 'error', ['enable']);
                }
                $routes = $definition->routes();
                if (is_string($routes) && $routes !== '' && !is_file($definition->path() . DIRECTORY_SEPARATOR . $routes)) $diagnostics[] = $this->diagnostic('route_file_missing', 'error', ['enable']);
                if (is_array($row)) foreach (['title' => $row['title'] ?? '', 'version' => $row['version'] ?? '', 'path' => $row['path'] ?? ''] as $field => $value) if ((string) $value !== (string) ($field === 'title' ? $definition->title() : ($field === 'version' ? $definition->version() : $definition->path()))) $diagnostics[] = $this->diagnostic('metadata_drift', 'warning', []);
            } elseif (is_array($row) && $lifecycle === 'installed_enabled') $diagnostics[] = $this->diagnostic('dependent_safety_unknown', 'warning', []);
            foreach ($errors[$name] ?? [] as $error) $diagnostics[] = $this->diagnostic(str_contains(strtolower((string) ($error['error'] ?? '')), 'valid json') ? 'malformed_discovery' : 'invalid_metadata', 'error', ['install', 'enable']);
            foreach ($installed as $dependentName => $dependent) if ($dependentName !== $name && ($dependent['status'] ?? null) === 'enabled' && $definition instanceof ModuleDefinition && in_array($name, $this->dependencyNames($definitions[$dependentName] ?? null), true)) $diagnostics[] = $this->diagnostic('enabled_dependent', 'error', ['disable', 'uninstall']);
            $diagnosticCodes = array_values(array_map(static fn (array $item): string => $item['code'], $diagnostics));
            $actions = $this->actions($lifecycle, $discovery, $diagnosticCodes, $name);
            $items[] = ['name' => $name, 'title' => $definition?->title() ?? (string) ($row['title'] ?? $name), 'version' => $definition?->version() ?? (string) ($row['version'] ?? ''), 'stored_version' => is_array($row) ? (string) ($row['version'] ?? '') : null, 'discovered_version' => $definition?->version(), 'lifecycle_state' => $lifecycle, 'discovery_state' => $discovery, 'dependencies' => $definition instanceof ModuleDefinition ? $this->dependencyNames($definition) : [], 'diagnostics' => $diagnostics, 'available_actions' => $actions['available_actions'], 'denial_reasons' => $actions['denial_reasons']];
        }
        if ($this->packages !== null) $items = $this->packages->enrich($items);
        return $items;
    }

    public function actionAllowed(string $name, string $action): bool
    {
        foreach ($this->inventory() as $item) if (($item['name'] ?? '') === $name) return (($item['available_actions'][$action]['enabled'] ?? false) === true);
        return false;
    }

    private function dependencyNames(?ModuleDefinition $definition): array
    {
        if (!$definition instanceof ModuleDefinition) return [];
        $names = [];
        foreach (($definition->requires()['modules'] ?? []) as $dependency) { $name = is_array($dependency) ? (string) ($dependency['name'] ?? '') : (string) $dependency; if ($name !== '') $names[] = $name; }
        return array_values(array_unique($names));
    }

    private function actions(string $lifecycle, string $discovery, array $codes, string $name): array
    {
        $actions = []; $reasons = [];
        foreach (['install', 'enable', 'disable', 'uninstall'] as $action) { $actions[$action] = ['visible' => false, 'enabled' => false]; $reasons[$action] = []; }
        if ($lifecycle === 'not_installed') { $actions['install']['visible'] = true; if ($discovery === 'valid') $actions['install']['enabled'] = true; else $reasons['install'] = [$discovery === 'missing' ? 'discovery_missing' : 'invalid_metadata']; }
        if ($lifecycle === 'installed_disabled') { $actions['enable']['visible'] = true; $actions['uninstall']['visible'] = true; $reasons['enable'] = $this->enableReasons($codes); $actions['enable']['enabled'] = $reasons['enable'] === []; $reasons['uninstall'] = in_array('enabled_dependent', $codes, true) ? ['enabled_dependent'] : []; $actions['uninstall']['enabled'] = $reasons['uninstall'] === []; }
        if ($lifecycle === 'installed_enabled') { $actions['disable']['visible'] = true; $reasons['disable'] = in_array('enabled_dependent', $codes, true) ? ['enabled_dependent'] : ($name === 'module-manager' ? ['canonical_operator'] : []); $actions['disable']['enabled'] = $reasons['disable'] === []; $reasons['uninstall'] = ['enabled_module']; }
        if ($lifecycle === 'invalid') { $reasons['enable'] = $reasons['disable'] = $reasons['uninstall'] = ['invalid_stored_status']; }
        return ['available_actions' => $actions, 'denial_reasons' => $reasons];
    }

    private function enableReasons(array $codes): array
    {
        $blocked = ['invalid_stored_status', 'route_file_missing', 'self_dependency', 'duplicate_dependency', 'dependency_missing', 'dependency_disabled', 'dependency_cycle', 'invalid_metadata', 'malformed_discovery'];
        return array_values(array_unique(array_values(array_intersect($blocked, $codes))));
    }

    private function diagnostic(string $code, string $severity, array $blocked): array
    {
        return ['code' => $code, 'severity' => $severity, 'blocked_actions' => $blocked, 'message' => 'Module evidence requires review.'];
    }
}
