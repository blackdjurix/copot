<?php

namespace Copot\Core\Admin;

use Copot\Core\User;

class AdminDashboardRegistry
{
    private array $widgets = [];
    private int $registrationOrder = 0;

    public function add(
        string $id,
        string $title,
        string $description,
        ?string $url = null,
        string|array|null $permissions = null,
        int $priority = 100
    ): void {
        $id = trim($id);
        $title = trim($title);
        $description = trim($description);
        $url = $url === null ? null : trim($url);

        if ($id === '' || preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $id) !== 1) {
            throw new \InvalidArgumentException('Admin dashboard widget ID is invalid.');
        }

        if ($title === '') {
            throw new \InvalidArgumentException('Admin dashboard widget title cannot be empty.');
        }

        if ($description === '') {
            throw new \InvalidArgumentException('Admin dashboard widget description cannot be empty.');
        }

        if ($url !== null && ($url === '' || !str_starts_with($url, '/'))) {
            throw new \InvalidArgumentException('Admin dashboard widget URL must be root-relative.');
        }

        if (isset($this->widgets[$id])) {
            throw new \InvalidArgumentException("Admin dashboard widget [{$id}] is already registered.");
        }

        $this->widgets[$id] = [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'permissions' => $this->normalizePermissions($permissions),
            'priority' => $priority,
            'registration_order' => $this->registrationOrder++,
        ];
    }

    public function itemsFor(?User $user): array
    {
        if (!$user) {
            return [];
        }

        $widgets = [];

        foreach ($this->widgets as $widget) {
            if (!$this->isVisible($widget['permissions'], $user)) {
                continue;
            }

            $widgets[] = $widget;
        }

        usort($widgets, static function (array $left, array $right): int {
            $priority = $left['priority'] <=> $right['priority'];

            return $priority !== 0
                ? $priority
                : $left['registration_order'] <=> $right['registration_order'];
        });

        return array_map(static fn (array $widget): array => [
            'id' => $widget['id'],
            'title' => $widget['title'],
            'description' => $widget['description'],
            'url' => $widget['url'],
        ], $widgets);
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
                throw new \InvalidArgumentException('Admin dashboard widget permissions must be strings.');
            }

            $permission = trim($permission);

            if ($permission !== '' && !in_array($permission, $normalized, true)) {
                $normalized[] = $permission;
            }
        }

        return $normalized;
    }

    private function isVisible(array $permissions, User $user): bool
    {
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
