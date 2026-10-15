<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;
    if (!$condition) throw new RuntimeException($message);
};
$read = static fn (string $path): string => (string) file_get_contents($basePath . '/' . $path);

$routes = $read('routes/site_settings.php');
$view = $read('resources/views/admin/site-settings.php');
$redirectsView = $read('resources/views/admin/site-settings-redirects.php');
$redirectRoutes = $read('modules/redirects/routes.php');
$repository = $read('app/Core/RedirectRepository.php');
$service = $read('app/Core/RedirectService.php');
$css = $read('public/admin-assets/css/admin.css');

$assert(str_contains($routes, 'new \\Copot\\Core\\RedirectRepository') && str_contains($routes, '$redirectRepository->all()'), 'Site Settings does not read through the canonical Redirect repository.');
$assert(str_contains($view, 'site-settings-redirects.php') && str_contains($redirectsView, 'Add Redirect'), 'Redirects is not projected inline with an Add Redirect action.');
$assert(str_contains($redirectsView, '<th scope="col">Source</th>') && str_contains($redirectsView, '<th scope="col">Destination</th>') && str_contains($redirectsView, '<th scope="col">Type</th>') && str_contains($redirectsView, '<th scope="col">Actions</th>'), 'Inline Redirects table columns are incomplete.');
$assert(str_contains($redirectsView, 'No redirects yet') && str_contains($redirectsView, 'site-settings-redirects__empty'), 'Redirects empty state is missing.');
$assert(str_contains($redirectsView, "/create") && str_contains($redirectsView, "/edit") && str_contains($redirectsView, "/delete"), 'Inline Redirect actions do not point to canonical action paths.');
$assert(str_contains($redirectsView, 'statusCode()') && str_contains($redirectsView, '301 Permanent') && str_contains($redirectsView, '302 Temporary'), 'Redirect Type does not use the existing canonical status code.');
$assert(str_contains($redirectsView, 'htmlspecialchars') || str_contains($redirectsView, '$escape('), 'Redirect source and destination output is not escaped.');
$assert(str_contains($redirectRoutes, "['admin.access', 'redirects.manage']") && str_contains($routes, "'redirects.manage'"), 'Redirect projection changed the capability permission boundary.');
$assert(str_contains($repository, 'public function all(): array') && str_contains($service, 'public function create') && str_contains($service, 'public function update') && str_contains($service, 'public function delete'), 'Canonical Redirect repository/service authority is incomplete.');
$assert(!str_contains($redirectsView, 'settings.update'), 'Redirect projection introduced an unrelated settings.update dependency.');
$assert(str_contains($css, '.site-settings-redirects__table') && str_contains($css, 'site-settings-redirects__toolbar') && str_contains($css, '@media (max-width: 760px)'), 'Redirects responsive table treatment is missing.');

echo "WU5 Site Settings Redirects projection passed ({$assertions} assertions)." . PHP_EOL;
