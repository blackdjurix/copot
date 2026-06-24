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

    private function isInsideModule(string $path, string $modulePath): bool
    {
        $modulePath = rtrim($modulePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $modulePath);
    }
}
