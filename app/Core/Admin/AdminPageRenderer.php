<?php

namespace Copot\Core\Admin;

use Copot\Core\AdminNavigation;
use Copot\Core\User;
use Copot\Core\View;

class AdminPageRenderer
{
    private AdminIcon $icons;

    public function __construct(
        private View $view,
        private AdminUrl $adminUrl,
        private AdminNavigation $navigation,
        private string $appName,
        private string $siteName,
        private string $documentLocale = 'en',
        ?AdminIcon $icons = null
    ) {
        $this->icons = $icons ?? new AdminIcon();
    }

    public function render(
        string $title,
        string $content,
        User $user,
        string $csrfToken,
        ?string $currentPath = null,
        ?array $pageFrame = null
    ): string {
        $navigation = $this->resolveNavigation($this->navigation->itemsFor($user), $currentPath);

        if ($pageFrame !== null) {
            $content = $this->renderPageFrame($pageFrame + [
                'title' => $title,
                'content' => $content,
            ]);
        }

        return $this->view->render('admin/layout', [
            'title' => $title,
            'appName' => $this->appName,
            'siteName' => $this->siteName,
            'documentLocale' => $this->documentLocale(),
            'adminBaseUrl' => $this->adminUrl->baseUrl(),
            'adminLogoutUrl' => $this->adminUrl->childUrl('logout'),
            'url' => fn (string $path): string => $this->adminUrl->url($path),
            'csrfToken' => $csrfToken,
            'userName' => $user->name(),
            'userEmail' => $user->email(),
            'currentPath' => $currentPath === null ? null : $this->adminUrl->url($currentPath),
            'navigation' => $navigation,
            'renderAdminIcon' => fn (?string $key, string $class = 'admin-icon'): string => $this->icons->render($key, $class),
            'content' => $content,
        ]);
    }

    /**
     * Render the bounded Webcore-owned frame inside the existing admin-main.
     * Consumer content is intentionally rendered as opaque HTML.
     */
    public function renderPageFrame(array $frame): string
    {
        $title = $frame['title'] ?? null;
        $content = $frame['content'] ?? null;

        if (!is_string($title) || trim($title) === '') {
            throw new \InvalidArgumentException('Admin Page Frame title is required.');
        }

        if (!is_string($content)) {
            throw new \InvalidArgumentException('Admin Page Frame content must be a string.');
        }

        $surface = $frame['surface'] ?? 'panel';
        $spacing = $frame['spacing'] ?? 'default';

        if (!in_array($surface, ['panel', 'transparent'], true)) {
            throw new \InvalidArgumentException('Admin Page Frame surface is invalid.');
        }

        if (!in_array($spacing, ['default', 'none'], true)) {
            throw new \InvalidArgumentException('Admin Page Frame spacing is invalid.');
        }

        $optional = [];
        foreach (['description', 'bar', 'footer'] as $region) {
            $value = $frame[$region] ?? null;

            if ($value !== null && !is_string($value)) {
                throw new \InvalidArgumentException("Admin Page Frame {$region} must be a string or null.");
            }

            $optional[$region] = $value !== null && $value !== '' ? $value : null;
        }

        $titleId = 'admin-page-frame-title-' . substr(hash('sha256', trim($title)), 0, 12);

        return $this->view->render('admin/page-frame', [
            'title' => trim($title),
            'titleId' => $titleId,
            'description' => $optional['description'],
            'bar' => $optional['bar'],
            'content' => $content,
            'footer' => $optional['footer'],
            'surface' => $surface,
            'spacing' => $spacing,
        ]);
    }

    private function resolveNavigation(array $items, ?string $currentPath): array
    {
        $currentPath = $this->normalizePath($currentPath ?? '');
        $baseUrl = $this->adminUrl->routeBaseUrl();

        foreach ($items as $index => $item) {
            $url = $this->normalizePath($this->adminUrl->routePath((string) ($item['url'] ?? '')));
            $items[$index]['active'] = $this->isActiveNavigationItem($url, $currentPath, $baseUrl);
        }

        return $items;
    }

    private function isActiveNavigationItem(string $url, string $currentPath, string $baseUrl): bool
    {
        if ($url === '' || $currentPath === '') {
            return false;
        }

        if ($url === $baseUrl) {
            return $currentPath === $baseUrl;
        }

        return $currentPath === $url || str_starts_with($currentPath, $url . '/');
    }

    private function normalizePath(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH);

        if (!is_string($path) || trim($path) === '') {
            return '';
        }

        return '/' . trim($path, '/');
    }

    public function documentLocale(): string
    {
        $locale = str_replace('_', '-', trim($this->documentLocale));

        return preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/i', $locale) === 1 ? $locale : 'en';
    }
}
