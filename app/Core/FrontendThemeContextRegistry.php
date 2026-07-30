<?php

namespace Copot\Core;

use Throwable;

final class FrontendThemeContextRegistry
{
    private array $contributors = [];
    private bool $frozen = false;

    public function __construct(private Diagnostics $diagnostics)
    {
    }

    public function register(FrontendThemeContextContributor $contributor): void
    {
        if ($this->frozen) {
            throw new \RuntimeException('Frontend Theme context registry is frozen.');
        }

        $key = trim($contributor->contextKey());
        if ($key === '' || preg_match('/^[a-z][a-z0-9_-]*$/D', $key) !== 1) {
            throw new \InvalidArgumentException('Frontend Theme context contributor key is invalid.');
        }

        $this->contributors[] = [$key, $contributor];
    }

    public function freeze(): void
    {
        $this->frozen = true;
    }

    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    public function compose(array $theme): array
    {
        $metadata = isset($theme['metadata']) && is_array($theme['metadata']) ? $theme['metadata'] : [];
        $supports = isset($metadata['supports']) && is_array($metadata['supports']) ? $metadata['supports'] : [];
        $input = new FrontendThemeContext((string) ($theme['theme_id'] ?? ''), $metadata, $supports);
        $composed = [];

        foreach ($this->contributors as [$key, $contributor]) {
            if (array_key_exists($key, $composed)) {
                $this->diagnostics->warning('frontend.theme_context.collision', 'Duplicate frontend Theme context key was omitted.', ['component' => 'frontend-theme-context', 'operation' => 'collision', 'slot' => $key]);
                continue;
            }

            try {
                $fragment = $contributor->contribute($input);
                if (!is_array($fragment)) {
                    throw new \RuntimeException('Contributor returned a non-array fragment.');
                }
                $composed[$key] = $fragment;
            } catch (Throwable $exception) {
                $this->diagnostics->warning('frontend.theme_context.contributor_failed', 'Frontend Theme context contributor failed closed.', ['component' => 'frontend-theme-context', 'operation' => 'contribute', 'slot' => $key]);
            }
        }

        return $composed;
    }
}
