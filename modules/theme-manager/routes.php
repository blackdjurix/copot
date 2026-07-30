<?php

use Copot\Core\Response;

require_once __DIR__ . '/Services/ThemeManagerAdmin.php';

$themeManagerAdmin = new ThemeManagerAdmin($app);
$themesPath = $app->adminUrl()->childUrl('themes');

$app->adminNavigation()->addRequired('Themes', $themesPath, ['admin.access', 'themes.manage'], 'theme', 65);

$app->router()->get($themesPath, static function ($request) use ($themeManagerAdmin): Response {
    return $themeManagerAdmin->inventoryResponse($request);
});

$app->router()->post($app->adminUrl()->childUrl('themes/{theme_id}/activate'), static function ($request) use ($themeManagerAdmin): Response {
    return $themeManagerAdmin->activateResponse($request);
});

$app->router()->get($app->adminUrl()->childUrl('themes/{theme_id}/screenshot'), static function ($request, array $params) use ($themeManagerAdmin): Response {
    return $themeManagerAdmin->screenshotResponse($request, (string) ($params['theme_id'] ?? ''));
});
