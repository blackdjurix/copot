<?php

namespace Copot\Core;

class ViewResolver
{
    public function __construct(
        private ThemeLoader $themes,
        private string $coreViewsPath,
        private string $modulesPath
    ) {
        $this->coreViewsPath = rtrim($coreViewsPath, DIRECTORY_SEPARATOR);
        $this->modulesPath = rtrim($modulesPath, DIRECTORY_SEPARATOR);
    }

    public function resolve(string $viewName): string
    {
        [$namespace, $viewPath] = $this->parseViewName($viewName);

        if ($namespace === 'core') {
            return $this->resolveCoreView($viewPath, $viewName);
        }

        if ($namespace === 'theme') {
            return $this->resolveThemeView($viewPath, $viewName);
        }

        return $this->resolveModuleView($namespace, $viewPath, $viewName);
    }

    private function parseViewName(string $viewName): array
    {
        if (str_contains($viewName, "\0")) {
            throw new ViewException('View name contains an invalid null byte.');
        }

        $parts = explode('::', $viewName, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new ViewException("View name [{$viewName}] must use namespace::view syntax.");
        }

        $namespace = $parts[0];
        $view = $parts[1];

        if (!preg_match('/^[a-z0-9-]+$/', $namespace)) {
            throw new ViewException("View namespace [{$namespace}] is invalid.");
        }

        $viewPath = $this->viewPath($view);

        return [$namespace, $viewPath];
    }

    private function viewPath(string $view): string
    {
        if (
            str_contains($view, "\0")
            || str_contains($view, '/')
            || str_contains($view, '\\')
            || str_contains($view, ':')
            || str_starts_with($view, '.')
            || str_ends_with($view, '.')
            || str_contains($view, '..')
        ) {
            throw new ViewException("View path [{$view}] is invalid.");
        }

        $segments = explode('.', $view);

        foreach ($segments as $segment) {
            if ($segment === '' || !preg_match('/^[a-z0-9_-]+$/', $segment)) {
                throw new ViewException("View path segment [{$segment}] is invalid.");
            }
        }

        return implode(DIRECTORY_SEPARATOR, $segments) . '.php';
    }

    private function resolveCoreView(string $viewPath, string $viewName): string
    {
        $themeViews = $this->themes->themePath() . DIRECTORY_SEPARATOR . 'views';

        return $this->firstExisting([
            [$themeViews, $viewPath],
            [$this->coreViewsPath, $viewPath],
        ], "Core view [{$viewName}] was not found.");
    }

    private function resolveThemeView(string $viewPath, string $viewName): string
    {
        $themeViews = $this->themes->themePath() . DIRECTORY_SEPARATOR . 'views';

        return $this->firstExisting([
            [$themeViews, $viewPath],
        ], "Theme view [{$viewName}] was not found.");
    }

    private function resolveModuleView(string $module, string $viewPath, string $viewName): string
    {
        if ($module === 'core' || $module === 'theme') {
            throw new ViewException("View namespace [{$module}] is reserved.");
        }

        $themeModuleViews = $this->themes->themePath()
            . DIRECTORY_SEPARATOR . 'views'
            . DIRECTORY_SEPARATOR . 'modules'
            . DIRECTORY_SEPARATOR . $module;
        $moduleViews = $this->modulesPath
            . DIRECTORY_SEPARATOR . $module
            . DIRECTORY_SEPARATOR . 'views';

        return $this->firstExisting([
            [$themeModuleViews, $viewPath],
            [$moduleViews, $viewPath],
        ], "Module view [{$viewName}] was not found.");
    }

    private function firstExisting(array $candidates, string $errorMessage): string
    {
        foreach ($candidates as [$root, $relativePath]) {
            $candidate = $this->resolveCandidate($root, $relativePath);

            if ($candidate !== null) {
                return $candidate;
            }
        }

        throw new ViewException($errorMessage);
    }

    private function resolveCandidate(string $root, string $relativePath): ?string
    {
        $path = $root . DIRECTORY_SEPARATOR . $relativePath;

        if (!is_file($path)) {
            return null;
        }

        $resolvedRoot = realpath($root);
        $resolvedPath = realpath($path);

        if ($resolvedRoot === false || $resolvedPath === false || !$this->isInsideDirectory($resolvedPath, $resolvedRoot)) {
            throw new ViewException("Resolved view path [{$path}] is outside the expected view root.");
        }

        return $resolvedPath;
    }

    private function isInsideDirectory(string $path, string $directory): bool
    {
        $directory = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $directory);
    }
}
