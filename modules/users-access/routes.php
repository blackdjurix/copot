<?php

use Copot\Core\PasswordHasher;
use Copot\Core\PermissionChecker;
use Copot\Core\Response;

require_once __DIR__ . '/Services/ManagedUser.php';
require_once __DIR__ . '/Services/UsersRepository.php';
require_once __DIR__ . '/Services/UsersService.php';
require_once __DIR__ . '/Services/UsersValidationException.php';

$usersRepository = new UsersRepository($app->database());
$usersService = new UsersService(
    $usersRepository,
    new PasswordHasher(),
    new PermissionChecker($app->database()),
    $app->database()
);
$usersAdminBase = $app->adminUrl()->baseUrl();
$usersAdminUrlService = $app->adminUrl();
$usersAdminUrl = static fn (string $path = ''): string => $usersAdminUrlService->childUrl($path);

$app->adminNavigation()->add('Users', $usersAdminUrl('users'), 'users.read');

$usersRenderView = static function (string $view, array $data = []) use ($usersAdminUrl): string {
    $file = __DIR__ . '/views/admin/' . $view . '.php';

    if (!is_file($file)) {
        throw new RuntimeException("Users admin view [{$view}] was not found.");
    }

    $data['adminUrl'] = $usersAdminUrl;
    extract($data, EXTR_SKIP);
    $initialOutputLevel = ob_get_level();

    if (!@ob_start()) {
        throw new RuntimeException('Users admin view output buffer is unavailable.');
    }

    try {
        require $file;

        if (ob_get_level() !== $initialOutputLevel + 1) {
            throw new RuntimeException('Users admin view output buffer state is invalid.');
        }

        $rendered = @ob_get_clean();

        if (!is_string($rendered)) {
            throw new RuntimeException('Users admin view output buffer could not be read.');
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

$usersRenderAdmin = static function (
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

$usersRequireAdmin = static function ($request, array $permissions) use ($app, $usersAdminBase) {
    if (!$app->auth()->check()) {
        return Response::redirect($usersAdminBase);
    }

    $user = $app->auth()->user();

    if (!$user?->can('admin.access')) {
        return $app->adminErrors()->response($request, 403);
    }

    foreach ($permissions as $permission) {
        if (!$user->can($permission)) {
            return $app->adminErrors()->response($request, 403);
        }
    }

    return $user;
};

$usersValidateCsrf = static function ($request) use ($app): ?Response {
    return $app->csrf()->validateOrReject($request) instanceof Response
        ? $app->adminErrors()->response($request, 419)
        : null;
};

$usersNotice = static function ($request): ?string {
    return match ($request->input('notice')) {
        'created' => 'User created.',
        'updated' => 'User updated.',
        'password' => 'Password updated.',
        'status' => 'Account status updated.',
        default => null,
    };
};

$usersCreateData = static fn (array $values = []): array => [
    'name' => (string) ($values['name'] ?? ''),
    'email' => (string) ($values['email'] ?? ''),
    'status' => (string) ($values['status'] ?? 'inactive'),
];

$usersEditData = static fn (ManagedUser $target, array $values = []): array => [
    'name' => (string) ($values['name'] ?? $target->name()),
    'email' => (string) ($values['email'] ?? $target->email()),
];

$usersRenderCreate = static function (
    $request,
    $user,
    array $values = [],
    array $errors = [],
    int $status = 200
) use ($app, $usersAdminUrl, $usersCreateData, $usersRenderAdmin, $usersRenderView): Response {
    $content = $usersRenderView('create', [
        'formAction' => $usersAdminUrl('users'),
        'csrfToken' => $app->session()->csrfToken(),
        'canManageStatus' => $user->can('users.status.manage'),
        'errors' => $errors,
        'values' => $usersCreateData($values),
    ]);

    return $usersRenderAdmin('Create User', $content, $user, $request->path(), $status);
};

$usersRenderEdit = static function (
    $request,
    $user,
    ManagedUser $target,
    array $identityValues = [],
    array $errors = [],
    ?string $errorSection = null,
    ?string $notice = null,
    int $status = 200
) use ($app, $usersAdminUrl, $usersEditData, $usersRenderAdmin, $usersRenderView): Response {
    $content = $usersRenderView('edit', [
        'target' => $target,
        'identityValues' => $usersEditData($target, $identityValues),
        'identityAction' => $usersAdminUrl('users/' . $target->id()),
        'passwordAction' => $usersAdminUrl('users/' . $target->id() . '/password'),
        'statusAction' => $usersAdminUrl('users/' . $target->id() . '/status'),
        'csrfToken' => $app->session()->csrfToken(),
        'canUpdate' => $user->can('users.update'),
        'canManagePassword' => $user->can('users.password.manage'),
        'canManageStatus' => $user->can('users.status.manage'),
        'errors' => $errors,
        'errorSection' => $errorSection,
        'notice' => $notice,
    ]);

    return $usersRenderAdmin('User Details', $content, $user, $request->path(), $status);
};

$app->router()->get($usersAdminUrl('users'), function ($request) use (
    $app,
    $usersRepository,
    $usersRequireAdmin,
    $usersRenderAdmin,
    $usersRenderView
): Response {
    $user = $usersRequireAdmin($request, ['users.read']);

    if ($user instanceof Response) {
        return $user;
    }

    try {
        $users = $usersRepository->paginate();
    } catch (PDOException) {
        return $app->adminErrors()->response($request, 503);
    }

    $content = $usersRenderView('list', [
        'users' => $users,
        'canCreate' => $user->can('users.create'),
    ]);

    return $usersRenderAdmin('Users', $content, $user, $request->path());
});

$app->router()->get($usersAdminUrl('users/create'), function ($request) use (
    $usersRenderCreate,
    $usersRequireAdmin
): Response {
    $user = $usersRequireAdmin($request, ['users.create']);

    return $user instanceof Response ? $user : $usersRenderCreate($request, $user);
});

$app->router()->post($usersAdminUrl('users'), function ($request) use (
    $app,
    $usersAdminBase,
    $usersAdminUrl,
    $usersRenderCreate,
    $usersRequireAdmin,
    $usersService,
    $usersValidateCsrf
): Response {
    $user = $usersRequireAdmin($request, ['users.create']);

    if ($user instanceof Response) {
        return $user;
    }

    if ($csrfResponse = $usersValidateCsrf($request)) {
        return $csrfResponse;
    }

    $input = [
        'name' => $request->post('name'),
        'email' => $request->post('email'),
        'password' => $request->post('password'),
        'password_confirmation' => $request->post('password_confirmation'),
    ];

    if ($request->post('status') !== null) {
        $input['status'] = $request->post('status');
    }

    try {
        $id = $usersService->create($input, $user->can('users.status.manage'));
    } catch (UsersValidationException $exception) {
        return $usersRenderCreate($request, $user, $exception->safeValues(), $exception->errors(), 422);
    } catch (PDOException) {
        return $app->adminErrors()->response($request, 503);
    }

    if (!$user->can('users.read')) {
        return Response::redirect($usersAdminBase);
    }

    return Response::redirect($usersAdminUrl('users/' . $id . '/edit') . '?notice=created');
});

$app->router()->get($usersAdminUrl('users/{id}/edit'), function ($request, array $params) use (
    $app,
    $usersNotice,
    $usersRenderEdit,
    $usersRepository,
    $usersRequireAdmin
): Response {
    $user = $usersRequireAdmin($request, ['users.read']);

    if ($user instanceof Response) {
        return $user;
    }

    try {
        $id = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $target = is_int($id) ? $usersRepository->findById($id) : null;
    } catch (PDOException) {
        return $app->adminErrors()->response($request, 503);
    }

    if (!$target instanceof ManagedUser) {
        return $app->adminErrors()->response($request, 404);
    }

    return $usersRenderEdit($request, $user, $target, notice: $usersNotice($request));
});

$app->router()->post($usersAdminUrl('users/{id}/password'), function ($request, array $params) use (
    $app,
    $usersAdminUrl,
    $usersRenderEdit,
    $usersRepository,
    $usersRequireAdmin,
    $usersService,
    $usersValidateCsrf
): Response {
    $user = $usersRequireAdmin($request, ['users.read', 'users.password.manage']);

    if ($user instanceof Response) {
        return $user;
    }

    if ($csrfResponse = $usersValidateCsrf($request)) {
        return $csrfResponse;
    }

    try {
        $id = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $target = is_int($id) ? $usersRepository->findById($id) : null;

        if (!$target instanceof ManagedUser) {
            return $app->adminErrors()->response($request, 404);
        }

        $usersService->changePassword($id, [
            'password' => $request->post('password'),
            'password_confirmation' => $request->post('password_confirmation'),
        ]);
    } catch (UsersValidationException $exception) {
        return $usersRenderEdit(
            $request,
            $user,
            $target,
            errors: $exception->errors(),
            errorSection: 'password',
            status: 422
        );
    } catch (PDOException) {
        return $app->adminErrors()->response($request, 503);
    }

    return Response::redirect($usersAdminUrl('users/' . $id . '/edit') . '?notice=password');
});

$app->router()->post($usersAdminUrl('users/{id}/status'), function ($request, array $params) use (
    $app,
    $usersAdminUrl,
    $usersRenderEdit,
    $usersRepository,
    $usersRequireAdmin,
    $usersService,
    $usersValidateCsrf
): Response {
    $user = $usersRequireAdmin($request, ['users.read', 'users.status.manage']);

    if ($user instanceof Response) {
        return $user;
    }

    if ($csrfResponse = $usersValidateCsrf($request)) {
        return $csrfResponse;
    }

    try {
        $id = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $target = is_int($id) ? $usersRepository->findById($id) : null;

        if (!$target instanceof ManagedUser) {
            return $app->adminErrors()->response($request, 404);
        }

        $usersService->changeStatus($id, $request->post('status'), $user->id());
        $target = $usersRepository->findById($id) ?? $target;
    } catch (UsersValidationException $exception) {
        return $usersRenderEdit(
            $request,
            $user,
            $target,
            errors: $exception->errors(),
            errorSection: 'status',
            status: 422
        );
    } catch (PDOException) {
        return $app->adminErrors()->response($request, 503);
    }

    return Response::redirect($usersAdminUrl('users/' . $id . '/edit') . '?notice=status');
});

$app->router()->post($usersAdminUrl('users/{id}'), function ($request, array $params) use (
    $app,
    $usersAdminUrl,
    $usersRenderEdit,
    $usersRepository,
    $usersRequireAdmin,
    $usersService,
    $usersValidateCsrf
): Response {
    $user = $usersRequireAdmin($request, ['users.read', 'users.update']);

    if ($user instanceof Response) {
        return $user;
    }

    if ($csrfResponse = $usersValidateCsrf($request)) {
        return $csrfResponse;
    }

    try {
        $id = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $target = is_int($id) ? $usersRepository->findById($id) : null;

        if (!$target instanceof ManagedUser) {
            return $app->adminErrors()->response($request, 404);
        }

        $usersService->updateIdentity($id, [
            'name' => $request->post('name'),
            'email' => $request->post('email'),
        ]);
    } catch (UsersValidationException $exception) {
        return $usersRenderEdit(
            $request,
            $user,
            $target,
            $exception->safeValues(),
            $exception->errors(),
            'identity',
            status: 422
        );
    } catch (PDOException) {
        return $app->adminErrors()->response($request, 503);
    }

    return Response::redirect($usersAdminUrl('users/' . $id . '/edit') . '?notice=updated');
});
