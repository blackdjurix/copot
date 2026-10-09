<?php

declare(strict_types=1);

use Copot\Core\DeploymentContext;

$basePath = dirname(__DIR__);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$read = static fn (string $path): string => (string) file_get_contents($basePath . '/' . $path);

$root = DeploymentContext::forLegacyApplication($basePath, '/');
$subdirectory = DeploymentContext::forLegacyApplication($basePath, '/copot-dev');
$assert($root->url('/assets/navigation.css?v=wu3') === '/assets/navigation.css?v=wu3', 'Domain-root asset URL changed unexpectedly.');
$assert($subdirectory->url('/assets/navigation.css?v=wu3') === '/copot-dev/assets/navigation.css?v=wu3', 'Subdirectory asset URL was not prefixed.');
$assert($subdirectory->url('/admin-assets/js/admin-settings.js') === '/copot-dev/admin-assets/js/admin-settings.js', 'Subdirectory admin asset URL was not prefixed.');

$layout = $read('resources/views/layout.php');
$mediaView = $read('resources/views/admin/media/list.php');
$mediaRoute = $read('routes/media_admin.php');
$settingsRoute = $read('routes/site_settings.php');
$contentRoute = $read('routes/content_admin.php');

$assert(str_contains($layout, "is_callable(\$url ?? null) ? \$url('/assets/navigation.css?v=wu3')"), 'Public navigation CSS is not deployment-aware.');
$assert(str_contains($layout, "is_callable(\$url ?? null) ? \$url('/assets/navigation.js?v=wu3')"), 'Public navigation JS is not deployment-aware.');
$assert(str_contains($mediaView, "is_callable(\$url ?? null) ? \$url('/admin-assets/js/admin-core-media.js')"), 'Media core script is not deployment-aware.');
$assert(str_contains($mediaView, "is_callable(\$url ?? null) ? \$url('/admin-assets/js/admin-media-list.js?v=wu4-1')"), 'Media list script is not deployment-aware.');
$assert(str_contains($mediaRoute, "'url' => fn (string \$path): string => \$app->url(\$path)"), 'Media view URL callback is not supplied.');
$assert(str_contains($settingsRoute, "'url' => static fn (string \$url): string => \$app->url(\$url)"), 'Site Settings view URL callback is not supplied.');
$assert(str_contains($contentRoute, '$contentRoute = fn (string $path = \'\'): string => $app->adminUrl()->childUrl('), 'Content browser URLs do not use childUrl().');
$assert(substr_count($contentRoute, 'routeChildUrl(') >= 4, 'Content route registration no longer uses routeChildUrl().');

echo "subdirectory_url_compatibility: {$assertions} assertions passed\n";
