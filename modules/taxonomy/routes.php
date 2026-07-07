<?php

use Copot\Core\Response;

require_once __DIR__ . '/Services/TaxonomyType.php';
require_once __DIR__ . '/Services/TaxonomyTerm.php';
require_once __DIR__ . '/Services/TaxonomyRepository.php';
require_once __DIR__ . '/Services/TaxonomyAssignmentRepository.php';
require_once __DIR__ . '/Services/Slugger.php';

$taxonomyAdminBase = $app->adminUrl()->baseUrl();
$taxonomyRepository = new TaxonomyRepository($app->database());
$taxonomyAssignments = new TaxonomyAssignmentRepository($app->database());
$taxonomySlugger = new TaxonomySlugger();
$taxonomyAdminUrlService = $app->adminUrl();
$taxonomyAdminUrl = static function (string $path = '') use ($taxonomyAdminUrlService): string {
    return $taxonomyAdminUrlService->childUrl($path);
};
$taxonomyAllowedTypes = ['category', 'tag'];
$taxonomyPermissions = [
    'taxonomy.create',
    'taxonomy.update',
    'taxonomy.delete',
];

$app->adminNavigation()->add('Taxonomy', $app->adminUrl()->childUrl('taxonomy'), $taxonomyPermissions);

$app->adminDashboard()->add(
    'taxonomy.overview',
    'Taxonomy',
    'Manage classification types and the terms assigned to content.',
    $app->adminUrl()->childUrl('taxonomy'),
    $taxonomyPermissions,
    300
);

$taxonomyUserCanAny = function ($user, array $permissions): bool {
    foreach ($permissions as $permission) {
        if ($user?->can($permission)) {
            return true;
        }
    }

    return false;
};

$taxonomyRequireAdmin = function ($request, array $permissions) use ($app, $taxonomyAdminBase, $taxonomyUserCanAny) {
    if (!$app->auth()->check()) {
        return Response::redirect($taxonomyAdminBase);
    }

    $user = $app->auth()->user();

    if (!$user?->can('admin.access') || !$taxonomyUserCanAny($user, $permissions)) {
        return $app->adminErrors()->response($request, 403);
    }

    return $user;
};

$taxonomyRenderView = function (string $view, array $data = []) use ($taxonomyAdminUrl): string {
    $file = __DIR__ . '/views/admin/' . $view . '.php';

    if (!is_file($file)) {
        throw new RuntimeException("Taxonomy admin view [{$view}] was not found.");
    }

    $data['adminUrl'] = $taxonomyAdminUrl;

    extract($data, EXTR_SKIP);

    $initialOutputLevel = ob_get_level();

    if (!@ob_start()) {
        throw new RuntimeException('Taxonomy admin view output buffer is unavailable.');
    }

    try {
        require $file;

        if (ob_get_level() !== $initialOutputLevel + 1) {
            throw new RuntimeException('Taxonomy admin view output buffer state is invalid.');
        }

        $rendered = @ob_get_clean();

        if (!is_string($rendered)) {
            throw new RuntimeException('Taxonomy admin view output buffer could not be read.');
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

$taxonomyRenderAdmin = function (
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
        $app->csrf()->token(),
        $currentPath
    ), $status);
};

$taxonomyTypeAllowed = function (string $typeSlug) use ($taxonomyAllowedTypes): bool {
    return in_array($typeSlug, $taxonomyAllowedTypes, true);
};

$taxonomyResolveType = function (string $typeSlug) use ($taxonomyRepository, $taxonomyTypeAllowed): ?TaxonomyType {
    $typeSlug = trim($typeSlug);

    if (!$taxonomyTypeAllowed($typeSlug)) {
        return null;
    }

    return $taxonomyRepository->findTypeBySlug($typeSlug);
};

$taxonomyResolveTerm = function (string $typeSlug, int $termId) use ($taxonomyRepository, $taxonomyResolveType): array {
    $type = $taxonomyResolveType($typeSlug);

    if (!$type || $termId <= 0) {
        return [null, null];
    }

    $term = $taxonomyRepository->findTermById($termId);

    if (!$term || $term->taxonomyTypeId() !== $type->id()) {
        return [$type, null];
    }

    return [$type, $term];
};

$taxonomyToFormData = function (?TaxonomyTerm $term = null): array {
    if (!$term) {
        return [
            'id' => null,
            'name' => '',
            'slug' => '',
            'description' => '',
            'sort_order' => 0,
        ];
    }

    return [
        'id' => $term->id(),
        'name' => $term->name(),
        'slug' => $term->slug(),
        'description' => $term->description() ?? '',
        'sort_order' => $term->sortOrder(),
    ];
};

$taxonomyReadFormData = function ($request, TaxonomyType $type, ?TaxonomyTerm $existing = null) use ($taxonomyRepository, $taxonomySlugger): array {
    $data = [
        'taxonomy_type_id' => $type->id(),
        'parent_id' => null,
        'name' => trim((string) $request->input('name', '')),
        'slug' => trim((string) $request->input('slug', '')),
        'description' => (string) $request->input('description', ''),
        'sort_order' => $request->input('sort_order', 0),
    ];
    $errors = [];

    if ($data['name'] === '') {
        $errors[] = 'Name is required.';
    }

    if ($data['sort_order'] === '' || !is_numeric($data['sort_order'])) {
        $errors[] = 'Sort order must be numeric.';
        $data['sort_order'] = 0;
    }

    try {
        $data['slug'] = $taxonomySlugger->unique(
            $data['slug'] !== '' ? $data['slug'] : $data['name'],
            $taxonomyRepository,
            $type->id(),
            $existing?->id()
        );
    } catch (InvalidArgumentException $exception) {
        $errors[] = $exception->getMessage();
    }

    return [$data, $errors];
};

$taxonomyValidateCsrf = function ($request) use ($app): ?Response {
    $csrfResponse = $app->csrf()->validateOrReject($request);

    return $csrfResponse instanceof Response
        ? $app->adminErrors()->response($request, 419)
        : null;
};

$app->router()->get($app->adminUrl()->childUrl('taxonomy/{type}/{term_id}/edit'), function ($request, array $params) use (
    $app,
    $taxonomyAdminBase,
    $taxonomyRequireAdmin,
    $taxonomyRenderAdmin,
    $taxonomyRenderView,
    $taxonomyResolveTerm,
    $taxonomyToFormData
): Response {
    $user = $taxonomyRequireAdmin($request, ['taxonomy.update']);

    if ($user instanceof Response) {
        return $user;
    }

    [$type, $term] = $taxonomyResolveTerm((string) ($params['type'] ?? ''), (int) ($params['term_id'] ?? 0));

    if (!$type || !$term) {
        return $app->adminErrors()->response($request, 404);
    }

    $content = $taxonomyRenderView('form', [
        'adminBase' => $taxonomyAdminBase,
        'type' => $type,
        'formAction' => $app->adminUrl()->childUrl('taxonomy/' . $type->slug() . '/' . $term->id()),
        'heading' => 'Edit ' . $type->name(),
        'submitLabel' => 'Save term',
        'csrfToken' => $app->csrf()->token(),
        'errors' => [],
        'term' => $taxonomyToFormData($term),
    ]);

    return $taxonomyRenderAdmin('Edit Taxonomy Term', $content, $user, $request->path());
});

$app->router()->get($app->adminUrl()->childUrl('taxonomy/{type}/create'), function ($request, array $params) use (
    $app,
    $taxonomyAdminBase,
    $taxonomyRequireAdmin,
    $taxonomyRenderAdmin,
    $taxonomyRenderView,
    $taxonomyResolveType,
    $taxonomyToFormData
): Response {
    $user = $taxonomyRequireAdmin($request, ['taxonomy.create']);

    if ($user instanceof Response) {
        return $user;
    }

    $type = $taxonomyResolveType((string) ($params['type'] ?? ''));

    if (!$type) {
        return $app->adminErrors()->response($request, 404);
    }

    $content = $taxonomyRenderView('form', [
        'adminBase' => $taxonomyAdminBase,
        'type' => $type,
        'formAction' => $app->adminUrl()->childUrl('taxonomy/' . $type->slug()),
        'heading' => 'Create ' . $type->name(),
        'submitLabel' => 'Create term',
        'csrfToken' => $app->csrf()->token(),
        'errors' => [],
        'term' => $taxonomyToFormData(),
    ]);

    return $taxonomyRenderAdmin('Create Taxonomy Term', $content, $user, $request->path());
});

$app->router()->get($app->adminUrl()->childUrl('taxonomy'), function ($request) use (
    $taxonomyAllowedTypes,
    $taxonomyPermissions,
    $taxonomyRepository,
    $taxonomyRequireAdmin,
    $taxonomyRenderAdmin,
    $taxonomyRenderView,
    $taxonomyAdminBase
): Response {
    $user = $taxonomyRequireAdmin($request, $taxonomyPermissions);

    if ($user instanceof Response) {
        return $user;
    }

    $types = array_filter(
        $taxonomyRepository->allTypes(),
        fn (TaxonomyType $type): bool => in_array($type->slug(), $taxonomyAllowedTypes, true)
    );

    $content = $taxonomyRenderView('types', [
        'adminBase' => $taxonomyAdminBase,
        'types' => $types,
        'canCreate' => $user->can('taxonomy.create'),
        'canUpdate' => $user->can('taxonomy.update'),
        'canDelete' => $user->can('taxonomy.delete'),
    ]);

    return $taxonomyRenderAdmin('Taxonomy', $content, $user, $request->path());
});

$app->router()->get($app->adminUrl()->childUrl('taxonomy/{type}'), function ($request, array $params) use (
    $app,
    $taxonomyAssignments,
    $taxonomyRepository,
    $taxonomyPermissions,
    $taxonomyRequireAdmin,
    $taxonomyRenderAdmin,
    $taxonomyRenderView,
    $taxonomyResolveType,
    $taxonomyAdminBase
): Response {
    $user = $taxonomyRequireAdmin($request, $taxonomyPermissions);

    if ($user instanceof Response) {
        return $user;
    }

    $type = $taxonomyResolveType((string) ($params['type'] ?? ''));

    if (!$type) {
        return $app->adminErrors()->response($request, 404);
    }

    $terms = $taxonomyRepository->termsByType($type->slug());
    $usageCounts = [];

    foreach ($terms as $term) {
        $usageCounts[$term->id()] = $taxonomyAssignments->usageCount($term->id());
    }

    $content = $taxonomyRenderView('terms', [
        'adminBase' => $taxonomyAdminBase,
        'type' => $type,
        'terms' => $terms,
        'usageCounts' => $usageCounts,
        'canCreate' => $user->can('taxonomy.create'),
        'canUpdate' => $user->can('taxonomy.update'),
        'canDelete' => $user->can('taxonomy.delete'),
        'csrfToken' => $app->csrf()->token(),
        'error' => null,
    ]);

    return $taxonomyRenderAdmin($type->name(), $content, $user, $request->path());
});

$app->router()->post($app->adminUrl()->childUrl('taxonomy/{type}/{term_id}/delete'), function ($request, array $params) use (
    $app,
    $taxonomyAssignments,
    $taxonomyRepository,
    $taxonomyRequireAdmin,
    $taxonomyRenderAdmin,
    $taxonomyRenderView,
    $taxonomyResolveTerm,
    $taxonomyValidateCsrf,
    $taxonomyAdminBase
): Response {
    $csrfResponse = $taxonomyValidateCsrf($request);

    if ($csrfResponse) {
        return $csrfResponse;
    }

    $user = $taxonomyRequireAdmin($request, ['taxonomy.delete']);

    if ($user instanceof Response) {
        return $user;
    }

    [$type, $term] = $taxonomyResolveTerm((string) ($params['type'] ?? ''), (int) ($params['term_id'] ?? 0));

    if (!$type || !$term) {
        return $app->adminErrors()->response($request, 404);
    }

    try {
        $taxonomyRepository->deleteTermIfUnused($term->id(), $taxonomyAssignments);
    } catch (RuntimeException $exception) {
        $terms = $taxonomyRepository->termsByType($type->slug());
        $usageCounts = [];

        foreach ($terms as $existingTerm) {
            $usageCounts[$existingTerm->id()] = $taxonomyAssignments->usageCount($existingTerm->id());
        }

        $content = $taxonomyRenderView('terms', [
            'adminBase' => $taxonomyAdminBase,
            'type' => $type,
            'terms' => $terms,
            'usageCounts' => $usageCounts,
            'canCreate' => $user->can('taxonomy.create'),
            'canUpdate' => $user->can('taxonomy.update'),
            'canDelete' => $user->can('taxonomy.delete'),
            'csrfToken' => $app->csrf()->token(),
            'error' => $exception->getMessage(),
        ]);

        return $taxonomyRenderAdmin($type->name(), $content, $user, $request->path(), 409);
    }

    return Response::redirect($app->adminUrl()->childUrl('taxonomy/' . $type->slug()));
});

$app->router()->post($app->adminUrl()->childUrl('taxonomy/{type}/{term_id}'), function ($request, array $params) use (
    $app,
    $taxonomyRepository,
    $taxonomyRequireAdmin,
    $taxonomyRenderAdmin,
    $taxonomyRenderView,
    $taxonomyResolveTerm,
    $taxonomyReadFormData,
    $taxonomyToFormData,
    $taxonomyValidateCsrf,
    $taxonomyAdminBase
): Response {
    $csrfResponse = $taxonomyValidateCsrf($request);

    if ($csrfResponse) {
        return $csrfResponse;
    }

    $user = $taxonomyRequireAdmin($request, ['taxonomy.update']);

    if ($user instanceof Response) {
        return $user;
    }

    [$type, $term] = $taxonomyResolveTerm((string) ($params['type'] ?? ''), (int) ($params['term_id'] ?? 0));

    if (!$type || !$term) {
        return $app->adminErrors()->response($request, 404);
    }

    [$data, $errors] = $taxonomyReadFormData($request, $type, $term);

    if ($errors !== []) {
        $content = $taxonomyRenderView('form', [
            'adminBase' => $taxonomyAdminBase,
            'type' => $type,
            'formAction' => $app->adminUrl()->childUrl('taxonomy/' . $type->slug() . '/' . $term->id()),
            'heading' => 'Edit ' . $type->name(),
            'submitLabel' => 'Save term',
            'csrfToken' => $app->csrf()->token(),
            'errors' => $errors,
            'term' => array_merge($taxonomyToFormData($term), $data),
        ]);

        return $taxonomyRenderAdmin('Edit Taxonomy Term', $content, $user, $request->path(), 422);
    }

    try {
        $taxonomyRepository->updateTerm($term->id(), $data);
    } catch (InvalidArgumentException $exception) {
        $content = $taxonomyRenderView('form', [
            'adminBase' => $taxonomyAdminBase,
            'type' => $type,
            'formAction' => $app->adminUrl()->childUrl('taxonomy/' . $type->slug() . '/' . $term->id()),
            'heading' => 'Edit ' . $type->name(),
            'submitLabel' => 'Save term',
            'csrfToken' => $app->csrf()->token(),
            'errors' => [$exception->getMessage()],
            'term' => array_merge($taxonomyToFormData($term), $data),
        ]);

        return $taxonomyRenderAdmin('Edit Taxonomy Term', $content, $user, $request->path(), 422);
    }

    return Response::redirect($app->adminUrl()->childUrl('taxonomy/' . $type->slug()));
});

$app->router()->post($app->adminUrl()->childUrl('taxonomy/{type}'), function ($request, array $params) use (
    $app,
    $taxonomyRepository,
    $taxonomyRequireAdmin,
    $taxonomyRenderAdmin,
    $taxonomyRenderView,
    $taxonomyResolveType,
    $taxonomyReadFormData,
    $taxonomyToFormData,
    $taxonomyValidateCsrf,
    $taxonomyAdminBase
): Response {
    $csrfResponse = $taxonomyValidateCsrf($request);

    if ($csrfResponse) {
        return $csrfResponse;
    }

    $user = $taxonomyRequireAdmin($request, ['taxonomy.create']);

    if ($user instanceof Response) {
        return $user;
    }

    $type = $taxonomyResolveType((string) ($params['type'] ?? ''));

    if (!$type) {
        return $app->adminErrors()->response($request, 404);
    }

    [$data, $errors] = $taxonomyReadFormData($request, $type);

    if ($errors !== []) {
        $content = $taxonomyRenderView('form', [
            'adminBase' => $taxonomyAdminBase,
            'type' => $type,
            'formAction' => $app->adminUrl()->childUrl('taxonomy/' . $type->slug()),
            'heading' => 'Create ' . $type->name(),
            'submitLabel' => 'Create term',
            'csrfToken' => $app->csrf()->token(),
            'errors' => $errors,
            'term' => array_merge($taxonomyToFormData(), $data),
        ]);

        return $taxonomyRenderAdmin('Create Taxonomy Term', $content, $user, $request->path(), 422);
    }

    try {
        $taxonomyRepository->createTerm($data);
    } catch (InvalidArgumentException $exception) {
        $content = $taxonomyRenderView('form', [
            'adminBase' => $taxonomyAdminBase,
            'type' => $type,
            'formAction' => $app->adminUrl()->childUrl('taxonomy/' . $type->slug()),
            'heading' => 'Create ' . $type->name(),
            'submitLabel' => 'Create term',
            'csrfToken' => $app->csrf()->token(),
            'errors' => [$exception->getMessage()],
            'term' => array_merge($taxonomyToFormData(), $data),
        ]);

        return $taxonomyRenderAdmin('Create Taxonomy Term', $content, $user, $request->path(), 422);
    }

    return Response::redirect($app->adminUrl()->childUrl('taxonomy/' . $type->slug()));
});
