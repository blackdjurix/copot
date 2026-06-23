<?php

namespace Copot\Core;

class View
{
    public function __construct(private string $viewPath)
    {
    }

    public function render(string $view, array $data = []): string
    {
        $view = trim(str_replace('.', '/', $view), '/\\');

        if ($view === '' || str_contains($view, '..') || !preg_match('/^[A-Za-z0-9_\/-]+$/', $view)) {
            throw new \InvalidArgumentException("Invalid view name [{$view}].");
        }

        $file = rtrim($this->viewPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $view . '.php';

        if (!is_file($file)) {
            throw new \RuntimeException("View [{$view}] was not found.");
        }

        $basePath = realpath($this->viewPath);
        $resolvedFile = realpath($file);

        if ($basePath === false || $resolvedFile === false || !str_starts_with($resolvedFile, $basePath . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException("View [{$view}] is outside the view path.");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $resolvedFile;

        return (string) ob_get_clean();
    }
}
