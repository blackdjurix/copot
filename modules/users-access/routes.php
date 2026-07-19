<?php

use Copot\Core\PasswordHasher;
use Copot\Core\Response;

require_once __DIR__ . '/Services/ManagedUser.php';
require_once __DIR__ . '/Services/ManagedRole.php';
require_once __DIR__ . '/Services/UsersRepository.php';
require_once __DIR__ . '/Services/RolesRepository.php';
require_once __DIR__ . '/Services/AccessInvariantGuard.php';
require_once __DIR__ . '/Services/UsersService.php';
require_once __DIR__ . '/Services/UsersValidationException.php';
require_once __DIR__ . '/Services/RolesService.php';
require_once __DIR__ . '/Services/RolesValidationException.php';

$usersRepository = new UsersRepository($app->database());
$rolesRepository = new RolesRepository($app->database());
$usersAccessInvariant = new AccessInvariantGuard(
    $rolesRepository,
    $usersRepository
);
$usersService = new UsersService(
    $usersRepository,
    new PasswordHasher(),
    $usersAccessInvariant,
    $app->database()
);
$rolesService = new RolesService(
    $rolesRepository,
    $usersRepository,
    $usersAccessInvariant,
    $app->database()
);
$usersAdminBase = $app->adminUrl()->baseUrl();
$usersAdminUrlService = $app->adminUrl();
$usersAdminUrl = static fn (string $path = ''): string => $usersAdminUrlService->childUrl($path);

$app->adminNavigation()->add('Users', $usersAdminUrl('users'), 'users.read', 'users');
$app->adminNavigation()->add('Roles', $usersAdminUrl('roles'), [
    'roles.read',
    'roles.manage',
    'roles.permissions.manage',
], 'roles');

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
        'roles' => 'User roles updated.',
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
) use ($app, $rolesRepository, $usersAdminUrl, $usersEditData, $usersRenderAdmin, $usersRenderView): Response {
    try {
        $availableRoles = $rolesRepository->all();
        $assignedRoleIds = $rolesRepository->roleIdsForUser($target->id());
    } catch (PDOException) {
        return $app->adminErrors()->response($request, 503);
    }

    $content = $usersRenderView('edit', [
        'target' => $target,
        'identityValues' => $usersEditData($target, $identityValues),
        'identityAction' => $usersAdminUrl('users/' . $target->id()),
        'passwordAction' => $usersAdminUrl('users/' . $target->id() . '/password'),
        'statusAction' => $usersAdminUrl('users/' . $target->id() . '/status'),
        'rolesAction' => $usersAdminUrl('users/' . $target->id() . '/roles'),
        'csrfToken' => $app->session()->csrfToken(),
        'canUpdate' => $user->can('users.update'),
        'canManagePassword' => $user->can('users.password.manage'),
        'canManageStatus' => $user->can('users.status.manage'),
        'canManageRoles' => $user->can('users.roles.manage'),
        'availableRoles' => $availableRoles,
        'assignedRoleIds' => $assignedRoleIds,
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

$app->router()->post($usersAdminUrl('users/{id}/roles'), function ($request, array $params) use (
    $app,
    $rolesService,
    $usersAdminUrl,
    $usersRenderEdit,
    $usersRepository,
    $usersRequireAdmin,
    $usersValidateCsrf
): Response {
    $user = $usersRequireAdmin($request, ['users.read', 'users.roles.manage']);

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

        $present = $request->post('role_ids_present');
        $payload = $request->post('role_ids');
        $desiredRoleIds = [];

        if ($present !== '1') {
            throw new RolesValidationException(['role_ids' => 'Role selection marker is invalid.']);
        }

        if ($payload !== null && !is_array($payload)) {
            throw new RolesValidationException(['role_ids' => 'Role selection payload is invalid.']);
        }

        if (is_array($payload)) {
            foreach ($payload as $value) {
                if (!is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
                    throw new RolesValidationException(['role_ids' => 'One or more selected values are invalid.']);
                }

                $desiredRoleIds[] = (int) $value;
            }
        }

        $rolesService->replaceUserRoles($user->id(), $target->id(), $desiredRoleIds);
    } catch (RolesValidationException $exception) {
        return $usersRenderEdit(
            $request,
            $user,
            $target,
            errors: $exception->errors(),
            errorSection: 'roles',
            status: 422
        );
    } catch (PDOException) {
        return $app->adminErrors()->response($request, 503);
    }

    return Response::redirect($usersAdminUrl('users/' . $target->id() . '/edit') . '?notice=roles');
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

$rolesResolve = static function ($request, array $params) use ($app, $rolesRepository) {
    try {
        $id = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $role = is_int($id) ? $rolesRepository->findById($id) : null;
    } catch (PDOException) {
        return $app->adminErrors()->response($request, 503);
    }

    return $role instanceof ManagedRole ? $role : $app->adminErrors()->response($request, 404);
};

$rolesNotice = static fn ($request): ?string => match ($request->input('notice')) {
    'created' => 'Role created.',
    'updated' => 'Role updated.',
    'permissions' => 'Role permissions updated.',
    'deleted' => 'Role deleted.',
    default => null,
};

$rolesRenderCreate = static function ($request, $user, array $values = [], array $errors = [], int $status = 200) use (
    $app,
    $usersAdminUrl,
    $usersRenderAdmin,
    $usersRenderView
): Response {
    $content = $usersRenderView('roles-create', [
        'formAction' => $usersAdminUrl('roles'),
        'csrfToken' => $app->session()->csrfToken(),
        'values' => ['name' => (string) ($values['name'] ?? ''), 'slug' => (string) ($values['slug'] ?? '')],
        'errors' => $errors,
    ]);

    return $usersRenderAdmin('Create Role', $content, $user, $request->path(), $status);
};

$rolesRenderEdit = static function (
    $request,
    $user,
    ManagedRole $role,
    array $values = [],
    array $errors = [],
    ?string $errorSection = null,
    ?string $notice = null,
    int $status = 200
) use ($app, $rolesRepository, $usersAdminUrl, $usersRenderAdmin, $usersRenderView): Response {
    try {
        $permissions = $rolesRepository->permissions();
        usort($permissions, static fn (array $left, array $right): int =>
            [(string) $left['slug'], (int) $left['id']] <=> [(string) $right['slug'], (int) $right['id']]
        );
        $assignedPermissionIds = $rolesRepository->permissionIdsForRole($role->id());
        $assignedUserCount = $rolesRepository->assignedUserCount($role->id());
    } catch (PDOException) {
        return $app->adminErrors()->response($request, 503);
    }

    $content = $usersRenderView('roles-edit', [
        'role' => $role,
        'identityValues' => ['name' => (string) ($values['name'] ?? $role->name())],
        'identityAction' => $usersAdminUrl('roles/' . $role->id()),
        'permissionsAction' => $usersAdminUrl('roles/' . $role->id() . '/permissions'),
        'deleteAction' => $usersAdminUrl('roles/' . $role->id() . '/delete'),
        'csrfToken' => $app->session()->csrfToken(),
        'permissions' => $permissions,
        'assignedPermissionIds' => $assignedPermissionIds,
        'assignedUserCount' => $assignedUserCount,
        'canManage' => $user->can('roles.manage'),
        'canManagePermissions' => $user->can('roles.permissions.manage'),
        'errors' => $errors,
        'errorSection' => $errorSection,
        'notice' => $notice,
    ]);

    return $usersRenderAdmin('Role Details', $content, $user, $request->path(), $status);
};

$app->router()->get($usersAdminUrl('roles'), function ($request) use (
    $app, $rolesNotice, $rolesRepository, $usersRequireAdmin, $usersRenderAdmin, $usersRenderView
): Response {
    $user = $usersRequireAdmin($request, ['roles.read']);
    if ($user instanceof Response) return $user;

    try {
        $roles = array_map(static fn (ManagedRole $role): array => [
            'role' => $role,
            'assignedUserCount' => $rolesRepository->assignedUserCount($role->id()),
            'permissionCount' => count($rolesRepository->permissionIdsForRole($role->id())),
        ], $rolesRepository->paginate());
    } catch (PDOException) {
        return $app->adminErrors()->response($request, 503);
    }

    return $usersRenderAdmin('Roles', $usersRenderView('roles-list', [
        'roles' => $roles,
        'canCreate' => $user->can('roles.manage'),
        'notice' => $rolesNotice($request),
    ]), $user, $request->path());
});

$app->router()->get($usersAdminUrl('roles/create'), function ($request) use ($rolesRenderCreate, $usersRequireAdmin): Response {
    $user = $usersRequireAdmin($request, ['roles.manage']);
    return $user instanceof Response ? $user : $rolesRenderCreate($request, $user);
});

$app->router()->post($usersAdminUrl('roles'), function ($request) use (
    $app, $rolesRenderCreate, $rolesService, $usersAdminBase, $usersAdminUrl, $usersRequireAdmin, $usersValidateCsrf
): Response {
    $user = $usersRequireAdmin($request, ['roles.manage']);
    if ($user instanceof Response) return $user;
    if ($csrf = $usersValidateCsrf($request)) return $csrf;

    try {
        $id = $rolesService->create(['name' => $request->post('name'), 'slug' => $request->post('slug')]);
    } catch (RolesValidationException $exception) {
        return $rolesRenderCreate($request, $user, $exception->safeValues(), $exception->errors(), 422);
    } catch (PDOException) {
        return $app->adminErrors()->response($request, 503);
    }

    return Response::redirect($user->can('roles.read')
        ? $usersAdminUrl('roles/' . $id . '/edit') . '?notice=created'
        : $usersAdminBase);
});

$app->router()->get($usersAdminUrl('roles/{id}/edit'), function ($request, array $params) use (
    $rolesNotice, $rolesRenderEdit, $rolesResolve, $usersRequireAdmin
): Response {
    $user = $usersRequireAdmin($request, ['roles.read']);
    if ($user instanceof Response) return $user;
    $role = $rolesResolve($request, $params);
    return $role instanceof Response ? $role : $rolesRenderEdit($request, $user, $role, notice: $rolesNotice($request));
});

$app->router()->post($usersAdminUrl('roles/{id}/permissions'), function ($request, array $params) use (
    $app, $rolesRenderEdit, $rolesResolve, $rolesService, $usersAdminUrl, $usersRequireAdmin, $usersValidateCsrf
): Response {
    $user = $usersRequireAdmin($request, ['roles.read', 'roles.permissions.manage']);
    if ($user instanceof Response) return $user;
    if ($csrf = $usersValidateCsrf($request)) return $csrf;
    $role = $rolesResolve($request, $params);
    if ($role instanceof Response) return $role;

    $present = $request->post('permission_ids_present');
    $payload = $request->post('permission_ids');
    $desired = [];
    $payloadError = null;
    if ($present !== '1') {
        $payloadError = ['permissions' => 'Permission selection marker is invalid.'];
    } elseif ($payload !== null && !is_array($payload)) {
        $payloadError = ['permissions' => 'Permission selection payload is invalid.'];
    } elseif (is_array($payload)) {
        foreach ($payload as $value) {
            if (!is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
                $payloadError = ['permissions' => 'One or more selected values are invalid.'];
                break;
            }
            $desired[] = (int) $value;
        }
    }

    try {
        if ($payloadError !== null) throw new RolesValidationException($payloadError);
        $rolesService->replaceRolePermissions($user->id(), $role->id(), $desired);
    } catch (RolesValidationException $exception) {
        return $rolesRenderEdit($request, $user, $role, errors: $exception->errors(), errorSection: 'permissions', status: 422);
    } catch (PDOException) {
        return $app->adminErrors()->response($request, 503);
    }

    return Response::redirect($usersAdminUrl('roles/' . $role->id() . '/edit') . '?notice=permissions');
});

$app->router()->post($usersAdminUrl('roles/{id}/delete'), function ($request, array $params) use (
    $app, $rolesRenderEdit, $rolesResolve, $rolesService, $usersAdminBase, $usersAdminUrl, $usersRequireAdmin, $usersValidateCsrf
): Response {
    $user = $usersRequireAdmin($request, ['roles.manage']);
    if ($user instanceof Response) return $user;
    if ($csrf = $usersValidateCsrf($request)) return $csrf;
    $role = $rolesResolve($request, $params);
    if ($role instanceof Response) return $role;

    try {
        $rolesService->delete($user->id(), $role->id());
    } catch (RolesValidationException $exception) {
        return $rolesRenderEdit($request, $user, $role, errors: $exception->errors(), errorSection: 'delete', status: 422);
    } catch (PDOException) {
        return $app->adminErrors()->response($request, 503);
    }

    return Response::redirect($user->can('roles.read') ? $usersAdminUrl('roles') . '?notice=deleted' : $usersAdminBase);
});

$app->router()->post($usersAdminUrl('roles/{id}'), function ($request, array $params) use (
    $app, $rolesRenderEdit, $rolesResolve, $rolesService, $usersAdminBase, $usersAdminUrl, $usersRequireAdmin, $usersValidateCsrf
): Response {
    $user = $usersRequireAdmin($request, ['roles.manage']);
    if ($user instanceof Response) return $user;
    if ($csrf = $usersValidateCsrf($request)) return $csrf;
    $role = $rolesResolve($request, $params);
    if ($role instanceof Response) return $role;

    try {
        $rolesService->updateName($role->id(), ['name' => $request->post('name')]);
    } catch (RolesValidationException $exception) {
        return $rolesRenderEdit($request, $user, $role, $exception->safeValues(), $exception->errors(), 'identity', status: 422);
    } catch (PDOException) {
        return $app->adminErrors()->response($request, 503);
    }

    return Response::redirect($user->can('roles.read')
        ? $usersAdminUrl('roles/' . $role->id() . '/edit') . '?notice=updated'
        : $usersAdminBase);
});
