<?php

use Copot\Core\Content;
use Copot\Core\ContentDuplicateSlugException;
use Copot\Core\ContentRepository;
use Copot\Core\ContentService;
use Copot\Core\ContentStaleWriteException;
use Copot\Core\ContentWriteException;
use Copot\Core\MediaRepository;
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
$contentService = new ContentService($app->database(), $contentRepository);
$contentSlugger = new \Copot\Core\Slugger();

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

$renderForm = static function (string $title, string $action, array $data, array $errors, $user, string $path, string $mode) use ($app, $contentBase, $contentRoute, $formData): Response {
    $html = '<section class="admin-content-form-page admin-stack" aria-labelledby="webcore-content-form-title">'
        . '<header class="admin-page-heading"><div class="admin-page-heading__copy"><h2 class="admin-page-heading__title" id="webcore-content-form-title">'
        . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2><p class="admin-page-heading__description">Create or update a Page or Article using Webcore Content.</p></div>'
        . '<a class="admin-button admin-button--secondary" href="' . htmlspecialchars($contentRoute(), ENT_QUOTES, 'UTF-8') . '">Back to Content</a></header>'
        . '<div class="admin-panel"><form class="admin-form" method="post" action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '">'
        . '<input type="hidden" name="_token" value="' . htmlspecialchars($app->session()->csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
    if (!empty($data['updated_at'])) $html .= '<input type="hidden" name="expected_updated_at" value="' . htmlspecialchars((string) $data['updated_at'], ENT_QUOTES, 'UTF-8') . '">';
    if ($errors !== []) {
        $html .= '<div class="admin-alert admin-alert--danger" role="alert"><strong>Please correct the following errors.</strong><ul class="admin-alert__list">';
        foreach ($errors as $error) $html .= '<li>' . htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') . '</li>';
        $html .= '</ul></div>';
    }
    $field = static fn (string $id, string $label, string $value, string $type = 'text'): string => '<div class="admin-field"><label class="admin-field__label" for="' . $id . '">' . $label . '</label><input id="' . $id . '" name="' . $id . '" type="' . $type . '" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"></div>';
    $html .= $field('type', 'Type', (string) $data['type']);
    $html .= $field('title', 'Title', (string) $data['title']);
    $html .= $field('slug', 'Slug', (string) $data['slug']);
    $html .= '<div class="admin-field"><label class="admin-field__label" for="excerpt">Excerpt</label><textarea id="excerpt" name="excerpt" rows="3">' . htmlspecialchars((string) $data['excerpt'], ENT_QUOTES, 'UTF-8') . '</textarea></div>';
    $html .= '<div class="admin-field"><label class="admin-field__label" for="body">Body</label><textarea id="body" name="body" rows="12" required>' . htmlspecialchars((string) $data['body'], ENT_QUOTES, 'UTF-8') . '</textarea><p class="admin-field__help">Use plain text content.</p></div>';
    $html .= $field('featured_media_id', 'Featured Media ID (optional)', $data['featured_media_id'] === null ? '' : (string) $data['featured_media_id'], 'number');
    $html .= '<div class="admin-actions admin-form__actions"><a class="admin-button admin-button--secondary" href="' . htmlspecialchars($contentRoute(), ENT_QUOTES, 'UTF-8') . '">Cancel</a><button class="admin-button admin-button--primary" type="submit">' . ($mode === 'create' ? 'Create content' : 'Save changes') . '</button></div></form></div></section>';
    return Response::html($app->adminPageRenderer()->render($title, $html, $user, $app->session()->csrfToken(), $path, null, [['label' => 'Content', 'url' => $contentRoute()], ['label' => $title]]), $errors === [] ? 200 : 422);
};

$app->adminNavigation()->add('Content', $contentBase, 'content.read', 'content', 20);

$app->router()->get($app->adminUrl()->routeChildUrl('content'), function ($request) use ($app, $contentRepository, $requireContent, $contentRoute): Response {
    $user = $requireContent($request, 'content.read');
    if ($user instanceof Response) return $user;
    $workspace = $contentRepository->paginate(25, 0);
    $html = '<section class="admin-content-page admin-stack" aria-labelledby="webcore-content-title"><div class="admin-panel"><div class="admin-panel__header"><h2 class="admin-panel__title" id="webcore-content-title">Content</h2><p class="admin-panel__description">Webcore Pages and Articles.</p></div>';
    if ($workspace === []) {
        $html .= '<div class="admin-empty-state"><h3>No Content yet</h3><p>Create a Page or Article to begin.</p></div>';
    } else {
        $html .= '<div class="admin-table-wrapper"><table class="admin-table"><thead><tr><th>Title</th><th>Type</th><th>Status</th><th>Author</th><th>Actions</th></tr></thead><tbody>';
        foreach ($workspace as $item) {
            $edit = $contentRoute((string) $item->id() . '/edit');
            $html .= '<tr><td><strong>' . htmlspecialchars($item->title(), ENT_QUOTES, 'UTF-8') . '</strong><br><small>' . htmlspecialchars($item->slug(), ENT_QUOTES, 'UTF-8') . '</small></td><td>' . htmlspecialchars(ucfirst($item->type()), ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars(ucfirst($item->status()), ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars($item->authorId() === null ? '—' : (string) $item->authorId(), ENT_QUOTES, 'UTF-8') . '</td><td class="admin-row-actions"><a class="admin-button admin-button--secondary" href="' . htmlspecialchars($edit, ENT_QUOTES, 'UTF-8') . '">Edit</a>';
            if ($item->status() === 'draft' && $user->can('content.publish')) $html .= '<form method="post" action="' . htmlspecialchars($contentRoute((string) $item->id() . '/publish'), ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="_token" value="' . htmlspecialchars($app->session()->csrfToken(), ENT_QUOTES, 'UTF-8') . '"><button class="admin-button admin-button--primary" type="submit">Publish</button></form>';
            if ($item->status() !== 'archived' && $user->can('content.delete')) $html .= '<form method="post" action="' . htmlspecialchars($contentRoute((string) $item->id() . '/archive'), ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="_token" value="' . htmlspecialchars($app->session()->csrfToken(), ENT_QUOTES, 'UTF-8') . '"><button class="admin-button admin-button--danger" type="submit">Archive</button></form>';
            $html .= '</td></tr>';
        }
        $html .= '</tbody></table></div>';
    }
    if ($user->can('content.create')) $html .= '<div class="admin-actions"><a class="admin-button admin-button--primary" href="' . htmlspecialchars($contentRoute('create'), ENT_QUOTES, 'UTF-8') . '">Create content</a></div>';
    $html .= '</div></section>';
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
        'id' => $entry->id(), 'type' => $entry->type(), 'title' => $entry->title(), 'slug' => $entry->slug(), 'excerpt' => $entry->excerpt() ?? '', 'body' => $entry->body(), 'featured_media_id' => $entry->featuredMediaId(), 'updated_at' => $entry->updatedAt(),
    ], [], $user, $request->path(), 'edit');
});

$save = static function ($request, ?array $params = null) use ($app, $requireContent, $contentRepository, $contentService, $contentSlugger, $mediaRepository, $routeId, $renderForm, $contentRoute): Response {
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
    $featured = trim((string) $request->post('featured_media_id', ''));
    $featuredId = $featured === '' ? null : (filter_var($featured, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null);
    if ($featured !== '' && $featuredId === null) $errors[] = 'Featured Media reference is invalid.';
    if ($featuredId !== null && !$mediaRepository->findById($featuredId)) $errors[] = 'Featured Media reference is invalid.';
    $existing = $id === null ? null : $contentRepository->findById($id);
    if ($id !== null && !$existing) return $app->adminErrors()->response($request, 404);
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

// Register the generic edit endpoint last because the router's terminal
// parameter is intentionally greedy and must not shadow lifecycle actions.
$app->router()->post($app->adminUrl()->routeChildUrl('content'), $save);
$app->router()->post($app->adminUrl()->routeChildUrl('content/{id}'), $save);
