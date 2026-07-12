<?php

declare(strict_types=1);

use Copot\Core\Application;
use Copot\Core\Admin\AdminErrorRenderer;
use Copot\Core\Admin\AdminPageRenderer;
use Copot\Core\Admin\AdminUrl;
use Copot\Core\AdminNavigation;
use Copot\Core\Config;
use Copot\Core\Env;
use Copot\Core\PasswordHasher;
use Copot\Core\Request;
use Copot\Core\Response;
use Copot\Core\Router;
use Copot\Core\View;

$basePath = dirname(__DIR__);
chdir($basePath);
session_save_path(sys_get_temp_dir());
session_id('copotm31batch3ui' . bin2hex(random_bytes(5)));
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$value = static fn (Response $response, string $property): mixed => (new ReflectionProperty($response, $property))->getValue($response);
$status = static fn (Response $response): int => (int) $value($response, 'status');
$content = static fn (Response $response): string => (string) $value($response, 'content');
$location = static function (Response $response) use ($value): ?string {
    $headers = $value($response, 'headers');
    return is_array($headers) ? ($headers['Location'] ?? null) : null;
};

$app = new Application($basePath);
$app->session()->start();
require $basePath . '/routes/web.php';
require $basePath . '/routes/auth.php';
require $basePath . '/routes/admin.php';
require $basePath . '/modules/users-access/routes.php';
require $basePath . '/routes/admin_fallback.php';
$db = $app->database()->connection();
$db->beginTransaction();

try {
    $required = ['admin.access', 'users.read', 'users.create', 'users.update', 'users.password.manage',
        'users.status.manage', 'roles.read', 'roles.manage', 'users.roles.manage', 'roles.permissions.manage'];
    $permissionIds = [];
    foreach ($required as $slug) {
        $statement = $db->prepare('SELECT id FROM permissions WHERE slug = :slug');
        $statement->execute(['slug' => $slug]);
        $id = $statement->fetchColumn();
        if (!is_numeric($id)) {
            $db->prepare('INSERT INTO permissions (name, slug, created_at, updated_at) VALUES (:name, :slug, NOW(), NOW())')
                ->execute(['name' => $slug, 'slug' => $slug]);
            $id = $db->lastInsertId();
        }
        $permissionIds[$slug] = (int) $id;
    }

    $createActor = static function (string $name, array $slugs) use ($db, $permissionIds): int {
        $db->prepare("INSERT INTO users (name,email,password_hash,status,created_at,updated_at) VALUES (:name,:email,:hash,'active',NOW(),NOW())")
            ->execute(['name' => $name, 'email' => strtolower($name) . bin2hex(random_bytes(5)) . '@test.invalid', 'hash' => (new PasswordHasher())->make('Batch 3 integration password')]);
        $userId = (int) $db->lastInsertId();
        $db->prepare('INSERT INTO roles (name,slug,created_at,updated_at) VALUES (:name,:slug,NOW(),NOW())')
            ->execute(['name' => $name . ' role', 'slug' => 'b3-' . strtolower($name) . '-' . bin2hex(random_bytes(4))]);
        $roleId = (int) $db->lastInsertId();
        foreach ($slugs as $slug) $db->prepare('INSERT INTO role_permissions (role_id,permission_id) VALUES (?,?)')->execute([$roleId, $permissionIds[$slug]]);
        $db->prepare('INSERT INTO user_roles (user_id,role_id) VALUES (?,?)')->execute([$userId, $roleId]);
        return $userId;
    };
    $actors = [
        'noadmin' => $createActor('NoAdmin', ['roles.read']),
        'noadmin_manage' => $createActor('NoAdminManage', ['roles.manage']),
        'noadmin_permissions' => $createActor('NoAdminPermissions', ['roles.read', 'roles.permissions.manage']),
        'admin' => $createActor('AdminOnly', ['admin.access']),
        'read' => $createActor('Read', ['admin.access', 'roles.read']),
        'manage' => $createActor('Manage', ['admin.access', 'roles.manage']),
        'permissions' => $createActor('Permissions', ['admin.access', 'roles.permissions.manage']),
        'read_permissions' => $createActor('ReadPermissions', ['admin.access', 'roles.read', 'roles.permissions.manage']),
        'user_read' => $createActor('UserRead', ['admin.access', 'users.read']),
        'user_roles_only' => $createActor('UserRolesOnly', ['admin.access', 'users.roles.manage']),
        'user_roles' => $createActor('UserRoles', ['admin.access', 'users.read', 'users.roles.manage']),
        'noadmin_user_roles' => $createActor('NoAdminUserRoles', ['users.read', 'users.roles.manage']),
        'full' => $createActor('Full', $required),
    ];
    $sessionKey = (string) $app->config()->get('auth.session_key', '_copot_user_id');
    $switch = static function (int $id) use ($app, $sessionKey): void { $app->auth()->logout(); $app->session()->set($sessionKey, $id); };
    $request = static fn (string $method, string $path, array $post = [], array $query = []): Response => $app->run(new Request($method, $path, $query, $post));
    $csrf = static fn (array $post = []): array => ['_token' => $app->session()->csrfToken(), ...$post];
    $url = static fn (string $path = ''): string => $app->adminUrl()->childUrl($path);

    $roleIdBySlug = static function (string $slug) use ($db): int {
        $statement = $db->prepare('SELECT id FROM roles WHERE slug = :slug');
        $statement->execute(['slug' => $slug]);
        return (int) $statement->fetchColumn();
    };
    $roleName = static fn (int $id): string => (string) $db->query("SELECT name FROM roles WHERE id = {$id}")->fetchColumn();
    $roleExists = static fn (int $id): bool => (bool) $db->query("SELECT 1 FROM roles WHERE id = {$id}")->fetchColumn();
    $permissionSet = static function (int $roleId) use ($db): array {
        $statement = $db->prepare('SELECT permission_id FROM role_permissions WHERE role_id = :id ORDER BY permission_id');
        $statement->execute(['id' => $roleId]);
        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    };
    $userRoleSet = static function (int $userId) use ($db): array {
        $statement = $db->prepare('SELECT role_id FROM user_roles WHERE user_id = :id ORDER BY role_id');
        $statement->execute(['id' => $userId]);
        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    };
    $createRole = static function (string $name, string $slug, array $ids = []) use ($db): int {
        $db->prepare('INSERT INTO roles (name,slug,created_at,updated_at) VALUES (:name,:slug,NOW(),NOW())')
            ->execute(['name' => $name, 'slug' => $slug]);
        $id = (int) $db->lastInsertId();
        foreach ($ids as $permissionId) {
            $db->prepare('INSERT INTO role_permissions (role_id,permission_id) VALUES (?,?)')->execute([$id, $permissionId]);
        }
        return $id;
    };

    $source = (string) file_get_contents($basePath . '/modules/users-access/routes.php');
    $views = implode('', array_map('file_get_contents', glob($basePath . '/modules/users-access/views/admin/roles-*.php') ?: []));
    $assert(!preg_match('/[\'\"]\/admin(?:[\'\"\/]|$)/', $source . $views), 'Literal /admin dependency found.');
    foreach (["get(\$usersAdminUrl('roles')", "get(\$usersAdminUrl('roles/create')", "post(\$usersAdminUrl('roles')", "get(\$usersAdminUrl('roles/{id}/edit')", "post(\$usersAdminUrl('roles/{id}/permissions')", "post(\$usersAdminUrl('roles/{id}/delete')", "post(\$usersAdminUrl('roles/{id}')"] as $needle) $assert(str_contains($source, $needle), "Missing route source {$needle}.");

    // The administrator-capable fixture is capability-based and contains the exact recovery set.
    $recovery = ['admin.access', 'users.read', 'users.status.manage', 'roles.read', 'roles.manage',
        'users.roles.manage', 'roles.permissions.manage'];
    $recoveryPlaceholders = implode(',', array_fill(0, count($recovery), '?'));
    $recoveryStatement = $db->prepare("SELECT COUNT(DISTINCT permissions.slug) FROM user_roles
        JOIN role_permissions ON role_permissions.role_id=user_roles.role_id
        JOIN permissions ON permissions.id=role_permissions.permission_id
        WHERE user_roles.user_id=? AND permissions.slug IN ({$recoveryPlaceholders})");
    $recoveryStatement->execute([$actors['full'], ...$recovery]);
    $assert((int) $recoveryStatement->fetchColumn() === count($recovery),
        'Full actor fixture is not administrator-capable through its effective permission union.');

    // Navigation visibility uses the registered OR permission contract.
    foreach ([
        'admin' => false,
        'read' => true,
        'manage' => true,
        'permissions' => true,
    ] as $actor => $visible) {
        $switch($actors[$actor]);
        $items = $app->adminNavigation()->itemsFor($app->auth()->user());
        $roleItems = array_values(array_filter($items, static fn (array $item): bool => $item['label'] === 'Roles'));
        $assert(($roleItems !== []) === $visible, "Roles navigation visibility was incorrect for {$actor} actor.");
        if ($visible) $assert($roleItems[0]['url'] === $url('roles'), "Roles navigation URL was incorrect for {$actor} actor.");
    }

    // Register the module against a non-default AdminUrl and dispatch every locked route.
    $alternateDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-b3-control-' . bin2hex(random_bytes(6));
    if (!mkdir($alternateDirectory, 0777, true) && !is_dir($alternateDirectory)) throw new RuntimeException('Alternate Admin fixture directory failed.');
    try {
        $alternateConfigFile = $alternateDirectory . DIRECTORY_SEPARATOR . 'admin.php';
        if (file_put_contents($alternateConfigFile, "<?php\nreturn ['path' => 'control-panel'];\n") === false) throw new RuntimeException('Alternate Admin config failed.');
        $alternateUrl = new AdminUrl(new Config($alternateDirectory));
        $alternateNavigation = new AdminNavigation();
        $alternateRouter = new Router();
        $alternateView = new View($basePath . '/resources/views');
        $alternatePages = new AdminPageRenderer($alternateView, $alternateUrl, $alternateNavigation, 'Copot', 'copot', 'en');
        $alternateErrors = new AdminErrorRenderer($alternateView, $alternatePages, $alternateUrl, $app->auth(), $app->csrf(), 'admin.access');
        $alternateApp = new class($app, $alternateRouter, $alternateNavigation, $alternateUrl, $alternatePages, $alternateErrors) {
            public function __construct(private Application $base, private Router $routerValue,
                private AdminNavigation $navigationValue, private AdminUrl $urlValue,
                private AdminPageRenderer $pagesValue, private AdminErrorRenderer $errorsValue) {}
            public function database() { return $this->base->database(); }
            public function session() { return $this->base->session(); }
            public function csrf() { return $this->base->csrf(); }
            public function auth() { return $this->base->auth(); }
            public function router(): Router { return $this->routerValue; }
            public function adminNavigation(): AdminNavigation { return $this->navigationValue; }
            public function adminUrl(): AdminUrl { return $this->urlValue; }
            public function adminPageRenderer(): AdminPageRenderer { return $this->pagesValue; }
            public function adminErrors(): AdminErrorRenderer { return $this->errorsValue; }
        };
        (static function ($app) use ($basePath): void { require $basePath . '/modules/users-access/routes.php'; })($alternateApp);
        $switch($actors['full']);
        $alternateRoleId = $createRole('Alternate role', 'alternate-' . bin2hex(random_bytes(4)));
        $alternateDeleteId = $createRole('Alternate delete', 'alternate-delete-' . bin2hex(random_bytes(4)));
        $dispatchAlternate = static fn (string $method, string $path, array $post = []): Response =>
            $alternateRouter->dispatch(new Request($method, '/control-panel/' . ltrim($path, '/'), [], $post));
        $assert($status($dispatchAlternate('GET', 'roles')) === 200, 'Configured roles list route did not dispatch.');
        $assert($status($dispatchAlternate('GET', 'roles/create')) === 200, 'Configured static roles/create route collided.');
        $alternateCreateSlug = 'alternate-created-' . bin2hex(random_bytes(4));
        $assert($status($dispatchAlternate('POST', 'roles', $csrf(['name' => 'Alternate created', 'slug' => $alternateCreateSlug]))) === 302,
            'Configured role create route did not dispatch.');
        $assert($status($dispatchAlternate('GET', "roles/{$alternateRoleId}/edit")) === 200, 'Configured role edit route did not dispatch.');
        $assert($status($dispatchAlternate('POST', "roles/{$alternateRoleId}", $csrf(['name' => 'Alternate updated']))) === 302,
            'Configured role update route did not dispatch.');
        $assert($status($dispatchAlternate('POST', "roles/{$alternateRoleId}/permissions", $csrf(['permission_ids_present' => '1']))) === 302,
            'Configured permission replacement route did not dispatch.');
        $assert($status($dispatchAlternate('POST', "roles/{$alternateDeleteId}/delete", $csrf())) === 302,
            'Configured delete route did not dispatch.');
        $alternateUserRoleSet = $userRoleSet($actors['user_roles']);
        $assert($status($dispatchAlternate('POST', 'users/' . $actors['user_roles'] . '/roles', $csrf([
            'role_ids_present' => '1',
            'role_ids' => array_map('strval', $alternateUserRoleSet),
        ]))) === 302, 'Configured user-role replacement route did not dispatch.');
        foreach (['roles', 'roles/create', "roles/{$alternateRoleId}/edit"] as $path) {
            $assert($status($alternateRouter->dispatch(new Request('GET', '/admin/' . $path))) === 404,
                "Default Admin GET route remained registered for {$path}.");
        }
        foreach (['roles', "roles/{$alternateRoleId}", "roles/{$alternateRoleId}/permissions", "roles/{$alternateRoleId}/delete"] as $path) {
            $assert($status($alternateRouter->dispatch(new Request('POST', '/admin/' . $path, [], $csrf()))) === 404,
                "Default Admin POST route remained registered for {$path}.");
        }
        $assert($status($alternateRouter->dispatch(new Request(
            'POST',
            '/admin/users/' . $actors['user_roles'] . '/roles',
            [],
            $csrf(['role_ids_present' => '1'])
        ))) === 404, 'Default Admin user-role replacement route remained registered.');
        $alternateRoleItems = array_values(array_filter($alternateNavigation->itemsFor($app->auth()->user()),
            static fn (array $item): bool => $item['label'] === 'Roles'));
        $assert(($alternateRoleItems[0]['url'] ?? null) === '/control-panel/roles', 'Configured Roles navigation URL was incorrect.');
    } finally {
        if (isset($alternateConfigFile) && is_file($alternateConfigFile)) unlink($alternateConfigFile);
        if (is_dir($alternateDirectory)) rmdir($alternateDirectory);
    }

    $app->auth()->logout();
    $guest = $request('GET', $url('roles'));
    $assert($status($guest) === 302 && $location($guest) === $url(), 'Guest redirect contract failed.');
    $switch($actors['noadmin']); $assert($status($request('GET', $url('roles'))) === 403, 'admin.access guard failed.');
    $switch($actors['admin']); $assert($status($request('GET', $url('roles'))) === 403, 'roles.read guard failed.');
    $switch($actors['read']);
    $list = $request('GET', $url('roles'));
    $assert($status($list) === 200 && str_contains($content($list), 'Assigned users') && str_contains($content($list), 'Permissions'), 'Role list fields failed.');
    $assert(!str_contains($content($list), 'Create role'), 'Create action leaked without roles.manage.');
    $assert($status($request('GET', $url('roles/create'))) === 403, 'Create permission guard failed.');
    $switch($actors['manage']);
    $createForm = $request('GET', $url('roles/create'));
    $assert($status($createForm) === 200 && str_contains($content($createForm), 'action="' . $url('roles') . '"'), 'Create form failed.');
    $invalid = $request('POST', $url('roles'), $csrf(['name' => '<bad>', 'slug' => 'bad slug']));
    $assert($status($invalid) === 422 && str_contains($content($invalid), 'admin-shell') && str_contains($content($invalid), '&lt;bad&gt;'), 'Safe create validation failed.');
    $created = $request('POST', $url('roles'), $csrf(['name' => 'Created role', 'slug' => 'created-' . bin2hex(random_bytes(4))]));
    $assert($status($created) === 302 && $location($created) === $url(), 'Manage-only create redirect failed.');

    $switch($actors['full']);
    $slug = 'surface-' . bin2hex(random_bytes(4));
    $created = $request('POST', $url('roles'), $csrf(['name' => 'Surface <role>', 'slug' => $slug]));
    $roleId = (int) $db->query('SELECT id FROM roles WHERE slug=' . $db->quote($slug))->fetchColumn();
    $assert($location($created) === $url("roles/{$roleId}/edit") . '?notice=created', 'Readable create redirect failed.');
    $edit = $request('GET', $url("roles/{$roleId}/edit"));
    $assert($status($edit) === 200 && str_contains($content($edit), 'Surface &lt;role&gt;') && !str_contains($content($edit), 'name="slug"'), 'Edit escaping or immutable slug failed.');
    $assert(str_contains($content($edit), 'name="permission_ids_present" value="1"') && str_contains($content($edit), 'name="permission_ids[]"'), 'Desired-set form contract failed.');

    // admin.access is an independent prerequisite even when every route-specific permission is present.
    $adminAccessTargetId = $createRole(
        'Admin access target',
        'admin-access-target-' . bin2hex(random_bytes(4)),
        [$permissionIds['roles.read']]
    );
    $assertBaseDenied = static function (Response $response, string $route) use ($assert, $content, $status): void {
        $body = $content($response);
        $assert($status($response) === 403, "Missing admin.access did not return exact 403 for {$route}.");
        $assert(str_contains($body, 'Access denied') && str_contains($body, 'do not have permission'),
            "Missing admin.access did not use the controlled Admin 403 response for {$route}.");
    };

    $switch($actors['noadmin']);
    $assertBaseDenied($request('GET', $url('roles')), 'GET roles');
    $assertBaseDenied($request('GET', $url("roles/{$adminAccessTargetId}/edit")), 'GET roles/{id}/edit');

    $switch($actors['noadmin_manage']);
    $assertBaseDenied($request('GET', $url('roles/create')), 'GET roles/create');

    $deniedCreateSlug = 'denied-create-' . bin2hex(random_bytes(4));
    $beforeDeniedCreateCount = (int) $db->query('SELECT COUNT(*) FROM roles')->fetchColumn();
    $deniedCreate = $request('POST', $url('roles'), $csrf([
        'name' => 'Denied valid create',
        'slug' => $deniedCreateSlug,
    ]));
    $assertBaseDenied($deniedCreate, 'POST roles');
    $assert((int) $db->query('SELECT COUNT(*) FROM roles')->fetchColumn() === $beforeDeniedCreateCount
        && $roleIdBySlug($deniedCreateSlug) === 0,
        'Missing admin.access create request changed role count or created its valid slug.');

    $beforeDeniedName = $roleName($adminAccessTargetId);
    $deniedUpdate = $request('POST', $url("roles/{$adminAccessTargetId}"), $csrf([
        'name' => 'Denied but valid update',
    ]));
    $assertBaseDenied($deniedUpdate, 'POST roles/{id}');
    $assert($roleName($adminAccessTargetId) === $beforeDeniedName,
        'Missing admin.access update request changed the display name.');

    $beforeDeniedDeletePermissions = $permissionSet($adminAccessTargetId);
    $deniedDelete = $request('POST', $url("roles/{$adminAccessTargetId}/delete"), $csrf());
    $assertBaseDenied($deniedDelete, 'POST roles/{id}/delete');
    $assert($roleExists($adminAccessTargetId)
        && $permissionSet($adminAccessTargetId) === $beforeDeniedDeletePermissions,
        'Missing admin.access delete request removed the role or changed its permission set.');

    $switch($actors['noadmin_permissions']);
    $beforeDeniedPermissions = $permissionSet($adminAccessTargetId);
    $deniedPermissions = $request('POST', $url("roles/{$adminAccessTargetId}/permissions"), $csrf([
        'permission_ids_present' => '1',
        'permission_ids' => [(string) $permissionIds['roles.manage']],
    ]));
    $assertBaseDenied($deniedPermissions, 'POST roles/{id}/permissions');
    $assert($permissionSet($adminAccessTargetId) === $beforeDeniedPermissions,
        'Missing admin.access permission request changed the persisted desired set.');

    $switch($actors['full']);

    // Exact permission guards are exercised route-by-route before CSRF validation.
    $guardCases = [
        ['admin', 'GET', 'roles'],
        ['admin', 'GET', 'roles/create'],
        ['admin', 'POST', 'roles'],
        ['admin', 'GET', "roles/{$roleId}/edit"],
        ['read', 'POST', "roles/{$roleId}"],
        ['read', 'POST', "roles/{$roleId}/delete"],
        ['read', 'POST', "roles/{$roleId}/permissions"],
        ['permissions', 'POST', "roles/{$roleId}/permissions"],
    ];
    foreach ($guardCases as [$actor, $method, $path]) {
        $switch($actors[$actor]);
        $response = $request($method, $url($path));
        $assert($status($response) === 403, "Exact permission guard failed for {$method} {$path} using {$actor} actor.");
        $assert(str_contains($content($response), 'Access denied'), "Permission denial was not safely rendered for {$method} {$path}.");
    }
    $switch($actors['manage']);
    $assert($status($request('GET', $url('roles'))) === 403, 'roles.manage incorrectly implied roles.read on list.');
    $assert($status($request('GET', $url("roles/{$roleId}/edit"))) === 403, 'roles.manage incorrectly implied roles.read on edit.');
    $switch($actors['read_permissions']);
    $assert($status($request('POST', $url("roles/{$roleId}/permissions"), $csrf(['permission_ids_present' => '1']))) === 302,
        'roles.read plus roles.permissions.manage could not replace permissions.');
    $switch($actors['full']);

    // Identity validation remains in the identity section and preserves only safe submitted values.
    $safeIdentityValue = str_repeat('Safe <identity> ', 7);
    $invalidIdentity = $request('POST', $url("roles/{$roleId}"), $csrf(['name' => $safeIdentityValue]));
    $assert($status($invalidIdentity) === 422, 'Invalid display name did not return 422.');
    $assert(str_contains($content($invalidIdentity), 'admin-shell'), 'Identity validation did not render in Admin shell.');
    $assert(str_contains($content($invalidIdentity), 'id="role-identity-title"'), 'Identity validation was not rendered in identity section.');
    $assert(str_contains($content($invalidIdentity), 'Role name is required'), 'Identity validation error was not visible.');
    $assert(str_contains($content($invalidIdentity), 'Safe &lt;identity&gt;'), 'Safe identity value was not escaped and preserved.');
    $assert($roleName($roleId) === 'Surface <role>', 'Invalid identity mutation changed persistent name.');

    // Permission invariant rollback must render the general role error and current persisted checkbox state.
    $fullRoleId = (int) $db->query('SELECT role_id FROM user_roles WHERE user_id=' . $actors['full'] . ' ORDER BY role_id LIMIT 1')->fetchColumn();
    $fullBefore = $permissionSet($fullRoleId);
    $removedRecoveryId = $permissionIds['roles.manage'];
    $invariantDesired = array_values(array_diff($fullBefore, [$removedRecoveryId]));
    $invariantResponse = $request('POST', $url("roles/{$fullRoleId}/permissions"), $csrf([
        'permission_ids_present' => '1',
        'permission_ids' => array_map('strval', $invariantDesired),
    ]));
    $assert($status($invariantResponse) === 422, 'Permission self-invariant failure did not return 422.');
    $assert(str_contains($content($invariantResponse), 'id="role-permissions-title"'), 'Permission invariant did not render in permissions section.');
    $assert(str_contains($content($invariantResponse), 'You cannot remove your own administrator recovery access.'),
        'General role invariant error was lost.');
    $assert($permissionSet($fullRoleId) === $fullBefore, 'Permission invariant failure changed persisted assignments.');
    $checkedNeedle = 'value="' . $removedRecoveryId . '" checked';
    $assert(str_contains($content($invariantResponse), $checkedNeedle), 'Rolled-back assigned permission was not checked in response.');
    $unassignedPermissionId = $permissionIds['roles.read'];
    $unassignedRoleId = $createRole('Checkbox role', 'checkbox-' . bin2hex(random_bytes(4)), [$removedRecoveryId]);
    $checkboxPage = $request('GET', $url("roles/{$unassignedRoleId}/edit"));
    $assert(str_contains($content($checkboxPage), 'value="' . $removedRecoveryId . '" checked'), 'Assigned checkbox was not selected.');
    $assert(str_contains($content($checkboxPage), 'value="' . $unassignedPermissionId . '"  '), 'Unassigned checkbox was unexpectedly selected.');

    $updated = $request('POST', $url("roles/{$roleId}"), $csrf(['name' => 'Updated role']));
    $assert($location($updated) === $url("roles/{$roleId}/edit") . '?notice=updated', 'Update redirect failed.');

    foreach ([[], ['permission_ids_present' => '1', 'permission_ids' => '1'], ['permission_ids_present' => '1', 'permission_ids' => [['1']]], ['permission_ids_present' => '1', 'permission_ids' => ['bad']]] as $payload) {
        $response = $request('POST', $url("roles/{$roleId}/permissions"), $csrf($payload));
        $assert($status($response) === 422 && str_contains($content($response), 'Permissions'), 'Malformed desired-set payload was accepted.');
    }
    $empty = $request('POST', $url("roles/{$roleId}/permissions"), $csrf(['permission_ids_present' => '1']));
    $assert($location($empty) === $url("roles/{$roleId}/edit") . '?notice=permissions', 'All-unchecked desired set failed.');
    $multiple = $request('POST', $url("roles/{$roleId}/permissions"), $csrf(['permission_ids_present' => '1', 'permission_ids' => [(string) $permissionIds['roles.read'], (string) $permissionIds['roles.manage'], (string) $permissionIds['roles.read']]]));
    $assert($status($multiple) === 302, 'Multiple/duplicate valid desired IDs failed.');
    $assert($permissionSet($roleId) === array_values(array_unique([$permissionIds['roles.read'], $permissionIds['roles.manage']])),
        'Multiple/duplicate desired IDs did not persist the exact normalized set.');

    $switch($actors['permissions']);
    $assert($status($request('POST', $url("roles/{$roleId}/permissions"), $csrf(['permission_ids_present' => '1']))) === 403, 'roles.read was incorrectly implied.');
    $switch($actors['read_permissions']);
    $assert($status($request('POST', $url("roles/{$roleId}/permissions"), [])) === 419, 'Permission CSRF guard failed.');
    $switch($actors['full']);

    // Missing and invalid CSRF requests prove database state remains unchanged for every POST route.
    foreach (['missing' => null, 'invalid' => 'invalid-token'] as $case => $token) {
        $csrfCreateSlug = "csrf-create-{$case}-" . bin2hex(random_bytes(3));
        $beforeRoleCount = (int) $db->query('SELECT COUNT(*) FROM roles')->fetchColumn();
        $post = ['name' => 'CSRF create', 'slug' => $csrfCreateSlug];
        if ($token !== null) $post['_token'] = $token;
        $response = $request('POST', $url('roles'), $post);
        $assert($status($response) === 419 && str_contains($content($response), 'admin-shell'), "{$case} CSRF create was not rejected in shell.");
        $assert((int) $db->query('SELECT COUNT(*) FROM roles')->fetchColumn() === $beforeRoleCount && $roleIdBySlug($csrfCreateSlug) === 0,
            "{$case} CSRF create mutated role state.");

        $csrfTargetId = $createRole('CSRF target', "csrf-target-{$case}-" . bin2hex(random_bytes(3)), [$permissionIds['roles.read']]);
        $beforeName = $roleName($csrfTargetId);
        $beforePermissions = $permissionSet($csrfTargetId);
        $updatePost = ['name' => 'Mutated'];
        if ($token !== null) $updatePost['_token'] = $token;
        $response = $request('POST', $url("roles/{$csrfTargetId}"), $updatePost);
        $assert($status($response) === 419 && str_contains($content($response), 'admin-shell'), "{$case} CSRF update was not rejected in shell.");
        $assert($roleName($csrfTargetId) === $beforeName, "{$case} CSRF update changed display name.");

        $permissionPost = ['permission_ids_present' => '1', 'permission_ids' => [(string) $permissionIds['roles.manage']]];
        if ($token !== null) $permissionPost['_token'] = $token;
        $response = $request('POST', $url("roles/{$csrfTargetId}/permissions"), $permissionPost);
        $assert($status($response) === 419 && str_contains($content($response), 'admin-shell'), "{$case} CSRF permissions was not rejected in shell.");
        $assert($permissionSet($csrfTargetId) === $beforePermissions, "{$case} CSRF permissions changed assignment state.");

        $deletePost = $token === null ? [] : ['_token' => $token];
        $response = $request('POST', $url("roles/{$csrfTargetId}/delete"), $deletePost);
        $assert($status($response) === 419 && str_contains($content($response), 'admin-shell'), "{$case} CSRF delete was not rejected in shell.");
        $assert($roleExists($csrfTargetId) && $permissionSet($csrfTargetId) === $beforePermissions,
            "{$case} CSRF delete changed role or assignment state.");
    }

    // Complete delete lifecycle through actual routes.
    $seededId = (int) $db->query("SELECT id FROM roles WHERE slug='admin'")->fetchColumn();
    $seededDelete = $request('POST', $url("roles/{$seededId}/delete"), $csrf());
    $assert($status($seededDelete) === 422 && str_contains($content($seededDelete), 'id="role-delete-title"')
        && str_contains($content($seededDelete), 'Seeded roles cannot be deleted.') && $roleExists($seededId), 'Seeded admin lifecycle error failed.');
    $seededUserId = (int) $db->query("SELECT id FROM roles WHERE slug='user'")->fetchColumn();
    $seededUserDelete = $request('POST', $url("roles/{$seededUserId}/delete"), $csrf());
    $assert($status($seededUserDelete) === 422 && str_contains($content($seededUserDelete), 'id="role-delete-title"')
        && str_contains($content($seededUserDelete), 'Seeded roles cannot be deleted.') && $roleExists($seededUserId), 'Seeded user lifecycle error failed.');
    $assignedDeleteId = $createRole('Assigned delete', 'assigned-delete-' . bin2hex(random_bytes(4)));
    $db->prepare('INSERT INTO user_roles (user_id,role_id) VALUES (?,?)')->execute([$actors['admin'], $assignedDeleteId]);
    $assignedDelete = $request('POST', $url("roles/{$assignedDeleteId}/delete"), $csrf());
    $assert($status($assignedDelete) === 422 && str_contains($content($assignedDelete), 'id="role-delete-title"')
        && str_contains($content($assignedDelete), 'Assigned roles cannot be deleted.') && $roleExists($assignedDeleteId),
        'Assigned custom role lifecycle error failed.');
    $deleted = $request('POST', $url("roles/{$roleId}/delete"), $csrf());
    $assert($location($deleted) === $url('roles') . '?notice=deleted' && !$roleExists($roleId), 'Delete success redirect or persistence failed.');

    // Manage-only successful mutations must return to Admin base, never forbidden role pages.
    $switch($actors['manage']);
    $noReadSlug = 'no-read-' . bin2hex(random_bytes(4));
    $noReadCreate = $request('POST', $url('roles'), $csrf(['name' => 'No read create', 'slug' => $noReadSlug]));
    $noReadRoleId = $roleIdBySlug($noReadSlug);
    $assert($status($noReadCreate) === 302 && $location($noReadCreate) === $url() && $noReadRoleId > 0,
        'Manage-only create did not redirect to Admin base.');
    $noReadUpdate = $request('POST', $url("roles/{$noReadRoleId}"), $csrf(['name' => 'No read updated']));
    $assert($status($noReadUpdate) === 302 && $location($noReadUpdate) === $url() && $roleName($noReadRoleId) === 'No read updated',
        'Manage-only update did not redirect to Admin base.');
    $noReadDelete = $request('POST', $url("roles/{$noReadRoleId}/delete"), $csrf());
    $assert($status($noReadDelete) === 302 && $location($noReadDelete) === $url() && !$roleExists($noReadRoleId),
        'Manage-only delete did not redirect to Admin base.');

    // Dynamic role and permission values are escaped in actual list/edit HTML.
    $switch($actors['full']);
    $escapedRoleId = $createRole('Escaped <role>', 'escaped-<slug>-' . bin2hex(random_bytes(3)));
    $db->prepare('INSERT INTO permissions (name,slug,created_at,updated_at) VALUES (:name,:slug,NOW(),NOW())')
        ->execute(['name' => 'Permission <name>', 'slug' => 'permission-<slug>-' . bin2hex(random_bytes(3))]);
    $escapedPermissionId = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO role_permissions (role_id,permission_id) VALUES (?,?)')->execute([$escapedRoleId, $escapedPermissionId]);
    $escapedList = $request('GET', $url('roles'));
    $escapedEdit = $request('GET', $url("roles/{$escapedRoleId}/edit"));
    $assert(str_contains($content($escapedList), 'Escaped &lt;role&gt;') && str_contains($content($escapedList), 'escaped-&lt;slug&gt;-'),
        'Role display name or slug was not escaped in list.');
    $assert(str_contains($content($escapedEdit), 'Permission &lt;name&gt;') && str_contains($content($escapedEdit), 'permission-&lt;slug&gt;-'),
        'Permission display name or slug was not escaped in edit.');
    $assert($status($request('GET', $url('roles/create'))) === 200, 'Static roles/create collided with pattern route.');

    // Pass 4: User Details role summary and desired final-set assignment workflow.
    $assignmentTargetId = $actors['admin'];
    $assignmentPath = $url("users/{$assignmentTargetId}/roles");
    $assignmentBefore = $userRoleSet($assignmentTargetId);

    $app->auth()->logout();
    $guestAssignment = $request('POST', $assignmentPath, $csrf([
        'role_ids_present' => '1',
        'role_ids' => array_map('strval', $assignmentBefore),
    ]));
    $assert($status($guestAssignment) === 302 && $location($guestAssignment) === $url(),
        'Guest user-role request did not redirect to configured Admin base.');
    $assert($userRoleSet($assignmentTargetId) === $assignmentBefore, 'Guest user-role request changed assignments.');

    foreach ([
        'noadmin_user_roles' => 'admin.access',
        'user_roles_only' => 'users.read',
        'user_read' => 'users.roles.manage',
    ] as $actor => $missingPermission) {
        $switch($actors[$actor]);
        $before = $userRoleSet($assignmentTargetId);
        $denied = $request('POST', $assignmentPath, $csrf([
            'role_ids_present' => '1',
            'role_ids' => array_map('strval', $before),
        ]));
        $assert($status($denied) === 403, "Missing {$missingPermission} did not deny user-role replacement.");
        $assert($userRoleSet($assignmentTargetId) === $before,
            "Missing {$missingPermission} user-role request changed assignments.");
    }

    $switch($actors['user_read']);
    $readOnlyDetails = $request('GET', $url("users/{$assignmentTargetId}/edit"));
    $assert($status($readOnlyDetails) === 200 && str_contains($content($readOnlyDetails), 'id="user-roles-title"'),
        'users.read actor could not view the role summary.');
    $assert(!str_contains($content($readOnlyDetails), 'name="role_ids_present"'),
        'Read-only role summary exposed the mutation form.');
    $assert(str_contains($content($readOnlyDetails), 'Assigned'), 'Read-only role summary omitted assigned state.');

    $switch($actors['user_roles']);
    $editableDetails = $request('GET', $url("users/{$assignmentTargetId}/edit"));
    $assert(str_contains($content($editableDetails), 'action="' . $assignmentPath . '"')
        && str_contains($content($editableDetails), 'name="role_ids_present" value="1"'),
        'User-role form action or presence marker was incorrect.');
    foreach ($assignmentBefore as $assignedId) {
        $assert(str_contains($content($editableDetails), 'value="' . $assignedId . '" checked'),
            'Persisted assigned role was not checked.');
    }
    $assert(preg_match('/<input[^>]+name="role_ids\[\]"[^>]+value="' . $escapedRoleId . '"(?![^>]*checked)[^>]*>/',
        $content($editableDetails)) === 1, 'Persisted unassigned role was unexpectedly checked.');

    foreach (['missing' => [], 'invalid' => ['_token' => 'invalid-token']] as $case => $tokenInput) {
        $before = $userRoleSet($assignmentTargetId);
        $csrfFailure = $request('POST', $assignmentPath, [
            ...$tokenInput,
            'role_ids_present' => '1',
            'role_ids' => array_map('strval', $before),
        ]);
        $assert($status($csrfFailure) === 419 && str_contains($content($csrfFailure), 'admin-shell'),
            "{$case} CSRF user-role request was not rejected in Admin shell.");
        $assert($userRoleSet($assignmentTargetId) === $before,
            "{$case} CSRF user-role request changed assignments.");
    }

    $invalidPayloads = [
        'missing marker' => ['role_ids' => array_map('strval', $assignmentBefore)],
        'scalar' => ['role_ids_present' => '1', 'role_ids' => '1'],
        'nested' => ['role_ids_present' => '1', 'role_ids' => [['1']]],
        'non-numeric' => ['role_ids_present' => '1', 'role_ids' => ['bad']],
        'unknown' => ['role_ids_present' => '1', 'role_ids' => [(string) PHP_INT_MAX]],
    ];
    foreach ($invalidPayloads as $case => $payload) {
        $before = $userRoleSet($assignmentTargetId);
        $failure = $request('POST', $assignmentPath, $csrf($payload));
        $assert($status($failure) === 422 && str_contains($content($failure), 'id="user-roles-title"'),
            "{$case} user-role payload did not fail safely in Roles section.");
        $assert($userRoleSet($assignmentTargetId) === $before,
            "{$case} user-role payload changed persisted assignments.");
        foreach ($before as $assignedId) {
            $assert(str_contains($content($failure), 'value="' . $assignedId . '" checked'),
                "{$case} failure did not render persisted checked assignments.");
        }
    }

    $replacementRoleA = $createRole('Replacement A', 'replacement-a-' . bin2hex(random_bytes(4)));
    $replacementRoleB = $createRole('Replacement B', 'replacement-b-' . bin2hex(random_bytes(4)));
    $successfulAssignment = $request('POST', $assignmentPath, $csrf([
        'role_ids_present' => '1',
        'role_ids' => [(string) $replacementRoleB, (string) $replacementRoleA, (string) $replacementRoleA],
    ]));
    $assert($status($successfulAssignment) === 302
        && $location($successfulAssignment) === $url("users/{$assignmentTargetId}/edit") . '?notice=roles',
        'User-role success redirect was incorrect.');
    $assert($userRoleSet($assignmentTargetId) === [$replacementRoleA, $replacementRoleB],
        'User-role desired final set or duplicate normalization was incorrect.');
    $assignmentNotice = $request('GET', $url("users/{$assignmentTargetId}/edit"), [], ['notice' => 'roles']);
    $assert(str_contains($content($assignmentNotice), 'User roles updated.'),
        'User-role success notice was not rendered.');
    $emptyAssignment = $request('POST', $assignmentPath, $csrf(['role_ids_present' => '1']));
    $assert($status($emptyAssignment) === 302 && $userRoleSet($assignmentTargetId) === [],
        'Valid empty user-role desired set failed.');

    // Invariant failures must roll back and render persisted state in the Roles section.
    $switch($actors['full']);
    $fullUserRolesBefore = $userRoleSet($actors['full']);
    $selfInvariant = $request('POST', $url('users/' . $actors['full'] . '/roles'), $csrf(['role_ids_present' => '1']));
    $assert($status($selfInvariant) === 422 && str_contains($content($selfInvariant), 'id="user-roles-title"')
        && str_contains($content($selfInvariant), 'You cannot remove your own administrator recovery access.'),
        'Actor self-protection did not render safely in Roles section.');
    $assert($userRoleSet($actors['full']) === $fullUserRolesBefore,
        'Actor self-protection failure changed persisted roles.');
    foreach ($fullUserRolesBefore as $assignedId) {
        $assert(str_contains($content($selfInvariant), 'value="' . $assignedId . '" checked'),
            'Actor self-protection response did not show persisted checked role.');
    }

    $inactiveCapableId = $createActor('InactiveCapable', $required);
    $db->prepare("UPDATE users SET status='inactive' WHERE id=?")->execute([$inactiveCapableId]);
    $existingCapableStatement = $db->prepare("SELECT users.id FROM users
        JOIN user_roles ON user_roles.user_id=users.id
        JOIN role_permissions ON role_permissions.role_id=user_roles.role_id
        JOIN permissions ON permissions.id=role_permissions.permission_id
        WHERE users.status='active' AND users.id<>? AND permissions.slug IN ({$recoveryPlaceholders})
        GROUP BY users.id
        HAVING COUNT(DISTINCT permissions.slug)=?");
    $existingCapableStatement->execute([$actors['full'], ...$recovery, count($recovery)]);
    $existingCapableIds = array_map('intval', $existingCapableStatement->fetchAll(PDO::FETCH_COLUMN));
    if ($existingCapableIds !== []) {
        $placeholders = implode(',', array_fill(0, count($existingCapableIds), '?'));
        $db->prepare("UPDATE users SET status='inactive' WHERE id IN ({$placeholders})")
            ->execute($existingCapableIds);
    }
    $switch($actors['user_roles']);
    $finalInvariant = $request('POST', $url('users/' . $actors['full'] . '/roles'), $csrf(['role_ids_present' => '1']));
    $assert($status($finalInvariant) === 422
        && str_contains($content($finalInvariant), 'At least one active administrator-capable user is required.'),
        'Inactive capable candidate incorrectly satisfied final active administrator invariant.');
    $assert($userRoleSet($actors['full']) === $fullUserRolesBefore,
        'Final active administrator invariant failure changed persisted roles.');
    if ($existingCapableIds !== []) {
        $db->prepare("UPDATE users SET status='active' WHERE id IN ({$placeholders})")
            ->execute($existingCapableIds);
    }

    $recoveryIds = array_map(static fn (string $permission) => $permissionIds[$permission], $recovery);
    $split = intdiv(count($recoveryIds), 2);
    $unionRoleA = $createRole('Union recovery A', 'union-a-' . bin2hex(random_bytes(4)), array_slice($recoveryIds, 0, $split));
    $unionRoleB = $createRole('Union recovery B', 'union-b-' . bin2hex(random_bytes(4)), array_slice($recoveryIds, $split));
    $unionRoleAPermissions = $permissionSet($unionRoleA);
    $unionRoleBPermissions = $permissionSet($unionRoleB);
    $combinedUnionPermissions = array_values(array_unique([...$unionRoleAPermissions, ...$unionRoleBPermissions]));
    sort($combinedUnionPermissions);
    $sortedRecoveryIds = $recoveryIds;
    sort($sortedRecoveryIds);
    $assert(count(array_intersect($unionRoleAPermissions, $sortedRecoveryIds)) < count($sortedRecoveryIds),
        'Union role A unexpectedly contained the full recovery set.');
    $assert(count(array_intersect($unionRoleBPermissions, $sortedRecoveryIds)) < count($sortedRecoveryIds),
        'Union role B unexpectedly contained the full recovery set.');
    $assert($combinedUnionPermissions === $sortedRecoveryIds,
        'Combined split roles did not contain the exact recovery permission set.');

    $switch($actors['full']);
    $originalRecoveryRoleIds = $userRoleSet($actors['full']);
    $capabilityRepository = new RolesRepository($app->database());
    $assert($capabilityRepository->effectivePermissionMatchCount($actors['full'], $recovery) === count($recovery),
        'Union actor was not administrator-capable before split-role replacement.');

    $singleSplitFailure = $request('POST', $url('users/' . $actors['full'] . '/roles'), $csrf([
        'role_ids_present' => '1',
        'role_ids' => [(string) $unionRoleA],
    ]));
    $assert($status($singleSplitFailure) === 422
        && str_contains($content($singleSplitFailure), 'You cannot remove your own administrator recovery access.'),
        'A single incomplete split role did not trigger actor self-protection.');
    $assert($userRoleSet($actors['full']) === $originalRecoveryRoleIds,
        'Single split-role failure changed the actor assignment set.');

    $unionSuccess = $request('POST', $url('users/' . $actors['full'] . '/roles'), $csrf([
        'role_ids_present' => '1',
        'role_ids' => [(string) $unionRoleA, (string) $unionRoleB],
    ]));
    $assert($status($unionSuccess) === 302
        && $location($unionSuccess) === $url('users/' . $actors['full'] . '/edit') . '?notice=roles',
        'Capability-sensitive custom-role union replacement did not redirect successfully.');
    $assert($userRoleSet($actors['full']) === [$unionRoleA, $unionRoleB],
        'Capability-sensitive custom-role union did not persist the exact split-role set.');
    $assert(array_intersect($originalRecoveryRoleIds, $userRoleSet($actors['full'])) === [],
        'Original full-recovery role remained assigned after split-role replacement.');
    $assert($capabilityRepository->effectivePermissionMatchCount($actors['full'], $recovery) === count($recovery),
        'Actor was not administrator-capable through the resulting multi-role permission union.');

    $escapedUserRoleId = $createRole('User role <name>', 'user-role-<slug>-' . bin2hex(random_bytes(3)));
    $escapedUserDetails = $request('GET', $url('users/' . $actors['full'] . '/edit'));
    $assert(str_contains($content($escapedUserDetails), 'User role &lt;name&gt;')
        && str_contains($content($escapedUserDetails), 'user-role-&lt;slug&gt;-'),
        'User Details role name or slug was not escaped.');

    echo "M3.1 Batch 3 roles integration passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if ($db->inTransaction()) $db->rollBack();
}
