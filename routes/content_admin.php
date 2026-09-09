<?php

use Copot\Core\Content;
use Copot\Core\ContentDuplicateSlugException;
use Copot\Core\ContentRepository;
use Copot\Core\ContentService;
use Copot\Core\ContentFeaturedMediaReferenceService;
use Copot\Core\ContentStaleWriteException;
use Copot\Core\ContentWriteException;
use Copot\Core\MediaRepository;
use Copot\Core\MediaUsageRepository;
use Copot\Core\Response;

// The retained Content Manager owns the richer extension projection when it
// is enabled. The Core projection is registered for the mandatory baseline
// when that optional Module is disabled.
$contentModuleEnabled = false;
try {
    $contentModuleEnabled = ((new \Copot\Core\ModuleRepository($app->database()))->findByName('content')['status'] ?? null) === 'enabled';
} catch (Throwable) {
    $contentModuleEnabled = false;
}

if ($contentModuleEnabled) {
    return;
}

$contentBase = $app->adminUrl()->baseUrl();
$contentRoute = fn (string $path = ''): string => $app->adminUrl()->routeChildUrl($path === '' ? 'content' : 'content/' . trim($path, '/'));
$contentRepository = new ContentRepository($app->database());
$mediaRepository = new MediaRepository($app->database());
$contentMediaReferences = new ContentFeaturedMediaReferenceService($mediaRepository, new MediaUsageRepository($app->database()));
$contentService = new ContentService($app->database(), $contentRepository, null, $contentMediaReferences);
$contentSlugger = new \Copot\Core\Slugger();
$authorLabels = [];
try {
    $authorStatement = $app->database()->connection()->query('SELECT id, name, email FROM ' . $app->database()->table('users') . ' ORDER BY name ASC, email ASC');
    foreach ($authorStatement->fetchAll() as $authorRow) {
        $authorId = (int) ($authorRow['id'] ?? 0);
        if ($authorId < 1) continue;
        $name = trim((string) ($authorRow['name'] ?? ''));
        $email = trim((string) ($authorRow['email'] ?? ''));
        $authorLabels[$authorId] = $name !== '' ? $name : ($email !== '' ? $email : 'Unknown author');
    }
} catch (Throwable) {
    $authorLabels = [];
}

$requireContent = static function ($request, string $permission) use ($app): mixed {
    if (!$app->auth()->check()) {
        return Response::redirect($app->adminUrl()->baseUrl());
    }

    $user = $app->auth()->user();
    if (!$user?->can('admin.access') || !$user->can($permission)) {
        return $app->adminErrors()->response($request, 403);
    }

    return $user;
};

$validateCsrf = static function ($request) use ($app): ?Response {
    return $app->csrf()->validateOrReject($request) instanceof Response
        ? $app->adminErrors()->response($request, 419)
        : null;
};

$routeId = static function (mixed $value): ?int {
    if (!is_string($value) && !is_int($value)) return null;
    $id = filter_var((string) $value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return is_int($id) ? $id : null;
};

$formData = static function (?Content $content, ?string $status = null): array {
    return [
        'id' => $content?->id(),
        'type' => $content?->type() ?? 'page',
        'title' => $content?->title() ?? '',
        'slug' => $content?->slug() ?? '',
        'excerpt' => $content?->excerpt() ?? '',
        'body' => $content?->body() ?? '',
        'status' => $status ?? $content?->status() ?? 'draft',
        'author_id' => $content?->authorId(),
        'featured_media_id' => $content?->featuredMediaId(),
        'updated_at' => $content?->updatedAt(),
    ];
};

$renderForm = static function (string $title, string $action, array $data, array $errors, $user, string $path, string $mode) use ($app, $contentRoute, $mediaRepository): Response {
    $html = '<section class="admin-content-form-page admin-stack" aria-labelledby="webcore-content-form-title">'
        . '<header class="admin-page-heading"><div class="admin-page-heading__copy"><h2 class="admin-page-heading__title" id="webcore-content-form-title">'
        . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2><p class="admin-page-heading__description">Create or update a Page or Article using Webcore Content.</p></div>'
        . '<a class="admin-button admin-button--secondary" href="' . htmlspecialchars($contentRoute(), ENT_QUOTES, 'UTF-8') . '">Back to Content</a></header>'
        . '<div class="admin-panel"><div class="admin-panel__body"><form class="admin-form" method="post" action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '">'
        . '<input type="hidden" name="_token" value="' . htmlspecialchars($app->session()->csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
    if (!empty($data['updated_at'])) $html .= '<input type="hidden" name="expected_updated_at" value="' . htmlspecialchars((string) $data['updated_at'], ENT_QUOTES, 'UTF-8') . '">';
    if ($errors !== []) {
        $html .= '<div class="admin-alert admin-alert--danger" role="alert"><strong>Please correct the following errors.</strong><ul class="admin-alert__list">';
        foreach ($errors as $error) $html .= '<li>' . htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') . '</li>';
        $html .= '</ul></div>';
    }
    $field = static fn (string $id, string $label, string $value): string => '<div class="admin-field"><label class="admin-field__label" for="' . $id . '">' . $label . '</label><input id="' . $id . '" name="' . $id . '" type="text" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"></div>';
    $type = (string) ($data['type'] ?? 'page');
    $typeOptions = '';
    foreach (['page' => 'Page', 'article' => 'Article'] as $value => $label) {
        $typeOptions .= '<option value="' . $value . '"' . ($type === $value ? ' selected' : '') . '>' . $label . '</option>';
    }
    $featuredId = $data['featured_media_id'] === null ? null : (int) $data['featured_media_id'];
    $featured = $featuredId === null ? null : $mediaRepository->findById($featuredId);
    $featuredDescriptor = $featured !== null && $featured->kind() === 'image' ? [
        'id' => $featured->id()->value(),
        'title' => $featured->title(),
        'original_filename' => $featured->originalFilename(),
        'url' => $app->url('/media/' . $featured->id()->value()),
    ] : null;
    $canUseMedia = $user->can('media.use');
    $featuredJson = htmlspecialchars(json_encode($featuredDescriptor, JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8');
    $html .= '<div class="admin-content-form-layout"><fieldset class="admin-fieldset"><legend>Content details</legend>';
    $html .= '<div class="admin-field"><label class="admin-field__label" for="type">Type</label><select id="type" name="type">' . $typeOptions . '</select></div>';
    $html .= $field('title', 'Title', (string) $data['title']);
    $html .= $field('slug', 'Slug', (string) $data['slug']);
    $html .= '<div class="admin-field"><label class="admin-field__label" for="excerpt">Excerpt</label><textarea id="excerpt" name="excerpt" rows="3">' . htmlspecialchars((string) $data['excerpt'], ENT_QUOTES, 'UTF-8') . '</textarea></div>';
    $html .= '<div class="admin-field"><label class="admin-field__label" for="body">Body</label><textarea id="body" name="body" rows="12" required>' . htmlspecialchars((string) $data['body'], ENT_QUOTES, 'UTF-8') . '</textarea><p class="admin-field__help">Use plain text content.</p></div></fieldset>';
    $html .= '<aside class="admin-content-form-sidebar"><fieldset class="admin-fieldset" data-core-content-media-picker data-picker-url="' . htmlspecialchars($app->adminUrl()->childUrl('media/select'), ENT_QUOTES, 'UTF-8') . '" data-selected-media="' . $featuredJson . '"><legend>Featured Media</legend><input id="featured_media_id" name="featured_media_id" type="hidden" value="' . htmlspecialchars($featuredId === null ? '' : (string) $featuredId, ENT_QUOTES, 'UTF-8') . '" data-core-content-media-input><p class="admin-field__help" data-core-content-media-status aria-live="polite">' . ($featuredDescriptor === null ? 'Select an image from Core Media.' : 'A featured image is selected.') . '</p><div class="admin-media-picker__selected" data-core-content-media-selected' . ($featuredDescriptor === null ? ' hidden' : '') . '></div><div class="admin-actions"><button class="admin-button admin-button--secondary" type="button" data-core-content-media-open' . (!$canUseMedia ? ' disabled' : '') . '>' . ($featuredDescriptor === null ? 'Select media' : 'Change') . '</button><button class="admin-button admin-button--link" type="button" data-core-content-media-clear' . ($featuredDescriptor === null || !$canUseMedia ? ' hidden' : '') . '>Clear</button></div>' . (!$canUseMedia ? '<p class="admin-field__help">Media selection requires the Media use permission.</p>' : '') . '<dialog class="admin-media-picker" data-core-content-media-dialog aria-labelledby="core-content-media-picker-title"><div class="admin-media-picker__panel"><h3 id="core-content-media-picker-title">Select featured Media</h3><p class="admin-field__help">Choose an image from Core Media.</p><div class="admin-field"><label class="admin-field__label" for="core-content-media-search">Search media</label><input id="core-content-media-search" type="search" data-core-content-media-search aria-controls="core-content-media-results" placeholder="Search title or filename" autocomplete="off"></div><div class="admin-media-picker__results" data-core-content-media-results aria-live="polite"></div><div class="admin-actions"><button class="admin-button admin-button--secondary" type="button" data-core-content-media-close>Cancel</button></div></div></dialog></fieldset></aside></div>';
    $html .= '<script src="' . htmlspecialchars($app->url('/admin-assets/js/core-content-featured-media.js?v=wu5-1'), ENT_QUOTES, 'UTF-8') . '" defer></script>';
    $html .= '<div class="admin-actions admin-form__actions"><a class="admin-button admin-button--secondary" href="' . htmlspecialchars($contentRoute(), ENT_QUOTES, 'UTF-8') . '">Cancel</a><button class="admin-button admin-button--primary" type="submit">' . ($mode === 'create' ? 'Create content' : 'Save changes') . '</button></div></form>';
    if (($data['id'] ?? null) !== null) {
        $html .= '<div class="admin-actions admin-content-form-lifecycle" aria-label="Content lifecycle actions">';
        $status = (string) ($data['status'] ?? 'draft');
        if ($status === 'draft' && $user->can('content.publish')) {
            $html .= '<form method="post" action="' . htmlspecialchars($contentRoute((string) $data['id'] . '/publish'), ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="_token" value="' . htmlspecialchars($app->session()->csrfToken(), ENT_QUOTES, 'UTF-8') . '"><button class="admin-button admin-button--primary" type="submit">Publish</button></form>';
        }
        if ($status === 'published' && $user->can('content.publish')) {
            $html .= '<form method="post" action="' . htmlspecialchars($contentRoute((string) $data['id'] . '/draft'), ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="_token" value="' . htmlspecialchars($app->session()->csrfToken(), ENT_QUOTES, 'UTF-8') . '"><button class="admin-button admin-button--secondary" type="submit">Draft</button></form>';
        }
        if (in_array($status, ['draft', 'published'], true) && $user->can('content.delete')) {
            $html .= '<form method="post" action="' . htmlspecialchars($contentRoute((string) $data['id'] . '/archive'), ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="_token" value="' . htmlspecialchars($app->session()->csrfToken(), ENT_QUOTES, 'UTF-8') . '"><button class="admin-button admin-button--danger" type="submit">Archive</button></form>';
        }
        if ($status === 'archived' && $user->can('content.delete')) {
            $html .= '<form method="post" action="' . htmlspecialchars($contentRoute((string) $data['id'] . '/restore'), ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="_token" value="' . htmlspecialchars($app->session()->csrfToken(), ENT_QUOTES, 'UTF-8') . '"><button class="admin-button admin-button--secondary" type="submit">Restore to Draft</button></form>';
        }
        $html .= '</div>';
    }
    $html .= '</div></div></section>';
    return Response::html($app->adminPageRenderer()->render($title, $html, $user, $app->session()->csrfToken(), $path, null, [['label' => 'Content', 'url' => $contentRoute()], ['label' => $title]]), $errors === [] ? 200 : 422);
};

$app->adminNavigation()->add('Content', $contentRoute(), 'content.read', 'content', 20);

$app->router()->get($app->adminUrl()->routeChildUrl('content'), function ($request) use ($app, $contentRepository, $requireContent, $contentRoute, $authorLabels): Response {
    $user = $requireContent($request, 'content.read');
    if ($user instanceof Response) return $user;
    $workspace = $contentRepository->paginate(25, 0);
    $html = '<section class="admin-content-page admin-stack" aria-labelledby="webcore-content-title"><header class="admin-page-heading"><div class="admin-page-heading__copy"><h2 class="admin-page-heading__title" id="webcore-content-title">Content</h2><p class="admin-page-heading__description">Webcore Pages and Articles.</p></div>';
    if ($user->can('content.create')) $html .= '<a class="admin-button admin-button--primary" href="' . htmlspecialchars($contentRoute('create'), ENT_QUOTES, 'UTF-8') . '">Create content</a>';
    $html .= '</header><div class="admin-panel admin-content-filters admin-filter-toolbar" data-core-content-list-filters aria-label="Content filters"><div class="admin-content-filter-field admin-content-search"><label for="core-content-list-search">Search</label><input id="core-content-list-search" type="search" data-core-content-list-search aria-controls="core-content-list-table" placeholder="Title or slug" autocomplete="off"></div><div class="admin-content-filter-field"><label for="core-content-list-type">Type</label><select id="core-content-list-type" data-core-content-list-type><option value="">All</option><option value="page">Page</option><option value="article">Article</option></select></div><div class="admin-content-filter-field"><label for="core-content-list-status">Status</label><select id="core-content-list-status" data-core-content-list-status><option value="">All</option><option value="draft">Draft</option><option value="published">Published</option><option value="archived">Archived</option></select></div><div class="admin-content-filter-field"><label for="core-content-list-author">Author</label><select id="core-content-list-author" data-core-content-list-author><option value="">All</option>';
    foreach ($authorLabels as $authorId => $authorLabel) $html .= '<option value="' . (int) $authorId . '">' . htmlspecialchars($authorLabel, ENT_QUOTES, 'UTF-8') . '</option>';
    $html .= '</select></div><div class="admin-content-filter-actions"><button class="admin-button admin-button--secondary" type="button" data-core-content-list-clear>Clear filters</button></div><p class="admin-content-filter-summary" data-core-content-list-summary aria-live="polite"></p></div><div class="admin-panel"><div class="admin-panel__body">';
    if ($workspace === []) {
        $html .= '<div class="admin-empty-state"><h3>No Content yet</h3><p>Create a Page or Article to begin.</p></div>';
    } else {
        $html .= '<div class="admin-table-wrap"><table class="admin-table" id="core-content-list-table"><thead><tr><th scope="col">Title</th><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Author</th></tr></thead><tbody>';
        foreach ($workspace as $item) {
            $edit = $contentRoute((string) $item->id() . '/edit');
            $rowAttributes = $user->can('content.update') ? ' data-content-edit-url="' . htmlspecialchars($edit, ENT_QUOTES, 'UTF-8') . '" tabindex="0" role="link" aria-label="Edit ' . htmlspecialchars($item->title(), ENT_QUOTES, 'UTF-8') . '"' : '';
            $authorId = $item->authorId();
            $authorLabel = $authorId === null ? '—' : ($authorLabels[$authorId] ?? 'Unknown author');
            $html .= '<tr data-core-content-row data-content-title="' . htmlspecialchars(strtolower($item->title()), ENT_QUOTES, 'UTF-8') . '" data-content-slug="' . htmlspecialchars(strtolower($item->slug()), ENT_QUOTES, 'UTF-8') . '" data-content-type="' . htmlspecialchars($item->type(), ENT_QUOTES, 'UTF-8') . '" data-content-status="' . htmlspecialchars($item->status(), ENT_QUOTES, 'UTF-8') . '" data-content-author="' . htmlspecialchars($authorId === null ? '' : (string) $authorId, ENT_QUOTES, 'UTF-8') . '"' . $rowAttributes . '><td data-label="Title"><strong>' . htmlspecialchars($item->title(), ENT_QUOTES, 'UTF-8') . '</strong><br><small>' . htmlspecialchars($item->slug(), ENT_QUOTES, 'UTF-8') . '</small></td><td data-label="Type">' . htmlspecialchars(ucfirst($item->type()), ENT_QUOTES, 'UTF-8') . '</td><td data-label="Status">' . htmlspecialchars(ucfirst($item->status()), ENT_QUOTES, 'UTF-8') . '</td><td data-label="Author">' . htmlspecialchars($authorLabel, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }
        $html .= '</tbody></table></div><p class="admin-empty-state admin-content-list-no-results" data-core-content-list-empty hidden role="status">No Content matches your search.</p>';
    }
    $html .= '</div></div><script src="' . htmlspecialchars($app->url('/admin-assets/js/core-content-list.js?v=wu5-2'), ENT_QUOTES, 'UTF-8') . '" defer></script></section>';
    return Response::html($app->adminPageRenderer()->render('Content', $html, $user, $app->session()->csrfToken(), $request->path(), null, []));
});

$app->router()->get($app->adminUrl()->routeChildUrl('content/create'), function ($request) use ($requireContent, $renderForm, $contentRoute): Response {
    $user = $requireContent($request, 'content.create');
    if ($user instanceof Response) return $user;
    return $renderForm('Create Content', $contentRoute(), ['type' => 'page', 'title' => '', 'slug' => '', 'excerpt' => '', 'body' => '', 'featured_media_id' => null], [], $user, $request->path(), 'create');
});

$app->router()->get($app->adminUrl()->routeChildUrl('content/{id}/edit'), function ($request, array $params) use ($app, $requireContent, $contentRepository, $routeId, $renderForm, $contentRoute): Response {
    $user = $requireContent($request, 'content.update');
    if ($user instanceof Response) return $user;
    $id = $routeId($params['id'] ?? null);
    $entry = $id === null ? null : $contentRepository->findById($id);
    if (!$entry) return $app->adminErrors()->response($request, 404);
    return $renderForm('Edit Content', $contentRoute((string) $entry->id()), [
        'id' => $entry->id(), 'type' => $entry->type(), 'title' => $entry->title(), 'slug' => $entry->slug(), 'excerpt' => $entry->excerpt() ?? '', 'body' => $entry->body(), 'status' => $entry->status(), 'featured_media_id' => $entry->featuredMediaId(), 'updated_at' => $entry->updatedAt(),
    ], [], $user, $request->path(), 'edit');
});

$save = static function ($request, ?array $params = null) use ($app, $requireContent, $contentRepository, $contentService, $contentMediaReferences, $contentSlugger, $routeId, $renderForm, $contentRoute): Response {
    $id = $params === null ? null : $routeId($params['id'] ?? null);
    $user = $requireContent($request, $id === null ? 'content.create' : 'content.update');
    if ($user instanceof Response) return $user;
    $csrf = $app->csrf()->validateOrReject($request);
    if ($csrf instanceof Response) return $app->adminErrors()->response($request, 419);
    $type = (string) $request->post('type', 'page');
    $title = trim((string) $request->post('title', ''));
    $slug = trim((string) $request->post('slug', ''));
    $body = trim((string) $request->post('body', ''));
    $errors = [];
    if (!in_array($type, ['page', 'article'], true)) $errors[] = 'Submitted content data is invalid.';
    if ($title === '') $errors[] = 'Title is required.';
    if ($body === '') $errors[] = 'Body is required.';
    try { if ($slug === '') $slug = $contentSlugger->generate($title); } catch (InvalidArgumentException) { $errors[] = 'Submitted content data is invalid.'; }
    $existing = $id === null ? null : $contentRepository->findById($id);
    if ($id !== null && !$existing) return $app->adminErrors()->response($request, 404);
    $featured = trim((string) $request->post('featured_media_id', ''));
    $featuredId = $featured === '' ? null : (filter_var($featured, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null);
    if ($featured !== '' && $featuredId === null) $errors[] = 'Featured Media reference is invalid.';
    $previousFeaturedId = $existing?->featuredMediaId();
    if ($featuredId !== $previousFeaturedId && !$user->can('media.use')) $errors[] = 'Featured Media selection is not authorized.';
    try { $contentMediaReferences->validate($featuredId); } catch (InvalidArgumentException) { $errors[] = 'Selected Media is unavailable for featured use.'; }
    $data = ['type' => $type, 'title' => $title, 'slug' => $slug, 'excerpt' => trim((string) $request->post('excerpt', '')), 'body' => $body, 'status' => $existing?->status() ?? 'draft', 'author_id' => $existing?->authorId() ?? $user->id(), 'featured_media_id' => $featuredId];
    if ($errors !== []) return $renderForm($id === null ? 'Create Content' : 'Edit Content', $id === null ? $contentRoute() : $contentRoute((string) $id), array_merge($data, ['updated_at' => $existing?->updatedAt()]), $errors, $user, $request->path(), $id === null ? 'create' : 'edit');
    try { if ($id === null) $contentService->create($data, [], $user->id()); else $contentService->update($id, $data, [], trim((string) $request->post('expected_updated_at', '')), $user->id()); }
    catch (ContentDuplicateSlugException|ContentStaleWriteException|InvalidArgumentException $exception) { return $renderForm($id === null ? 'Create Content' : 'Edit Content', $id === null ? $contentRoute() : $contentRoute((string) $id), array_merge($data, ['updated_at' => $existing?->updatedAt()]), [$exception instanceof ContentStaleWriteException ? $exception->getMessage() : ($exception instanceof ContentDuplicateSlugException ? $exception->getMessage() : 'Submitted content data is invalid.')], $user, $request->path(), $id === null ? 'create' : 'edit'); }
    catch (ContentWriteException) { return $app->adminErrors()->response($request, 503); }
    return Response::redirect($contentRoute());
};
foreach (['publish' => 'content.publish', 'archive' => 'content.delete'] as $action => $permission) {
    $app->router()->post($app->adminUrl()->routeChildUrl('content/{id}/' . $action), function ($request, array $params) use ($app, $requireContent, $contentRepository, $contentService, $routeId, $contentRoute, $permission, $action): Response {
        $user = $requireContent($request, $permission);
        if ($user instanceof Response) return $user;
        $csrf = $app->csrf()->validateOrReject($request);
        if ($csrf instanceof Response) return $app->adminErrors()->response($request, 419);
        $id = $routeId($params['id'] ?? null);
        if ($id === null || !$contentRepository->findById($id)) return $app->adminErrors()->response($request, 404);
        try { $action === 'publish' ? $contentService->publish($id) : $contentService->archive($id); }
        catch (InvalidArgumentException) { return $app->adminErrors()->response($request, 422); }
        catch (ContentWriteException) { return $app->adminErrors()->response($request, 503); }
        return Response::redirect($contentRoute());
    });
}

foreach (['draft' => 'content.publish', 'restore' => 'content.delete'] as $action => $permission) {
    $app->router()->post($app->adminUrl()->routeChildUrl('content/{id}/' . $action), function ($request, array $params) use ($app, $requireContent, $contentRepository, $contentService, $routeId, $contentRoute, $permission, $action): Response {
        $user = $requireContent($request, $permission);
        if ($user instanceof Response) return $user;
        $csrf = $app->csrf()->validateOrReject($request);
        if ($csrf instanceof Response) return $app->adminErrors()->response($request, 419);
        $id = $routeId($params['id'] ?? null);
        if ($id === null || !$contentRepository->findById($id)) return $app->adminErrors()->response($request, 404);
        try { $action === 'draft' ? $contentService->draft($id) : $contentService->restore($id); }
        catch (InvalidArgumentException) { return $app->adminErrors()->response($request, 422); }
        catch (ContentWriteException) { return $app->adminErrors()->response($request, 503); }
        return Response::redirect($contentRoute());
    });
}

// Register the generic edit endpoint last because the router's terminal
// parameter is intentionally greedy and must not shadow lifecycle actions.
$app->router()->post($app->adminUrl()->routeChildUrl('content'), $save);
$app->router()->post($app->adminUrl()->routeChildUrl('content/{id}'), $save);
