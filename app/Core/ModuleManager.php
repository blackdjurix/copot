<?php

namespace Copot\Core;

class ModuleManager
{
    private array $discovered = [];

    public function __construct(
        private ModuleDiscovery $discovery,
        private ModuleRepository $repository
    ) {
    }

    public function discover(): array
    {
        $this->discovered = [];

        foreach ($this->discovery->discover() as $module) {
            $this->discovered[$module->name()] = $module;
        }

        return array_values($this->discovered);
    }

    public function discoveryErrors(): array
    {
        return $this->discovery->errors();
    }

    public function installed(): array
    {
        return $this->repository->all();
    }

    public function install(string $name): void
    {
        if ($this->repository->findByName($name) !== null) {
            throw new \RuntimeException("Module [{$name}] is already installed.");
        }

        $module = $this->findDiscovered($name);

        if (!$module instanceof ModuleDefinition) {
            throw new \RuntimeException("Module [{$name}] was not found during discovery.");
        }

        $this->repository->create($module, 'disabled');
        $this->repository->replacePermissions($module);
    }

    public function enable(string $name): void
    {
        $installed = $this->repository->findByName($name);

        if ($installed === null) {
            throw new \RuntimeException("Module [{$name}] is not installed.");
        }

        $module = $this->findDiscovered($name);

        if (!$module instanceof ModuleDefinition) {
            throw new \RuntimeException("Module [{$name}] files were not found during discovery.");
        }

        $this->validateDependencies($module);
        $this->repository->updateStatus($name, 'enabled');
    }

    public function disable(string $name): void
    {
        if ($this->repository->findByName($name) === null) {
            throw new \RuntimeException("Module [{$name}] is not installed.");
        }

        $this->ensureNoEnabledDependents($name);
        $this->repository->updateStatus($name, 'disabled');
    }

    public function uninstall(string $name): void
    {
        if ($this->repository->findByName($name) === null) {
            throw new \RuntimeException("Module [{$name}] is not installed.");
        }

        $this->ensureNoEnabledDependents($name);
        $this->repository->delete($name);
    }

    private function findDiscovered(string $name): ?ModuleDefinition
    {
        if ($this->discovered === []) {
            $this->discover();
        }

        return $this->discovered[$name] ?? null;
    }

    private function validateDependencies(ModuleDefinition $module): void
    {
        $requires = $module->requires();
        $requiredModules = $requires['modules'] ?? [];

        if (!is_array($requiredModules)) {
            throw new \RuntimeException("Module [{$module->name()}] requires.modules must be an array.");
        }

        foreach ($requiredModules as $requiredModule) {
            $requiredName = is_array($requiredModule)
                ? (string) ($requiredModule['name'] ?? '')
                : (string) $requiredModule;

            if ($requiredName === '') {
                throw new \RuntimeException("Module [{$module->name()}] has an invalid module dependency.");
            }

            $installed = $this->repository->findByName($requiredName);

            if ($installed === null || ($installed['status'] ?? null) !== 'enabled') {
                throw new \RuntimeException("Module [{$module->name()}] requires enabled module [{$requiredName}].");
            }
        }
    }

    private function ensureNoEnabledDependents(string $name): void
    {
        $enabledModules = $this->repository->enabled();

        foreach ($enabledModules as $enabledModule) {
            $enabledName = (string) ($enabledModule['name'] ?? '');

            if ($enabledName === '' || $enabledName === $name) {
                continue;
            }

            $module = $this->findDiscovered($enabledName);

            if (!$module instanceof ModuleDefinition) {
                continue;
            }

            foreach ($this->requiredModuleNames($module) as $requiredName) {
                if ($requiredName === $name) {
                    throw new \RuntimeException("Module [{$name}] is required by enabled module [{$enabledName}].");
                }
            }
        }
    }

    private function requiredModuleNames(ModuleDefinition $module): array
    {
        $requires = $module->requires();
        $requiredModules = $requires['modules'] ?? [];

        if (!is_array($requiredModules)) {
            return [];
        }

        $names = [];

        foreach ($requiredModules as $requiredModule) {
            $requiredName = is_array($requiredModule)
                ? (string) ($requiredModule['name'] ?? '')
                : (string) $requiredModule;

            if ($requiredName !== '') {
                $names[] = $requiredName;
            }
        }

        return $names;
    }
}
