<?php

namespace Copot\Core\Admin;

use Copot\Core\Config;
use Copot\Core\DeploymentContext;

class AdminUrl
{
    private string $path;

    public function __construct(Config $config, ?DeploymentContext $deployment = null)
    {
        $path = $config->get('admin.path', 'admin');

        if (!is_string($path) || !preg_match('/^[a-z0-9-]+$/', $path)) {
            throw new \RuntimeException('Invalid admin path configuration.');
        }

        $this->path = $path;
        $this->deployment = $deployment;
    }

    private ?DeploymentContext $deployment = null;

    public function path(): string
    {
        return $this->path;
    }

    public function baseUrl(): string
    {
        return $this->url('/' . $this->path);
    }

    public function routeBaseUrl(): string
    {
        return '/' . $this->path;
    }

    public function childUrl(string $childPath): string
    {
        if (
            str_contains($childPath, "\0")
            || str_contains($childPath, '\\')
            || str_contains($childPath, '?')
            || str_contains($childPath, '#')
            || preg_match('/[\x00-\x1F\x7F]/', $childPath)
        ) {
            throw new \InvalidArgumentException('Admin child path contains unsafe characters.');
        }

        $childPath = trim($childPath, '/');

        if ($childPath === '') {
            return $this->baseUrl();
        }

        $segments = preg_split('#/+#', $childPath) ?: [];

        foreach ($segments as $segment) {
            if (
                $segment === '.'
                || $segment === '..'
                || !preg_match('/^[A-Za-z0-9._~{}-]+$/', $segment)
            ) {
                throw new \InvalidArgumentException('Admin child path contains an unsafe segment.');
            }
        }

        return $this->url('/' . $this->path . '/' . implode('/', $segments));
    }

    public function routeChildUrl(string $childPath): string
    {
        $generated = $this->childUrl($childPath);
        $base = $this->baseUrl();

        if ($generated === $base) {
            return $this->routeBaseUrl();
        }

        return $this->routeBaseUrl() . substr($generated, strlen($base));
    }

    public function routePath(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            return $url;
        }

        $base = $this->baseUrl();

        if ($path === $base) {
            return $this->routeBaseUrl();
        }

        return str_starts_with($path, $base . '/')
            ? $this->routeBaseUrl() . substr($path, strlen($base))
            : $path;
    }

    public function url(string $path): string
    {
        return $this->urlPath($path);
    }

    private function urlPath(string $path): string
    {
        return $this->deployment?->url($path) ?? $path;
    }
}
