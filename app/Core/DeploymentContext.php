<?php

namespace Copot\Core;

use InvalidArgumentException;

final class DeploymentContext
{
    private string $appRoot;
    private string $publicRoot;
    private string $basePath;

    public function __construct(string $appRoot, string $publicRoot, string $basePath = '/')
    {
        $this->appRoot = self::resolveDirectory($appRoot, 'APP_ROOT');
        $this->publicRoot = self::resolveDirectory($publicRoot, 'PUBLIC_ROOT');
        self::assertSafeRelationship($this->appRoot, $this->publicRoot);
        $this->basePath = self::normalizeBasePath($basePath);
    }

    public static function fromEntrypoint(string $entrypoint): self
    {
        $entrypointPath = realpath($entrypoint);

        if ($entrypointPath === false || !is_file($entrypointPath) || is_link($entrypointPath)) {
            throw new InvalidArgumentException('Public entrypoint is not a resolvable file.');
        }

        $publicRoot = self::environment('COPOT_PUBLIC_ROOT') ?? dirname($entrypointPath);
        $appRoot = self::environment('COPOT_APP_ROOT') ?? dirname($publicRoot);
        $basePath = self::environment('COPOT_BASE_PATH')
            ?? self::basePathFromScript($_SERVER['SCRIPT_NAME'] ?? '', basename($entrypointPath));

        return new self($appRoot, $publicRoot, $basePath);
    }

    public static function forApplicationRoot(string $appRoot, ?string $basePath = null): self
    {
        $resolvedAppRoot = self::resolveDirectory($appRoot, 'APP_ROOT');
        $publicRoot = $resolvedAppRoot . DIRECTORY_SEPARATOR . 'public';

        return new self($resolvedAppRoot, $publicRoot, $basePath ?? '/');
    }

    /**
     * Compatibility adapter for in-process application fixtures that do not
     * expose a web document root. Web entrypoints must use a strict context.
     */
    public static function forLegacyApplication(string $appRoot, ?string $basePath = null): self
    {
        $resolvedAppRoot = self::resolveDirectory($appRoot, 'APP_ROOT');
        return self::createUnchecked(
            $resolvedAppRoot,
            $resolvedAppRoot . DIRECTORY_SEPARATOR . 'public',
            self::normalizeBasePath($basePath ?? '/')
        );
    }

    public function appRoot(): string
    {
        return $this->appRoot;
    }

    public function publicRoot(): string
    {
        return $this->publicRoot;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function url(string $path): string
    {
        $path = trim($path);

        if ($path === '' || $path === '/') {
            return $this->basePath;
        }

        if (str_starts_with($path, '#') || preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $path)) {
            return $path;
        }

        $suffix = '';
        $pathOnly = $path;
        $suffixPosition = strcspn($path, '?#');

        if ($suffixPosition < strlen($path)) {
            $pathOnly = substr($path, 0, $suffixPosition);
            $suffix = substr($path, $suffixPosition);
        }

        $pathOnly = self::normalizeUrlPath($pathOnly);

        if ($this->basePath === '/' || $pathOnly === $this->basePath || str_starts_with($pathOnly, $this->basePath . '/')) {
            return $pathOnly . $suffix;
        }

        return rtrim($this->basePath, '/') . $pathOnly . $suffix;
    }

    public function isSplitRoot(): bool
    {
        return !self::isInside($this->publicRoot, $this->appRoot);
    }

    public function path(string $relativePath = ''): string
    {
        $relativePath = self::normalizeRelativePath($relativePath);

        return $relativePath === ''
            ? $this->appRoot
            : $this->appRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    }

    public function publicPath(string $relativePath = ''): string
    {
        $relativePath = self::normalizeRelativePath($relativePath);

        return $relativePath === ''
            ? $this->publicRoot
            : $this->publicRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    }

    private static function resolveDirectory(string $path, string $label): string
    {
        $path = trim($path);

        if ($path === '' || !self::isAbsolute($path)) {
            throw new InvalidArgumentException($label . ' must be an absolute directory.');
        }

        $resolved = realpath($path);

        if ($resolved === false || !is_dir($resolved) || is_link($path) || !is_readable($resolved)) {
            throw new InvalidArgumentException($label . ' is missing, unreadable, or unsafe.');
        }

        return rtrim($resolved, '/\\');
    }

    private static function isAbsolute(string $path): bool
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
    }

    private static function assertSafeRelationship(string $appRoot, string $publicRoot): void
    {
        if (self::samePath($appRoot, $publicRoot)) {
            throw new InvalidArgumentException('APP_ROOT and PUBLIC_ROOT must be different directories.');
        }

        if (self::isInside($appRoot, $publicRoot)) {
            throw new InvalidArgumentException('APP_ROOT must not be inside PUBLIC_ROOT.');
        }
    }

    private static function samePath(string $left, string $right): bool
    {
        return self::identityPath($left) === self::identityPath($right);
    }

    private static function isInside(string $path, string $directory): bool
    {
        $path = self::identityPath($path);
        $directory = rtrim(self::identityPath($directory), '/') . '/';

        return str_starts_with($path . '/', $directory);
    }

    private static function identityPath(string $path): string
    {
        $path = str_replace('\\', '/', rtrim($path, '/\\'));

        return DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
    }

    private static function normalizeBasePath(string $basePath): string
    {
        $basePath = str_replace('\\', '/', trim($basePath));

        if ($basePath === '') {
            return '/';
        }

        if (!str_starts_with($basePath, '/') || str_contains($basePath, "\0") || str_contains($basePath, '?') || str_contains($basePath, '#')) {
            throw new InvalidArgumentException('Deployment base path must be an absolute URL path.');
        }

        $basePath = '/' . trim($basePath, '/');

        $segments = trim($basePath, '/') === '' ? [] : explode('/', trim($basePath, '/'));

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..' || preg_match('/[\x00-\x1F\x7F]/', $segment)) {
                throw new InvalidArgumentException('Deployment base path contains an unsafe segment.');
            }
        }

        return $basePath === '/' ? '/' : rtrim($basePath, '/');
    }

    private static function normalizeUrlPath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));

        if ($path === '') {
            return '/';
        }

        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        $path = preg_replace('#/+#', '/', $path) ?? $path;

        foreach (explode('/', trim($path, '/')) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || preg_match('/[\x00-\x1F\x7F]/', $segment)) {
                throw new InvalidArgumentException('Internal URL contains an unsafe path segment.');
            }
        }

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private static function normalizeRelativePath(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', trim($relativePath));

        if ($relativePath === '') {
            return '';
        }

        foreach (explode('/', trim($relativePath, '/')) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || str_contains($segment, "\0")) {
                throw new InvalidArgumentException('Deployment path contains an unsafe segment.');
            }
        }

        return trim($relativePath, '/');
    }

    private static function basePathFromScript(string $scriptName, string $entrypointName): string
    {
        $scriptName = str_replace('\\', '/', trim($scriptName));

        if ($scriptName === '' || !str_starts_with($scriptName, '/')) {
            return '/';
        }

        $suffix = '/' . $entrypointName;

        if (str_ends_with($scriptName, $suffix)) {
            return self::normalizeBasePath(substr($scriptName, 0, -strlen($suffix)) ?: '/');
        }

        return self::normalizeBasePath(dirname($scriptName));
    }

    private static function environment(string $key): ?string
    {
        $value = $_SERVER[$key] ?? getenv($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function createUnchecked(string $appRoot, string $publicRoot, string $basePath): self
    {
        $reflection = new \ReflectionClass(self::class);
        /** @var self $context */
        $context = $reflection->newInstanceWithoutConstructor();
        $context->appRoot = $appRoot;
        $context->publicRoot = $publicRoot;
        $context->basePath = $basePath;

        return $context;
    }
}
