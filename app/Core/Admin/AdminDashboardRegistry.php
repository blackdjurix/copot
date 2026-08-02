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
        int $priority = 100,
        array $options = []
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

        $metadata = $this->normalizeOptions($options);

        $this->widgets[$id] = array_merge([
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'permissions' => $this->normalizePermissions($permissions),
            'priority' => $priority,
        ], $metadata, [
            'registration_order' => $this->registrationOrder++,
        ]);
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

            $content = $this->resolveContent($widget, $user);
            if ($content === false) {
                continue;
            }

            $widgets[] = $widget;
            $widgets[array_key_last($widgets)]['content'] = $content;
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
            'owner' => $widget['owner'],
            'purpose' => $widget['purpose'],
            'footprint' => $widget['footprint'],
            'content' => $widget['content'],
        ], $widgets);
    }

    private function normalizeOptions(array $options): array
    {
        $owner = $options['owner'] ?? null;
        if ($owner !== null && (!is_string($owner) || trim($owner) === '')) {
            throw new \InvalidArgumentException('Admin dashboard widget owner identity is invalid.');
        }

        $purpose = $options['purpose'] ?? null;
        if ($purpose !== null && (!is_string($purpose) || trim($purpose) === '')) {
            throw new \InvalidArgumentException('Admin dashboard widget purpose is invalid.');
        }

        $footprint = $options['footprint'] ?? 'compact';
        if (!is_string($footprint) || !in_array($footprint, ['compact', 'standard', 'wide'], true)) {
            throw new \InvalidArgumentException('Admin dashboard widget footprint is invalid.');
        }

        $provider = $options['provider'] ?? null;
        if ($provider !== null && !is_callable($provider)) {
            throw new \InvalidArgumentException('Admin dashboard widget provider is invalid.');
        }

        return [
            'owner' => $owner === null ? null : trim($owner),
            'purpose' => $purpose === null ? null : trim($purpose),
            'footprint' => $footprint,
            'provider' => $provider,
        ];
    }

    private function resolveContent(array $widget, User $user): array|null|false
    {
        $provider = $widget['provider'];
        if ($provider === null) {
            return null;
        }

        try {
            $content = $provider($user);
        } catch (\Throwable) {
            return false;
        }

        if ($content === null) {
            return null;
        }

        return is_array($content) ? $content : false;
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
