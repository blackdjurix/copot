<?php

namespace Copot\Core;

final class NavigationFrontendContextContributor implements FrontendThemeContextContributor
{
    public function __construct(private Database $database) {}

    public function contextKey(): string { return 'navigation'; }

    public function contribute(FrontendThemeContext $context): array
    {
        $repository = new NavigationRepository($this->database);
        $primary = $repository->primaryMenu();
        $locations = ['primary' => $primary ? $this->renderMenu($repository, $primary) : []];
        $declared = $context->supports()['navigation_locations'] ?? [];
        if ($context->themeId() !== '' && is_array($declared)) {
            foreach (array_values(array_unique(array_filter($declared, 'is_string'))) as $location) {
                if (!preg_match('/^[a-z][a-z0-9._-]*$/D', $location)) continue;
                $assigned = $repository->assignedMenu($context->themeId(), $location);
                $locations[$location] = $assigned ? $this->renderMenu($repository, $assigned) : ($location === 'primary' ? $locations['primary'] : []);
            }
        }
        return ['locations' => $locations];
    }

    private function renderMenu(NavigationRepository $repository, NavigationMenu $menu): array
    {
        $items = $repository->orderedItems($menu->id());
        $children = [];
        foreach ($items as $item) $children[$item->parentId() ?? 0][] = $item;
        $render = function (int $parent, array $path) use (&$render, $children): array {
            $result = [];
            foreach ($children[$parent] ?? [] as $item) {
                if (!$item->isVisible() || isset($path[$item->id()])) continue;
                $path[$item->id()] = true;
                $resolved = $this->resolve($item);
                if (!$resolved || !$resolved->isVisible() || $resolved->url() === null) continue;
                $result[] = ['label' => $resolved->label(), 'url' => $resolved->url(), 'kind' => $resolved->kind(), 'reference' => $resolved->reference() === '' ? null : $resolved->reference(), 'children' => $render($item->id(), $path)];
            }
            return $result;
        };
        return $render(0, []);
    }

    private function resolve(NavigationItem $item): ?NavigationRenderItem
    {
        if ($item->targetKind() === 'custom') return new NavigationRenderItem('custom', '', $item->label(), $item->customUrl(), true);
        if ($item->targetKind() === 'article_collection' && $item->targetReference() === 'articles') {
            return new NavigationRenderItem('article_collection', 'articles', $item->label(), '/articles', true);
        }
        if ($item->targetKind() !== 'content' || !is_string($item->targetReference())) return null;
        $content = (new ContentRepository($this->database))->findPublishedBySlug($item->targetReference());
        return $content instanceof Content ? new NavigationRenderItem('content', $content->slug(), $item->label() ?: $content->title(), '/content/' . $content->slug(), true) : null;
    }
}
