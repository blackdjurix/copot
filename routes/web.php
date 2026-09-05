<?php

use Copot\Core\Response;
use Copot\Core\ContentDeliveryService;
use Copot\Core\ContentRepository;
use Copot\Core\MediaDeliveryService;
use Copot\Core\MediaFileInspector;
use Copot\Core\MediaFilesystemStorage;
use Copot\Core\MediaRepository;
use Copot\Core\HomepageHeroImageService;
use Copot\Core\MediaLifecycleService;
use Copot\Core\MediaUsageRepository;

require_once $app->path('app/Core/HomepageHeroImageService.php');

$contentDelivery = new ContentDeliveryService(new ContentRepository($app->database()));
$contentRepository = new ContentRepository($app->database());
$mediaRepository = new MediaRepository($app->database());
$mediaDelivery = new MediaDeliveryService(new MediaRepository($app->database()), new MediaFileInspector(), new MediaFilesystemStorage($app->path('storage/media')));
$articleCollectionMediaUrl = static function (int $mediaId) use ($app, $mediaRepository): ?string {
    $media = $mediaRepository->findById($mediaId);
    return $media instanceof \Copot\Core\Media && $media->kind() === 'image'
        ? $app->url('/media/' . $media->id()->value())
        : null;
};

$homepageHero = new HomepageHeroImageService($app->settings(), $app->database(), new MediaRepository($app->database()), new MediaUsageRepository($app->database()), new MediaLifecycleService($app->database(), new MediaRepository($app->database()), null, new MediaUsageRepository($app->database())));

$renderFragment = static function (string $path, array $variables): string {
    extract($variables, EXTR_SKIP);
    ob_start();
    try { require $path; return (string) ob_get_clean(); }
    catch (Throwable $exception) { ob_end_clean(); throw $exception; }
};

$app->router()->get('/', function () use ($app, $homepageHero, $contentRepository, $contentDelivery, $mediaRepository, $renderFragment): Response {
    $homepageContent = null;
    $assignment = $app->settings()->get('site', 'homepage_content', null);
    if (is_array($assignment) && ($assignment['type'] ?? null) === 'page' && is_int($assignment['id'] ?? null)) {
        $page = $contentRepository->findById($assignment['id']);
        if ($page?->isPublished() && $page->type() === 'page') {
            $renderData = $page->toRenderData();
            $featuredMedia = null;
            if (($featuredId = $renderData['featured_media_id'] ?? null) !== null) {
                $media = $mediaRepository->findById((int) $featuredId);
                if ($media instanceof \Copot\Core\Media && $media->kind() === 'image') $featuredMedia = ['url' => $app->url('/media/' . $media->id()->value()), 'width' => $media->width(), 'height' => $media->height(), 'alt' => $media->title() !== '' ? $media->title() : $media->originalFilename()];
            }
            $homepageContent = $renderFragment($app->viewResolver()->resolve('content::show'), ['context' => ['content' => $renderData, 'featuredMedia' => $featuredMedia], 'content' => $renderData, 'featuredMedia' => $featuredMedia, 'breadcrumbs' => []]);
        }
    } elseif (is_array($assignment) && ($assignment['type'] ?? null) === 'article_collection' && ($assignment['reference'] ?? null) === 'articles') {
        $homepageContent = $renderFragment($app->viewResolver()->resolve('core::articles'), ['articles' => $contentDelivery->publishedArticles(), 'mediaUrl' => $articleCollectionMediaUrl]);
    }
    return Response::html($app->viewRenderer()->renderFile(
        $app->viewResolver()->resolve('core::home'),
        ['pageType' => 'homepage', 'currentPath' => '/', 'homepageHero' => $homepageHero->selected(), 'homepageContent' => $homepageContent],
        null,
        $app->branding()->name()
    ));
});

$app->router()->get('/content/{slug}', function ($request, array $params) use ($app, $contentDelivery, $mediaRepository): Response {
    $slug = trim((string) ($params['slug'] ?? ''));
    $renderData = $slug === '' ? null : $contentDelivery->findPublishedBySlug($slug);

    if ($renderData === null) {
        return $app->adminErrors()->response($request, 404);
    }

    $featuredMedia = null;
    $featuredId = $renderData['featured_media_id'] ?? null;
    if (is_int($featuredId) && $featuredId > 0) {
        $media = $mediaRepository->findById($featuredId);
        if ($media instanceof \Copot\Core\Media && $media->kind() === 'image') {
            $featuredMedia = [
                'url' => $app->url('/media/' . $media->id()->value()),
                'width' => $media->width(),
                'height' => $media->height(),
                'alt' => $media->title() !== '' ? $media->title() : $media->originalFilename(),
            ];
        }
    }

    return Response::html($app->viewRenderer()->renderFile(
        $app->viewResolver()->resolve('content::show'),
        ['pageType' => 'general', 'currentPath' => '/content/' . $slug, 'content' => $renderData, 'featuredMedia' => $featuredMedia, 'breadcrumbs' => [['label' => 'Home', 'url' => $app->url('/')], ['label' => (string) ($renderData['title'] ?? '')]]],
        null,
        (string) ($renderData['title'] ?? $app->branding()->name())
    ));
});

$app->router()->get('/articles', function () use ($app, $contentDelivery): Response {
    $articles = $contentDelivery->publishedArticles();
    return Response::html($app->viewRenderer()->renderFile(
        $app->viewResolver()->resolve('core::articles'),
        ['articles' => $articles, 'mediaUrl' => $articleCollectionMediaUrl],
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
