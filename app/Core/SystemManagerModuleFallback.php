<?php

namespace Copot\Core;

final class SystemManagerModuleFallback
{
    public function __construct(
        private ModuleDiscovery $discovery,
        private ModuleRepository $repository
    ) {
    }

    public function operational(): bool
    {
        $definitions = $this->definitions();
        $definition = $definitions['module-manager'] ?? null;
        $installed = $this->repository->findByName('module-manager');

        if (!$definition instanceof ModuleDefinition || !is_array($installed) || ($installed['status'] ?? null) !== 'enabled') {
            return false;
        }

        $routes = $definition->routes();
        return is_string($routes) && $routes !== '' && is_file($definition->path() . DIRECTORY_SEPARATOR . $routes);
    }

    public function inventory(): array
    {
        $definitions = $this->definitions();
        $installed = [];
        foreach ($this->repository->all() as $row) {
            if (isset($row['name']) && is_string($row['name'])) {
                $installed[$row['name']] = $row;
            }
        }

        $names = array_values(array_unique(array_merge(array_keys($definitions), array_keys($installed))));
        sort($names, SORT_STRING);
        $items = [];
        foreach ($names as $name) {
            $definition = $definitions[$name] ?? null;
            $row = $installed[$name] ?? null;
            $status = is_array($row) ? (string) ($row['status'] ?? 'invalid') : 'not_installed';
            $reasons = [];
            if ($definition instanceof ModuleDefinition) {
                foreach (($definition->requires()['modules'] ?? []) as $dependency) {
                    $dependencyName = is_array($dependency) ? (string) ($dependency['name'] ?? '') : (string) $dependency;
                    $dependencyRow = $installed[$dependencyName] ?? null;
                    if ($dependencyName !== '' && (!is_array($dependencyRow) || ($dependencyRow['status'] ?? null) !== 'enabled')) {
                        $reasons[] = 'Requires enabled module: ' . $dependencyName . '.';
                    }
                }
            }
            $items[] = [
                'name' => $name,
                'title' => $definition?->title() ?? (string) ($row['title'] ?? $name),
                'version' => $definition?->version() ?? (string) ($row['version'] ?? ''),
                'status' => $status,
                'available_actions' => [
                    'install' => $definition instanceof ModuleDefinition && !is_array($row),
                    'enable' => is_array($row) && $status === 'disabled' && $reasons === [],
                    'disable' => is_array($row) && $status === 'enabled' && $name !== 'module-manager',
                    'uninstall' => is_array($row) && $status === 'disabled' && $name !== 'module-manager',
                ],
                'blocking_reasons' => $reasons,
            ];
        }

        return $items;
    }

    private function definitions(): array
    {
        $definitions = [];
        foreach ($this->discovery->discover() as $definition) {
            $definitions[$definition->name()] = $definition;
        }
        return $definitions;
    }
}
