<?php

namespace Copot\Core;

class AdminNavigation
{
    private array $items = [];

    public function add(
        string $label,
        string $url,
        string|array|null $permissions = null,
        ?string $icon = null,
        ?int $order = null
    ): void {
        $label = trim($label);
        $url = '/' . trim($url, '/');

        if ($label === '') {
            throw new \InvalidArgumentException('Admin navigation label cannot be empty.');
        }

        if ($url === '/') {
            throw new \InvalidArgumentException('Admin navigation URL cannot be empty.');
        }

        foreach ($this->items as $item) {
            if ($item['url'] === $url) {
                return;
            }
        }

        $this->items[] = [
            'label' => $label,
            'url' => $url,
            'permissions' => $this->normalizePermissions($permissions),
            'icon' => $this->normalizeIcon($icon),
            'order' => $order,
        ];
    }

    public function itemsFor(?User $user): array
    {
        $items = [];

        $visibleItems = [];

        foreach ($this->items as $index => $item) {
            if (!$this->isVisible($item['permissions'], $user)) {
                continue;
            }

            $visibleItems[] = [
                'index' => $index,
                'item' => $item,
            ];
        }

        usort($visibleItems, static function (array $left, array $right): int {
            $leftOrder = $left['item']['order'] ?? PHP_INT_MAX;
            $rightOrder = $right['item']['order'] ?? PHP_INT_MAX;

            return [$leftOrder, $left['index']] <=> [$rightOrder, $right['index']];
        });

        foreach ($visibleItems as $visibleItem) {
            $item = $visibleItem['item'];

            $resolved = [
                'label' => $item['label'],
                'url' => $item['url'],
            ];

            if ($item['icon'] !== null) {
                $resolved['icon'] = $item['icon'];
            }

            $items[] = $resolved;
        }

        return $items;
    }

    private function normalizePermissions(string|array|null $permissions): array
    {
        if ($permissions === null) {
            return [];
        }

        if (is_string($permissions)) {
            $permission = trim($permissions);

            return $permission === '' ? [] : [$permission];
        }

        $normalized = [];

        foreach ($permissions as $permission) {
            if (!is_string($permission)) {
                throw new \InvalidArgumentException('Admin navigation permissions must be strings.');
            }

            $permission = trim($permission);

            if ($permission !== '' && !in_array($permission, $normalized, true)) {
                $normalized[] = $permission;
            }
        }

        return $normalized;
    }

    private function normalizeIcon(?string $icon): ?string
    {
        if ($icon === null) {
            return null;
        }

        $icon = strtolower(trim($icon));

        if ($icon === '') {
            return null;
        }

        if (str_starts_with($icon, 'icon-')) {
            $icon = substr($icon, 5);
        }

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $icon) !== 1) {
            throw new \InvalidArgumentException('Admin navigation icon key is invalid.');
        }

        return $icon;
    }

    private function isVisible(array $permissions, ?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($permissions === []) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
