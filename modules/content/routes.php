<?php

use Copot\Core\Response;
use Copot\Core\ModuleRepository;

require_once __DIR__ . '/Services/Content.php';
require_once __DIR__ . '/Services/ContentRepository.php';
require_once __DIR__ . '/Services/ContentService.php';
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
$contentService = new ContentService($app->database(), $contentRepository, $contentTaxonomyAssignments);
$contentValidationMessage = static function (InvalidArgumentException $exception): string {
    return match (true) {
        $exception instanceof ContentDuplicateSlugException => 'The content slug is already in use.',
        $exception instanceof ContentStaleWriteException => 'Content changed after it was loaded. Refresh and try again.',
        default => 'Submitted content data is invalid.',
    };
};
$contentRouteId = static function (mixed $value): ?int {
    if (!is_string($value) && !is_int($value)) {
        return null;
    }

    $normalized = (string) $value;

    if (!preg_match('/^[1-9][0-9]*$/', $normalized)) {
        return null;
    }

    $id = filter_var($normalized, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    return is_int($id) ? $id : null;
};
$contentAdminUrlService = $app->adminUrl();
$contentAdminUrl = static function (string $path = '') use ($contentAdminUrlService): string {
    return $contentAdminUrlService->childUrl($path);
};

$app->adminNavigation()->add('Content', $app->adminUrl()->childUrl('content'), [
    'content.read',
], 'content', 20);

$app->adminDashboard()->add(
    'content.overview',
    'Content',
    'Create, review, publish, and archive site content.',
    $app->adminUrl()->childUrl('content'),
    [
        'content.read',
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

    $initialOutputLevel = ob_get_level();

    if (!@ob_start()) {
        throw new RuntimeException('Content admin view output buffer is unavailable.');
    }

    try {
        require $file;

        if (ob_get_level() !== $initialOutputLevel + 1) {
            throw new RuntimeException('Content admin view output buffer state is invalid.');
        }

        $rendered = @ob_get_clean();

        if (!is_string($rendered)) {
            throw new RuntimeException('Content admin view output buffer could not be read.');
        }

        return $rendered;
    } catch (Throwable $exception) {
        while (ob_get_level() > $initialOutputLevel) {
            $level = ob_get_level();

            if (!@ob_end_clean() || ob_get_level() >= $level) {
                break;
            }
        }

        throw $exception;
    }
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
    $csrfResponse = $app->csrf()->validateOrReject($request);

    return $csrfResponse instanceof Response
        ? $app->adminErrors()->response($request, 419)
        : null;
};

$contentRequireAdmin = function ($request, array $permissions) use ($app, $contentAdminBase, $contentUserCanAny) {
    if (!$app->auth()->check()) {
        return Response::redirect($contentAdminBase);
    }

    $user = $app->auth()->user();

    if (!$user?->can('admin.access') || !$contentUserCanAny($user, $permissions)) {
        return $app->adminErrors()->response($request, 403);
    }

    return $user;
};

$contentNormalizeWorkspace = static function ($request): array {
    $search = trim((string) $request->input('q', ''));
    $type = $request->input('type');
    $status = $request->input('status');
    $page = max(1, (int) $request->input('page', 1));
    $requestedPerPage = (int) $request->input('per_page', 25);

    return [
        'search' => $search,
        'type' => in_array($type, ['page', 'article'], true) ? $type : null,
        'status' => in_array($status, ['draft', 'published', 'archived'], true) ? $status : null,
        'page' => $page,
        'per_page' => $requestedPerPage < 1 ? 25 : min($requestedPerPage, 100),
    ];
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
            'updated_at' => null,
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
        'updated_at' => $content->updatedAt(),
    ];
};

$contentReadFormData = function ($request, $user, ?Content $existing = null) use ($contentSlugger, $contentValidationMessage): array {
    $errors = [];
    $invalidPayload = false;
    $readScalar = static function (string $field, mixed $default = '') use ($request, &$invalidPayload): string {
        $value = $request->input($field, $default);

        if ($value !== null && !is_scalar($value)) {
            $invalidPayload = true;

            return '';
        }

        return (string) $value;
    };
    $statusValue = $request->input('status', 'draft');
    $requestedStatus = is_scalar($statusValue) || $statusValue === null ? (string) $statusValue : '';
    $currentStatus = $existing?->status();

    if ($statusValue !== null && !is_scalar($statusValue)) {
        $invalidPayload = true;
    }

    if (!in_array($requestedStatus, ['draft', 'published', 'archived'], true)) {
        $requestedStatus = $currentStatus ?: 'draft';
    }

    if (!$user->can('content.publish')) {
        $requestedStatus = $currentStatus ?: 'draft';
    }

    $data = [
        'type' => $readScalar('type', 'page'),
        'title' => trim($readScalar('title')),
        'slug' => trim($readScalar('slug')),
        'excerpt' => $readScalar('excerpt'),
        'body' => $readScalar('body'),
        'status' => $requestedStatus,
        'author_id' => $existing?->authorId() ?? $user->id(),
    ];

    if ($invalidPayload) {
        $errors[] = 'Submitted content data is invalid.';
    }

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
        $slugInput = $data['slug'];

        if ($existing && $slugInput === '') {
            $data['slug'] = $existing->slug();
        } else {
            $data['slug'] = $contentSlugger->generate($slugInput !== '' ? $slugInput : $data['title']);
        }
    } catch (InvalidArgumentException $exception) {
        $errors[] = $contentValidationMessage($exception);
    }

    return [$data, $errors];
};

$contentTaxonomyIdsFromRequest = function ($request, string $field): array {
    $values = $request->post($field, []);

    if (!is_array($values)) {
        throw new InvalidArgumentException('Taxonomy assignments are invalid.');
    }

    $ids = [];

    foreach ($values as $value) {
        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentException('Taxonomy assignments are invalid.');
        }

        $normalized = (string) $value;

        if (!preg_match('/^[1-9][0-9]*$/', $normalized)) {
            throw new InvalidArgumentException('Taxonomy assignments are invalid.');
        }

        $id = (int) $normalized;

        if ($id <= 0) {
            throw new InvalidArgumentException('Taxonomy assignments are invalid.');
        }

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
    $contentNormalizeWorkspace,
    $contentAdminUrl,
    $contentTaxonomyForList,
    $contentTaxonomyEnabled,
    $contentAdminBase
): Response {
    $user = $contentRequireAdmin($request, ['content.read']);

    if ($user instanceof Response) {
        return $user;
    }

    $filters = $contentNormalizeWorkspace($request);
    $workspace = $contentRepository->workspace(
        $filters,
        $filters['per_page'],
        ($filters['page'] - 1) * $filters['per_page']
    );
    $lastPage = max(1, (int) ceil($workspace['total'] / $filters['per_page']));

    if ($workspace['total'] > 0 && $filters['page'] > $lastPage) {
        $filters['page'] = $lastPage;
        $workspace = $contentRepository->workspace(
            $filters,
            $filters['per_page'],
            ($filters['page'] - 1) * $filters['per_page']
        );
    }

    $query = array_filter([
        'q' => $filters['search'],
        'type' => $filters['type'],
        'status' => $filters['status'],
        'per_page' => $filters['per_page'],
    ], static fn ($value): bool => $value !== null && $value !== '');
    $paginationUrl = static function (int $page) use ($contentAdminUrl, $query): string {
        return $contentAdminUrl('content') . '?' . http_build_query(array_merge($query, ['page' => $page]));
    };
    $hasFilters = $filters['search'] !== '' || $filters['type'] !== null || $filters['status'] !== null;
    $contents = $workspace['items'];
    $content = $contentRenderView('list', [
        'adminBase' => $contentAdminBase,
        'contents' => $contents,
        'hasFilters' => $hasFilters,
        'page' => $filters['page'],
        'perPage' => $filters['per_page'],
        'total' => $workspace['total'],
        'lastPage' => $lastPage,
        'paginationUrl' => $paginationUrl,
        'query' => $query,
        'search' => $filters['search'],
        'selectedType' => $filters['type'],
        'selectedStatus' => $filters['status'],
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
        return $app->adminErrors()->response($request, 404);
    }

    $entry = $contentRepository->findPublishedBySlug($slug);

    if (!$entry) {
        return $app->adminErrors()->response($request, 404);
    }

    return Response::html($app->viewRenderer()->renderFile(
        $app->viewResolver()->resolve('content::show'),
        ['content' => $entry],
        null,
        $entry->title()
    ));
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
    $user = $contentRequireAdmin($request, ['content.create']);

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
    $contentRouteId,
    $contentRequireAdmin,
    $contentRenderAdmin,
    $contentRenderView,
    $contentSelectedTaxonomy,
    $contentTaxonomyOptions,
    $contentToFormData,
    $contentAdminBase
): Response {
    $user = $contentRequireAdmin($request, ['content.update']);

    if ($user instanceof Response) {
        return $user;
    }

    $id = $contentRouteId($params['id'] ?? null);
    $entry = $id !== null ? $contentRepository->findById($id) : null;

    if (!$entry) {
        return $app->adminErrors()->response($request, 404);
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
    $contentService,
    $contentRequireAdmin,
    $contentRenderAdmin,
    $contentRenderView,
    $contentReadFormData,
    $contentSelectedTaxonomyFromRequest,
    $contentTaxonomyOptions,
    $contentToFormData,
    $contentValidationMessage,
    $contentValidateCsrf,
    $contentAdminBase
): Response {
    $user = $contentRequireAdmin($request, ['content.create']);

    if ($user instanceof Response) {
        return $user;
    }

    $csrfResponse = $contentValidateCsrf($request);

    if ($csrfResponse) {
        return $csrfResponse;
    }

    [$data, $errors] = $contentReadFormData($request, $user);
    $selectedTaxonomy = [];

    try {
        $selectedTaxonomy = $contentSelectedTaxonomyFromRequest($request);
    } catch (InvalidArgumentException $exception) {
        $errors[] = $contentValidationMessage($exception);
    }

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
        $contentId = $contentService->create($data, $selectedTaxonomy);
    } catch (InvalidArgumentException $exception) {
        $content = $contentRenderView('form', [
            'adminBase' => $contentAdminBase,
            'formAction' => $app->adminUrl()->childUrl('content'),
            'formMode' => 'create',
            'heading' => 'Create Content',
            'submitLabel' => 'Create content',
            'csrfToken' => $app->session()->csrfToken(),
            'canPublish' => $user->can('content.publish'),
            'errors' => [$contentValidationMessage($exception)],
            'content' => array_merge($contentToFormData(), $data),
            'taxonomy' => $contentTaxonomyOptions(),
            'selectedTaxonomy' => $selectedTaxonomy,
        ]);

        return $contentRenderAdmin('Create Content', $content, $user, $request->path(), 422);
    } catch (ContentWriteException $exception) {
        return $app->adminErrors()->response($request, 503);
    }

    return Response::redirect($app->adminUrl()->childUrl('content'));
});

$app->router()->post($app->adminUrl()->childUrl('content/{id}/publish'), function ($request, array $params) use (
    $app,
    $contentRepository,
    $contentRouteId,
    $contentService,
    $contentRequireAdmin,
    $contentValidateCsrf
): Response {
    $user = $contentRequireAdmin($request, ['content.publish']);

    if ($user instanceof Response) {
        return $user;
    }

    $csrfResponse = $contentValidateCsrf($request);

    if ($csrfResponse) {
        return $csrfResponse;
    }

    $id = $contentRouteId($params['id'] ?? null);

    if ($id === null || !$contentRepository->findById($id)) {
        return $app->adminErrors()->response($request, 404);
    }

    try {
        $contentService->publish($id);
    } catch (InvalidArgumentException $exception) {
        return $app->adminErrors()->response($request, 422);
    } catch (ContentWriteException) {
        return $app->adminErrors()->response($request, 503);
    }

    return Response::redirect($app->adminUrl()->childUrl('content'));
});

$app->router()->post($app->adminUrl()->childUrl('content/{id}/draft'), function ($request, array $params) use (
    $app,
    $contentRepository,
    $contentRouteId,
    $contentService,
    $contentRequireAdmin,
    $contentValidateCsrf
): Response {
    $user = $contentRequireAdmin($request, ['content.publish']);

    if ($user instanceof Response) {
        return $user;
    }

    $csrfResponse = $contentValidateCsrf($request);

    if ($csrfResponse) {
        return $csrfResponse;
    }

    $id = $contentRouteId($params['id'] ?? null);

    if ($id === null || !$contentRepository->findById($id)) {
        return $app->adminErrors()->response($request, 404);
    }

    try {
        $contentService->draft($id);
    } catch (InvalidArgumentException $exception) {
        return $app->adminErrors()->response($request, 422);
    } catch (ContentWriteException) {
        return $app->adminErrors()->response($request, 503);
    }

    return Response::redirect($app->adminUrl()->childUrl('content'));
});

$app->router()->post($app->adminUrl()->childUrl('content/{id}/archive'), function ($request, array $params) use (
    $app,
    $contentRepository,
    $contentRouteId,
    $contentService,
    $contentRequireAdmin,
    $contentValidateCsrf
): Response {
    $user = $contentRequireAdmin($request, ['content.delete']);

    if ($user instanceof Response) {
        return $user;
    }

    $csrfResponse = $contentValidateCsrf($request);

    if ($csrfResponse) {
        return $csrfResponse;
    }

    $id = $contentRouteId($params['id'] ?? null);

    if ($id === null || !$contentRepository->findById($id)) {
        return $app->adminErrors()->response($request, 404);
    }

    try {
        $contentService->archive($id);
    } catch (InvalidArgumentException $exception) {
        return $app->adminErrors()->response($request, 422);
    } catch (ContentWriteException) {
        return $app->adminErrors()->response($request, 503);
    }

    return Response::redirect($app->adminUrl()->childUrl('content'));
});

$app->router()->post($app->adminUrl()->childUrl('content/{id}/restore'), function ($request, array $params) use (
    $app,
    $contentRepository,
    $contentRouteId,
    $contentService,
    $contentRequireAdmin,
    $contentValidateCsrf
): Response {
    $user = $contentRequireAdmin($request, ['content.delete']);

    if ($user instanceof Response) {
        return $user;
    }

    $csrfResponse = $contentValidateCsrf($request);

    if ($csrfResponse) {
        return $csrfResponse;
    }

    $id = $contentRouteId($params['id'] ?? null);

    if ($id === null || !$contentRepository->findById($id)) {
        return $app->adminErrors()->response($request, 404);
    }

    try {
        $contentService->restore($id);
    } catch (InvalidArgumentException $exception) {
        return $app->adminErrors()->response($request, 422);
    } catch (ContentWriteException) {
        return $app->adminErrors()->response($request, 503);
    }

    return Response::redirect($app->adminUrl()->childUrl('content'));
});

$app->router()->post($app->adminUrl()->childUrl('content/{id}'), function ($request, array $params) use (
    $app,
    $contentRepository,
    $contentRouteId,
    $contentService,
    $contentRequireAdmin,
    $contentRenderAdmin,
    $contentRenderView,
    $contentReadFormData,
    $contentSelectedTaxonomyFromRequest,
    $contentTaxonomyOptions,
    $contentToFormData,
    $contentValidationMessage,
    $contentValidateCsrf,
    $contentAdminBase
): Response {
    $user = $contentRequireAdmin($request, ['content.update']);

    if ($user instanceof Response) {
        return $user;
    }

    $csrfResponse = $contentValidateCsrf($request);

    if ($csrfResponse) {
        return $csrfResponse;
    }

    $id = $contentRouteId($params['id'] ?? null);
    $entry = $id !== null ? $contentRepository->findById($id) : null;

    if (!$entry) {
        return $app->adminErrors()->response($request, 404);
    }

    [$data, $errors] = $contentReadFormData($request, $user, $entry);
    $selectedTaxonomy = [];

    try {
        $selectedTaxonomy = $contentSelectedTaxonomyFromRequest($request);
    } catch (InvalidArgumentException $exception) {
        $errors[] = $contentValidationMessage($exception);
    }

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
        $expectedUpdatedAt = $request->input('expected_updated_at', '');
        $contentService->update(
            $entry->id(),
            $data,
            $selectedTaxonomy,
            is_string($expectedUpdatedAt) ? trim($expectedUpdatedAt) : ''
        );
    } catch (InvalidArgumentException $exception) {
        $content = $contentRenderView('form', [
            'adminBase' => $contentAdminBase,
            'formAction' => $app->adminUrl()->childUrl('content/' . $entry->id()),
            'formMode' => 'edit',
            'heading' => 'Edit Content',
            'submitLabel' => 'Save changes',
            'csrfToken' => $app->session()->csrfToken(),
            'canPublish' => $user->can('content.publish'),
            'errors' => [$contentValidationMessage($exception)],
            'content' => array_merge($contentToFormData($entry), $data),
            'taxonomy' => $contentTaxonomyOptions(),
            'selectedTaxonomy' => $selectedTaxonomy,
        ]);

        return $contentRenderAdmin('Edit Content', $content, $user, $request->path(), 422);
    } catch (ContentWriteException $exception) {
        return $app->adminErrors()->response($request, 503);
    }

    return Response::redirect($app->adminUrl()->childUrl('content'));
});
