<?php

use Copot\Core\Response;
use Copot\Core\ThemeException;
use Copot\Core\ViewException;

$app->router()->get('/', function () use ($app): Response {
    try {
        return Response::html($app->viewRenderer()->renderFile(
            $app->viewResolver()->resolve('core::home'),
            [],
            null,
            $app->config()->get('app.name', 'Copot')
        ));
    } catch (ThemeException|ViewException|\Throwable) {
        return Response::html('<h1>Theme rendering error.</h1>', 500);
    }
});

$app->router()->get('/theme-assets/{themeId}/{assetPath}', function ($request, array $params) use ($app): Response {
    try {
        return $app->themeAssets()->serve($params['themeId'] ?? '', $params['assetPath'] ?? '');
    } catch (\Throwable) {
        return Response::content('404 Not Found', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
});
