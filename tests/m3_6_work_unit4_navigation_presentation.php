<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void { $assertions++; if (!$condition) { throw new RuntimeException($message); } };
$manifest = json_decode((string) file_get_contents($basePath . '/modules/navigation/module.json'), true, 512, JSON_THROW_ON_ERROR);
$assert(($manifest['routes'] ?? null) === 'routes.php', 'Navigation route entry is missing.');
$views = ['menus.php', 'menu-form.php', 'items.php', 'item-form.php'];
foreach ($views as $view) {
    $contents = (string) file_get_contents($basePath . '/modules/navigation/views/admin/' . $view);
    $assert(str_contains($contents, 'htmlspecialchars'), "{$view} does not escape output.");
    $assert(str_contains($contents, 'admin-panel'), "{$view} does not use Admin Shell panel structure.");
}
$items = (string) file_get_contents($basePath . '/modules/navigation/views/admin/items.php');
$form = (string) file_get_contents($basePath . '/modules/navigation/views/admin/item-form.php');
$assert(str_contains($items, 'item_ids[]') && str_contains($items, 'Move up') && str_contains($items, 'Move down'), 'Sibling reorder controls are missing.');
$assert(str_contains($form, 'target_mode') && str_contains($form, 'custom_url') && str_contains($form, 'target_kind') && str_contains($form, 'target_reference'), 'Custom/provider target form controls are incomplete.');
$assert(str_contains($form, 'parent_id') && str_contains($form, 'is_visible'), 'Parent and visibility controls are missing.');
$routes = (string) file_get_contents($basePath . '/modules/navigation/routes.php');
$assert(str_contains($routes, "can('admin.access')") && str_contains($routes, "can('navigation.manage')"), 'Both required permissions are not enforced.');
$assert(str_contains($routes, 'validateOrReject') && str_contains($routes, "adminNavigation()->add('Navigation'"), 'Shared CSRF or separate AdminNavigation registration is missing.');
echo "M3.6 Work Unit 4 Navigation presentation passed ({$assertions} assertions)." . PHP_EOL;
