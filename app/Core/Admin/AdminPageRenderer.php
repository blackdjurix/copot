<?php

namespace Copot\Core\Admin;

use Copot\Core\AdminNavigation;
use Copot\Core\User;
use Copot\Core\View;

class AdminPageRenderer
{
    public function __construct(
        private View $view,
        private AdminUrl $adminUrl,
        private AdminNavigation $navigation,
        private string $appName,
        private string $siteName,
        private string $documentLocale = 'en'
    ) {
    }

    public function render(
        string $title,
        string $content,
        User $user,
        string $csrfToken,
        ?string $currentPath = null
    ): string {
        $navigation = $this->resolveNavigation($this->navigation->itemsFor($user), $currentPath);

        return $this->view->render('admin/layout', [
            'title' => $title,
            'appName' => $this->appName,
            'siteName' => $this->siteName,
            'documentLocale' => $this->documentLocale(),
            'adminBaseUrl' => $this->adminUrl->baseUrl(),
            'adminLogoutUrl' => $this->adminUrl->childUrl('logout'),
            'csrfToken' => $csrfToken,
            'userName' => $user->name(),
            'userEmail' => $user->email(),
            'currentPath' => $currentPath,
            'navigation' => $navigation,
            'content' => $content,
        ]);
    }

    private function resolveNavigation(array $items, ?string $currentPath): array
    {
        $currentPath = $this->normalizePath($currentPath ?? '');
        $baseUrl = $this->adminUrl->baseUrl();

        foreach ($items as $index => $item) {
            $url = $this->normalizePath((string) ($item['url'] ?? ''));
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
