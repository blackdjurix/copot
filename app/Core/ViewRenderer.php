<?php

namespace Copot\Core;

use Throwable;

class ViewRenderer
{
    public function __construct(
        private ThemeLoader $themes,
        private ThemeAssets $themeAssets,
        private SiteBranding $branding
    )
    {
    }

    public function renderFile(string $contentPath, array $context = [], ?string $layout = null, ?string $title = null): string
    {
        $theme = $this->themes->activeTheme();
        $themeAsset = fn (string $path): string => $this->themeAssets->url($path);
        $title = $title ?? $this->branding->name();
        $contentPath = $this->resolveContentPath($contentPath);
        $variables = [
            'title' => $title,
            'theme' => $theme,
            'themeAsset' => $themeAsset,
            'branding' => $this->branding,
            'context' => $context,
        ];
        $content = $this->renderPhpFile($contentPath, $variables);

        return $this->renderPhpFile($this->themes->layoutPath($layout), $variables + [
            'content' => $content,
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
            $branding = $__variables['branding'] ?? null;
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
