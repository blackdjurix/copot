<?php

namespace Copot\Core\Admin;

use Copot\Core\Config;

class AdminUrl
{
    private string $path;

    public function __construct(Config $config)
    {
        $path = $config->get('admin.path', 'admin');

        if (!is_string($path) || !preg_match('/^[a-z0-9-]+$/', $path)) {
            throw new \RuntimeException('Invalid admin path configuration.');
        }

        $this->path = $path;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function baseUrl(): string
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

        return $this->baseUrl() . '/' . implode('/', $segments);
    }
}
