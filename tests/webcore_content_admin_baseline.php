<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$routes = (string) file_get_contents($basePath . '/routes/content_admin.php');
$bootstrap = (string) file_get_contents($basePath . '/bootstrap/app.php');
$assert(str_contains($bootstrap, "routes/content_admin.php"), 'Core Content Admin route is not loaded by bootstrap.');
$assert(str_contains($routes, "routeChildUrl('content')"), 'Core Content Admin list route is missing.');
$assert(str_contains($routes, "routeChildUrl('content/create')"), 'Core Content Admin create route is missing.');
$assert(str_contains($routes, "routeChildUrl('content/{id}/edit')"), 'Core Content Admin edit route is missing.');
$assert(str_contains($routes, "['publish' => 'content.publish', 'archive' => 'content.delete']"), 'Core Content publish/archive route permissions are missing.');
$assert(str_contains($routes, "'content/{id}/' . \$action"), 'Core Content publish/archive route registration is missing.');
$assert(str_contains($routes, 'new ContentService($app->database(), $contentRepository)'), 'Core route does not use Core ContentService.');
$assert(!str_contains($routes, "modules/content/routes.php"), 'Core route imports Content Manager routes.');
$assert(!str_contains($routes, 'contentTaxonomy'), 'Core baseline imports taxonomy behavior.');
$assert(str_contains($routes, 'validateOrReject'), 'Core route does not enforce CSRF validation.');
$assert(str_contains($routes, 'adminPageRenderer'), 'Core route does not use AdminPageRenderer.');
$assert(str_contains($routes, 'Content Manager') && str_contains($routes, 'disabled'), 'Core route does not document disabled-Module boundary.');
echo "Webcore Content Admin baseline static tests passed ({$assertions} assertions)." . PHP_EOL;
