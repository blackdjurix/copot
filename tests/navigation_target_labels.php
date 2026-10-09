<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$view = (string) file_get_contents($basePath . '/resources/views/admin/navigation/items.php');
$route = (string) file_get_contents($basePath . '/routes/navigation_admin.php');
$script = (string) file_get_contents($basePath . '/public/admin-assets/js/navigation-admin.js');

$assert(str_contains($view, "is_callable(\$url ?? null) ? \$url('/admin-assets/js/navigation-admin.js?v=wu3-dirty-transition')"), 'Navigation admin script is not deployment-aware.');
$assert(str_contains($route, "'url' => static fn (string \$url): string => \$app->url(\$url)"), 'Navigation view URL callback is not supplied.');
$assert(str_contains($view, '>Link</option>') && str_contains($view, '>Content</option>'), 'Link and Content target labels are missing.');
$assert(str_contains($view, '>Article Collection</option>') && str_contains($view, '>Navigation Group</option>'), 'Article Collection and Navigation Group target labels are missing.');
$assert(str_contains($script, "field('label', { 'class': 'admin-field__label', 'for': 'custom_url' }, 'Link')"), 'Link target field label is missing.');
$assert(str_contains($script, "field('label', { 'class': 'admin-field__label', 'for': 'content_reference' }, 'Content')"), 'Content target field label is missing.');
$assert(str_contains($script, "select.value === 'article_collection'"), 'Article Collection target synchronization is missing.');
$assert(str_contains($script, "select.value === 'navigation_group'" ) === false && str_contains($script, 'Navigation Group has no destination'), 'Navigation Group target synchronization is missing.');
$assert(str_contains($route, "'target_kind' => 'link'") && str_contains($route, "'target_kind' => 'content'"), 'Link and Content submission mappings are missing.');
$assert(str_contains($route, "'target_kind' => 'navigation_group'") && str_contains($route, "'target_kind' => 'article_collection'"), 'Article Collection and Navigation Group submission mappings are missing.');

echo "navigation_target_labels: {$assertions} assertions passed\n";
