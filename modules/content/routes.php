<?php

use Copot\Core\Response;
use Copot\Core\ThemeException;
use Copot\Core\ViewException;
use Copot\Core\ModuleRepository;

require_once __DIR__ . '/Services/Content.php';
require_once __DIR__ . '/Services/ContentRepository.php';
require_once __DIR__ . '/Services/Slugger.php';

$taxonomyServicesPath = dirname(__DIR__) . '/taxonomy/Services';
$contentTaxonomyAvailable = is_file($taxonomyServicesPath . '/TaxonomyType.php')
    && is_file($taxonomyServicesPath . '/TaxonomyTerm.php')
    && is_file($taxonomyServicesPath . '/TaxonomyRepository.php')
    && is_file($taxonomyServicesPath . '/TaxonomyAssignmentRepository.php');
$contentTaxonomyEnabled = false;

if ($contentTaxonomyAvailable) {
    try {
        $taxonomyModule = (new ModuleRepository($app->database()))->findByName('taxonomy');
        $contentTaxonomyEnabled = ($taxonomyModule['status'] ?? null) === 'enabled';
    } catch (Throwable) {
        $contentTaxonomyEnabled = false;
    }
}

if ($contentTaxonomyAvailable && $contentTaxonomyEnabled) {
    require_once $taxonomyServicesPath . '/TaxonomyType.php';
    require_once $taxonomyServicesPath . '/TaxonomyTerm.php';
    require_once $taxonomyServicesPath . '/TaxonomyRepository.php';
    require_once $taxonomyServicesPath . '/TaxonomyAssignmentRepository.php';
}

$contentAdminBase = $app->adminUrl()->baseUrl();
$contentRepository = new ContentRepository($app->database());
$contentSlugger = new Slugger();
$contentTaxonomyRepository = $contentTaxonomyEnabled ? new TaxonomyRepository($app->database()) : null;
$contentTaxonomyAssignments = $contentTaxonomyEnabled ? new TaxonomyAssignmentRepository($app->database()) : null;
$contentAdminUrlService = $app->adminUrl();
$contentAdminUrl = static function (string $path = '') use ($contentAdminUrlService): string {
    return $contentAdminUrlService->childUrl($path);
};

$app->adminNavigation()->add('Content', $app->adminUrl()->childUrl('content'), [
    'content.create',
    'content.update',
    'content.delete',
    'content.publish',
]);

$app->adminDashboard()->add(
    'content.overview',
    'Content',
    'Create, review, publish, and archive site content.',
    $app->adminUrl()->childUrl('content'),
    [
        'content.create',
        'content.update',
        'content.delete',
        'content.publish',
    ],
    200
);

$contentRenderView = function (string $view, array $data = []) use ($contentAdminUrl): string {
    $file = __DIR__ . '/views/admin/' . $view . '.php';

    if (!is_file($file)) {
        throw new RuntimeException("Content admin view [{$view}] was not found.");
    }

    $data['adminUrl'] = $contentAdminUrl;

    extract($data, EXTR_SKIP);

    ob_start();
    require $file;

    return (string) ob_get_clean();
};

$contentUserCanAny = function ($user, array $permissions): bool {
    foreach ($permissions as $permission) {
        if ($user?->can($permission)) {
            return true;
        }
    }

    return false;
};

$contentValidateCsrf = function ($request) use ($app): ?Response {
    return $app->csrf()->validateOrReject($request);
};

$contentRequireAdmin = function (array $permissions) use ($app, $contentAdminBase, $contentUserCanAny) {
    if (!$app->auth()->check()) {
        return Response::redirect($contentAdminBase);
    }

    $user = $app->auth()->user();

    if (!$user?->can('admin.access') || !$contentUserCanAny($user, $permissions)) {
        return Response::html('403 Forbidden', 403);
    }

    return $user;
};

$contentToFormData = function (?Content $content = null): array {
    if (!$content) {
        return [
            'id' => null,
            'type' => 'page',
            'title' => '',
            'slug' => '',
            'excerpt' => '',
            'body' => '',
            'status' => 'draft',
        ];
    }

    return [
        'id' => $content->id(),
        'type' => $content->type(),
        'title' => $content->title(),
        'slug' => $content->slug(),
        'excerpt' => $content->excerpt() ?? '',
        'body' => $content->body(),
        'status' => $content->status(),
    ];
};

$contentReadFormData = function ($request, $user, ?Content $existing = null) use ($contentSlugger, $contentRepository): array {
    $requestedStatus = (string) $request->input('status', 'draft');
    $currentStatus = $existing?->status();

    if (!in_array($requestedStatus, ['draft', 'published', 'archived'], true)) {
        $requestedStatus = $currentStatus ?: 'draft';
    }

    if (!$user->can('content.publish')) {
        $requestedStatus = $currentStatus ?: 'draft';
    }

    $data = [
        'type' => (string) $request->input('type', 'page'),
        'title' => trim((string) $request->input('title', '')),
        'slug' => trim((string) $request->input('slug', '')),
        'excerpt' => (string) $request->input('excerpt', ''),
        'body' => (string) $request->input('body', ''),
        'status' => $requestedStatus,
        'author_id' => $existing?->authorId() ?? $user->id(),
    ];

    $errors = [];

    if ($data['title'] === '') {
        $errors[] = 'Title is required.';
    }

    if (trim($data['body']) === '') {
        $errors[] = 'Body is required.';
    }

    if ($existing && $currentStatus !== $data['status'] && !$user->can('content.publish')) {
        $errors[] = 'Publishing status changes require content.publish permission.';
    }

    try {
        $ignoreId = $existing?->id();
        $data['slug'] = $contentSlugger->unique($data['slug'] !== '' ? $data['slug'] : $data['title'], $contentRepository, $ignoreId);
    } catch (InvalidArgumentException $exception) {
        $errors[] = $exception->getMessage();
    }

    return [$data, $errors];
};

$contentTaxonomyIdsFromRequest = function ($request, string $field): array {
    $values = $request->post($field, []);

    if (!is_array($values)) {
        $values = [$values];
    }

    $ids = [];

    foreach ($values as $value) {
        if (!is_numeric($value) || (int) $value <= 0) {
            continue;
        }

        $id = (int) $value;

        if (!in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }

    return $ids;
};

$contentTaxonomyOptions = function () use ($contentTaxonomyRepository): array {
    $empty = [
        'available' => false,
        'categories' => [],
        'tags' => [],
    ];

    if (!$contentTaxonomyRepository) {
        return $empty;
    }

    try {
        return [
            'available' => true,
            'categories' => $contentTaxonomyRepository->termsByType('category'),
            'tags' => $contentTaxonomyRepository->termsByType('tag'),
        ];
    } catch (Throwable) {
        return $empty;
    }
};

$contentSelectedTaxonomy = function (?Content $content = null) use ($contentTaxonomyAssignments): array {
    $empty = [
        'category_ids' => [],
        'tag_ids' => [],
    ];

    if (!$content || !$contentTaxonomyAssignments) {
        return $empty;
    }

    try {
        return [
            'category_ids' => array_map(
                fn (TaxonomyTerm $term): int => $term->id(),
                $contentTaxonomyAssignments->termsForEntityByType('content', $content->id(), 'category')
            ),
            'tag_ids' => array_map(
                fn (TaxonomyTerm $term): int => $term->id(),
                $contentTaxonomyAssignments->termsForEntityByType('content', $content->id(), 'tag')
            ),
        ];
    } catch (Throwable) {
        return $empty;
    }
};

$contentSelectedTaxonomyFromRequest = function ($request) use ($contentTaxonomyIdsFromRequest): array {
    return [
        'category_ids' => $contentTaxonomyIdsFromRequest($request, 'category_ids'),
        'tag_ids' => $contentTaxonomyIdsFromRequest($request, 'tag_ids'),
    ];
};

$contentSyncTaxonomy = function (int $contentId, array $selected) use ($contentTaxonomyAssignments): void {
    if (!$contentTaxonomyAssignments) {
        return;
    }

    try {
        $contentTaxonomyAssignments->syncForType('content', $contentId, 'category', $selected['category_ids'] ?? []);
        $contentTaxonomyAssignments->syncForType('content', $contentId, 'tag', $selected['tag_ids'] ?? []);
    } catch (Throwable) {
    }
};

$contentTaxonomyForList = function (array $contents) use ($contentTaxonomyAssignments): array {
    $terms = [];

    if (!$contentTaxonomyAssignments) {
        return $terms;
    }

    foreach ($contents as $entry) {
        try {
            $terms[$entry->id()] = [
                'categories' => $contentTaxonomyAssignments->termsForEntityByType('content', $entry->id(), 'category'),
                'tags' => $contentTaxonomyAssignments->termsForEntityByType('content', $entry->id(), 'tag'),
            ];
        } catch (Throwable) {
            return [];
        }
    }

    return $terms;
};

$contentRenderAdmin = function (
    string $title,
    string $content,
    $user,
    string $currentPath,
    int $status = 200
) use ($app): Response {
    return Response::html($app->adminPageRenderer()->render(
        $title,
        $content,
        $user,
        $app->session()->csrfToken(),
        $currentPath
    ), $status);
};

$app->router()->get($app->adminUrl()->childUrl('content'), function ($request) use (
    $app,
    $contentRepository,
    $contentRequireAdmin,
    $contentRenderAdmin,
    $contentRenderView,
    $contentTaxonomyForList,
    $contentTaxonomyEnabled,
    $contentAdminBase
): Response {
    $user = $contentRequireAdmin([
        'content.create',
        'content.update',
        'content.delete',
        'content.publish',
    ]);

    if ($user instanceof Response) {
        return $user;
    }

    $contents = $contentRepository->paginate(50);
    $content = $contentRenderView('list', [
        'adminBase' => $contentAdminBase,
        'contents' => $contents,
        'taxonomyAvailable' => $contentTaxonomyEnabled,
        'taxonomyTerms' => $contentTaxonomyForList($contents),
        'canCreate' => $user->can('content.create'),
        'canUpdate' => $user->can('content.update'),
        'canPublish' => $user->can('content.publish'),
        'canDelete' => $user->can('content.delete'),
        'csrfToken' => $app->session()->csrfToken(),
    ]);

    return $contentRenderAdmin('Content', $content, $user, $request->path());
});

$app->router()->get('/content/{slug}', function ($request, array $params) use ($app, $contentRepository): Response {
    $slug = trim((string) ($params['slug'] ?? ''));

    if ($slug === '') {
        return Response::html('404 Not Found', 404);
    }

    $entry = $contentRepository->findPublishedBySlug($slug);

    if (!$entry) {
        return Response::html('404 Not Found', 404);
    }

    try {
        return Response::html($app->viewRenderer()->renderFile(
            $app->viewResolver()->resolve('content::show'),
            ['content' => $entry],
            null,
            $entry->title()
        ));
    } catch (ThemeException|ViewException|\Throwable) {
        return Response::html('<h1>Theme rendering error.</h1>', 500);
    }
});

$app->router()->get($app->adminUrl()->childUrl('content/create'), function ($request) use (
    $app,
    $contentRequireAdmin,
    $contentRenderAdmin,
    $contentRenderView,
    $contentSelectedTaxonomy,
    $contentTaxonomyOptions,
    $contentToFormData,
    $contentAdminBase
): Response {
    $user = $contentRequireAdmin(['content.create']);

    if ($user instanceof Response) {
        return $user;
    }

    $content = $contentRenderView('form', [
        'adminBase' => $contentAdminBase,
        'formAction' => $app->adminUrl()->childUrl('content'),
        'formMode' => 'create',
        'heading' => 'Create Content',
        'submitLabel' => 'Create content',
        'csrfToken' => $app->session()->csrfToken(),
        'canPublish' => $user->can('content.publish'),
        'errors' => [],
        'content' => $contentToFormData(),
        'taxonomy' => $contentTaxonomyOptions(),
        'selectedTaxonomy' => $contentSelectedTaxonomy(),
    ]);

    return $contentRenderAdmin('Create Content', $content, $user, $request->path());
});

$app->router()->get($app->adminUrl()->childUrl('content/{id}/edit'), function ($request, array $params) use (
    $app,
    $contentRepository,
    $contentRequireAdmin,
    $contentRenderAdmin,
    $contentRenderView,
    $contentSelectedTaxonomy,
    $contentTaxonomyOptions,
    $contentToFormData,
    $contentAdminBase
): Response {
    $user = $contentRequireAdmin(['content.update']);

    if ($user instanceof Response) {
        return $user;
    }

    $id = (int) ($params['id'] ?? 0);
    $entry = $id > 0 ? $contentRepository->findById($id) : null;

    if (!$entry) {
        return Response::html('404 Not Found', 404);
    }

    $content = $contentRenderView('form', [
        'adminBase' => $contentAdminBase,
        'formAction' => $app->adminUrl()->childUrl('content/' . $entry->id()),
        'formMode' => 'edit',
        'heading' => 'Edit Content',
        'submitLabel' => 'Save changes',
        'csrfToken' => $app->session()->csrfToken(),
        'canPublish' => $user->can('content.publish'),
        'errors' => [],
        'content' => $contentToFormData($entry),
        'taxonomy' => $contentTaxonomyOptions(),
        'selectedTaxonomy' => $contentSelectedTaxonomy($entry),
    ]);

    return $contentRenderAdmin('Edit Content', $content, $user, $request->path());
});

$app->router()->post($app->adminUrl()->childUrl('content'), function ($request) use (
    $app,
    $contentRepository,
    $contentRequireAdmin,
    $contentRenderAdmin,
    $contentRenderView,
    $contentReadFormData,
    $contentSelectedTaxonomyFromRequest,
    $contentSyncTaxonomy,
    $contentTaxonomyOptions,
    $contentToFormData,
    $contentValidateCsrf,
    $contentAdminBase
): Response {
    $csrfResponse = $contentValidateCsrf($request);

    if ($csrfResponse) {
        return $csrfResponse;
    }

    $user = $contentRequireAdmin(['content.create']);

    if ($user instanceof Response) {
        return $user;
    }

    [$data, $errors] = $contentReadFormData($request, $user);
    $selectedTaxonomy = $contentSelectedTaxonomyFromRequest($request);

    if ($errors !== []) {
        $content = $contentRenderView('form', [
            'adminBase' => $contentAdminBase,
            'formAction' => $app->adminUrl()->childUrl('content'),
            'formMode' => 'create',
            'heading' => 'Create Content',
            'submitLabel' => 'Create content',
            'csrfToken' => $app->session()->csrfToken(),
            'canPublish' => $user->can('content.publish'),
            'errors' => $errors,
            'content' => array_merge($contentToFormData(), $data),
            'taxonomy' => $contentTaxonomyOptions(),
            'selectedTaxonomy' => $selectedTaxonomy,
        ]);

        return $contentRenderAdmin('Create Content', $content, $user, $request->path(), 422);
    }

    try {
        $contentId = $contentRepository->create($data);
        $contentSyncTaxonomy($contentId, $selectedTaxonomy);
    } catch (InvalidArgumentException $exception) {
        $content = $contentRenderView('form', [
            'adminBase' => $contentAdminBase,
            'formAction' => $app->adminUrl()->childUrl('content'),
            'formMode' => 'create',
            'heading' => 'Create Content',
            'submitLabel' => 'Create content',
            'csrfToken' => $app->session()->csrfToken(),
            'canPublish' => $user->can('content.publish'),
            'errors' => [$exception->getMessage()],
            'content' => array_merge($contentToFormData(), $data),
            'taxonomy' => $contentTaxonomyOptions(),
            'selectedTaxonomy' => $selectedTaxonomy,
        ]);

        return $contentRenderAdmin('Create Content', $content, $user, $request->path(), 422);
    }

    return Response::redirect($app->adminUrl()->childUrl('content'));
});

$app->router()->post($app->adminUrl()->childUrl('content/{id}/publish'), function ($request, array $params) use (
    $app,
    $contentRepository,
    $contentRequireAdmin,
    $contentValidateCsrf
): Response {
    $csrfResponse = $contentValidateCsrf($request);

    if ($csrfResponse) {
        return $csrfResponse;
    }

    $user = $contentRequireAdmin(['content.publish']);

    if ($user instanceof Response) {
        return $user;
    }

    $id = (int) ($params['id'] ?? 0);

    if ($id <= 0 || !$contentRepository->findById($id)) {
        return Response::html('404 Not Found', 404);
    }

    $contentRepository->publish($id);

    return Response::redirect($app->adminUrl()->childUrl('content'));
});

$app->router()->post($app->adminUrl()->childUrl('content/{id}/draft'), function ($request, array $params) use (
    $app,
    $contentRepository,
    $contentRequireAdmin,
    $contentValidateCsrf
): Response {
    $csrfResponse = $contentValidateCsrf($request);

    if ($csrfResponse) {
        return $csrfResponse;
    }

    $user = $contentRequireAdmin(['content.publish']);

    if ($user instanceof Response) {
        return $user;
    }

    $id = (int) ($params['id'] ?? 0);

    if ($id <= 0 || !$contentRepository->findById($id)) {
        return Response::html('404 Not Found', 404);
    }

    $contentRepository->draft($id);

    return Response::redirect($app->adminUrl()->childUrl('content'));
});

$app->router()->post($app->adminUrl()->childUrl('content/{id}/archive'), function ($request, array $params) use (
    $app,
    $contentRepository,
    $contentRequireAdmin,
    $contentValidateCsrf
): Response {
    $csrfResponse = $contentValidateCsrf($request);

    if ($csrfResponse) {
        return $csrfResponse;
    }

    $user = $contentRequireAdmin(['content.delete']);

    if ($user instanceof Response) {
        return $user;
    }

    $id = (int) ($params['id'] ?? 0);

    if ($id <= 0 || !$contentRepository->findById($id)) {
        return Response::html('404 Not Found', 404);
    }

    $contentRepository->archive($id);

    return Response::redirect($app->adminUrl()->childUrl('content'));
});

$app->router()->post($app->adminUrl()->childUrl('content/{id}'), function ($request, array $params) use (
    $app,
    $contentRepository,
    $contentRequireAdmin,
    $contentRenderAdmin,
    $contentRenderView,
    $contentReadFormData,
    $contentSelectedTaxonomyFromRequest,
    $contentSyncTaxonomy,
    $contentTaxonomyOptions,
    $contentToFormData,
    $contentValidateCsrf,
    $contentAdminBase
): Response {
    $csrfResponse = $contentValidateCsrf($request);

    if ($csrfResponse) {
        return $csrfResponse;
    }

    $user = $contentRequireAdmin(['content.update']);

    if ($user instanceof Response) {
        return $user;
    }

    $id = (int) ($params['id'] ?? 0);
    $entry = $id > 0 ? $contentRepository->findById($id) : null;

    if (!$entry) {
        return Response::html('404 Not Found', 404);
    }

    [$data, $errors] = $contentReadFormData($request, $user, $entry);
    $selectedTaxonomy = $contentSelectedTaxonomyFromRequest($request);

    if ($errors !== []) {
        $content = $contentRenderView('form', [
            'adminBase' => $contentAdminBase,
            'formAction' => $app->adminUrl()->childUrl('content/' . $entry->id()),
            'formMode' => 'edit',
            'heading' => 'Edit Content',
            'submitLabel' => 'Save changes',
            'csrfToken' => $app->session()->csrfToken(),
            'canPublish' => $user->can('content.publish'),
            'errors' => $errors,
            'content' => array_merge($contentToFormData($entry), $data),
            'taxonomy' => $contentTaxonomyOptions(),
            'selectedTaxonomy' => $selectedTaxonomy,
        ]);

        return $contentRenderAdmin('Edit Content', $content, $user, $request->path(), 422);
    }

    try {
        $contentRepository->update($entry->id(), $data);
        $contentSyncTaxonomy($entry->id(), $selectedTaxonomy);
    } catch (InvalidArgumentException $exception) {
        $content = $contentRenderView('form', [
            'adminBase' => $contentAdminBase,
            'formAction' => $app->adminUrl()->childUrl('content/' . $entry->id()),
            'formMode' => 'edit',
            'heading' => 'Edit Content',
            'submitLabel' => 'Save changes',
            'csrfToken' => $app->session()->csrfToken(),
            'canPublish' => $user->can('content.publish'),
            'errors' => [$exception->getMessage()],
            'content' => array_merge($contentToFormData($entry), $data),
            'taxonomy' => $contentTaxonomyOptions(),
            'selectedTaxonomy' => $selectedTaxonomy,
        ]);

        return $contentRenderAdmin('Edit Content', $content, $user, $request->path(), 422);
    }

    return Response::redirect($app->adminUrl()->childUrl('content'));
});
