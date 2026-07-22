<?php

declare(strict_types=1);

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

$source = static fn (string $relativePath): string => (string) file_get_contents($basePath . '/' . $relativePath);
$list = $source('modules/content/views/admin/list.php');
$form = $source('modules/content/views/admin/form.php');
$css = $source('public/admin-assets/css/admin.css');

$assert(str_contains($list, 'class="admin-content-header"'), 'Content list header hierarchy is missing.');
$assert(str_contains($list, 'name="q"') && str_contains($list, 'id="content-search"'), 'Content search label or request field regressed.');
$assert(str_contains($list, 'id="content-type"') && str_contains($list, 'id="content-status"') && str_contains($list, 'id="content-per-page"'), 'Content filter controls are incomplete.');
$assert(str_contains($list, 'No content yet') && str_contains($list, 'No matching content'), 'Distinct Content empty states are missing.');
$assert(str_contains($list, 'admin-content-type-badge') && str_contains($list, '$typeLabels'), 'Human-readable Content type presentation is missing.');
$assert(str_contains($list, '$statusLabels') && str_contains($list, 'admin-badge--success'), 'Human-readable Content status presentation is missing.');
$assert(str_contains($list, 'data-label="Title"') && str_contains($list, 'data-label="Actions"'), 'Responsive Content row labels are missing.');
$assert(str_contains($list, 'admin-content-slug-column'), 'Narrow-screen reduced-column hook is missing.');
$assert(str_contains($list, "\$paginationUrl(\$currentPage - 1)") && str_contains($list, "\$paginationUrl(\$currentPage + 1)"), 'Content pagination links are incomplete.');
$assert(!str_contains($list, 'type="checkbox" name="selected'), 'Unsupported bulk-selection controls were introduced.');
$assert(!str_contains($list, 'name="taxonomy"'), 'Unsupported taxonomy filter behavior was introduced.');

$assert(preg_match('/<fieldset class="[^"]*admin-content-form-section--main/', $form) === 1, 'Content form detail grouping is missing.');
$assert(str_contains($form, '<legend>Status</legend>') && str_contains($form, '<legend>Taxonomy</legend>'), 'Content form group semantics are incomplete.');
$assert(str_contains($form, 'id="content-form-errors"') && str_contains($form, 'aria-describedby="content-form-errors"'), 'Global Content validation recovery association is missing.');
$assert(str_contains($form, 'aria-invalid="true"') && str_contains($form, '$fieldErrorId'), 'Field-level Content validation association is missing.');
$assert(str_contains($form, '$renderFieldErrors') && str_contains($form, 'id="taxonomy"'), 'Field errors must have one stable association target per field, including Taxonomy.');
$assert(str_contains($form, '$errorTarget = $field === \'taxonomy\' && !$taxonomyAvailable ? null : $field;'), 'Unavailable Taxonomy errors must not link to a missing form target.');
$assert(str_contains($form, 'name="expected_updated_at"'), 'Stale-write form token was removed.');
$assert(str_contains($form, 'name="category_ids[]"') && str_contains($form, 'name="tag_ids[]"'), 'Taxonomy field names changed.');

$assert(str_contains($css, '.admin-content-filters') && str_contains($css, '@media (max-width: 680px)'), 'Content responsive filter styles are missing.');
$assert(str_contains($css, '.admin-content-table-panel .admin-table td::before'), 'Responsive Content row labels are missing from CSS.');
$assert(str_contains($css, '.admin-content-form-layout') && str_contains($css, '.admin-content-form-actions'), 'Content form responsive layout styles are missing.');

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
        return in_array($permission, ['content.read', 'taxonomy.read', 'users.read', 'roles.read', 'modules.manage', 'settings.update'], true);
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
$navigation->add('Settings', '/dapur/settings', 'settings.update', 'settings', 70);
$navigation->add('Dashboard', '/dapur', null, 'dashboard', 10);
$navigation->add('Content', '/dapur/content', 'content.read', 'content', 20);
$navigation->add('Taxonomy', '/dapur/taxonomy', 'taxonomy.read', 'taxonomy', 30);
$navigation->add('Users', '/dapur/users', 'users.read', 'users', 40);
$navigation->add('Roles', '/dapur/roles', 'roles.read', 'roles', 50);
$navigation->add('Modules', '/dapur/modules', 'modules.manage', 'modules', 60);
$navigation->add('Hidden', '/dapur/hidden', 'hidden.read', 'lock', 25);
$assert(array_column($navigation->itemsFor($user), 'label') === [
    'Dashboard', 'Content', 'Taxonomy', 'Users', 'Roles', 'Modules', 'Settings',
], 'Final Admin navigation order or permission filtering regressed.');

$tieAndFallback = new AdminNavigation();
$tieAndFallback->add('Fallback first', '/fallback-first');
$tieAndFallback->add('Explicit tie first', '/tie-first', null, null, 20);
$tieAndFallback->add('Explicit tie second', '/tie-second', null, null, 20);
$tieAndFallback->add('Fallback second', '/fallback-second');
$assert(array_column($tieAndFallback->itemsFor($user), 'label') === [
    'Explicit tie first', 'Explicit tie second', 'Fallback first', 'Fallback second',
], 'Navigation fallback and equal-order handling is not deterministic.');

$renderer = $source('app/Core/Admin/AdminPageRenderer.php');
$assert(str_contains($renderer, 'isActiveNavigationItem') && str_contains($renderer, "\$items[\$index]['active']"), 'Navigation active-state metadata handling changed.');

foreach ([
    'app/Core/Application.php' => "adminNavigation->add('Dashboard', \$this->adminUrl->baseUrl(), null, 'dashboard', 10)",
    'modules/content/routes.php' => "], 'content', 20);",
    'modules/taxonomy/routes.php' => "'taxonomy', 30);",
    'modules/users-access/routes.php' => "'users', 40);",
    'modules/module-manager/routes.php' => "'modules', 60);",
    'modules/settings-manager/routes.php' => "'settings', 70);",
] as $relativePath => $needle) {
    $assert(str_contains(str_replace("\r\n", "\n", $source($relativePath)), $needle), "Final navigation order metadata is missing in [{$relativePath}].");
}

echo "M3.4 Content Batch 6 presentation passed ({$assertions} assertions)." . PHP_EOL;
