<?php

declare(strict_types=1);

use Copot\Core\Admin\AdminIcon;
use Copot\Core\AdminNavigation;
use Copot\Core\PermissionChecker;
use Copot\Core\User;

$basePath = dirname(__DIR__);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$iconsPath = $basePath . '/public/admin-assets/icons';
$iconFiles = glob($iconsPath . '/icon-*.svg') ?: [];
$assert(count($iconFiles) === 106, 'Default Admin icon pack must contain 106 semantic SVG files.');
$assert(is_file($iconsPath . '/manifest.json'), 'Default Admin icon manifest is missing.');

$manifest = json_decode((string) file_get_contents($iconsPath . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$assert(($manifest['iconCount'] ?? null) === 106, 'Admin icon manifest count is incorrect.');
$assert(count($manifest['icons'] ?? []) === 106, 'Admin icon manifest inventory is incomplete.');

foreach ($iconFiles as $iconFile) {
    $svg = (string) file_get_contents($iconFile);
    $assert(preg_match('/\A<svg\b[^>]*>.*<\/svg>\z/is', trim($svg)) === 1, 'Invalid SVG root: ' . basename($iconFile));
    $assert(str_contains($svg, 'viewBox="0 0 24 24"'), 'SVG viewBox contract is invalid: ' . basename($iconFile));
    $assert(str_contains($svg, 'currentColor'), 'SVG currentColor contract is missing: ' . basename($iconFile));
    $assert(!preg_match('/<(?:script|style|foreignObject|image|iframe|object|embed)\b/i', $svg), 'Forbidden SVG element found: ' . basename($iconFile));
    $assert(!preg_match('/\son[a-z]+\s*=/i', $svg), 'Event handler found in SVG: ' . basename($iconFile));
    $assert(!preg_match('/\s(?:href|xlink:href)\s*=/i', $svg), 'External SVG reference found: ' . basename($iconFile));
}

$icons = new AdminIcon($iconsPath);
$dashboardIcon = $icons->render('dashboard', 'admin-nav-icon__svg');
$assert(str_contains($dashboardIcon, 'class="admin-icon admin-nav-icon__svg"'), 'Rendered icon class contract is invalid.');
$assert(str_contains($dashboardIcon, 'aria-hidden="true"'), 'Rendered icon must be decorative.');
$assert(str_contains($dashboardIcon, 'focusable="false"'), 'Rendered icon must not receive focus.');
$assert($icons->exists('dashboard'), 'Known icon key was not resolved.');
$assert(!$icons->exists('../dashboard'), 'Unsafe icon key was accepted.');
$assert($icons->render('../dashboard') === $icons->render('module'), 'Unsafe icon key must resolve through the generic fallback.');

$permissions = new class extends PermissionChecker {
    public function __construct()
    {
    }

    public function userHasRole(int $userId, string $role): bool
    {
        return false;
    }

    public function userCan(int $userId, string $permission): bool
    {
        return $permission === 'content.read';
    }
};
$user = new User([
    'id' => 1,
    'name' => 'Admin',
    'email' => 'admin@example.test',
    'password_hash' => 'unused',
    'status' => 'active',
], $permissions);

$navigation = new AdminNavigation();
$navigation->add('Dashboard', '/dapur', null, 'dashboard');
$navigation->add('Content', '/dapur/content', 'content.read', 'content');
$navigation->add('Hidden', '/dapur/hidden', 'hidden.read', 'lock');
$items = $navigation->itemsFor($user);
$assert($items === [
    ['label' => 'Dashboard', 'url' => '/dapur', 'icon' => 'dashboard'],
    ['label' => 'Content', 'url' => '/dapur/content', 'icon' => 'content'],
], 'Permission filtering or optional navigation icon metadata regressed.');

$invalidIconRejected = false;
try {
    $navigation->add('Unsafe', '/dapur/unsafe', null, '../unsafe');
} catch (InvalidArgumentException) {
    $invalidIconRejected = true;
}
$assert($invalidIconRejected, 'Unsafe navigation icon key was not rejected.');

$orderedNavigation = new AdminNavigation();
$orderedNavigation->add('Settings', '/dapur/settings', null, 'settings', 70);
$orderedNavigation->add('Dashboard', '/dapur', null, 'dashboard', 10);
$orderedNavigation->add('Content', '/dapur/content', null, 'content', 20);
$orderedNavigation->add('Taxonomy', '/dapur/taxonomy', null, 'taxonomy', 30);
$orderedNavigation->add('Users', '/dapur/users', null, 'users', 40);
$orderedNavigation->add('Roles', '/dapur/roles', null, 'roles', 50);
$orderedNavigation->add('Modules', '/dapur/modules', null, 'modules', 60);
$assert(array_column($orderedNavigation->itemsFor($user), 'label') === [
    'Dashboard', 'Content', 'Taxonomy', 'Users', 'Roles', 'Modules', 'Settings',
], 'Explicit Admin navigation order was not applied.');

$layout = (string) file_get_contents($basePath . '/resources/views/admin/layout.php');
$css = (string) file_get_contents($basePath . '/public/admin-assets/css/admin.css');
$script = (string) file_get_contents($basePath . '/public/admin-assets/js/admin-shell.js');

$assert(substr_count($layout, '<script defer src="/admin-assets/js/admin-shell.js"></script>') === 1, 'Admin shell script must load exactly once.');
$assert(str_contains($layout, 'aria-controls="admin-sidebar"'), 'Mobile navigation trigger does not control the shared sidebar.');
$assert(str_contains($layout, 'data-admin-nav-overlay'), 'Mobile navigation overlay hook is missing.');
$assert(str_contains($layout, 'class="admin-breadcrumb" aria-label="Breadcrumb"'), 'Shared breadcrumb landmark is missing.');
$assert(substr_count($layout, 'foreach ($navigationItems as $item)') === 2, 'Sidebar and Quick menu must use the same filtered navigation source.');
$assert(str_contains($layout, "\$icon(\$item['icon'] ?? 'module'"), 'Navigation icon fallback is missing.');
$assert(!str_contains($layout, 'Command Search'), 'Command Search must remain omitted.');
$assert(!str_contains($layout, 'Notifications'), 'Notifications must remain omitted.');
$assert(!str_contains($layout, 'admin-sidebar-status'), 'Sidebar system status must remain omitted.');
$assert(!preg_match('/[\'"]\/admin[\'"]/', $layout), 'Literal Admin route was introduced into the shared layout.');

$assert(str_contains($css, 'Shell Foundation Batch 1: Copot Admin default theme'), 'Shell Foundation CSS section is missing.');
$assert(str_contains($css, '.admin-icon'), 'Shared inline SVG presentation contract is missing.');
$assert(str_contains($css, 'html.admin-shell-js .admin-shell-page .admin-sidebar'), 'Progressive-enhancement off-canvas selector is missing.');
$assert(str_contains($css, 'var(--admin-color-overlay)'), 'Token-based mobile overlay is missing.');
$assert(str_contains($css, 'html.admin-nav-open') && str_contains($css, 'overflow: hidden;'), 'Mobile navigation scroll-lock contract is missing.');

$assert(str_contains($script, "document.documentElement.classList.add('admin-shell-js')"), 'No-JavaScript progressive enhancement gate is missing.');
$assert(str_contains($script, "window.matchMedia('(max-width: 900px)')"), 'Responsive state synchronization is missing.');
$assert(str_contains($script, "openButton.setAttribute('aria-expanded', 'true')"), 'Open state does not synchronize aria-expanded.');
$assert(str_contains($script, "event.key === 'Escape'"), 'Escape dismissal is missing.');
$assert(str_contains($script, "'inert' in mainShell"), 'Background interaction isolation is missing.');
$assert(str_contains($script, 'trapNavigationFocus(event)'), 'Mobile drawer focus containment is missing.');
$assert(str_contains($script, 'openButton.focus()'), 'Focus return to the trigger is missing.');
$assert(!str_contains($script, 'command-search'), 'Admin shell JavaScript must not implement Command Search.');

foreach ([
    'app/Core/Application.php' => "adminNavigation->add('Dashboard', \$this->adminUrl->baseUrl(), null, 'dashboard', 10)",
    'modules/content/routes.php' => "], 'content', 20);",
    'modules/taxonomy/routes.php' => "'taxonomy', 30);",
    'modules/settings-manager/routes.php' => "'settings', 70);",
    'modules/users-access/routes.php' => "'users', 40);",
    'modules/module-manager/routes.php' => "'modules', 60);",
] as $relativePath => $needle) {
    $source = (string) file_get_contents($basePath . '/' . $relativePath);
    $assert(str_contains(str_replace("\r\n", "\n", $source), $needle), "Expected icon registration is missing in [{$relativePath}].");
}

$usersRoutes = (string) file_get_contents($basePath . '/modules/users-access/routes.php');
$assert(str_contains(str_replace("\r\n", "\n", $usersRoutes), "'roles.permissions.manage',\n], 'roles', 50);"), 'Roles navigation icon registration is missing.');

echo "Shell Foundation Batch 1 smoke tests passed ({$assertions} assertions)." . PHP_EOL;
