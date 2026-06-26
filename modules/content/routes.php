<?php

use Copot\Core\Response;
use Copot\Core\ThemeException;
use Copot\Core\ViewException;

require_once __DIR__ . '/Services/Content.php';
require_once __DIR__ . '/Services/ContentRepository.php';
require_once __DIR__ . '/Services/Slugger.php';

$contentAdminPath = $app->config()->get('admin.path', 'admin');

if (!is_string($contentAdminPath) || !preg_match('/^[a-z0-9-]+$/', $contentAdminPath)) {
    throw new RuntimeException('Invalid admin path configuration.');
}

$contentAdminBase = '/' . $contentAdminPath;
$contentRepository = new ContentRepository($app->database());
$contentSlugger = new Slugger();

$app->adminNavigation()->add('Content', $contentAdminBase . '/content', [
    'content.create',
    'content.update',
    'content.delete',
    'content.publish',
]);

$contentRenderView = function (string $view, array $data = []): string {
    $file = __DIR__ . '/views/admin/' . $view . '.php';

    if (!is_file($file)) {
        throw new RuntimeException("Content admin view [{$view}] was not found.");
    }

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

$contentRenderAdmin = function (string $title, string $content, $user, int $status = 200) use ($app, $contentAdminBase): Response {
    return Response::html($app->view()->render('admin/layout', [
        'title' => $title,
        'appName' => $app->config()->get('app.name', 'Copot'),
        'adminPath' => $contentAdminBase,
        'csrfToken' => $app->session()->csrfToken(),
        'userName' => $user->name(),
        'userEmail' => $user->email(),
        'navigation' => $app->adminNavigation()->itemsFor($user),
        'content' => $content,
    ]), $status);
};

$app->router()->get($contentAdminBase . '/content', function () use (
    $app,
    $contentRepository,
    $contentRequireAdmin,
    $contentRenderAdmin,
    $contentRenderView,
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

    $content = $contentRenderView('list', [
        'adminBase' => $contentAdminBase,
        'contents' => $contentRepository->paginate(50),
        'canCreate' => $user->can('content.create'),
        'canUpdate' => $user->can('content.update'),
        'canPublish' => $user->can('content.publish'),
        'canDelete' => $user->can('content.delete'),
        'csrfToken' => $app->session()->csrfToken(),
    ]);

    return $contentRenderAdmin('Content', $content, $user);
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

$app->router()->get($contentAdminBase . '/content/create', function () use (
    $app,
    $contentRequireAdmin,
    $contentRenderAdmin,
    $contentRenderView,
    $contentToFormData,
    $contentAdminBase
): Response {
    $user = $contentRequireAdmin(['content.create']);

    if ($user instanceof Response) {
        return $user;
    }

    $content = $contentRenderView('form', [
        'adminBase' => $contentAdminBase,
        'formAction' => $contentAdminBase . '/content',
        'formMode' => 'create',
        'heading' => 'Create Content',
        'submitLabel' => 'Create content',
        'csrfToken' => $app->session()->csrfToken(),
        'canPublish' => $user->can('content.publish'),
        'errors' => [],
        'content' => $contentToFormData(),
    ]);

    return $contentRenderAdmin('Create Content', $content, $user);
});

$app->router()->get($contentAdminBase . '/content/{id}/edit', function ($request, array $params) use (
    $app,
    $contentRepository,
    $contentRequireAdmin,
    $contentRenderAdmin,
    $contentRenderView,
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
        'formAction' => $contentAdminBase . '/content/' . $entry->id(),
        'formMode' => 'edit',
        'heading' => 'Edit Content',
        'submitLabel' => 'Save changes',
        'csrfToken' => $app->session()->csrfToken(),
        'canPublish' => $user->can('content.publish'),
        'errors' => [],
        'content' => $contentToFormData($entry),
    ]);

    return $contentRenderAdmin('Edit Content', $content, $user);
});

$app->router()->post($contentAdminBase . '/content', function ($request) use (
    $app,
    $contentRepository,
    $contentRequireAdmin,
    $contentRenderAdmin,
    $contentRenderView,
    $contentReadFormData,
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

    if ($errors !== []) {
        $content = $contentRenderView('form', [
            'adminBase' => $contentAdminBase,
            'formAction' => $contentAdminBase . '/content',
            'formMode' => 'create',
            'heading' => 'Create Content',
            'submitLabel' => 'Create content',
            'csrfToken' => $app->session()->csrfToken(),
            'canPublish' => $user->can('content.publish'),
            'errors' => $errors,
            'content' => array_merge($contentToFormData(), $data),
        ]);

        return $contentRenderAdmin('Create Content', $content, $user, 422);
    }

    try {
        $contentRepository->create($data);
    } catch (InvalidArgumentException $exception) {
        $content = $contentRenderView('form', [
            'adminBase' => $contentAdminBase,
            'formAction' => $contentAdminBase . '/content',
            'formMode' => 'create',
            'heading' => 'Create Content',
            'submitLabel' => 'Create content',
            'csrfToken' => $app->session()->csrfToken(),
            'canPublish' => $user->can('content.publish'),
            'errors' => [$exception->getMessage()],
            'content' => array_merge($contentToFormData(), $data),
        ]);

        return $contentRenderAdmin('Create Content', $content, $user, 422);
    }

    return Response::redirect($contentAdminBase . '/content');
});

$app->router()->post($contentAdminBase . '/content/{id}/publish', function ($request, array $params) use (
    $contentRepository,
    $contentRequireAdmin,
    $contentValidateCsrf,
    $contentAdminBase
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

    return Response::redirect($contentAdminBase . '/content');
});

$app->router()->post($contentAdminBase . '/content/{id}/draft', function ($request, array $params) use (
    $contentRepository,
    $contentRequireAdmin,
    $contentValidateCsrf,
    $contentAdminBase
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

    return Response::redirect($contentAdminBase . '/content');
});

$app->router()->post($contentAdminBase . '/content/{id}/archive', function ($request, array $params) use (
    $contentRepository,
    $contentRequireAdmin,
    $contentValidateCsrf,
    $contentAdminBase
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

    return Response::redirect($contentAdminBase . '/content');
});

$app->router()->post($contentAdminBase . '/content/{id}', function ($request, array $params) use (
    $app,
    $contentRepository,
    $contentRequireAdmin,
    $contentRenderAdmin,
    $contentRenderView,
    $contentReadFormData,
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

    if ($errors !== []) {
        $content = $contentRenderView('form', [
            'adminBase' => $contentAdminBase,
            'formAction' => $contentAdminBase . '/content/' . $entry->id(),
            'formMode' => 'edit',
            'heading' => 'Edit Content',
            'submitLabel' => 'Save changes',
            'csrfToken' => $app->session()->csrfToken(),
            'canPublish' => $user->can('content.publish'),
            'errors' => $errors,
            'content' => array_merge($contentToFormData($entry), $data),
        ]);

        return $contentRenderAdmin('Edit Content', $content, $user, 422);
    }

    try {
        $contentRepository->update($entry->id(), $data);
    } catch (InvalidArgumentException $exception) {
        $content = $contentRenderView('form', [
            'adminBase' => $contentAdminBase,
            'formAction' => $contentAdminBase . '/content/' . $entry->id(),
            'formMode' => 'edit',
            'heading' => 'Edit Content',
            'submitLabel' => 'Save changes',
            'csrfToken' => $app->session()->csrfToken(),
            'canPublish' => $user->can('content.publish'),
            'errors' => [$exception->getMessage()],
            'content' => array_merge($contentToFormData($entry), $data),
        ]);

        return $contentRenderAdmin('Edit Content', $content, $user, 422);
    }

    return Response::redirect($contentAdminBase . '/content');
});
