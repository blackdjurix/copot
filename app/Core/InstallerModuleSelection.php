<?php

namespace Copot\Core;

final class InstallerModuleSelection
{
    public const MANDATORY_MODULES = [
        'content',
        'settings-manager',
        'taxonomy',
        'module-manager',
        'navigation',
        'theme-manager',
        'media',
        'redirects',
        'form-manager',
    ];

    private const EXCLUDED_MODULES = ['example'];

    public function __construct(private ModuleDiscovery $discovery)
    {
    }

    public function catalog(): array
    {
        $mandatory = [];
        $optional = [];

        foreach ($this->discovery->discover() as $definition) {
            if (!$definition instanceof ModuleDefinition || in_array($definition->name(), self::EXCLUDED_MODULES, true)) {
                continue;
            }

            $item = [
                'name' => $definition->name(),
                'title' => $definition->title(),
                'description' => $definition->description() ?? '',
                'version' => $definition->version(),
                'requires' => $this->dependencyNames($definition),
                'mandatory' => in_array($definition->name(), self::MANDATORY_MODULES, true),
                'recommended' => false,
            ];

            if ($item['mandatory']) {
                $mandatory[] = $item;
            } else {
                $optional[] = $item;
            }
        }

        usort($mandatory, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']));
        usort($optional, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']));

        return ['mandatory' => $mandatory, 'optional' => $optional];
    }

    public function initialSelection(): array
    {
        return [
            'staged' => false,
            'install' => [],
            'active' => [],
        ];
    }

    public function normalize(array $input): array
    {
        $optionalNames = array_column($this->catalog()['optional'], 'name');
        $install = $this->names($input['install'] ?? [], $optionalNames);
        $active = $this->names($input['active'] ?? [], $optionalNames);

        return [
            'staged' => true,
            'install' => $install,
            'active' => array_values(array_intersect($active, $install)),
        ];
    }

    public function validate(array $selection): array
    {
        $catalog = $this->catalog();
        $definitions = [];
        foreach ($this->discovery->discover() as $definition) {
            if ($definition instanceof ModuleDefinition) {
                $definitions[$definition->name()] = $definition;
            }
        }

        $optionalNames = array_fill_keys(array_column($catalog['optional'], 'name'), true);
        $install = array_values(array_unique(array_filter($selection['install'] ?? [], 'is_string')));
        $active = array_values(array_unique(array_filter($selection['active'] ?? [], 'is_string')));

        foreach ($install as $name) {
            if (!isset($optionalNames[$name])) {
                throw new InstallerValidationException(['modules' => 'The selected Module is not available for installation.']);
            }
        }

        foreach ($active as $name) {
            if (!in_array($name, $install, true)) {
                throw new InstallerValidationException(['modules' => 'Active Modules must also be selected for installation.']);
            }
        }

        $available = array_fill_keys(self::MANDATORY_MODULES, true);
        foreach ($install as $name) {
            $available[$name] = true;
        }
        $activeSet = array_fill_keys(self::MANDATORY_MODULES, true);
        foreach ($active as $name) {
            $activeSet[$name] = true;
        }
        $visiting = [];
        $visited = [];
        foreach ($active as $name) {
            $this->validateDependencies($name, $definitions, $available, $activeSet, $visiting, $visited);
        }

        sort($install, SORT_STRING);
        sort($active, SORT_STRING);

        return ['staged' => true, 'install' => $install, 'active' => $active];
    }

    private function validateDependencies(string $name, array $definitions, array $available, array $active, array &$visiting, array &$visited): void
    {
        if (isset($visited[$name])) {
            return;
        }
        if (isset($visiting[$name])) {
            throw new InstallerValidationException(['modules' => "Module dependency cycle detected at [{$name}]."]);
        }
        $definition = $definitions[$name] ?? null;
        if (!$definition instanceof ModuleDefinition) {
            throw new InstallerValidationException(['modules' => "Module [{$name}] is unavailable."]);
        }

        $visiting[$name] = true;
        foreach ($this->dependencyNames($definition) as $dependency) {
            if (!isset($available[$dependency])) {
                throw new InstallerValidationException(['modules' => "Module [{$name}] requires [{$dependency}] to be installed."]);
            }
            if (!isset($active[$dependency])) {
                throw new InstallerValidationException(['modules' => "Module [{$name}] requires [{$dependency}] to be active."]);
            }
            $this->validateDependencies($dependency, $definitions, $available, $active, $visiting, $visited);
        }
        unset($visiting[$name]);
        $visited[$name] = true;
    }

    private function dependencyNames(ModuleDefinition $definition): array
    {
        $dependencies = $definition->requires()['modules'] ?? [];
        $names = [];
        foreach (is_array($dependencies) ? $dependencies : [] as $dependency) {
            $name = is_string($dependency) ? $dependency : (is_array($dependency) ? ($dependency['name'] ?? '') : '');
            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }
        sort($names, SORT_STRING);
        return array_values(array_unique($names));
    }

    private function names(mixed $value, array $allowed): array
    {
        if (!is_array($value)) {
            return [];
        }
        $names = [];
        foreach ($value as $name => $selected) {
            if (is_int($name) && is_string($selected)) {
                $name = $selected;
                $selected = true;
            }
            if ($selected && is_string($name) && in_array($name, $allowed, true)) {
                $names[] = $name;
            }
        }
        sort($names, SORT_STRING);
        return array_values(array_unique($names));
    }
}
