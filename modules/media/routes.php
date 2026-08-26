<?php

use Copot\Core\Response;

require_once __DIR__ . '/Services/MediaUploadException.php';
require_once __DIR__ . '/Services/MediaId.php';
require_once __DIR__ . '/Services/Media.php';
require_once __DIR__ . '/Services/MediaVariant.php';
require_once __DIR__ . '/Services/MediaUsage.php';
require_once __DIR__ . '/Services/MediaVariantRepository.php';
require_once __DIR__ . '/Services/MediaUsageRepository.php';
require_once __DIR__ . '/Services/MediaContentReferenceService.php';
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
require_once __DIR__ . '/Services/MediaVariantKey.php';
require_once __DIR__ . '/Services/MediaProcessingService.php';
require_once __DIR__ . '/Services/MediaPendingPreparationService.php';
require_once __DIR__ . '/Services/MediaFeaturedProfile.php';
require_once __DIR__ . '/Services/MediaVariantDeliveryService.php';
require_once __DIR__ . '/Services/MediaAdmin.php';

$mediaRepository = new MediaRepository($app->database());
$mediaVariants = new MediaVariantRepository($app->database());
$mediaUsages = new MediaUsageRepository($app->database());
$mediaInspector = new MediaFileInspector();
$mediaOriginalStorage = new MediaFilesystemStorage($app->path('storage/media'));
$mediaVariantStorage = new MediaVariantFilesystemStorage($app->path('storage/media'));
$mediaDiagnostics = method_exists($app, 'diagnostics') ? $app->diagnostics() : null;
$mediaLifecycle = new MediaLifecycleService($app->database(), $mediaRepository, $mediaVariants, $mediaUsages, $mediaOriginalStorage, $mediaVariantStorage, $mediaDiagnostics);
$mediaDelivery = new MediaDeliveryService($mediaRepository, $mediaInspector, $mediaOriginalStorage);
$mediaVariantDelivery = new MediaVariantDeliveryService($mediaRepository, $mediaVariants, $mediaVariantStorage, $mediaInspector);
$mediaId = static function (string $value): ?int { return preg_match('/^[1-9][0-9]*$/', $value) ? (int) $value : null; };
$mediaUrl = static fn (string $path): string => method_exists($app, 'url') ? $app->url($path) : $path;
$mediaUrl = static fn (string $path): string => method_exists($app, 'url') ? $app->url($path) : $path;
$notFound = static fn (): \Copot\Core\Response => \Copot\Core\Response::content('404 Not Found', 404, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
$app->router()->get('/media/{id}/download', static function ($request, array $params) use ($mediaDelivery, $mediaId, $notFound) { $id = $mediaId($params['id'] ?? ''); if ($id === null) return $notFound(); try { return $mediaDelivery->download($id); } catch (Throwable) { return $notFound(); } });
$app->router()->get('/media/{id}/variant/{key}', static function ($request, array $params) use ($mediaVariantDelivery, $mediaId, $notFound): Response { $id = $mediaId($params['id'] ?? ''); if ($id === null) return $notFound(); return $mediaVariantDelivery->inline($id, (string) ($params['key'] ?? '')); });
$app->router()->get('/media/{id}', static function ($request, array $params) use ($mediaDelivery, $mediaId, $notFound) { $id = $mediaId($params['id'] ?? ''); if ($id === null) return $notFound(); try { return $mediaDelivery->inline($id); } catch (Throwable) { return $notFound(); } });

if (!method_exists($app, 'adminUrl') || !method_exists($app, 'adminNavigation')) {
    return;
}

$mediaUpload = new MediaUploadService($app->database(), $mediaLifecycle, $mediaInspector, $mediaOriginalStorage, $mediaDiagnostics);
$mediaProcessing = new MediaProcessingService($app->database(), $mediaRepository, $mediaVariants, $mediaInspector, new MediaGdImageProcessor(), $mediaOriginalStorage, $mediaVariantStorage, $mediaDiagnostics);
$mediaPending = new MediaPendingPreparationService($mediaProcessing, $mediaVariants, $mediaVariantStorage, $app->session());
$mediaAdmin = new MediaAdmin($mediaRepository, $mediaUpload, $mediaLifecycle, $mediaProcessing);

$mediaAdminUrl = $app->adminUrl();
$mediaAdminBase = $mediaAdminUrl->baseUrl();
$mediaAdminPath = $mediaAdminUrl->childUrl('media');
$mediaUploadPath = $mediaAdminUrl->childUrl('media/upload');
$mediaPickerPath = $mediaAdminUrl->childUrl('media/context-picker');
$app->adminNavigation()->add('Media', $mediaAdminPath, 'media.view', 'image', 25);


$mediaRenderView = static function (string $view, array $data = []) use ($app, $mediaAdminUrl): string {
    $file = __DIR__ . '/views/admin/' . $view . '.php';
    if (!is_file($file)) throw new RuntimeException("Media admin view [{$view}] was not found.");
    $data['adminUrl'] = static fn (string $path = ''): string => $mediaAdminUrl->childUrl($path);
    $data['url'] = static fn (string $path): string => method_exists($app, 'url') ? $app->url($path) : $path;
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
$app->router()->get($mediaPickerPath, function ($request) use ($mediaRequireAdmin, $mediaRepository, $mediaVariants, $mediaUrl): Response {
    $user = $mediaRequireAdmin($request, ['media.use']);
    if ($user instanceof Response) return $user;
    if ((string) $request->input('consumer', '') !== 'content') return Response::content(json_encode(['error' => 'Unavailable picker context.']), 422, ['Content-Type' => 'application/json; charset=UTF-8']);
    $workspace = $mediaRepository->workspace(['search' => trim((string) $request->input('q', '')), 'capability' => 'editable'], 24, max(0, ((int) $request->input('page', 1) - 1) * 24));
    $items = array_map(fn (Media $media): array => ['id' => $media->id()->value(), 'title' => $media->title(), 'original_filename' => $media->originalFilename(), 'mime_type' => $media->mimeType(), 'url' => $mediaUrl('/media/' . $media->id()->value())], $workspace['items']);
    $currentId = (string) $request->input('current', ''); $contentId=(int)$request->input('content_id',0);
    $current = preg_match('/^[1-9][0-9]*$/', $currentId) ? $mediaRepository->findById((int) $currentId) : null;
    $currentAllowed = $current && in_array($current->mimeType(), ['image/jpeg', 'image/png', 'image/webp'], true);
    $prepared = $currentAllowed && $contentId > 0 ? array_values(array_filter($mediaVariants->forMedia($current->id()), static fn (MediaVariant $variant): bool => $variant->variantKey() === MediaVariantKey::contentSlot($current->id(), $contentId, (int)$variant->width()))) : [];
    usort($prepared, static fn (MediaVariant $a, MediaVariant $b): int => ($b->width() ?? 0) <=> ($a->width() ?? 0));
    $descriptor = $currentAllowed && $prepared !== [] ? ['id' => $current->id()->value(), 'title' => $current->title(), 'original_filename' => $current->originalFilename(), 'url' => $mediaUrl('/media/' . $current->id()->value() . '/variant/' . rawurlencode($prepared[0]->variantKey())), 'srcset' => implode(', ', array_map(fn (MediaVariant $variant): string => $mediaUrl('/media/' . $current->id()->value() . '/variant/' . rawurlencode($variant->variantKey())) . ' ' . (int) $variant->width() . 'w', $prepared)), 'width' => $prepared[0]->width(), 'height' => $prepared[0]->height(), 'alt' => $current->title() !== '' ? $current->title() : $current->originalFilename()] : null;
    return Response::content(json_encode(['items' => $items, 'total' => $workspace['total'], 'page' => max(1, (int) $request->input('page', 1)), 'per_page' => 24, 'current' => $descriptor, 'stale' => $currentId !== '' && (!$currentAllowed || $descriptor === null)], JSON_THROW_ON_ERROR), 200, ['Content-Type' => 'application/json; charset=UTF-8', 'Cache-Control' => 'no-store']);
});
$app->router()->post($app->adminUrl()->childUrl('media/{id}/delete'), function ($request, array $params) use ($app, $mediaRequireAdmin, $mediaValidateCsrf, $mediaLifecycle, $mediaRepository, $mediaUsages, $mediaId, $mediaAdminPath): Response {
    $user = $mediaRequireAdmin($request, ['media.delete']); if ($user instanceof Response) return $user;
    $csrf = $mediaValidateCsrf($request); if ($csrf) return $csrf;
    $id = $mediaId((string) ($params['id'] ?? '')); if ($id === null || !$mediaRepository->findById($id)) return Response::content('404 Not Found', 404);
    try { $mediaLifecycle->delete($id); return Response::redirect($mediaAdminPath . '?notice=deleted'); }
    catch (MediaInUseException) {
        $details = array_map(static function (MediaUsage $usage): string { return $usage->consumerType() === 'content' && $usage->usageKey() === 'featured_media' ? 'Content #' . $usage->consumerId() . ' — Featured media' : 'Media is used by another managed record.'; }, array_slice($mediaUsages->forMedia($id), 0, 8));
        $message = 'Media cannot be deleted while it is in use.' . ($details !== [] ? ' ' . $details[0] : '');
        return Response::redirect($mediaAdminPath . '?error=' . rawurlencode($message), 303);
    } catch (Throwable) { return $app->adminErrors()->response($request, 503); }
});
$app->router()->post($mediaPickerPath . '/upload', function ($request) use ($app, $mediaRequireAdmin, $mediaValidateCsrf, $mediaUpload): Response {
    $user = $mediaRequireAdmin($request, ['media.use']);
    if ($user instanceof Response) return $user;
    if (!$user->can('media.upload')) return $app->adminErrors()->response($request, 403);
    $csrf = $mediaValidateCsrf($request); if ($csrf) return $csrf;
    try {
        $file = $request->file('media');
        if (!$file) throw new MediaUploadValidationException('invalid');
        $facts = (new MediaFileInspector())->inspect((string) $file['tmp_name']);
        if (!in_array($facts->mimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) throw new MediaUploadValidationException('invalid');
        $id = $mediaUpload->upload(MediaUploadSource::fromArray($file), (string) $request->post('title', ''));
        return Response::content(json_encode(['id' => $id->value()], JSON_THROW_ON_ERROR), 201, ['Content-Type' => 'application/json; charset=UTF-8']);
    } catch (MediaUploadValidationException) { return Response::content(json_encode(['error' => 'The uploaded file could not be validated.']), 422, ['Content-Type' => 'application/json; charset=UTF-8']); }
      catch (Throwable) { return Response::content(json_encode(['error' => 'The media could not be uploaded.']), 503, ['Content-Type' => 'application/json; charset=UTF-8']); }
});
$app->router()->post($mediaPickerPath . '/process', function ($request) use ($app, $mediaRequireAdmin, $mediaValidateCsrf, $mediaPending, $mediaId): Response {
    $user = $mediaRequireAdmin($request, ['media.use']); if ($user instanceof Response) return $user;
    $csrf = $mediaValidateCsrf($request); if ($csrf) return $csrf;
    $id = $mediaId((string) $request->post('media_id', '')); $crop = $request->post('crop', []);
    if ($id === null || !is_array($crop)) return Response::content(json_encode(['error' => 'The featured image could not be prepared.']), 422, ['Content-Type' => 'application/json; charset=UTF-8']);
    try { $pending = $mediaPending->prepare($user->id(), $id, MediaFeaturedProfile::request($id, array_map('intval', $crop))); $variants = array_map(static fn (array $variant): array => ['width'=>$variant['width'],'height'=>$variant['height'],'url'=>$app->adminUrl()->childUrl('media/context-picker/pending/' . rawurlencode($pending['token']) . '/' . rawurlencode($variant['key']))], $pending['variants']); return Response::content(json_encode(['id'=>$id,'pending_token'=>$pending['token'],'variants'=>$variants], JSON_THROW_ON_ERROR), 200, ['Content-Type'=>'application/json; charset=UTF-8','Cache-Control'=>'no-store']); }
    catch (Throwable) { return Response::content(json_encode(['error' => 'The featured image could not be prepared.']), 422, ['Content-Type' => 'application/json; charset=UTF-8']); }
});
$app->router()->get($mediaPickerPath . '/pending/{token}/{key}', function ($request, array $params) use ($app, $mediaRequireAdmin, $mediaPending, $mediaVariantStorage, $mediaInspector): Response { $user=$mediaRequireAdmin($request,['media.use']); if($user instanceof Response)return $user; $token=(string)($params['token']??'');$key=(string)($params['key']??''); if(!preg_match('/^[a-f0-9]{64}$/',$token)||!preg_match('/^pending-[a-f0-9]{32}$/',$key))return Response::content('404 Not Found',404); $variant=$mediaPending->variant($token,$user->id(),$key);$path=$variant?$mediaVariantStorage->resolve($variant->storageKey()):null;if(!$variant||!$path)return Response::content('404 Not Found',404);try{$facts=$mediaInspector->inspect($path);}catch(Throwable){return Response::content('404 Not Found',404);}if($facts->byteSize()!==$variant->byteSize())return Response::content('404 Not Found',404);$body=@file_get_contents($path);return is_string($body)?Response::content($body,200,['Content-Type'=>$variant->mimeType(),'Cache-Control'=>'no-store','X-Content-Type-Options'=>'nosniff']):Response::content('404 Not Found',404); });
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
$mediaRenderList = static function ($request, $user, array $filters, array $workspace, ?string $error = null) use ($app, $mediaRenderView, $mediaRenderAdmin, $mediaAdminUrl, $mediaAdmin, $mediaPage, $mediaUsages, $mediaVariants): Response {
    $lastPage = $mediaPage($workspace);
    $query = array_filter(['q' => $filters['search'], 'kind' => $filters['kind'], 'capability' => $filters['capability']], static fn ($value): bool => $value !== null && $value !== '');
    $paginationUrl = static fn (int $page): string => $mediaAdminUrl->childUrl('media') . '?' . http_build_query(array_merge($query, ['page' => $page]));
    $evidence = [];
    foreach ($workspace['items'] as $item) {
        $id = $item->id()->value();
        $usages = $mediaUsages->forMedia($item->id());
        $variants = $mediaVariants->forMedia($item->id());
        $evidence[$id] = [
            'usageCount' => count($usages),
            'variantCount' => count($variants),
            'variantWidths' => array_values(array_unique(array_filter(array_map(static fn (MediaVariant $variant): ?int => $variant->width(), $variants), static fn (?int $width): bool => $width !== null))),
        ];
    }
    $content = $mediaRenderView('list', [
        'mediaItems' => $workspace['items'], 'total' => $workspace['total'], 'page' => $filters['page'], 'lastPage' => $lastPage,
        'paginationUrl' => $paginationUrl, 'query' => $query, 'search' => $filters['search'], 'selectedKind' => $filters['kind'],
        'selectedCapability' => $filters['capability'], 'hasFilters' => $filters['search'] !== '' || $filters['kind'] !== null || $filters['capability'] !== null,
        'canEdit' => $user->can('media.edit'), 'canDelete' => $user->can('media.delete'), 'canUpload' => $user->can('media.upload'), 'csrfToken' => $app->csrf()->token(), 'notice' => $request->input('notice'), 'error' => $error,
        'isEditable' => static fn (Media $item): bool => $mediaAdmin->isEditable($item), 'evidence' => $evidence,
    ]);
    return $mediaRenderAdmin('Media', $content, $user, $request->path());
};

$app->router()->get($mediaAdminPath, function ($request) use ($mediaRequireAdmin, $mediaAdmin, $mediaNormalizeWorkspace, $mediaPage, $mediaRenderList) {
    $user = $mediaRequireAdmin($request, ['media.view']); if ($user instanceof Response) return $user;
    $filters = $mediaNormalizeWorkspace($request); $workspace = $mediaAdmin->workspace($filters, $filters['page']); $lastPage = $mediaPage($workspace);
    if ($workspace['total'] > 0 && $filters['page'] > $lastPage) { $filters['page'] = $lastPage; $workspace = $mediaAdmin->workspace($filters, $filters['page']); }
    return $mediaRenderList($request, $user, $filters, $workspace, (string) $request->input('error', ''));
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
    catch (MediaUploadValidationException $exception) {
        $error = str_contains($exception->getMessage(), 'PDF structure')
            ? 'The uploaded PDF is invalid or incomplete.'
            : 'The uploaded file could not be validated.';
        return $mediaRenderAdmin('Upload Media', $mediaRenderView('upload', ['csrfToken' => $app->csrf()->token(), 'error' => $error, 'title' => $title]), $user, $request->path(), 422);
    }
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
