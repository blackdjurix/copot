<?php

namespace Copot\Core;

class ModuleLoader
{
    private array $errors = [];

    public function __construct(
        private ModuleDiscovery $discovery,
        private ModuleRepository $repository
    ) {
    }

    public function loadListeners(Application $app): void
    {
        $this->errors = [];
        $discovered = $this->discoveredByName();

        try {
            $enabled = $this->repository->enabled();
        } catch (\Throwable) {
            throw new \RuntimeException('Unable to load enabled module listener contributions.');
        }

        foreach ($enabled as $moduleRow) {
            $name = (string) ($moduleRow['name'] ?? '');
            $module = $discovered[$name] ?? null;

            if (!$module instanceof ModuleDefinition) {
                if ($this->hasDiscoveryError($name)) {
                    throw new \RuntimeException("Module [{$name}] listener contribution metadata is invalid.");
                }

                continue;
            }

            $this->loadModuleListeners($app, $module);
        }
    }

    public function loadRoutes(Application $app): void
    {
        $this->errors = [];
        $discovered = $this->discoveredByName();

        try {
            $enabled = $this->repository->enabled();
        } catch (\Throwable $exception) {
            $this->errors[] = 'Unable to load enabled modules from database: ' . $exception->getMessage();

            return;
        }

        foreach ($enabled as $moduleRow) {
            $name = (string) ($moduleRow['name'] ?? '');
            $module = $discovered[$name] ?? null;

            if (!$module instanceof ModuleDefinition) {
                $this->errors[] = "Enabled module [{$name}] was not found on disk.";

                continue;
            }

            $this->loadModuleRoutes($app, $module);
        }
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private function discoveredByName(): array
    {
        $modules = [];

        foreach ($this->discovery->discover() as $module) {
            $modules[$module->name()] = $module;
        }

        foreach ($this->discovery->errors() as $error) {
            $module = (string) ($error['module'] ?? 'unknown');
            $message = (string) ($error['error'] ?? 'Invalid module metadata.');
            $this->errors[] = "Module discovery error [{$module}]: {$message}";
        }

        return $modules;
    }

    private function loadModuleRoutes(Application $app, ModuleDefinition $module): void
    {
        $routes = $module->routes();

        if ($routes === null || trim($routes) === '') {
            return;
        }

        $modulePath = realpath($module->path());

        if ($modulePath === false) {
            $this->errors[] = "Module [{$module->name()}] path does not exist.";

            return;
        }

        $routePath = $modulePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $routes);

        if (!is_file($routePath)) {
            return;
        }

        $resolvedRoutePath = realpath($routePath);

        if ($resolvedRoutePath === false || !$this->isInsideModule($resolvedRoutePath, $modulePath)) {
            $this->errors[] = "Module [{$module->name()}] routes path must stay inside the module folder.";

            return;
        }

        require $resolvedRoutePath;
    }

    private function loadModuleListeners(Application $app, ModuleDefinition $module): void
    {
        $listeners = $module->listeners();

        if ($listeners === null) {
            return;
        }

        $modulePath = realpath($module->path());

        if ($modulePath === false) {
            throw new \RuntimeException("Module [{$module->name()}] listener contribution is unavailable.");
        }

        $listenerPath = $modulePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $listeners);

        if (!is_file($listenerPath)) {
            throw new \RuntimeException("Module [{$module->name()}] listener contribution file is missing.");
        }

        $resolvedListenerPath = realpath($listenerPath);

        if ($resolvedListenerPath === false || !$this->isInsideModule($resolvedListenerPath, $modulePath)) {
            throw new \RuntimeException("Module [{$module->name()}] listener contribution must stay inside the module folder.");
        }

        try {
            $contributions = require $resolvedListenerPath;
        } catch (\Throwable) {
            throw new \RuntimeException("Module [{$module->name()}] listener contribution could not be loaded.");
        }

        if (!is_array($contributions)) {
            throw new \RuntimeException("Module [{$module->name()}] listener contribution must return an array.");
        }

        foreach ($contributions as $eventName => $listener) {
            if (!is_string($eventName) || !is_callable($listener)) {
                throw new \RuntimeException("Module [{$module->name()}] listener contribution map is invalid.");
            }

            try {
                $app->events()->listen($eventName, $listener);
            } catch (\InvalidArgumentException) {
                throw new \RuntimeException("Module [{$module->name()}] listener contribution contains an invalid event name.");
            }
        }
    }

    private function hasDiscoveryError(string $name): bool
    {
        foreach ($this->discovery->errors() as $error) {
            if (($error['module'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }

    private function isInsideModule(string $path, string $modulePath): bool
    {
        $modulePath = rtrim($modulePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $modulePath);
    }
}
