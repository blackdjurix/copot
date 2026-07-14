<?php

namespace Copot\Core;

class ModuleLifecycleException extends \RuntimeException
{
}

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
        try {
            if ($this->repository->findByName($name) !== null) {
                throw new \RuntimeException("Module [{$name}] is already installed.");
            }

            $module = $this->findDiscovered($name);

            if (!$module instanceof ModuleDefinition) {
                throw new \RuntimeException("Module [{$name}] was not found during discovery.");
            }

            $this->repository->atomic(function () use ($module): void {
                $this->repository->create($module, 'disabled');
                $this->repository->replacePermissions($module);
            });
        } catch (ModuleLifecycleException $failure) {
            throw $failure;
        } catch (\Throwable $failure) {
            throw new ModuleLifecycleException('Module installation failed.', 0, $failure);
        }
    }

    public function enable(string $name): void
    {
        try {
            $installed = $this->repository->findByName($name);

            if ($installed === null) {
                throw new \RuntimeException("Module [{$name}] is not installed.");
            }

            if (($installed['status'] ?? null) !== 'disabled') {
                throw new \RuntimeException("Module [{$name}] is not disabled.");
            }

            $module = $this->findDiscovered($name);

            if (!$module instanceof ModuleDefinition) {
                throw new \RuntimeException("Module [{$name}] files were not found during discovery.");
            }

            $this->validateDependencies($module);
            $this->validateContributionFiles($module);
            $this->repository->updateStatus($name, 'enabled');
        } catch (ModuleLifecycleException $failure) {
            throw $failure;
        } catch (\Throwable $failure) {
            throw new ModuleLifecycleException('Module enablement failed.', 0, $failure);
        }
    }

    public function disable(string $name): void
    {
        try {
            $installed = $this->repository->findByName($name);

            if ($installed === null) {
                throw new \RuntimeException("Module [{$name}] is not installed.");
            }

            if (($installed['status'] ?? null) !== 'enabled') {
                throw new \RuntimeException("Module [{$name}] is not enabled.");
            }

            $this->ensureNoEnabledDependents($name, true);
            $this->repository->updateStatus($name, 'disabled');
        } catch (ModuleLifecycleException $failure) {
            throw $failure;
        } catch (\Throwable $failure) {
            throw new ModuleLifecycleException('Module disablement failed.', 0, $failure);
        }
    }

    public function uninstall(string $name): void
    {
        try {
            $installed = $this->repository->findByName($name);

            if ($installed === null) {
                throw new \RuntimeException("Module [{$name}] is not installed.");
            }

            if (($installed['status'] ?? null) !== 'disabled') {
                throw new \RuntimeException("Module [{$name}] must be disabled before uninstalling.");
            }

            $this->ensureNoEnabledDependents($name);
            $this->repository->delete($name);
        } catch (ModuleLifecycleException $failure) {
            throw $failure;
        } catch (\Throwable $failure) {
            throw new ModuleLifecycleException('Module uninstallation failed.', 0, $failure);
        }
    }

    private function findDiscovered(string $name): ?ModuleDefinition
    {
        $this->discover();

        return $this->discovered[$name] ?? null;
    }

    private function validateDependencies(ModuleDefinition $module): void
    {
        $visiting = [];
        $visited = [];
        $this->validateReachableDependencies($module, $visiting, $visited);
    }

    private function validateReachableDependencies(ModuleDefinition $module, array &$visiting, array &$visited): void
    {
        $name = $module->name();

        if (isset($visiting[$name])) {
            throw new \RuntimeException("Module [{$name}] is part of a dependency cycle.");
        }

        if (isset($visited[$name])) {
            return;
        }

        $visiting[$name] = true;
        $requiredModules = $module->requires()['modules'] ?? [];

        if (!is_array($requiredModules)) {
            throw new \RuntimeException("Module [{$name}] requires.modules must be an array.");
        }

        $names = [];
        foreach ($requiredModules as $requiredModule) {
            if (is_array($requiredModule)) {
                if (count($requiredModule) !== 1 || !isset($requiredModule['name']) || !is_string($requiredModule['name'])) {
                    throw new \RuntimeException("Module [{$name}] declares an unsupported version constraint.");
                }
                $requiredName = $requiredModule['name'];
            } elseif (is_string($requiredModule)) {
                $requiredName = $requiredModule;
            } else {
                throw new \RuntimeException("Module [{$name}] has an invalid module dependency.");
            }

            if ($requiredName === '' || preg_match('/^[a-z0-9_-]+$/', $requiredName) !== 1) {
                throw new \RuntimeException("Module [{$name}] has an invalid module dependency.");
            }
            if ($requiredName === $name) {
                throw new \RuntimeException("Module [{$name}] declares a self-dependency.");
            }
            if (isset($names[$requiredName])) {
                throw new \RuntimeException("Module [{$name}] declares a duplicate dependency.");
            }
            $names[$requiredName] = true;

            $installed = $this->repository->findByName($requiredName);
            if ($installed === null || ($installed['status'] ?? null) !== 'enabled') {
                throw new \RuntimeException("Module [{$name}] requires enabled module [{$requiredName}].");
            }

            $dependency = $this->discovered[$requiredName] ?? null;
            if (!$dependency instanceof ModuleDefinition) {
                throw new \RuntimeException("Module [{$name}] dependency [{$requiredName}] was not found during discovery.");
            }

            $this->validateReachableDependencies($dependency, $visiting, $visited);
        }

        unset($visiting[$name]);
        $visited[$name] = true;
    }

    private function ensureNoEnabledDependents(string $name, bool $allowUnavailableRecovery = false): void
    {
        $targetUnavailable = !($this->findDiscovered($name) instanceof ModuleDefinition);

        if ($targetUnavailable && $allowUnavailableRecovery) {
            return;
        }

        $enabledModules = $this->repository->enabled();

        foreach ($enabledModules as $enabledModule) {
            $enabledName = (string) ($enabledModule['name'] ?? '');

            if ($enabledName === '' || $enabledName === $name) {
                continue;
            }

            $module = $this->findDiscovered($enabledName);

            if (!$module instanceof ModuleDefinition) {
                if ($targetUnavailable && $allowUnavailableRecovery) {
                    continue;
                }
                throw new \RuntimeException("Module dependency safety for [{$name}] is unknown.");
            }

            foreach ($this->dependencyNames($module, true) as $requiredName) {
                if ($requiredName === $name) {
                    throw new \RuntimeException("Module [{$name}] is required by enabled module [{$enabledName}].");
                }
            }
        }
    }

    private function dependencyNames(ModuleDefinition $module, bool $failClosed): array
    {
        $requires = $module->requires();
        $requiredModules = $requires['modules'] ?? [];

        if (!is_array($requiredModules)) {
            if ($failClosed) {
                throw new \RuntimeException('Module dependency safety is unknown.');
            }

            throw new \RuntimeException("Module [{$module->name()}] requires.modules must be an array.");
        }

        $names = [];

        foreach ($requiredModules as $requiredModule) {
            if (is_array($requiredModule)) {
                if (count($requiredModule) !== 1 || !isset($requiredModule['name']) || !is_string($requiredModule['name'])) {
                    if ($failClosed) {
                        throw new \RuntimeException('Module dependency safety is unknown.');
                    }
                    throw new \RuntimeException("Module [{$module->name()}] declares an unsupported version constraint.");
                }
                $requiredName = $requiredModule['name'];
            } elseif (is_string($requiredModule) && $requiredModule !== '') {
                $requiredName = $requiredModule;
            } else {
                if ($failClosed) {
                    throw new \RuntimeException('Module dependency safety is unknown.');
                }
                throw new \RuntimeException("Module [{$module->name()}] has an invalid module dependency.");
            }

            $names[] = $requiredName;
        }

        return $names;
    }

    private function validateContributionFiles(ModuleDefinition $module): void
    {
        $modulePath = realpath($module->path());

        if ($modulePath === false || !is_dir($modulePath)) {
            throw new \RuntimeException("Module [{$module->name()}] path is unavailable.");
        }

        foreach (['routes' => 'route', 'listeners' => 'listener'] as $field => $label) {
            $relative = $module->{$field}();

            if ($relative === null || $relative === '') {
                continue;
            }

            if (!$this->isSafeRelativePath($relative)) {
                throw new \RuntimeException("Module [{$module->name()}] declares an invalid {$label} path.");
            }

            $path = $modulePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);

            if (!is_file($path)) {
                throw new \RuntimeException("Module [{$module->name()}] declares an unavailable {$label} file.");
            }

            $resolved = realpath($path);
            if ($resolved === false || !$this->isInsideModule($resolved, $modulePath)) {
                throw new \RuntimeException("Module [{$module->name()}] declares an escaping {$label} path.");
            }
        }
    }

    private function isSafeRelativePath(string $path): bool
    {
        $path = str_replace('\\', '/', $path);

        if (
            $path === ''
            || str_contains($path, "\0")
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

        return true;
    }

    private function isInsideModule(string $path, string $modulePath): bool
    {
        return str_starts_with($path, rtrim($modulePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
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
}
