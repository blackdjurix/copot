<?php

namespace Copot\Core;

class ThemeAssets
{
    private const MIME_TYPES = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
    ];

    public function __construct(private ThemeLoader $themes)
    {
    }

    public function url(string $assetPath): string
    {
        $theme = $this->themes->activeTheme();
        $assetPath = $this->normalizeAssetPath($assetPath);

        return '/theme-assets/' . rawurlencode($theme['theme_id']) . '/' . $this->encodePath($assetPath);
    }

    public function serve(string $themeId, string $assetPath): Response
    {
        try {
            $path = $this->resolveAssetPath($themeId, $assetPath);
            $mimeType = $this->mimeType($path);
            $content = file_get_contents($path);

            if ($content === false) {
                return $this->notFound();
            }

            return Response::content($content, 200, [
                'Content-Type' => $mimeType,
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (ThemeException) {
            return $this->notFound();
        }
    }

    private function resolveAssetPath(string $themeId, string $assetPath): string
    {
        $theme = $this->themes->activeTheme();

        if ($themeId !== $theme['theme_id']) {
            throw new ThemeException('Only active theme assets may be served.');
        }

        $assetPath = $this->normalizeAssetPath($assetPath);
        $assetsRoot = $theme['theme_path'] . DIRECTORY_SEPARATOR . 'assets';
        $resolvedAssetsRoot = realpath($assetsRoot);

        if ($resolvedAssetsRoot === false || !is_dir($resolvedAssetsRoot)) {
            throw new ThemeException('Active theme assets directory was not found.');
        }

        $candidate = $resolvedAssetsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $assetPath);

        if (!is_file($candidate)) {
            throw new ThemeException('Theme asset was not found.');
        }

        $resolvedPath = realpath($candidate);

        if ($resolvedPath === false || !$this->isInsideDirectory($resolvedPath, $resolvedAssetsRoot)) {
            throw new ThemeException('Theme asset path is outside the active theme assets directory.');
        }

        $this->mimeType($resolvedPath);

        return $resolvedPath;
    }

    private function normalizeAssetPath(string $path): string
    {
        $path = rawurldecode($path);
        $path = str_replace('\\', '/', trim($path));

        if ($path === '' || str_starts_with($path, '/') || str_contains($path, "\0") || preg_match('/^[A-Za-z]:\//', $path)) {
            throw new ThemeException('Theme asset path must be a safe relative path.');
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '..') {
                throw new ThemeException('Theme asset path must not contain empty or parent directory segments.');
            }
        }

        return $path;
    }

    private function mimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (!isset(self::MIME_TYPES[$extension])) {
            throw new ThemeException("Theme asset extension [{$extension}] is not supported.");
        }

        return self::MIME_TYPES[$extension];
    }

    private function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    private function isInsideDirectory(string $path, string $directory): bool
    {
        $directory = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $directory);
    }

    private function notFound(): Response
    {
        return Response::content('404 Not Found', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
