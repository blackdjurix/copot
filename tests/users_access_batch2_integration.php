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
session_id('copotm31batch2' . bin2hex(random_bytes(5)));

require $basePath . '/bootstrap/autoload.php';

Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$responseValue = static function (Response $response, string $property): mixed {
    return (new ReflectionProperty($response, $property))->getValue($response);
};
$statusOf = static fn (Response $response): int => (int) $responseValue($response, 'status');
$contentOf = static fn (Response $response): string => (string) $responseValue($response, 'content');
$locationOf = static function (Response $response) use ($responseValue): ?string {
    $headers = $responseValue($response, 'headers');

    return is_array($headers) && is_string($headers['Location'] ?? null) ? $headers['Location'] : null;
};

$app = new Application($basePath);
$app->session()->start();
require $basePath . '/routes/web.php';
require $basePath . '/routes/auth.php';
require $basePath . '/routes/admin.php';
require $basePath . '/modules/users-access/routes.php';
require $basePath . '/routes/admin_fallback.php';

$connection = $app->database()->connection();
$connection->beginTransaction();
$passwords = new PasswordHasher();
$sessionKey = (string) $app->config()->get('auth.session_key', '_copot_user_id');
$adminBase = $app->adminUrl()->baseUrl();
$usersPath = $app->adminUrl()->childUrl('users');
$suffix = bin2hex(random_bytes(8));

try {
$permissionNames = [
    'admin.access' => 'Access admin shell',
    'users.read' => 'Read users',
    'users.create' => 'Create users',
    'users.update' => 'Update users',
    'users.password.manage' => 'Manage user passwords',
    'users.status.manage' => 'Manage user status',
    'roles.read' => 'Read roles and permissions',
    'roles.manage' => 'Manage roles',
    'users.roles.manage' => 'Manage user roles',
    'roles.permissions.manage' => 'Manage role permissions',
];
$permissionIds = [];

foreach ($permissionNames as $slug => $name) {
    $statement = $connection->prepare('SELECT id FROM permissions WHERE slug = :slug LIMIT 1');
    $statement->execute(['slug' => $slug]);
    $permissionId = $statement->fetchColumn();

    if (!is_numeric($permissionId)) {
        $statement = $connection->prepare(
            'INSERT INTO permissions (name, slug, created_at, updated_at) VALUES (:name, :slug, NOW(), NOW())'
        );
        $statement->execute(['name' => $name, 'slug' => $slug]);
        $permissionId = $connection->lastInsertId();
    }

    $permissionIds[$slug] = (int) $permissionId;
}

$createActor = static function (string $label, array $permissions) use (
    $connection,
    $passwords,
    $permissionIds,
    $suffix
): int {
    $email = strtolower($label) . '-' . bin2hex(random_bytes(4)) . '-' . $suffix . '@example.test';
    $statement = $connection->prepare(
        "INSERT INTO users (name, email, password_hash, status, created_at, updated_at)
        VALUES (:name, :email, :password_hash, 'active', NOW(), NOW())"
    );
    $statement->execute([
        'name' => $label,
        'email' => $email,
        'password_hash' => $passwords->make('Integration actor password'),
    ]);
    $userId = (int) $connection->lastInsertId();
    $roleSlug = 'm31-' . strtolower($label) . '-' . bin2hex(random_bytes(4));
    $connection->prepare(
        'INSERT INTO roles (name, slug, created_at, updated_at) VALUES (:name, :slug, NOW(), NOW())'
    )->execute(['name' => $label . ' role', 'slug' => $roleSlug]);
    $roleId = (int) $connection->lastInsertId();
    $rolePermission = $connection->prepare(
        'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)'
    );

    foreach ($permissions as $permission) {
        $rolePermission->execute(['role_id' => $roleId, 'permission_id' => $permissionIds[$permission]]);
    }

    $connection->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')
        ->execute(['user_id' => $userId, 'role_id' => $roleId]);

    return $userId;
};

$actors = [
    'no_admin' => $createActor('NoAdmin', ['users.read']),
    'admin_only' => $createActor('AdminOnly', ['admin.access']),
    'read' => $createActor('Read', ['admin.access', 'users.read']),
    'create' => $createActor('Create', ['admin.access', 'users.create']),
    'read_create' => $createActor('ReadCreate', ['admin.access', 'users.read', 'users.create']),
    'read_update' => $createActor('ReadUpdate', ['admin.access', 'users.read', 'users.update']),
    'read_password' => $createActor('ReadPassword', ['admin.access', 'users.read', 'users.password.manage']),
    'read_status' => $createActor('ReadStatus', ['admin.access', 'users.read', 'users.status.manage']),
    'full' => $createActor('Full', array_keys($permissionNames)),
];

$switchUser = static function (int $userId) use ($app, $sessionKey): void {
    $app->auth()->logout();
    $app->session()->set($sessionKey, $userId);
};
$request = static function (
    string $method,
    string $path,
    array $input = [],
    array $query = []
) use ($app): Response {
    return $app->run(new Request($method, $path, $query, $input));
};
$csrfInput = static fn (array $input = []): array => ['_token' => $app->session()->csrfToken(), ...$input];

    $routeSource = (string) file_get_contents($basePath . '/modules/users-access/routes.php');
    $viewSources = implode('', array_map(
        static fn (string $file): string => (string) file_get_contents($file),
        glob($basePath . '/modules/users-access/views/admin/*.php') ?: []
    ));
    $assert(!preg_match('/[\'\"]\/admin(?:[\'\"\/]|$)/', $routeSource . $viewSources),
        'Users module contains a literal Admin runtime path.');
    $assert(str_contains($routeSource, 'childUrl'), 'Users routes do not use the configured Admin URL service.');

    $alternateConfigDirectory = sys_get_temp_dir()
        . DIRECTORY_SEPARATOR
        . 'copot-m31-admin-path-'
        . bin2hex(random_bytes(6));

    if (!mkdir($alternateConfigDirectory, 0777, true) && !is_dir($alternateConfigDirectory)) {
        throw new RuntimeException('Unable to create alternate Admin path fixture directory.');
    }

    try {
        $alternateConfigFile = $alternateConfigDirectory . DIRECTORY_SEPARATOR . 'admin.php';

        if (file_put_contents($alternateConfigFile, "<?php\nreturn ['path' => 'dapur'];\n") === false) {
            throw new RuntimeException('Unable to create alternate Admin path fixture config.');
        }

        $alternateAdminUrl = new AdminUrl(new Config($alternateConfigDirectory));
        $alternateNavigation = new AdminNavigation();
        $alternateRouter = new Router();
        $alternateView = new View($basePath . '/resources/views');
        $alternatePages = new AdminPageRenderer(
            $alternateView,
            $alternateAdminUrl,
            $alternateNavigation,
            'Copot',
            'copot',
            'en'
        );
        $alternateErrors = new AdminErrorRenderer(
            $alternateView,
            $alternatePages,
            $alternateAdminUrl,
            $app->auth(),
            $app->csrf(),
            'admin.access'
        );
        $alternateApp = new class(
            $app,
            $alternateRouter,
            $alternateNavigation,
            $alternateAdminUrl,
            $alternatePages,
            $alternateErrors
        ) {
            public function __construct(
                private Application $application,
                private Router $router,
                private AdminNavigation $navigation,
                private AdminUrl $adminUrl,
                private AdminPageRenderer $pages,
                private AdminErrorRenderer $errors
            ) {
            }

            public function database()
            {
                return $this->application->database();
            }

            public function session()
            {
                return $this->application->session();
            }

            public function csrf()
            {
                return $this->application->csrf();
            }

            public function auth()
            {
                return $this->application->auth();
            }

            public function router(): Router
            {
                return $this->router;
            }

            public function adminNavigation(): AdminNavigation
            {
                return $this->navigation;
            }

            public function adminUrl(): AdminUrl
            {
                return $this->adminUrl;
            }

            public function adminPageRenderer(): AdminPageRenderer
            {
                return $this->pages;
            }

            public function adminErrors(): AdminErrorRenderer
            {
                return $this->errors;
            }
        };

        (static function ($app) use ($basePath): void {
            require $basePath . '/modules/users-access/routes.php';
        })($alternateApp);

        $app->auth()->logout();
        $alternateGuest = $alternateRouter->dispatch(new Request('GET', '/dapur/users'));
        $assert($statusOf($alternateGuest) === 302, 'Alternate-path Users list route was not registered.');
        $assert($locationOf($alternateGuest) === '/dapur',
            'Alternate-path guest request did not redirect to configured Admin base.');
        $assert($statusOf($alternateRouter->dispatch(new Request('GET', '/admin/users'))) === 404,
            'Users list route remained registered under the default Admin path.');

        $switchUser($actors['read']);
        $alternateList = $alternateRouter->dispatch(new Request('GET', '/dapur/users'));
        $assert($statusOf($alternateList) === 200, 'Authorized alternate-path Users list failed.');
        $assert(str_contains($contentOf($alternateList), 'User accounts'),
            'Authorized alternate-path Users page did not render.');
        $assert(str_contains($contentOf($alternateList), 'href="/dapur/users"'),
            'Alternate-path Users navigation URL was incorrect.');
        $assert(!str_contains($contentOf($alternateList), 'href="/admin/users"'),
            'Alternate-path Users response fell back to default navigation URL.');

        $switchUser($actors['full']);
        $alternateCreate = $alternateRouter->dispatch(new Request('GET', '/dapur/users/create'));
        $assert($statusOf($alternateCreate) === 200, 'Alternate-path create form failed.');
        $assert(str_contains($contentOf($alternateCreate), 'action="/dapur/users"'),
            'Alternate-path create form action was incorrect.');
        $assert(!str_contains($contentOf($alternateCreate), 'action="/admin/users"'),
            'Alternate-path create form fell back to the default Admin path.');
    } finally {
        if (isset($alternateConfigFile) && is_file($alternateConfigFile)) {
            unlink($alternateConfigFile);
        }

        if (is_dir($alternateConfigDirectory)) {
            rmdir($alternateConfigDirectory);
        }
    }

    $app->auth()->logout();
    $guest = $request('GET', $usersPath);
    $assert($statusOf($guest) === 302, 'Guest list request was not redirected.');
    $assert($locationOf($guest) === $adminBase, 'Guest list redirect ignored configured Admin base URL.');

    $switchUser($actors['no_admin']);
    $assert($statusOf($request('GET', $usersPath)) === 403, 'Missing admin.access was not denied.');
    $switchUser($actors['admin_only']);
    $assert($statusOf($request('GET', $usersPath)) === 403, 'Missing users.read was not denied.');
    $switchUser($actors['read']);
    $assert($statusOf($request('GET', $app->adminUrl()->childUrl('users/create'))) === 403,
        'Missing users.create was not denied.');
    $assert($statusOf($request('POST', $app->adminUrl()->childUrl('users/' . $actors['read']))) === 403,
        'Missing users.update was not denied before CSRF.');
    $assert($statusOf($request('POST', $app->adminUrl()->childUrl('users/' . $actors['read'] . '/password'))) === 403,
        'Missing users.password.manage was not denied before CSRF.');
    $assert($statusOf($request('POST', $app->adminUrl()->childUrl('users/' . $actors['read'] . '/status'))) === 403,
        'Missing users.status.manage was not denied before CSRF.');

    $list = $request('GET', $usersPath);
    $assert($statusOf($list) === 200, 'Authorized user list failed.');
    $assert(str_contains($contentOf($list), 'Read'), 'User list omitted safe fixture data.');
    $assert(str_contains($contentOf($list), 'href="' . $usersPath . '"'),
        'Users navigation was not visible with users.read.');
    $assert(!str_contains($contentOf($list), '>Roles<'), 'Roles navigation leaked into Batch 2.');
    $switchUser($actors['admin_only']);
    $dashboard = $request('GET', $adminBase);
    $assert(!str_contains($contentOf($dashboard), 'href="' . $usersPath . '"'),
        'Users navigation was visible without users.read.');

    $switchUser($actors['read_create']);
    $createForm = $request('GET', $app->adminUrl()->childUrl('users/create'));
    $assert($statusOf($createForm) === 200, 'Create form did not render.');
    $assert(str_contains($contentOf($createForm), 'name="status" value="inactive"'),
        'Create form without status permission did not fix status to inactive.');
    $assert(!str_contains($contentOf($createForm), '<option value="active"'),
        'Create form exposed active status without status permission.');

    $newPassword = 'Integration user password ' . $suffix;
    $unauthorizedActive = $request('POST', $usersPath, $csrfInput([
        'name' => 'Unauthorized Active',
        'email' => "unauthorized-active-{$suffix}@example.test",
        'password' => $newPassword,
        'password_confirmation' => $newPassword,
        'status' => 'active',
    ]));
    $assert($statusOf($unauthorizedActive) === 422, 'Unauthorized active creation was not rejected.');
    $assert(str_contains($contentOf($unauthorizedActive), 'not authorized'),
        'Unauthorized active creation lacked controlled feedback.');

    $invalidMarker = 'never-repopulate-' . $suffix;
    $invalidCreate = $request('POST', $usersPath, $csrfInput([
        'name' => '',
        'email' => 'invalid-email',
        'password' => $invalidMarker,
        'password_confirmation' => 'different-' . $invalidMarker,
    ]));
    $assert($statusOf($invalidCreate) === 422, 'Invalid create did not render 422.');
    $assert(str_contains($contentOf($invalidCreate), 'admin-shell'), 'Create validation did not render in Admin shell.');
    $assert(!str_contains($contentOf($invalidCreate), $invalidMarker), 'Create validation repopulated a password.');

    $switchUser($actors['full']);
    $activeEmail = "active-created-{$suffix}@example.test";
    $activeCreate = $request('POST', $usersPath, $csrfInput([
        'name' => 'Active Created',
        'email' => $activeEmail,
        'password' => $newPassword,
        'password_confirmation' => $newPassword,
        'status' => 'active',
    ]));
    $assert($statusOf($activeCreate) === 302, 'Authorized active creation failed.');
    $activeCreatedId = (int) $connection->query(
        "SELECT id FROM users WHERE email = " . $connection->quote($activeEmail)
    )->fetchColumn();
    $assert($activeCreatedId > 0, 'Authorized active create did not persist a user.');
    $assert($locationOf($activeCreate) === $app->adminUrl()->childUrl('users/' . $activeCreatedId . '/edit') . '?notice=created',
        'Create redirect did not target the configured edit path.');

    $duplicateCreate = $request('POST', $usersPath, $csrfInput([
        'name' => 'Duplicate',
        'email' => strtoupper($activeEmail),
        'password' => $newPassword,
        'password_confirmation' => $newPassword,
        'status' => 'inactive',
    ]));
    $assert($statusOf($duplicateCreate) === 422, 'Duplicate create did not render 422.');
    $assert(str_contains($contentOf($duplicateCreate), 'Email is already in use.'),
        'Duplicate create lacked safe email feedback.');

    $switchUser($actors['create']);
    $inactiveEmail = "no-read-created-{$suffix}@example.test";
    $noReadCreate = $request('POST', $usersPath, $csrfInput([
        'name' => 'No Read Created',
        'email' => $inactiveEmail,
        'password' => $newPassword,
        'password_confirmation' => $newPassword,
    ]));
    $assert($statusOf($noReadCreate) === 302 && $locationOf($noReadCreate) === $adminBase,
        'Create without users.read did not redirect to Admin base.');

    $switchUser($actors['full']);
    $editPath = $app->adminUrl()->childUrl('users/' . $activeCreatedId . '/edit');
    $edit = $request('GET', $editPath);
    $assert($statusOf($edit) === 200 && str_contains($contentOf($edit), 'Active Created'),
        'Edit target did not render.');
    $editHtml = $contentOf($edit);
    $primaryStart = strpos($editHtml, 'admin-user-detail-column--primary');
    $secondaryStart = strpos($editHtml, 'admin-user-detail-column--secondary');
    $primaryHtml = ($primaryStart !== false && $secondaryStart !== false && $secondaryStart > $primaryStart)
        ? substr($editHtml, $primaryStart, $secondaryStart - $primaryStart)
        : '';
    $secondaryHtml = $secondaryStart !== false ? substr($editHtml, $secondaryStart) : '';
    $assert(str_contains($editHtml, 'admin-user-detail-layout')
        && $primaryStart !== false
        && $secondaryStart !== false,
        'User Detail did not use the Role Detail two-column layout pattern.');
    $assert(str_contains($primaryHtml, 'user-identity-title')
        && str_contains($primaryHtml, 'user-roles-title')
        && !str_contains($primaryHtml, 'user-summary-title'),
        'User Detail primary column does not contain only identity and role assignment sections.');
    $assert(str_contains($secondaryHtml, 'user-summary-title')
        && str_contains($secondaryHtml, 'user-password-title')
        && str_contains($secondaryHtml, 'user-status-title')
        && !str_contains($primaryHtml, 'user-password-title')
        && !str_contains($primaryHtml, 'user-status-title'),
        'User Detail secondary column does not contain summary, password, and status sections.');
    $assert(str_contains($editHtml, 'name="role_ids[]"')
        && str_contains($editHtml, 'name="password"')
        && str_contains($editHtml, 'name="password_confirmation"')
        && str_contains($editHtml, 'name="status"')
        && str_contains($editHtml, 'name="_token"'),
        'User Detail form and role checkbox contracts were not preserved.');
    $roleOptionCount = substr_count($editHtml, 'class="admin-user-role-option"');
    $roleCheckboxCount = substr_count($editHtml, 'name="role_ids[]"');
    $assert($roleOptionCount > 0
        && $roleOptionCount === $roleCheckboxCount
        && str_contains($editHtml, 'admin-user-role-option__header')
        && str_contains($editHtml, 'admin-user-role-option__title')
        && str_contains($editHtml, 'admin-user-role-option__meta')
        && str_contains($editHtml, 'role_ids_present'),
        'User role options did not retain compact markup, metadata, and checkbox inputs.');
    $roleStatement = $connection->prepare(
        "SELECT id FROM roles WHERE slug LIKE :slug ORDER BY id DESC LIMIT 1"
    );
    $roleStatement->execute(['slug' => 'm31-full-%']);
    $fullRoleId = (int) $roleStatement->fetchColumn();
    $roleEdit = $request('GET', $app->adminUrl()->childUrl('roles/' . $fullRoleId . '/edit'));
    $roleEditHtml = $contentOf($roleEdit);
    $assert($fullRoleId > 0 && $statusOf($roleEdit) === 200
        && str_contains($roleEditHtml, 'admin-permission-option__header')
        && str_contains($roleEditHtml, 'admin-permission-option__title')
        && str_contains($roleEditHtml, 'admin-permission-option__slug')
        && str_contains($roleEditHtml, 'name="permission_ids[]"')
        && str_contains($roleEditHtml, 'name="permission_ids_present"')
        && str_contains($roleEditHtml, 'name="_token"'),
        'Role Detail permission options did not retain the compact title/slug row and form contract.');
    $missing = $request('GET', $app->adminUrl()->childUrl('users/999999999/edit'));
    $assert($statusOf($missing) === 404, 'Missing edit target did not use shared 404.');
    $assert(str_contains($contentOf($missing), 'admin-shell'), 'Shared 404 did not render in Admin shell.');

    foreach ([
        $usersPath => ['name' => 'CSRF', 'email' => 'csrf@example.test', 'password' => $newPassword, 'password_confirmation' => $newPassword],
        $app->adminUrl()->childUrl('users/' . $activeCreatedId) => ['name' => 'CSRF', 'email' => $activeEmail],
        $app->adminUrl()->childUrl('users/' . $activeCreatedId . '/password') => ['password' => $newPassword, 'password_confirmation' => $newPassword],
        $app->adminUrl()->childUrl('users/' . $activeCreatedId . '/status') => ['status' => 'inactive'],
    ] as $path => $input) {
        $input['_token'] = 'invalid';
        $csrfFailure = $request('POST', $path, $input);
        $assert($statusOf($csrfFailure) === 419, "CSRF rejection failed for [{$path}].");
        $assert(str_contains($contentOf($csrfFailure), 'admin-shell'),
            "CSRF rejection did not render in Admin shell for [{$path}].");
    }

    $invalidIdentity = $request('POST', $app->adminUrl()->childUrl('users/' . $activeCreatedId), $csrfInput([
        'name' => '',
        'email' => 'invalid',
    ]));
    $assert($statusOf($invalidIdentity) === 422, 'Invalid identity did not render 422.');
    $assert(str_contains($contentOf($invalidIdentity), 'Admin Created') === false,
        'Identity validation rendered unrelated unsafe state.');

    $updatedEmail = "updated-created-{$suffix}@example.test";
    $identityUpdate = $request('POST', $app->adminUrl()->childUrl('users/' . $activeCreatedId), $csrfInput([
        'name' => 'Updated Created',
        'email' => $updatedEmail,
    ]));
    $assert($statusOf($identityUpdate) === 302, 'Identity update failed.');
    $assert((string) $connection->query("SELECT name FROM users WHERE id = {$activeCreatedId}")->fetchColumn() === 'Updated Created',
        'Identity update was not persisted.');

    $passwordMarker = 'invalid-password-' . $suffix;
    $invalidPassword = $request(
        'POST',
        $app->adminUrl()->childUrl('users/' . $activeCreatedId . '/password'),
        $csrfInput(['password' => $passwordMarker, 'password_confirmation' => 'mismatch-' . $passwordMarker])
    );
    $assert($statusOf($invalidPassword) === 422, 'Invalid password change did not render 422.');
    $assert(!str_contains($contentOf($invalidPassword), $passwordMarker),
        'Password validation repopulated plaintext password data.');

    $changedPassword = 'Changed integration password ' . $suffix;
    $passwordChange = $request('POST', $app->adminUrl()->childUrl('users/' . $activeCreatedId . '/password'), $csrfInput([
        'password' => $changedPassword,
        'password_confirmation' => $changedPassword,
    ]));
    $assert($statusOf($passwordChange) === 302, 'Password change failed.');
    $changedHash = (string) $connection->query(
        "SELECT password_hash FROM users WHERE id = {$activeCreatedId}"
    )->fetchColumn();
    $assert($passwords->verify($changedPassword, $changedHash), 'Password change did not persist a compatible hash.');

    $invalidStatus = $request('POST', $app->adminUrl()->childUrl('users/' . $activeCreatedId . '/status'), $csrfInput([
        'status' => 'Active',
    ]));
    $assert($statusOf($invalidStatus) === 422, 'Invalid status did not render 422.');
    $statusChange = $request('POST', $app->adminUrl()->childUrl('users/' . $activeCreatedId . '/status'), $csrfInput([
        'status' => 'inactive',
    ]));
    $assert($statusOf($statusChange) === 302, 'Ordinary status change failed.');
    $assert((string) $connection->query("SELECT status FROM users WHERE id = {$activeCreatedId}")->fetchColumn() === 'inactive',
        'Ordinary status change was not persisted.');
    $statusActivation = $request('POST', $app->adminUrl()->childUrl('users/' . $activeCreatedId . '/status'), $csrfInput([
        'status' => 'active',
    ]));
    $assert($statusOf($statusActivation) === 302, 'Ordinary account activation failed.');
    $assert((string) $connection->query("SELECT status FROM users WHERE id = {$activeCreatedId}")->fetchColumn() === 'active',
        'Ordinary account activation was not persisted.');

    $selfDeactivation = $request('POST', $app->adminUrl()->childUrl('users/' . $actors['full'] . '/status'), $csrfInput([
        'status' => 'inactive',
    ]));
    $assert($statusOf($selfDeactivation) === 422, 'Self-deactivation was not rejected.');
    $adminTargetDeactivation = $request('POST', $app->adminUrl()->childUrl('users/' . $actors['read'] . '/status'), $csrfInput([
        'status' => 'inactive',
    ]));
    $assert($statusOf($adminTargetDeactivation) === 302,
        'Safe admin.access-only target deactivation was rejected.');
    $assert((string) $connection->query(
        'SELECT status FROM users WHERE id = ' . (int) $actors['read']
    )->fetchColumn() === 'inactive', 'Safe admin.access-only target deactivation was not persisted.');

    echo "M3.1 Batch 2 integration tests passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
