<?php

use Copot\Core\Response;

require_once __DIR__ . '/Services/MediaUploadException.php';
require_once __DIR__ . '/Services/MediaId.php';
require_once __DIR__ . '/Services/Media.php';
require_once __DIR__ . '/Services/MediaVariant.php';
require_once __DIR__ . '/Services/MediaUsage.php';
require_once __DIR__ . '/Services/MediaVariantRepository.php';
require_once __DIR__ . '/Services/MediaUsageRepository.php';
require_once __DIR__ . '/Services/MediaRepository.php';
require_once __DIR__ . '/Services/MediaLifecycleService.php';
require_once __DIR__ . '/Services/MediaUploadSource.php';
require_once __DIR__ . '/Services/MediaUploadInspection.php';
require_once __DIR__ . '/Services/MediaStagedFile.php';
require_once __DIR__ . '/Services/MediaFileInspector.php';
require_once __DIR__ . '/Services/MediaFilesystemStorage.php';
require_once __DIR__ . '/Services/MediaUploadService.php';
require_once __DIR__ . '/Services/MediaDeliveryService.php';
require_once __DIR__ . '/Services/MediaProcessingException.php';
require_once __DIR__ . '/Services/MediaProcessingFacts.php';
require_once __DIR__ . '/Services/MediaProcessingRequest.php';
require_once __DIR__ . '/Services/MediaImageProcessor.php';
require_once __DIR__ . '/Services/MediaGdImageProcessor.php';
require_once __DIR__ . '/Services/MediaVariantFilesystemStorage.php';
require_once __DIR__ . '/Services/MediaProcessingService.php';
require_once __DIR__ . '/Services/MediaAdmin.php';

$mediaRepository = new MediaRepository($app->database());
$mediaVariants = new MediaVariantRepository($app->database());
$mediaUsages = new MediaUsageRepository($app->database());
$mediaLifecycle = new MediaLifecycleService($app->database(), $mediaRepository, $mediaVariants, $mediaUsages);
$mediaInspector = new MediaFileInspector();
$mediaOriginalStorage = new MediaFilesystemStorage($app->path('storage/media'));
$mediaVariantStorage = new MediaVariantFilesystemStorage($app->path('storage/media'));
$mediaDelivery = new MediaDeliveryService($mediaRepository, $mediaInspector, $mediaOriginalStorage);
$mediaId = static function (string $value): ?int { return preg_match('/^[1-9][0-9]*$/', $value) ? (int) $value : null; };
$notFound = static fn (): \Copot\Core\Response => \Copot\Core\Response::content('404 Not Found', 404, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
$app->router()->get('/media/{id}/download', static function ($request, array $params) use ($mediaDelivery, $mediaId, $notFound) { $id = $mediaId($params['id'] ?? ''); if ($id === null) return $notFound(); try { return $mediaDelivery->download($id); } catch (Throwable) { return $notFound(); } });
$app->router()->get('/media/{id}', static function ($request, array $params) use ($mediaDelivery, $mediaId, $notFound) { $id = $mediaId($params['id'] ?? ''); if ($id === null) return $notFound(); try { return $mediaDelivery->inline($id); } catch (Throwable) { return $notFound(); } });

if (!method_exists($app, 'adminUrl') || !method_exists($app, 'adminNavigation')) {
    return;
}

$mediaDiagnostics = method_exists($app, 'diagnostics') ? $app->diagnostics() : null;
$mediaUpload = new MediaUploadService($app->database(), $mediaLifecycle, $mediaInspector, $mediaOriginalStorage, $mediaDiagnostics);
$mediaProcessing = new MediaProcessingService($app->database(), $mediaRepository, $mediaVariants, $mediaInspector, new MediaGdImageProcessor(), $mediaOriginalStorage, $mediaVariantStorage, $mediaDiagnostics);
$mediaAdmin = new MediaAdmin($mediaRepository, $mediaUpload, $mediaLifecycle, $mediaProcessing);

$mediaAdminUrl = $app->adminUrl();
$mediaAdminBase = $mediaAdminUrl->baseUrl();
$mediaAdminPath = $mediaAdminUrl->childUrl('media');
$mediaUploadPath = $mediaAdminUrl->childUrl('media/upload');
$app->adminNavigation()->add('Media', $mediaAdminPath, 'media.view', 'image', 25);

$mediaRenderView = static function (string $view, array $data = []) use ($app, $mediaAdminUrl): string {
    $file = __DIR__ . '/views/admin/' . $view . '.php';
    if (!is_file($file)) throw new RuntimeException("Media admin view [{$view}] was not found.");
    $data['adminUrl'] = static fn (string $path = ''): string => $mediaAdminUrl->childUrl($path);
    extract($data, EXTR_SKIP);
    ob_start();
    try { require $file; return (string) ob_get_clean(); }
    catch (Throwable $exception) { ob_end_clean(); throw $exception; }
};
$mediaRenderAdmin = static function (string $title, string $content, $user, string $path, int $status = 200) use ($app): Response {
    return Response::html($app->adminPageRenderer()->render($title, $content, $user, $app->csrf()->token(), $path), $status);
};
$mediaCanAny = static function ($user, array $permissions): bool {
    foreach ($permissions as $permission) if ($user?->can($permission)) return true;
    return false;
};
$mediaRequireAdmin = static function ($request, array $permissions) use ($app, $mediaAdminBase, $mediaCanAny) {
    if (!$app->auth()->check()) return Response::redirect($mediaAdminBase);
    $user = $app->auth()->user();
    if (!$user?->can('admin.access') || !$mediaCanAny($user, $permissions)) return $app->adminErrors()->response($request, 403);
    return $user;
};
$mediaValidateCsrf = static function ($request) use ($app): ?Response {
    return $app->csrf()->validateOrReject($request) instanceof Response
        ? $app->adminErrors()->response($request, 419)
        : null;
};
$mediaNormalizeWorkspace = static function ($request): array {
    $kind = $request->input('kind');
    $capability = $request->input('capability');
    return [
        'search' => trim((string) $request->input('q', '')),
        'kind' => in_array($kind, ['image', 'document'], true) ? $kind : null,
        'capability' => in_array($capability, ['editable', 'manage-only'], true) ? $capability : null,
        'page' => max(1, (int) $request->input('page', 1)),
    ];
};
$mediaPage = static function (array $workspace): int { return max(1, (int) ceil($workspace['total'] / MediaAdmin::PAGE_SIZE)); };
$mediaRenderList = static function ($request, $user, array $filters, array $workspace, ?string $error = null) use ($app, $mediaRenderView, $mediaRenderAdmin, $mediaAdminUrl, $mediaAdmin, $mediaPage): Response {
    $lastPage = $mediaPage($workspace);
    $query = array_filter(['q' => $filters['search'], 'kind' => $filters['kind'], 'capability' => $filters['capability']], static fn ($value): bool => $value !== null && $value !== '');
    $paginationUrl = static fn (int $page): string => $mediaAdminUrl->childUrl('media') . '?' . http_build_query(array_merge($query, ['page' => $page]));
    $content = $mediaRenderView('list', [
        'mediaItems' => $workspace['items'], 'total' => $workspace['total'], 'page' => $filters['page'], 'lastPage' => $lastPage,
        'paginationUrl' => $paginationUrl, 'query' => $query, 'search' => $filters['search'], 'selectedKind' => $filters['kind'],
        'selectedCapability' => $filters['capability'], 'hasFilters' => $filters['search'] !== '' || $filters['kind'] !== null || $filters['capability'] !== null,
        'canEdit' => $user->can('media.edit'), 'canUpload' => $user->can('media.upload'), 'csrfToken' => $app->csrf()->token(), 'notice' => $request->input('notice'), 'error' => $error,
        'isEditable' => static fn (Media $item): bool => $mediaAdmin->isEditable($item),
    ]);
    return $mediaRenderAdmin('Media', $content, $user, $request->path());
};

$app->router()->get($mediaAdminPath, function ($request) use ($mediaRequireAdmin, $mediaAdmin, $mediaNormalizeWorkspace, $mediaPage, $mediaRenderList) {
    $user = $mediaRequireAdmin($request, ['media.view']); if ($user instanceof Response) return $user;
    $filters = $mediaNormalizeWorkspace($request); $workspace = $mediaAdmin->workspace($filters, $filters['page']); $lastPage = $mediaPage($workspace);
    if ($workspace['total'] > 0 && $filters['page'] > $lastPage) { $filters['page'] = $lastPage; $workspace = $mediaAdmin->workspace($filters, $filters['page']); }
    return $mediaRenderList($request, $user, $filters, $workspace);
});
$app->router()->get($mediaUploadPath, function ($request) use ($mediaRequireAdmin, $mediaRenderView, $mediaRenderAdmin, $app): Response {
    $user = $mediaRequireAdmin($request, ['media.upload']); if ($user instanceof Response) return $user;
    return $mediaRenderAdmin('Upload Media', $mediaRenderView('upload', ['csrfToken' => $app->csrf()->token(), 'error' => null, 'title' => '']), $user, $request->path());
});
$app->router()->post($mediaUploadPath, function ($request) use ($mediaRequireAdmin, $mediaValidateCsrf, $mediaAdmin, $mediaRenderView, $mediaRenderAdmin, $app, $mediaUploadPath, $mediaAdminPath): Response {
    $user = $mediaRequireAdmin($request, ['media.upload']); if ($user instanceof Response) return $user;
    $csrf = $mediaValidateCsrf($request); if ($csrf) return $csrf;
    $title = trim((string) $request->post('title', '')); $file = $request->file('media');
    try { $mediaAdmin->upload($file ?? [], $title); return Response::redirect($mediaAdminPath . '?notice=uploaded'); }
    catch (MediaUploadException) { return $mediaRenderAdmin('Upload Media', $mediaRenderView('upload', ['csrfToken' => $app->csrf()->token(), 'error' => 'The media could not be uploaded.', 'title' => $title]), $user, $request->path(), 422); }
});
$app->router()->post($app->adminUrl()->childUrl('media/{id}/title'), function ($request, array $params) use ($app, $mediaRequireAdmin, $mediaValidateCsrf, $mediaAdmin, $mediaRepository, $mediaId, $mediaAdminPath): Response {
    $user = $mediaRequireAdmin($request, ['media.edit']); if ($user instanceof Response) return $user;
    $csrf = $mediaValidateCsrf($request); if ($csrf) return $csrf;
    $id = $mediaId((string) ($params['id'] ?? '')); if ($id === null || !$mediaRepository->findById($id)) return Response::content('404 Not Found', 404);
    try { $mediaAdmin->updateTitle($id, (string) $request->post('title', '')); return Response::redirect($mediaAdminPath . '?notice=title-updated'); }
    catch (InvalidArgumentException) { return $app->adminErrors()->response($request, 422); }
    catch (Throwable) { return $app->adminErrors()->response($request, 503); }
});
$app->router()->post($app->adminUrl()->childUrl('media/{id}/process'), function ($request, array $params) use ($mediaRequireAdmin, $mediaValidateCsrf, $mediaAdmin, $mediaRepository, $mediaId, $mediaAdminPath, $app): Response {
    $user = $mediaRequireAdmin($request, ['media.edit']); if ($user instanceof Response) return $user;
    $csrf = $mediaValidateCsrf($request); if ($csrf) return $csrf;
    $id = $mediaId((string) ($params['id'] ?? '')); if ($id === null || !$mediaRepository->findById($id)) return Response::content('404 Not Found', 404);
    try { $mediaAdmin->process($id, (string) $request->post('preset', '')); return Response::redirect($mediaAdminPath . '?notice=processed'); }
    catch (MediaProcessingValidationException) { return $app->adminErrors()->response($request, 422); }
    catch (MediaProcessingCapabilityException) { return $app->adminErrors()->response($request, 503); }
    catch (MediaProcessingException) { return $app->adminErrors()->response($request, 503); }
    catch (Throwable) { return $app->adminErrors()->response($request, 503); }
});
