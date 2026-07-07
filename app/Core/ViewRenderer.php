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

            $initialOutputLevel = ob_get_level();

            if (!@ob_start()) {
                throw new \RuntimeException('Theme view output buffer is unavailable.');
            }

            try {
                require $__path;

                if (ob_get_level() !== $initialOutputLevel + 1) {
                    throw new \RuntimeException('Theme view output buffer state is invalid.');
                }

                $rendered = @ob_get_clean();

                if (!is_string($rendered)) {
                    throw new \RuntimeException('Theme view output buffer could not be read.');
                }

                return $rendered;
            } catch (Throwable $exception) {
                while (ob_get_level() > $initialOutputLevel) {
                    $level = ob_get_level();

                    if (!@ob_end_clean() || ob_get_level() >= $level) {
                        break;
                    }
                }

                throw $exception;
            }
        };

        return $render($path, $variables);
    }
}
