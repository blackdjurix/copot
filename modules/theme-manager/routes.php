<?php

use Copot\Core\Response;

require_once __DIR__ . '/Services/ThemeManagerAdmin.php';
require_once __DIR__ . '/Services/ThemeSettingsAdmin.php';

$themeManagerAdmin = new ThemeManagerAdmin($app);
$themeSettingsAdmin = new ThemeSettingsAdmin($app);
$themesPath = $app->adminUrl()->childUrl('themes');

$app->adminNavigation()->addRequired('Themes', $themesPath, ['admin.access', 'themes.manage'], 'theme', 65);

$app->router()->get($themesPath, static function ($request) use ($themeManagerAdmin): Response {
    return $themeManagerAdmin->inventoryResponse($request);
});

$app->router()->post($app->adminUrl()->childUrl('themes/{theme_id}/activate'), static function ($request, array $params) use ($themeManagerAdmin): Response {
    return $themeManagerAdmin->activateResponse($request, (string) ($params['theme_id'] ?? ''));
});

$app->router()->get($app->adminUrl()->childUrl('themes/{theme_id}/screenshot'), static function ($request, array $params) use ($themeManagerAdmin): Response {
    return $themeManagerAdmin->screenshotResponse($request, (string) ($params['theme_id'] ?? ''));
});

$settingsPath = $app->adminUrl()->childUrl('themes/{theme_id}/settings');
$resetPath = $app->adminUrl()->childUrl('themes/{theme_id}/settings/reset');
$app->router()->get($settingsPath, static function ($request, array $params) use ($themeSettingsAdmin): Response {
    return $themeSettingsAdmin->showResponse($request, (string) ($params['theme_id'] ?? ''));
});
$app->router()->post($settingsPath, static function ($request, array $params) use ($themeSettingsAdmin): Response {
    return $themeSettingsAdmin->saveResponse($request, (string) ($params['theme_id'] ?? ''));
});
$app->router()->post($resetPath, static function ($request, array $params) use ($themeSettingsAdmin): Response {
    return $themeSettingsAdmin->resetResponse($request, (string) ($params['theme_id'] ?? ''));
});
