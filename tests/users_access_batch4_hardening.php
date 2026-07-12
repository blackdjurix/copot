<?php

declare(strict_types=1);

use Copot\Core\Application;
use Copot\Core\Env;
use Copot\Core\PasswordHasher;
use Copot\Core\Request;
use Copot\Core\Response;

$basePath = dirname(__DIR__);

chdir($basePath);
session_save_path(sys_get_temp_dir());
session_id('copotm31batch4' . bin2hex(random_bytes(5)));

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

$app = new Application($basePath);
$app->session()->start();

require $basePath . '/routes/web.php';
require $basePath . '/routes/auth.php';
require $basePath . '/routes/admin.php';
require $basePath . '/modules/content/routes.php';
require $basePath . '/modules/taxonomy/routes.php';
require $basePath . '/modules/users-access/routes.php';
require $basePath . '/routes/admin_fallback.php';

$connection = $app->database()->connection();
$passwords = new PasswordHasher();
$sessionKey = (string) $app->config()->get('auth.session_key', '_copot_user_id');
$adminUrl = static fn (string $path = ''): string => $app->adminUrl()->childUrl($path);
$suffix = bin2hex(random_bytes(8));

$connection->beginTransaction();

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
        'settings.update' => 'Update settings',
        'content.create' => 'Create content',
        'taxonomy.create' => 'Create taxonomy',
    ];
    $permissionIds = [];

    foreach ($permissionNames as $slug => $name) {
        $statement = $connection->prepare('SELECT id FROM permissions WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $slug]);
        $permissionId = $statement->fetchColumn();

        if (!is_numeric($permissionId)) {
            $statement = $connection->prepare(
                'INSERT INTO permissions (name, slug, created_at, updated_at)
                VALUES (:name, :slug, NOW(), NOW())'
            );
            $statement->execute(['name' => $name, 'slug' => $slug]);
            $permissionId = $connection->lastInsertId();
        }

        $permissionIds[$slug] = (int) $permissionId;
    }

    $createActor = static function (
        string $label,
        array $permissions
    ) use ($connection, $passwords, $permissionIds, $suffix): int {
        $email = strtolower($label) . '-' . bin2hex(random_bytes(4)) . '-' . $suffix . '@example.test';
        $statement = $connection->prepare(
            "INSERT INTO users (name, email, password_hash, status, created_at, updated_at)
            VALUES (:name, :email, :password_hash, 'active', NOW(), NOW())"
        );
        $statement->execute([
            'name' => $label,
            'email' => $email,
            'password_hash' => $passwords->make('M3.1 Batch 4 actor password'),
        ]);
        $userId = (int) $connection->lastInsertId();

        $statement = $connection->prepare(
            'INSERT INTO roles (name, slug, created_at, updated_at)
            VALUES (:name, :slug, NOW(), NOW())'
        );
        $statement->execute([
            'name' => $label . ' role',
            'slug' => 'm31-b4-' . strtolower($label) . '-' . bin2hex(random_bytes(4)),
        ]);
        $roleId = (int) $connection->lastInsertId();

        $assignPermission = $connection->prepare(
            'INSERT INTO role_permissions (role_id, permission_id)
            VALUES (:role_id, :permission_id)'
        );

        foreach ($permissions as $permission) {
            $assignPermission->execute([
                'role_id' => $roleId,
                'permission_id' => $permissionIds[$permission],
            ]);
        }

        $connection->prepare(
            'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
        )->execute(['user_id' => $userId, 'role_id' => $roleId]);

        return $userId;
    };

    $actors = [
        'full' => $createActor('Full', array_keys($permissionNames)),
        'users_read' => $createActor('UsersRead', ['admin.access', 'users.read']),
        'settings' => $createActor('Settings', ['admin.access', 'settings.update']),
        'content' => $createActor('Content', ['admin.access', 'content.create']),
        'taxonomy' => $createActor('Taxonomy', ['admin.access', 'taxonomy.create']),
    ];

    $switchUser = static function (int $userId) use ($app, $sessionKey): void {
        $app->auth()->logout();
        $app->session()->set($sessionKey, $userId);
    };

    $request = static function (
        string $method,
        string $path,
        array $post = [],
        array $query = []
    ) use ($app): Response {
        return $app->run(new Request($method, $path, $query, $post));
    };

    $csrf = static fn (array $post = []): array => [
        '_token' => $app->session()->csrfToken(),
        ...$post,
    ];

    $targetEmail = 'batch4-target-' . $suffix . '@example.test';
    $originalPassword = 'M3.1 Batch 4 original password ' . $suffix;
    $originalHash = $passwords->make($originalPassword);
    $connection->prepare(
        "INSERT INTO users (name, email, password_hash, status, created_at, updated_at)
        VALUES (:name, :email, :password_hash, 'active', NOW(), NOW())"
    )->execute([
        'name' => 'Batch 4 target',
        'email' => $targetEmail,
        'password_hash' => $originalHash,
    ]);
    $targetId = (int) $connection->lastInsertId();

    $switchUser($actors['full']);

    /*
     * Pass 1: missing and invalid CSRF must reject every Users mutation
     * before any persistent change.
     */
    foreach (['missing' => null, 'invalid' => 'invalid-token'] as $case => $token) {
        $createEmail = "batch4-csrf-create-{$case}-{$suffix}@example.test";
        $createPost = [
            'name' => 'Blocked create',
            'email' => $createEmail,
            'password' => 'Blocked create password ' . $suffix,
            'password_confirmation' => 'Blocked create password ' . $suffix,
            'status' => 'inactive',
        ];

        if ($token !== null) {
            $createPost['_token'] = $token;
        }

        $createResponse = $request('POST', $adminUrl('users'), $createPost);
        $createStatus = $statusOf($createResponse);
        $createBody = trim(strip_tags($contentOf($createResponse)));
        $assert(
            $createStatus === 419,
            "{$case} CSRF user create returned {$createStatus}, expected 419. Body: "
                . substr(preg_replace('/\\s+/', ' ', $createBody) ?? '', 0, 300)
        );
        $assert(
            str_contains($contentOf($createResponse), 'admin-shell'),
            "{$case} CSRF user create did not render in Admin shell."
        );
        $statement = $connection->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $statement->execute(['email' => $createEmail]);
        $assert((int) $statement->fetchColumn() === 0, "{$case} CSRF user create persisted a user.");

        $beforeIdentity = $connection->query(
            'SELECT name, email FROM users WHERE id = ' . $targetId
        )->fetch(PDO::FETCH_ASSOC);
        $identityPost = [
            'name' => 'Blocked identity',
            'email' => "blocked-identity-{$case}-{$suffix}@example.test",
        ];

        if ($token !== null) {
            $identityPost['_token'] = $token;
        }

        $identityResponse = $request('POST', $adminUrl("users/{$targetId}"), $identityPost);
        $assert($statusOf($identityResponse) === 419, "{$case} CSRF identity update was not rejected.");
        $afterIdentity = $connection->query(
            'SELECT name, email FROM users WHERE id = ' . $targetId
        )->fetch(PDO::FETCH_ASSOC);
        $assert($afterIdentity === $beforeIdentity, "{$case} CSRF identity update changed persisted state.");

        $beforeHash = (string) $connection->query(
            'SELECT password_hash FROM users WHERE id = ' . $targetId
        )->fetchColumn();
        $passwordPost = [
            'password' => 'Blocked password ' . $case . $suffix,
            'password_confirmation' => 'Blocked password ' . $case . $suffix,
        ];

        if ($token !== null) {
            $passwordPost['_token'] = $token;
        }

        $passwordResponse = $request(
            'POST',
            $adminUrl("users/{$targetId}/password"),
            $passwordPost
        );
        $assert($statusOf($passwordResponse) === 419, "{$case} CSRF password update was not rejected.");
        $afterHash = (string) $connection->query(
            'SELECT password_hash FROM users WHERE id = ' . $targetId
        )->fetchColumn();
        $assert($afterHash === $beforeHash, "{$case} CSRF password update changed persisted hash.");

        $beforeStatus = (string) $connection->query(
            'SELECT status FROM users WHERE id = ' . $targetId
        )->fetchColumn();
        $statusPost = ['status' => 'inactive'];

        if ($token !== null) {
            $statusPost['_token'] = $token;
        }

        $statusResponse = $request(
            'POST',
            $adminUrl("users/{$targetId}/status"),
            $statusPost
        );
        $assert($statusOf($statusResponse) === 419, "{$case} CSRF status update was not rejected.");
        $afterStatus = (string) $connection->query(
            'SELECT status FROM users WHERE id = ' . $targetId
        )->fetchColumn();
        $assert($afterStatus === $beforeStatus, "{$case} CSRF status update changed persisted status.");
    }

    /*
     * Pass 2: hostile values must be escaped across Users list,
     * User Details, validation responses, and the role summary.
     */
    $hostileName = 'User <script>alert("user")</script>';
    $hostileEmail = 'hostile+<tag>-' . $suffix . '@example.test';
    $connection->prepare(
        'UPDATE users SET name = :name, email = :email, updated_at = NOW() WHERE id = :id'
    )->execute([
        'id' => $targetId,
        'name' => $hostileName,
        'email' => $hostileEmail,
    ]);

    $hostileRoleName = 'Role <img src=x onerror=alert(1)>';
    $connection->prepare(
        'INSERT INTO roles (name, slug, created_at, updated_at)
        VALUES (:name, :slug, NOW(), NOW())'
    )->execute([
        'name' => $hostileRoleName,
        'slug' => 'hostile-role-' . $suffix,
    ]);
    $hostileRoleId = (int) $connection->lastInsertId();
    $connection->prepare(
        'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
    )->execute(['user_id' => $targetId, 'role_id' => $hostileRoleId]);

    $usersList = $request('GET', $adminUrl('users'));
    $assert($statusOf($usersList) === 200, 'Users list did not render for escaping verification.');
    $usersListContent = $contentOf($usersList);
    $assert(
        str_contains($usersListContent, htmlspecialchars($hostileName, ENT_QUOTES, 'UTF-8')),
        'Hostile user name was not escaped in Users list.'
    );
    $assert(!str_contains($usersListContent, '<script>alert("user")</script>'), 'Raw script leaked in Users list.');
    $assert(
        str_contains($usersListContent, htmlspecialchars($hostileEmail, ENT_QUOTES, 'UTF-8')),
        'Hostile user email was not escaped in Users list.'
    );

    $userDetails = $request('GET', $adminUrl("users/{$targetId}/edit"));
    $assert($statusOf($userDetails) === 200, 'User Details did not render for escaping verification.');
    $userDetailsContent = $contentOf($userDetails);
    $assert(
        str_contains($userDetailsContent, htmlspecialchars($hostileName, ENT_QUOTES, 'UTF-8')),
        'Hostile user name was not escaped in User Details.'
    );
    $assert(
        str_contains($userDetailsContent, htmlspecialchars($hostileEmail, ENT_QUOTES, 'UTF-8')),
        'Hostile user email was not escaped in User Details.'
    );
    $assert(
        str_contains($userDetailsContent, htmlspecialchars($hostileRoleName, ENT_QUOTES, 'UTF-8')),
        'Hostile role name was not escaped in User Details role summary.'
    );
    $assert(!str_contains($userDetailsContent, '<img src=x onerror=alert(1)>'), 'Raw hostile role HTML leaked.');

    $submittedName = 'Submitted <svg onload=alert(1)>';
    $invalidIdentity = $request(
        'POST',
        $adminUrl("users/{$targetId}"),
        $csrf([
            'name' => $submittedName,
            'email' => 'not-an-email',
        ])
    );
    $assert($statusOf($invalidIdentity) === 422, 'Invalid hostile identity did not return 422.');
    $invalidIdentityContent = $contentOf($invalidIdentity);
    $assert(
        str_contains($invalidIdentityContent, htmlspecialchars($submittedName, ENT_QUOTES, 'UTF-8')),
        'Safe submitted hostile identity value was not escaped in validation response.'
    );
    $assert(
        !str_contains($invalidIdentityContent, '<svg onload=alert(1)>'),
        'Raw hostile identity HTML leaked in validation response.'
    );

    $passwordMarker = 'batch4-secret-' . $suffix;
    $invalidPassword = $request(
        'POST',
        $adminUrl("users/{$targetId}/password"),
        $csrf([
            'password' => $passwordMarker,
            'password_confirmation' => 'mismatch-' . $passwordMarker,
        ])
    );
    $assert($statusOf($invalidPassword) === 422, 'Invalid password did not return 422.');
    $assert(
        !str_contains($contentOf($invalidPassword), $passwordMarker),
        'Password validation response exposed submitted password material.'
    );

    /*
     * Pass 3: M3.1 permissions must not disturb existing Settings,
     * Content, or Taxonomy permission behavior.
     */
    $moduleStatus = static function (string $name) use ($connection): ?string {
        $statement = $connection->prepare('SELECT status FROM modules WHERE name = :name LIMIT 1');
        $statement->execute(['name' => $name]);
        $status = $statement->fetchColumn();

        return is_string($status) ? $status : null;
    };

    $switchUser($actors['settings']);
    $settingsResponse = $request('GET', $adminUrl('settings'));
    $assert($statusOf($settingsResponse) === 200, 'Existing Settings authorization regressed.');
    $assert(
        str_contains($contentOf($settingsResponse), 'admin-shell'),
        'Settings page no longer renders through Admin shell.'
    );
    $assert(
        $statusOf($request('GET', $adminUrl('users'))) === 403,
        'settings.update incorrectly granted Users access.'
    );

    if ($moduleStatus('content') === 'enabled') {
        $switchUser($actors['content']);
        $contentResponse = $request('GET', $adminUrl('content'));
        $assert($statusOf($contentResponse) === 200, 'Existing Content authorization regressed.');
        $assert(
            str_contains($contentOf($contentResponse), 'admin-shell'),
            'Content page no longer renders through Admin shell.'
        );
        $assert(
            $statusOf($request('GET', $adminUrl('users'))) === 403,
            'content.create incorrectly granted Users access.'
        );

        $switchUser($actors['users_read']);
        $assert(
            $statusOf($request('GET', $adminUrl('content'))) === 403,
            'users.read incorrectly granted Content access.'
        );
    }

    if ($moduleStatus('taxonomy') === 'enabled') {
        $switchUser($actors['taxonomy']);
        $taxonomyResponse = $request('GET', $adminUrl('taxonomy'));
        $assert($statusOf($taxonomyResponse) === 200, 'Existing Taxonomy authorization regressed.');
        $assert(
            str_contains($contentOf($taxonomyResponse), 'admin-shell'),
            'Taxonomy page no longer renders through Admin shell.'
        );
        $assert(
            $statusOf($request('GET', $adminUrl('users'))) === 403,
            'taxonomy.create incorrectly granted Users access.'
        );

        $switchUser($actors['users_read']);
        $assert(
            $statusOf($request('GET', $adminUrl('taxonomy'))) === 403,
            'users.read incorrectly granted Taxonomy access.'
        );
    }

    $switchUser($actors['users_read']);
    $assert(
        $statusOf($request('GET', $adminUrl('settings'))) === 403,
        'users.read incorrectly granted Settings access.'
    );

    /*
     * Pass 4: representative source-level failure-containment contract.
     * Runtime failure injection remains intentionally excluded until a safe
     * module-local seam exists; destructive DDL is not an acceptable test fixture.
     */
    $routeSource = (string) file_get_contents($basePath . '/modules/users-access/routes.php');
    $assert(
        substr_count($routeSource, 'catch (PDOException)') >= 8,
        'Users & Access routes lost representative PDO failure boundaries.'
    );
    $assert(
        substr_count($routeSource, 'adminErrors()->response($request, 503)') >= 8,
        'Users & Access PDO failures are not consistently mapped to sanitized Admin 503 responses.'
    );
    $assert(
        !str_contains($routeSource, '$exception->getMessage()'),
        'Users & Access routes expose raw exception messages.'
    );

    echo "M3.1 Batch 4 hardening tests passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
