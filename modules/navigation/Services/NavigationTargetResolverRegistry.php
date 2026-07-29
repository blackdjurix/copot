<?php

final class NavigationTargetResolverRegistry
{
    private array $resolvers = [];

    public function register(NavigationTargetResolver $resolver): void
    {
        $kind = strtolower(trim($resolver->kind()));

        if ($kind === '' || preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $kind) !== 1) {
            throw new InvalidArgumentException('Navigation target resolver kind is invalid.');
        }

        if (isset($this->resolvers[$kind])) {
            throw new InvalidArgumentException("Navigation target resolver [{$kind}] is already registered.");
        }

        $this->resolvers[$kind] = $resolver;
    }

    public function resolve(string $kind, string $reference): ?NavigationRenderItem
    {
        $kind = strtolower(trim($kind));
        $reference = trim($reference);

        if ($kind === '' || $reference === '') {
            return null;
        }

        $resolver = $this->resolvers[$kind] ?? null;

        if (!$resolver instanceof NavigationTargetResolver) {
            return null;
        }

        return $resolver->resolve($reference);
    }

    public function has(string $kind): bool
    {
        return isset($this->resolvers[strtolower(trim($kind))]);
    }
}
