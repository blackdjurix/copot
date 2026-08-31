<?php

use Copot\Core\MediaAdmin;
use Copot\Core\MediaFileInspector;
use Copot\Core\MediaDeliveryService;
use Copot\Core\MediaFilesystemStorage;
use Copot\Core\MediaLifecycleService;
use Copot\Core\MediaRepository;
use Copot\Core\MediaUploadService;
use Copot\Core\MediaUsageRepository;
use Copot\Core\Response;

$mediaRepository = new MediaRepository($app->database());
$mediaUsages = new MediaUsageRepository($app->database());
$mediaInspector = new MediaFileInspector();
$mediaStorage = new MediaFilesystemStorage($app->path('storage/media'));
$mediaDelivery = new MediaDeliveryService($mediaRepository, $mediaInspector, $mediaStorage);
$mediaLifecycle = new MediaLifecycleService($app->database(), $mediaRepository, null, $mediaUsages, $mediaStorage);
$mediaAdmin = new MediaAdmin($mediaRepository, new MediaUploadService($app->database(), $mediaLifecycle, $mediaInspector, $mediaStorage, $app->diagnostics()), $mediaLifecycle, $mediaUsages);
$mediaPath = $app->adminUrl()->childUrl('media');
$mediaUploadPath = $app->adminUrl()->childUrl('media/upload');
$mediaId = static fn (mixed $value): ?int => is_scalar($value) && preg_match('/^[1-9][0-9]*$/', (string) $value) ? (int) $value : null;
$app->router()->get('/media/{id}', function ($request, array $params) use ($mediaDelivery, $mediaId): Response {
    $id = $mediaId($params['id'] ?? null);
    return $id === null ? Response::content('404 Not Found', 404, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']) : $mediaDelivery->inline($id);
});
$requireMedia = static function ($request, string $permission) use ($app): mixed {
    if (!$app->auth()->check()) return Response::redirect($app->adminUrl()->baseUrl());
    $user = $app->auth()->user();
    if (!$user?->can('admin.access') || !$user->can($permission)) return $app->adminErrors()->response($request, 403);
    return $user;
};
$render = static function (string $view, array $data, $user, string $path, int $status = 200, ?array $frame = null, ?array $breadcrumbs = null) use ($app): Response {
    return Response::html($app->adminPageRenderer()->render($view === 'list' ? 'Media' : 'Upload Media', $app->view()->render('admin/media/' . $view, $data), $user, $app->csrf()->token(), $path, $frame, $breadcrumbs), $status);
};
$app->adminNavigation()->add('Media', $mediaPath, 'media.view', 'image', 25);

$app->router()->get($mediaPath, function ($request) use ($app, $mediaAdmin, $mediaRepository, $mediaUsages, $requireMedia, $render, $mediaPath): Response {
    $user = $requireMedia($request, 'media.view'); if ($user instanceof Response) return $user;
    $items = $mediaAdmin->inventory((int) $request->input('page', 1)); $evidence = [];
    foreach ($items as $item) $evidence[$item->id()->value()] = $mediaUsages->forMedia($item->id());
    $bar = $user->can('media.upload') ? '<a class="admin-button admin-button--primary" href="' . htmlspecialchars($app->adminUrl()->childUrl('media/upload'), ENT_QUOTES, 'UTF-8') . '">Upload media</a>' : null;
    return $render('list', ['items' => $items, 'evidence' => $evidence, 'canDelete' => $user->can('media.delete'), 'csrfToken' => $app->csrf()->token(), 'notice' => $request->input('notice'), 'error' => $request->input('error'), 'adminUrl' => fn (string $path = '') => $app->adminUrl()->childUrl($path), 'mediaUrl' => fn (int $id): string => $app->url('/media/' . $id)], $user, $request->path(), 200, ['description' => 'Core Media inventory and original files.', 'bar' => $bar, 'surface' => 'transparent', 'spacing' => 'default']);
});
$app->router()->get($mediaUploadPath, function ($request) use ($requireMedia, $render, $app, $mediaPath): Response { $user = $requireMedia($request, 'media.upload'); if ($user instanceof Response) return $user; return $render('upload', ['csrfToken' => $app->csrf()->token(), 'error' => null, 'title' => '', 'adminUrl' => fn (string $path = '') => $app->adminUrl()->childUrl($path)], $user, $request->path(), 200, ['description' => 'Upload one supported image or PDF document.', 'bar' => null, 'surface' => 'transparent', 'spacing' => 'default'], [['label' => 'Media', 'url' => $mediaPath], ['label' => 'Upload Media']]); });
$app->router()->get($app->adminUrl()->childUrl('media/select'), function ($request) use ($requireMedia, $mediaRepository, $mediaPath, $app): Response {
    $user = $requireMedia($request, 'media.use'); if ($user instanceof Response) return $user;
    $items = $mediaRepository->paginate('', 50, 0);
    return Response::content(json_encode(['items' => array_map(static fn (\Copot\Core\Media $item): array => ['id' => $item->id()->value(), 'title' => $item->title(), 'original_filename' => $item->originalFilename(), 'mime_type' => $item->mimeType(), 'url' => $app->url('/media/' . $item->id()->value())], $items)], JSON_THROW_ON_ERROR), 200, ['Content-Type' => 'application/json; charset=UTF-8', 'Cache-Control' => 'no-store']);
});
$app->router()->post($mediaUploadPath, function ($request) use ($app, $requireMedia, $mediaAdmin, $render, $mediaPath): Response {
    $user = $requireMedia($request, 'media.upload'); if ($user instanceof Response) return $user;
    if ($app->csrf()->validateOrReject($request) instanceof Response) return $app->adminErrors()->response($request, 419);
    $title = trim((string) $request->post('title', ''));
    try { $mediaAdmin->upload($request->file('media') ?? [], $title); return Response::redirect($mediaPath . '?notice=uploaded'); }
    catch (\Copot\Core\MediaUploadValidationException) { return $render('upload', ['csrfToken' => $app->csrf()->token(), 'error' => 'The uploaded file could not be validated.', 'title' => $title, 'adminUrl' => fn (string $path = '') => $app->adminUrl()->childUrl($path)], $user, $request->path(), 422); }
    catch (\Copot\Core\MediaUploadException) { return $render('upload', ['csrfToken' => $app->csrf()->token(), 'error' => 'The media could not be uploaded.', 'title' => $title, 'adminUrl' => fn (string $path = '') => $app->adminUrl()->childUrl($path)], $user, $request->path(), 422); }
});
$app->router()->post($app->adminUrl()->childUrl('media/{id}/delete'), function ($request, array $params) use ($app, $requireMedia, $mediaAdmin, $mediaId, $mediaPath): Response {
    $user = $requireMedia($request, 'media.delete'); if ($user instanceof Response) return $user;
    if ($app->csrf()->validateOrReject($request) instanceof Response) return $app->adminErrors()->response($request, 419);
    $id = $mediaId($params['id'] ?? null); if ($id === null) return $app->adminErrors()->response($request, 404);
    try { $mediaAdmin->delete($id); return Response::redirect($mediaPath . '?notice=deleted'); } catch (\Copot\Core\MediaInUseException) { return Response::redirect($mediaPath . '?error=' . rawurlencode('Media cannot be deleted while it is in use.'), 303); } catch (\Copot\Core\MediaNotFoundException) { return $app->adminErrors()->response($request, 404); } catch (\Throwable) { return $app->adminErrors()->response($request, 503); }
});
