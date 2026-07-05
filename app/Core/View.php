<?php

namespace Copot\Core;

use RuntimeException;
use Throwable;

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

        $initialOutputLevel = ob_get_level();

        if (!@ob_start()) {
            throw new RuntimeException('View output buffer is unavailable.');
        }

        try {
            require $resolvedFile;

            if (ob_get_level() !== $initialOutputLevel + 1) {
                throw new RuntimeException('View output buffer state is invalid.');
            }

            $content = @ob_get_clean();

            if (!is_string($content)) {
                throw new RuntimeException('View output buffer could not be read.');
            }

            return $content;
        } catch (Throwable $exception) {
            $this->discardOutputBuffersTo($initialOutputLevel);

            throw $exception;
        }
    }

    private function discardOutputBuffersTo(int $initialLevel): void
    {
        while (ob_get_level() > $initialLevel) {
            $level = ob_get_level();

            if (!@ob_end_clean() || ob_get_level() >= $level) {
                break;
            }
        }
    }
}
