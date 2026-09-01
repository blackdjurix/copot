<?php

use Copot\Core\Response;
use Copot\Core\ContentDeliveryService;
use Copot\Core\ContentRepository;
use Copot\Core\MediaDeliveryService;
use Copot\Core\MediaFileInspector;
use Copot\Core\MediaFilesystemStorage;
use Copot\Core\MediaRepository;

$contentDelivery = new ContentDeliveryService(new ContentRepository($app->database()));
$mediaDelivery = new MediaDeliveryService(new MediaRepository($app->database()), new MediaFileInspector(), new MediaFilesystemStorage($app->path('storage/media')));

$app->router()->get('/', function () use ($app): Response {
    return Response::html($app->viewRenderer()->renderFile(
        $app->viewResolver()->resolve('core::home'),
        [],
        null,
        $app->branding()->name()
    ));
});

$app->router()->get('/content/{slug}', function ($request, array $params) use ($app, $contentDelivery): Response {
    $slug = trim((string) ($params['slug'] ?? ''));
    $renderData = $slug === '' ? null : $contentDelivery->findPublishedBySlug($slug);

    if ($renderData === null) {
        return $app->adminErrors()->response($request, 404);
    }

    return Response::html($app->viewRenderer()->renderFile(
        $app->viewResolver()->resolve('content::show'),
        ['content' => $renderData],
        null,
        (string) ($renderData['title'] ?? $app->branding()->name())
    ));
});

$app->router()->get('/articles', function () use ($app, $contentDelivery): Response {
    $articles = $contentDelivery->publishedArticles();
    return Response::html($app->viewRenderer()->renderFile(
        $app->viewResolver()->resolve('core::articles'),
        ['articles' => $articles],
        null,
        'Articles'
    ));
});

$app->router()->get('/media/{id}', function ($request, array $params) use ($mediaDelivery): Response {
    $id = (string) ($params['id'] ?? '');
    if (!preg_match('/^[1-9][0-9]*$/', $id)) return Response::content('404 Not Found', 404);
    return $mediaDelivery->inline((int) $id);
});

$app->router()->get('/media/{id}/download', function ($request, array $params) use ($mediaDelivery): Response {
    $id = (string) ($params['id'] ?? '');
    if (!preg_match('/^[1-9][0-9]*$/', $id)) return Response::content('404 Not Found', 404);
    return $mediaDelivery->download((int) $id);
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
