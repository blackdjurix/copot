<?php

use Copot\Core\FrontendThemeContext;
use Copot\Core\FrontendThemeContextContributor;
use Copot\Core\Database;

final class NavigationFrontendContextContributor implements FrontendThemeContextContributor
{
    public function __construct(private Database $database)
    {
    }

    public function contextKey(): string
    {
        return 'navigation';
    }

    public function contribute(FrontendThemeContext $context): array
    {
        $declared = $context->supports()['navigation_locations'] ?? [];
        if (!is_array($declared)) {
            return ['locations' => []];
        }

        require_once __DIR__ . '/NavigationMenu.php';
        require_once __DIR__ . '/NavigationItem.php';
        require_once __DIR__ . '/NavigationRenderItem.php';
        require_once __DIR__ . '/NavigationTargetResolver.php';
        require_once __DIR__ . '/NavigationTargetResolverRegistry.php';
        require_once __DIR__ . '/NavigationTargetResolverRegistryFactory.php';
        require_once __DIR__ . '/NavigationRepository.php';
        require_once __DIR__ . '/NavigationService.php';
        require_once __DIR__ . '/NavigationFrontendReader.php';

        $repository = new NavigationRepository($this->database);
        $reader = new NavigationFrontendReader($repository, new NavigationService($this->database, $repository));

        return [
            'locations' => $reader->locationsForTheme(
                $context->themeId(),
                array_values(array_unique(array_filter($declared, 'is_string'))),
                (new NavigationTargetResolverRegistryFactory($this->database))->create()
            ),
        ];
    }
}
