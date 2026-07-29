<?php

use Copot\Core\Database;
use Copot\Core\ModuleRepository;

final class NavigationTargetResolverRegistryFactory
{
    public function __construct(
        private Database $database,
        private ?string $contentServicesPath = null
    ) {
    }

    public function create(): NavigationTargetResolverRegistry
    {
        $registry = new NavigationTargetResolverRegistry();

        if (!$this->contentIsEnabled()) {
            return $registry;
        }

        $servicesPath = $this->contentServicesPath ?? dirname(__DIR__, 2) . '/content/Services';
        $required = [
            $servicesPath . '/Content.php',
            $servicesPath . '/ContentRepository.php',
            $servicesPath . '/ContentNavigationTargetResolver.php',
        ];

        foreach ($required as $file) {
            if (!is_file($file)) {
                return $registry;
            }
        }

        try {
            require_once $servicesPath . '/Content.php';
            require_once $servicesPath . '/ContentRepository.php';
            require_once $servicesPath . '/ContentNavigationTargetResolver.php';
            $registry->register(new ContentNavigationTargetResolver(new ContentRepository($this->database)));
        } catch (Throwable) {
            return $registry;
        }

        return $registry;
    }

    private function contentIsEnabled(): bool
    {
        try {
            $module = (new ModuleRepository($this->database))->findByName('content');

            return ($module['status'] ?? null) === 'enabled';
        } catch (Throwable) {
            return false;
        }
    }
}
