<?php

use Copot\Core\Response;

require_once __DIR__ . '/Services/TaxonomyType.php';
require_once __DIR__ . '/Services/TaxonomyTerm.php';
require_once __DIR__ . '/Services/TaxonomyRepository.php';
require_once __DIR__ . '/Services/TaxonomyAssignmentRepository.php';
require_once __DIR__ . '/Services/Slugger.php';

$taxonomyAdminPath = $app->config()->get('admin.path', 'admin');

if (!is_string($taxonomyAdminPath) || !preg_match('/^[a-z0-9-]+$/', $taxonomyAdminPath)) {
    throw new RuntimeException('Invalid admin path configuration.');
}

$taxonomyAdminBase = '/' . $taxonomyAdminPath;
$taxonomyRepository = new TaxonomyRepository($app->database());
$taxonomyAssignments = new TaxonomyAssignmentRepository($app->database());
$taxonomySlugger = new TaxonomySlugger();
$taxonomyAllowedTypes = ['category', 'tag'];
$taxonomyPermissions = [
    'taxonomy.create',
    'taxonomy.update',
    'taxonomy.delete',
];

$app->adminNavigation()->add('Taxonomy', $taxonomyAdminBase . '/taxonomy', $taxonomyPermissions);

$taxonomyUserCanAny = function ($user, array $permissions): bool {
    foreach ($permissions as $permission) {
        if ($user?->can($permission)) {
            return true;
        }
    }

    return false;
};

$taxonomyRequireAdmin = function (array $permissions) use ($app, $taxonomyAdminBase, $taxonomyUserCanAny) {
    if (!$app->auth()->check()) {
        return Response::redirect($taxonomyAdminBase);
    }

    $user = $app->auth()->user();

    if (!$user?->can('admin.access') || !$taxonomyUserCanAny($user, $permissions)) {
        return Response::html('403 Forbidden', 403);
    }

    return $user;
};

$taxonomyRenderView = function (string $view, array $data = []): string {
    $file = __DIR__ . '/views/admin/' . $view . '.php';

    if (!is_file($file)) {
        throw new RuntimeException("Taxonomy admin view [{$view}] was not found.");
    }

    extract($data, EXTR_SKIP);

    ob_start();
    require $file;

    return (string) ob_get_clean();
};

$taxonomyRenderAdmin = function (string $title, string $content, $user, int $status = 200) use ($app, $taxonomyAdminBase): Response {
    return Response::html($app->view()->render('admin/layout', [
        'title' => $title,
        'appName' => $app->config()->get('app.name', 'Copot'),
        'siteName' => $app->siteName(),
        'adminPath' => $taxonomyAdminBase,
        'csrfToken' => $app->csrf()->token(),
        'userName' => $user->name(),
        'userEmail' => $user->email(),
        'navigation' => $app->adminNavigation()->itemsFor($user),
        'content' => $content,
    ]), $status);
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
    return $app->csrf()->validateOrReject($request);
};

$app->router()->get($taxonomyAdminBase . '/taxonomy/{type}/{term_id}/edit', function ($request, array $params) use (
    $app,
    $taxonomyAdminBase,
    $taxonomyRequireAdmin,
    $taxonomyRenderAdmin,
    $taxonomyRenderView,
    $taxonomyResolveTerm,
    $taxonomyToFormData
): Response {
    $user = $taxonomyRequireAdmin(['taxonomy.update']);

    if ($user instanceof Response) {
        return $user;
    }

    [$type, $term] = $taxonomyResolveTerm((string) ($params['type'] ?? ''), (int) ($params['term_id'] ?? 0));

    if (!$type || !$term) {
        return Response::html('404 Not Found', 404);
    }

    $content = $taxonomyRenderView('form', [
        'adminBase' => $taxonomyAdminBase,
        'type' => $type,
        'formAction' => $taxonomyAdminBase . '/taxonomy/' . $type->slug() . '/' . $term->id(),
        'heading' => 'Edit ' . $type->name(),
        'submitLabel' => 'Save term',
        'csrfToken' => $app->csrf()->token(),
        'errors' => [],
        'term' => $taxonomyToFormData($term),
    ]);

    return $taxonomyRenderAdmin('Edit Taxonomy Term', $content, $user);
});

$app->router()->get($taxonomyAdminBase . '/taxonomy/{type}/create', function ($request, array $params) use (
    $app,
    $taxonomyAdminBase,
    $taxonomyRequireAdmin,
    $taxonomyRenderAdmin,
    $taxonomyRenderView,
    $taxonomyResolveType,
    $taxonomyToFormData
): Response {
    $user = $taxonomyRequireAdmin(['taxonomy.create']);

    if ($user instanceof Response) {
        return $user;
    }

    $type = $taxonomyResolveType((string) ($params['type'] ?? ''));

    if (!$type) {
        return Response::html('404 Not Found', 404);
    }

    $content = $taxonomyRenderView('form', [
        'adminBase' => $taxonomyAdminBase,
        'type' => $type,
        'formAction' => $taxonomyAdminBase . '/taxonomy/' . $type->slug(),
        'heading' => 'Create ' . $type->name(),
        'submitLabel' => 'Create term',
        'csrfToken' => $app->csrf()->token(),
        'errors' => [],
        'term' => $taxonomyToFormData(),
    ]);

    return $taxonomyRenderAdmin('Create Taxonomy Term', $content, $user);
});

$app->router()->get($taxonomyAdminBase . '/taxonomy', function () use (
    $taxonomyAllowedTypes,
    $taxonomyPermissions,
    $taxonomyRepository,
    $taxonomyRequireAdmin,
    $taxonomyRenderAdmin,
    $taxonomyRenderView,
    $taxonomyAdminBase
): Response {
    $user = $taxonomyRequireAdmin($taxonomyPermissions);

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

    return $taxonomyRenderAdmin('Taxonomy', $content, $user);
});

$app->router()->get($taxonomyAdminBase . '/taxonomy/{type}', function ($request, array $params) use (
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
    $user = $taxonomyRequireAdmin($taxonomyPermissions);

    if ($user instanceof Response) {
        return $user;
    }

    $type = $taxonomyResolveType((string) ($params['type'] ?? ''));

    if (!$type) {
        return Response::html('404 Not Found', 404);
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

    return $taxonomyRenderAdmin($type->name(), $content, $user);
});

$app->router()->post($taxonomyAdminBase . '/taxonomy/{type}/{term_id}/delete', function ($request, array $params) use (
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

    $user = $taxonomyRequireAdmin(['taxonomy.delete']);

    if ($user instanceof Response) {
        return $user;
    }

    [$type, $term] = $taxonomyResolveTerm((string) ($params['type'] ?? ''), (int) ($params['term_id'] ?? 0));

    if (!$type || !$term) {
        return Response::html('404 Not Found', 404);
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

        return $taxonomyRenderAdmin($type->name(), $content, $user, 409);
    }

    return Response::redirect($taxonomyAdminBase . '/taxonomy/' . $type->slug());
});

$app->router()->post($taxonomyAdminBase . '/taxonomy/{type}/{term_id}', function ($request, array $params) use (
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

    $user = $taxonomyRequireAdmin(['taxonomy.update']);

    if ($user instanceof Response) {
        return $user;
    }

    [$type, $term] = $taxonomyResolveTerm((string) ($params['type'] ?? ''), (int) ($params['term_id'] ?? 0));

    if (!$type || !$term) {
        return Response::html('404 Not Found', 404);
    }

    [$data, $errors] = $taxonomyReadFormData($request, $type, $term);

    if ($errors !== []) {
        $content = $taxonomyRenderView('form', [
            'adminBase' => $taxonomyAdminBase,
            'type' => $type,
            'formAction' => $taxonomyAdminBase . '/taxonomy/' . $type->slug() . '/' . $term->id(),
            'heading' => 'Edit ' . $type->name(),
            'submitLabel' => 'Save term',
            'csrfToken' => $app->csrf()->token(),
            'errors' => $errors,
            'term' => array_merge($taxonomyToFormData($term), $data),
        ]);

        return $taxonomyRenderAdmin('Edit Taxonomy Term', $content, $user, 422);
    }

    try {
        $taxonomyRepository->updateTerm($term->id(), $data);
    } catch (InvalidArgumentException $exception) {
        $content = $taxonomyRenderView('form', [
            'adminBase' => $taxonomyAdminBase,
            'type' => $type,
            'formAction' => $taxonomyAdminBase . '/taxonomy/' . $type->slug() . '/' . $term->id(),
            'heading' => 'Edit ' . $type->name(),
            'submitLabel' => 'Save term',
            'csrfToken' => $app->csrf()->token(),
            'errors' => [$exception->getMessage()],
            'term' => array_merge($taxonomyToFormData($term), $data),
        ]);

        return $taxonomyRenderAdmin('Edit Taxonomy Term', $content, $user, 422);
    }

    return Response::redirect($taxonomyAdminBase . '/taxonomy/' . $type->slug());
});

$app->router()->post($taxonomyAdminBase . '/taxonomy/{type}', function ($request, array $params) use (
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

    $user = $taxonomyRequireAdmin(['taxonomy.create']);

    if ($user instanceof Response) {
        return $user;
    }

    $type = $taxonomyResolveType((string) ($params['type'] ?? ''));

    if (!$type) {
        return Response::html('404 Not Found', 404);
    }

    [$data, $errors] = $taxonomyReadFormData($request, $type);

    if ($errors !== []) {
        $content = $taxonomyRenderView('form', [
            'adminBase' => $taxonomyAdminBase,
            'type' => $type,
            'formAction' => $taxonomyAdminBase . '/taxonomy/' . $type->slug(),
            'heading' => 'Create ' . $type->name(),
            'submitLabel' => 'Create term',
            'csrfToken' => $app->csrf()->token(),
            'errors' => $errors,
            'term' => array_merge($taxonomyToFormData(), $data),
        ]);

        return $taxonomyRenderAdmin('Create Taxonomy Term', $content, $user, 422);
    }

    try {
        $taxonomyRepository->createTerm($data);
    } catch (InvalidArgumentException $exception) {
        $content = $taxonomyRenderView('form', [
            'adminBase' => $taxonomyAdminBase,
            'type' => $type,
            'formAction' => $taxonomyAdminBase . '/taxonomy/' . $type->slug(),
            'heading' => 'Create ' . $type->name(),
            'submitLabel' => 'Create term',
            'csrfToken' => $app->csrf()->token(),
            'errors' => [$exception->getMessage()],
            'term' => array_merge($taxonomyToFormData(), $data),
        ]);

        return $taxonomyRenderAdmin('Create Taxonomy Term', $content, $user, 422);
    }

    return Response::redirect($taxonomyAdminBase . '/taxonomy/' . $type->slug());
});
