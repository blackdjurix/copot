<?php

use Copot\Core\Response;

require_once __DIR__ . '/Services/RedirectExceptions.php';
require_once __DIR__ . '/Services/Redirect.php';
require_once __DIR__ . '/Services/RedirectRepository.php';
require_once __DIR__ . '/Services/RedirectService.php';

$redirectRepository = new RedirectRepository($app->database());
$redirectAdminUrl = $app->adminUrl();
$redirectAdminBase = $redirectAdminUrl->baseUrl();
$redirectPath = $redirectAdminUrl->childUrl('redirects');
$redirectService = new RedirectService($app->database(), $redirectRepository, $redirectAdminBase);

$app->adminNavigation()->addRequired('Redirects', $redirectPath, ['admin.access', 'redirects.manage'], 'link', 35);

$redirectRequireAdmin = static function ($request) use ($app, $redirectAdminBase) {
    if (!$app->auth()->check()) {
        return Response::redirect($redirectAdminBase);
    }

    $user = $app->auth()->user();

    if (!$user?->can('admin.access') || !$user->can('redirects.manage')) {
        return $app->adminErrors()->response($request, 403);
    }

    return $user;
};

$redirectValidateCsrf = static function ($request) use ($app): ?Response {
    return $app->csrf()->validateOrReject($request) instanceof Response
        ? $app->adminErrors()->response($request, 419)
        : null;
};

$redirectRenderView = static function (string $view, array $data = []): string {
    if (!in_array($view, ['list', 'form'], true)) {
        throw new RuntimeException('Redirect Admin view is unavailable.');
    }

    $file = __DIR__ . '/views/admin/' . $view . '.php';
    if (!is_file($file)) {
        throw new RuntimeException('Redirect Admin view is unavailable.');
    }

    extract($data, EXTR_SKIP);
    ob_start();
    try {
        require $file;
        return (string) ob_get_clean();
    } catch (Throwable $failure) {
        ob_end_clean();
        throw $failure;
    }
};

$redirectRenderAdmin = static function (string $title, string $content, $user, string $path, int $status = 200) use ($app): Response {
    return Response::html(
        $app->adminPageRenderer()->render($title, $content, $user, $app->csrf()->token(), $path),
        $status
    );
};

$redirectMessage = static function (Throwable $failure): string {
    if ($failure instanceof RedirectStaleWriteException) {
        return 'This Redirect was changed elsewhere. Reload it before saving or deleting.';
    }

    if ($failure instanceof RedirectNotFoundException) {
        return 'The requested Redirect no longer exists.';
    }

    if ($failure instanceof InvalidArgumentException) {
        $message = $failure->getMessage();

        return match (true) {
            str_contains($message, 'source') || str_contains($message, 'reserved') => 'The source path is invalid, reserved, or already in use.',
            str_contains($message, 'target') => 'The target is invalid or would create a managed Redirect chain.',
            str_contains($message, 'status') => 'The status must be 301 or 302.',
            str_contains($message, 'exist') => 'The requested Redirect no longer exists.',
            default => 'The Redirect data could not be accepted.',
        };
    }

    return 'The Redirect could not be saved. Please try again.';
};

$redirectFormData = static function (?Redirect $redirect = null): array {
    return $redirect === null
        ? ['id' => null, 'source_path' => '', 'target' => '', 'status_code' => 302, 'updated_at' => null]
        : $redirect->toArray();
};

$redirectFormResponse = static function ($request, $user, array $data, array $errors, string $mode, int $status = 422) use ($redirectRenderView, $redirectRenderAdmin, $redirectPath, $app): Response {
    $content = $redirectRenderView('form', [
        'formAction' => $mode === 'edit' ? $app->adminUrl()->childUrl('redirects/' . (int) $data['id'] . '/edit') : $redirectPath,
        'formMode' => $mode,
        'heading' => $mode === 'edit' ? 'Edit Redirect' : 'Create Redirect',
        'submitLabel' => $mode === 'edit' ? 'Save changes' : 'Create Redirect',
        'errors' => $errors,
        'redirect' => $data,
        'csrfToken' => $app->csrf()->token(),
        'adminUrl' => static fn (string $path = ''): string => $app->adminUrl()->childUrl($path),
    ]);

    return $redirectRenderAdmin($mode === 'edit' ? 'Edit Redirect' : 'Create Redirect', $content, $user, $request->path(), $status);
};

$app->router()->get($redirectPath, static function ($request) use ($redirectRequireAdmin, $redirectRepository, $redirectRenderView, $redirectRenderAdmin, $redirectAdminUrl): Response {
    $user = $redirectRequireAdmin($request);
    if ($user instanceof Response) {
        return $user;
    }

    try {
        $content = $redirectRenderView('list', [
            'redirects' => $redirectRepository->all(),
            'adminUrl' => static fn (string $path = ''): string => $redirectAdminUrl->childUrl($path),
            'workspaceUrl' => $redirectAdminUrl->childUrl('redirects'),
            'csrfToken' => $request->input('_token', ''),
            'notice' => $request->input('notice'),
            'error' => $request->input('error'),
        ]);

        return $redirectRenderAdmin('Redirects', $content, $user, $request->path());
    } catch (Throwable) {
        return $redirectRenderAdmin('Redirects', '<div class="admin-alert admin-alert--danger" role="alert">Redirects are temporarily unavailable.</div>', $user, $request->path(), 503);
    }
});

$app->router()->get($redirectPath . '/create', static function ($request) use ($redirectRequireAdmin, $redirectFormResponse): Response {
    $user = $redirectRequireAdmin($request);
    if ($user instanceof Response) {
        return $user;
    }

    return $redirectFormResponse($request, $user, ['id' => null, 'source_path' => '', 'target' => '', 'status_code' => 302, 'updated_at' => null], [], 'create', 200);
});

$app->router()->post($redirectPath, static function ($request) use ($redirectRequireAdmin, $redirectValidateCsrf, $redirectService, $redirectFormResponse, $redirectPath): Response {
    $user = $redirectRequireAdmin($request);
    if ($user instanceof Response) {
        return $user;
    }

    $csrf = $redirectValidateCsrf($request);
    if ($csrf instanceof Response) {
        return $csrf;
    }

    $data = [
        'id' => null,
        'source_path' => is_scalar($request->input('source_path', '')) ? (string) $request->input('source_path', '') : '',
        'target' => is_scalar($request->input('target', '')) ? (string) $request->input('target', '') : '',
        'status_code' => is_scalar($request->input('status_code', 302)) ? $request->input('status_code', 302) : '',
        'updated_at' => null,
    ];

    try {
        $redirectService->create($data);
        return Response::redirect($redirectPath . '?notice=created');
    } catch (InvalidArgumentException $failure) {
        return $redirectFormResponse($request, $user, $data, [$failure->getMessage()], 'create');
    } catch (Throwable) {
        return $redirectFormResponse($request, $user, $data, ['The Redirect could not be saved. Please try again.'], 'create');
    }
});

$app->router()->get($redirectPath . '/{id}/edit', static function ($request, array $params) use ($app, $redirectRequireAdmin, $redirectService, $redirectFormResponse): Response {
    $user = $redirectRequireAdmin($request);
    if ($user instanceof Response) {
        return $user;
    }

    $id = preg_match('/^[1-9][0-9]*$/', (string) ($params['id'] ?? '')) ? (int) $params['id'] : 0;
    try {
        $redirect = $id > 0 ? $redirectService->findById($id) : null;
    } catch (Throwable) {
        return $app->adminErrors()->response($request, 503);
    }
    if (!$redirect instanceof Redirect) {
        return $app->adminErrors()->response($request, 404);
    }

    return $redirectFormResponse($request, $user, $redirect->toArray(), [], 'edit', 200);
});

$app->router()->post($redirectPath . '/{id}/edit', static function ($request, array $params) use ($app, $redirectRequireAdmin, $redirectValidateCsrf, $redirectService, $redirectFormResponse, $redirectMessage, $redirectPath): Response {
    $user = $redirectRequireAdmin($request);
    if ($user instanceof Response) {
        return $user;
    }

    $csrf = $redirectValidateCsrf($request);
    if ($csrf instanceof Response) {
        return $csrf;
    }

    $id = preg_match('/^[1-9][0-9]*$/', (string) ($params['id'] ?? '')) ? (int) $params['id'] : 0;
    try {
        $current = $id > 0 ? $redirectService->findById($id) : null;
    } catch (Throwable) {
        return $app->adminErrors()->response($request, 503);
    }
    if (!$current instanceof Redirect) {
        return $app->adminErrors()->response($request, 404);
    }

    $data = [
        'id' => $id,
        'source_path' => is_scalar($request->input('source_path', '')) ? (string) $request->input('source_path', '') : '',
        'target' => is_scalar($request->input('target', '')) ? (string) $request->input('target', '') : '',
        'status_code' => is_scalar($request->input('status_code', 302)) ? $request->input('status_code', 302) : '',
        'updated_at' => $current->updatedAt(),
    ];
    $expected = $request->input('expected_updated_at', '');

    try {
        $redirectService->update($id, $data, is_scalar($expected) ? (string) $expected : '');
        return Response::redirect($redirectPath . '?notice=updated');
    } catch (InvalidArgumentException $failure) {
        return $redirectFormResponse($request, $user, $data, [$redirectMessage($failure)], 'edit');
    } catch (RedirectStaleWriteException $failure) {
        return $redirectFormResponse($request, $user, $data, [$redirectMessage($failure)], 'edit');
    } catch (Throwable) {
        return $redirectFormResponse($request, $user, $data, ['The Redirect could not be saved. Please try again.'], 'edit');
    }
});

$app->router()->post($redirectPath . '/{id}/delete', static function ($request, array $params) use ($redirectRequireAdmin, $redirectValidateCsrf, $redirectService, $redirectPath, $redirectMessage, $app): Response {
    $user = $redirectRequireAdmin($request);
    if ($user instanceof Response) {
        return $user;
    }

    $csrf = $redirectValidateCsrf($request);
    if ($csrf instanceof Response) {
        return $csrf;
    }

    $id = preg_match('/^[1-9][0-9]*$/', (string) ($params['id'] ?? '')) ? (int) $params['id'] : 0;
    try {
        $current = $id > 0 ? $redirectService->findById($id) : null;
    } catch (Throwable) {
        return $app->adminErrors()->response($request, 503);
    }
    if (!$current instanceof Redirect) {
        return $app->adminErrors()->response($request, 404);
    }

    try {
        $redirectService->delete($id, $current->updatedAt());
        return Response::redirect($redirectPath . '?notice=deleted');
    } catch (RedirectStaleWriteException $failure) {
        return Response::redirect($redirectPath . '?error=' . rawurlencode($redirectMessage($failure)));
    } catch (Throwable) {
        return $app->adminErrors()->response($request, 503);
    }
});
