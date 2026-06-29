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
        private string $siteName
    ) {
    }

    public function render(
        string $title,
        string $content,
        User $user,
        string $csrfToken,
        ?string $currentPath = null
    ): string {
        return $this->view->render('admin/layout', [
            'title' => $title,
            'appName' => $this->appName,
            'siteName' => $this->siteName,
            'adminBaseUrl' => $this->adminUrl->baseUrl(),
            'adminLogoutUrl' => $this->adminUrl->childUrl('logout'),
            'csrfToken' => $csrfToken,
            'userName' => $user->name(),
            'userEmail' => $user->email(),
            'currentPath' => $currentPath,
            'navigation' => $this->navigation->itemsFor($user),
            'content' => $content,
        ]);
    }
}
