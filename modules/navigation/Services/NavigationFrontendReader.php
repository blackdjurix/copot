<?php

final class NavigationFrontendReader
{
    public function __construct(
        private NavigationRepository $repository,
        private NavigationService $service
    ) {
    }

    public function locationsForTheme(string $themeId, array $locationKeys, NavigationTargetResolverRegistry $resolvers): array
    {
        $locations = [];
        foreach ($locationKeys as $locationKey) {
            if (!is_string($locationKey) || preg_match('/^[a-z][a-z0-9._-]*$/D', $locationKey) !== 1) {
                continue;
            }

            $locations[$locationKey] = $this->location($themeId, $locationKey, $resolvers);
        }

        return $locations;
    }

    private function location(string $themeId, string $locationKey, NavigationTargetResolverRegistry $resolvers): array
    {
        $menu = $this->repository->assignedMenu($themeId, $locationKey);
        if (!$menu instanceof NavigationMenu) {
            return [];
        }

        $items = $this->service->itemsForMenu($menu->id());
        $children = [];
        foreach ($items as $item) {
            $parent = $item->parentId() ?? 0;
            $children[$parent][] = $item;
        }

        $render = function (int $parentId, array $path) use (&$render, $children, $resolvers): array {
            $result = [];
            foreach ($children[$parentId] ?? [] as $item) {
                if (isset($path[$item->id()]) || !$item->isVisible()) {
                    continue;
                }

                $path[$item->id()] = true;
                $resolved = $item->targetKind() === 'custom'
                    ? new NavigationRenderItem('custom', '', $item->label(), $item->customUrl(), true)
                    : $resolvers->resolve($item->targetKind(), (string) $item->targetReference());

                if (!$resolved instanceof NavigationRenderItem || !$resolved->isVisible() || $resolved->url() === null) {
                    continue;
                }

                $result[] = [
                    'label' => $item->label(),
                    'url' => $resolved->url(),
                    'kind' => $resolved->kind(),
                    'reference' => $resolved->reference() === '' ? null : $resolved->reference(),
                    'children' => $render($item->id(), $path),
                ];
            }

            return $result;
        };

        return $render(0, []);
    }
}
