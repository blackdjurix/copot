<?php

namespace Copot\Core\Admin;

final class AdminIcon
{
    private const FALLBACK_KEY = 'module';
    private const MAX_FILE_BYTES = 65536;

    private string $basePath;
    private array $cache = [];

    public function __construct(?string $basePath = null)
    {
        $this->basePath = rtrim(
            $basePath ?? dirname(__DIR__, 3) . '/public/admin-assets/icons',
            '/\\'
        );
    }

    public function render(?string $key, string $class = 'admin-icon'): string
    {
        $normalizedKey = $this->normalizeKey($key) ?? self::FALLBACK_KEY;
        $normalizedClass = $this->normalizeClassList($class);
        $cacheKey = $normalizedKey . '|' . $normalizedClass;

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $svg = $this->load($normalizedKey);

        if ($svg === null && $normalizedKey !== self::FALLBACK_KEY) {
            $svg = $this->load(self::FALLBACK_KEY);
        }

        if ($svg === null) {
            return $this->cache[$cacheKey] = '';
        }

        $rendered = preg_replace_callback(
            '/<svg\\b([^>]*)>/i',
            static function (array $matches) use ($normalizedClass): string {
                $attributes = preg_replace(
                    '/\\s(?:class|aria-hidden|focusable|width|height)\\s*=\\s*(?:"[^"]*"|\'[^\']*\')/i',
                    '',
                    (string) ($matches[1] ?? '')
                );

                return '<svg' . $attributes
                    . ' class="' . htmlspecialchars($normalizedClass, ENT_QUOTES, 'UTF-8') . '"'
                    . ' aria-hidden="true" focusable="false">';
            },
            $svg,
            1
        );

        return $this->cache[$cacheKey] = is_string($rendered) ? trim($rendered) : '';
    }

    public function exists(?string $key): bool
    {
        $normalizedKey = $this->normalizeKey($key);

        return $normalizedKey !== null && is_file($this->pathFor($normalizedKey));
    }

    private function load(string $key): ?string
    {
        $path = $this->pathFor($key);

        if (!is_file($path)) {
            return null;
        }

        $size = filesize($path);

        if (!is_int($size) || $size < 1 || $size > self::MAX_FILE_BYTES) {
            return null;
        }

        $svg = file_get_contents($path);

        if (!is_string($svg)) {
            return null;
        }

        $svg = trim($svg);

        if (
            preg_match('/\\A<svg\\b[^>]*>.*<\\/svg>\\z/is', $svg) !== 1
            || preg_match('/<(?:script|style|foreignObject|image|iframe|object|embed)\\b/i', $svg) === 1
            || preg_match('/\\son[a-z]+\\s*=/i', $svg) === 1
            || preg_match('/\\s(?:href|xlink:href)\\s*=/i', $svg) === 1
            || preg_match('/\\b(?:javascript|data):/i', $svg) === 1
            || preg_match('/\\bviewBox\\s*=\\s*["\']0 0 24 24["\']/i', $svg) !== 1
        ) {
            return null;
        }

        return $svg;
    }

    private function normalizeKey(?string $key): ?string
    {
        if (!is_string($key)) {
            return null;
        }

        $key = strtolower(trim($key));

        if (str_starts_with($key, 'icon-')) {
            $key = substr($key, 5);
        }

        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key) === 1 ? $key : null;
    }

    private function normalizeClassList(string $class): string
    {
        $classes = ['admin-icon'];

        foreach (preg_split('/\\s+/', trim($class)) ?: [] as $candidate) {
            if (
                preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $candidate) === 1
                && !in_array($candidate, $classes, true)
            ) {
                $classes[] = $candidate;
            }
        }

        return implode(' ', $classes);
    }

    private function pathFor(string $key): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'icon-' . $key . '.svg';
    }
}
