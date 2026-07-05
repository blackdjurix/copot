<?php

use Copot\Core\Response;

$app->router()->get('/', function () use ($app): Response {
    return Response::html($app->viewRenderer()->renderFile(
        $app->viewResolver()->resolve('core::home'),
        [],
        null,
        $app->branding()->name()
    ));
});

$app->router()->get('/theme-assets/{themeId}/{assetPath}', function ($request, array $params) use ($app): Response {
    try {
        return $app->themeAssets()->serve($params['themeId'] ?? '', $params['assetPath'] ?? '');
    } catch (\Throwable) {
        return Response::content('404 Not Found', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
});

$app->router()->get('/site-assets/logo', function () use ($app): Response {
    try {
        return $app->siteAssets()->serve('logo');
    } catch (\Throwable) {
        return Response::content('404 Not Found', 404, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
});

$app->router()->get('/site-assets/favicon', function () use ($app): Response {
    try {
        return $app->siteAssets()->serve('favicon');
    } catch (\Throwable) {
        return Response::content('404 Not Found', 404, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
});
