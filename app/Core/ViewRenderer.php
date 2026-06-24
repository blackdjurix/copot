<?php

namespace Copot\Core;

use Throwable;

class ViewRenderer
{
    public function __construct(
        private ThemeLoader $themes,
        private ThemeAssets $themeAssets
    )
    {
    }

    public function renderFile(string $contentPath, array $context = [], ?string $layout = null, ?string $title = null): string
    {
        $theme = $this->themes->activeTheme();
        $themeAsset = fn (string $path): string => $this->themeAssets->url($path);
        $title = $title ?? 'Copot';
        $contentPath = $this->resolveContentPath($contentPath);
        $content = $this->renderPhpFile($contentPath, [
            'title' => $title,
            'theme' => $theme,
            'themeAsset' => $themeAsset,
            'context' => $context,
        ]);

        return $this->renderPhpFile($this->themes->layoutPath($layout), [
            'content' => $content,
            'title' => $title,
            'theme' => $theme,
            'themeAsset' => $themeAsset,
            'context' => $context,
        ]);
    }

    private function resolveContentPath(string $contentPath): string
    {
        if (!is_file($contentPath)) {
            throw new ThemeException("Content file [{$contentPath}] was not found.");
        }

        $resolvedPath = realpath($contentPath);

        if ($resolvedPath === false || !is_file($resolvedPath)) {
            throw new ThemeException("Content file [{$contentPath}] could not be resolved.");
        }

        return $resolvedPath;
    }

    private function renderPhpFile(string $path, array $variables): string
    {
        $render = static function (string $__path, array $__variables): string {
            $content = $__variables['content'] ?? null;
            $title = $__variables['title'] ?? null;
            $theme = $__variables['theme'] ?? [];
            $themeAsset = $__variables['themeAsset'] ?? null;
            $context = $__variables['context'] ?? [];

            ob_start();

            try {
                require $__path;

                return (string) ob_get_clean();
            } catch (Throwable $exception) {
                ob_end_clean();

                throw $exception;
            }
        };

        return $render($path, $variables);
    }
}
