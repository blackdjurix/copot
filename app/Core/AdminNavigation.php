<?php

namespace Copot\Core;

class AdminNavigation
{
    private array $items = [];

    public function add(string $label, string $url, string|array|null $permissions = null): void
    {
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
        ];
    }

    public function itemsFor(?User $user): array
    {
        $items = [];

        foreach ($this->items as $item) {
            if (!$this->isVisible($item['permissions'], $user)) {
                continue;
            }

            $items[] = [
                'label' => $item['label'],
                'url' => $item['url'],
            ];
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
