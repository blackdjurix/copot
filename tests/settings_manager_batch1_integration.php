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
session_id('copotm32batch1' . bin2hex(random_bytes(5)));
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$responseValue = static fn (Response $response, string $property): mixed =>
    (new ReflectionProperty($response, $property))->getValue($response);
$statusOf = static fn (Response $response): int => (int) $responseValue($response, 'status');
$contentOf = static fn (Response $response): string => (string) $responseValue($response, 'content');
$headersOf = static fn (Response $response): array => (array) $responseValue($response, 'headers');

$app = new Application($basePath);
$app->session()->start();
$connection = $app->database()->connection();
$createdUserIds = [];
$createdRoleIds = [];
$settingsSnapshot = [];
$moduleSnapshot = null;

foreach ([
    ['site', 'name'],
    ['site', 'tagline'],
    ['localization', 'timezone'],
    ['localization', 'locale'],
    ['localization', 'date_format'],
    ['localization', 'time_format'],
] as [$namespace, $key]) {
    $statement = $connection->prepare(
        'SELECT * FROM settings WHERE namespace = :namespace AND setting_key = :setting_key LIMIT 1'
    );
    $statement->execute(['namespace' => $namespace, 'setting_key' => $key]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $settingsSnapshot[$namespace . '.' . $key] = is_array($row) ? $row : null;
}

$moduleStatement = $connection->prepare('SELECT * FROM modules WHERE name = :name LIMIT 1');
$moduleStatement->execute(['name' => 'settings-manager']);
$moduleRow = $moduleStatement->fetch(PDO::FETCH_ASSOC);
$moduleSnapshot = is_array($moduleRow) ? $moduleRow : null;

try {
    $installedModules = [];

    foreach ($app->modules()->installed() as $module) {
        $installedModules[(string) ($module['name'] ?? '')] = $module;
    }

    if (!isset($installedModules['settings-manager'])) {
        $app->modules()->install('settings-manager');
        $installedModules['settings-manager'] = ['status' => 'disabled'];
    }

    if (($installedModules['settings-manager']['status'] ?? null) !== 'enabled') {
        $app->modules()->enable('settings-manager');
    }

    require $basePath . '/routes/web.php';
    require $basePath . '/routes/auth.php';
    require $basePath . '/routes/admin.php';
    $app->moduleLoader()->loadRoutes($app);
    require $basePath . '/routes/admin_fallback.php';

    $coreRoutes = (string) file_get_contents($basePath . '/routes/admin.php');
    $managerRoutes = (string) file_get_contents($basePath . '/modules/settings-manager/routes.php');
    $assert(!str_contains($coreRoutes, "childUrl('settings')"), 'Core still owns the Settings route.');
    $assert(!str_contains($coreRoutes, "'settings.update'"), 'Core still owns Settings navigation or authorization.');
    $assert(str_contains($managerRoutes, "childUrl('settings')"), 'Settings Manager does not own the Settings route.');
    $assert(str_contains($managerRoutes, "'settings.update'"), 'Settings Manager permission guard is missing.');
    $assert(
        is_file($basePath . '/modules/settings-manager/views/admin/settings.php'),
        'Settings Manager view boundary is missing.'
    );
    $assert(
        !is_file($basePath . '/resources/views/admin/settings.php'),
        'Legacy Core Settings view still exists.'
    );

    $permissionIds = [];

    foreach (['admin.access', 'settings.update'] as $slug) {
        $statement = $connection->prepare('SELECT id FROM permissions WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $slug]);
        $permissionId = $statement->fetchColumn();
        $assert(is_numeric($permissionId), "Required permission [{$slug}] is unavailable.");
        $permissionIds[$slug] = (int) $permissionId;
    }

    $createActor = static function (string $label, array $permissions) use (
        $connection,
        $permissionIds,
        &$createdRoleIds,
        &$createdUserIds
    ): int {
        $suffix = bin2hex(random_bytes(6));
        $connection->prepare(
            "INSERT INTO users (name, email, password_hash, status, created_at, updated_at)
            VALUES (:name, :email, :password_hash, 'active', NOW(), NOW())"
        )->execute([
            'name' => $label,
            'email' => strtolower($label) . '-' . $suffix . '@example.test',
            'password_hash' => (new PasswordHasher())->make('M3.2 Batch 1 password'),
        ]);
        $userId = (int) $connection->lastInsertId();
        $createdUserIds[] = $userId;
        $connection->prepare(
            'INSERT INTO roles (name, slug, created_at, updated_at)
            VALUES (:name, :slug, NOW(), NOW())'
        )->execute([
            'name' => $label . ' role',
            'slug' => 'm32-b1-' . strtolower($label) . '-' . $suffix,
        ]);
        $roleId = (int) $connection->lastInsertId();
        $createdRoleIds[] = $roleId;
        $assign = $connection->prepare(
            'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)'
        );

        foreach ($permissions as $permission) {
            $assign->execute(['role_id' => $roleId, 'permission_id' => $permissionIds[$permission]]);
        }

        $connection->prepare(
            'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
        )->execute(['user_id' => $userId, 'role_id' => $roleId]);

        return $userId;
    };

    $authorizedId = $createActor('SettingsOwner', ['admin.access', 'settings.update']);
    $unauthorizedId = $createActor('AdminOnly', ['admin.access']);
    $sessionKey = (string) $app->config()->get('auth.session_key', '_copot_user_id');
    $settingsPath = $app->adminUrl()->childUrl('settings');
    $switchUser = static function (int $userId) use ($app, $sessionKey): void {
        $app->auth()->logout();
        $app->session()->set($sessionKey, $userId);
    };

    $guest = $app->run(new Request('GET', $settingsPath));
    $assert($statusOf($guest) === 302, 'Guest Settings request did not preserve authentication redirect.');
    $assert(
        ($headersOf($guest)['Location'] ?? null) === $app->adminUrl()->baseUrl(),
        'Guest Settings redirect ignored configured Admin base.'
    );

    $switchUser($unauthorizedId);
    $denied = $app->run(new Request('GET', $settingsPath));
    $assert($statusOf($denied) === 403, 'Missing settings.update was not denied.');

    $switchUser($authorizedId);
    $settings = $app->run(new Request('GET', $settingsPath));
    $assert($statusOf($settings) === 200, 'Manager-owned Settings GET did not render.');
    $assert(str_contains($contentOf($settings), 'admin-shell'), 'Settings GET left the Admin shell.');
    $assert(str_contains($contentOf($settings), 'Site Name'), 'Site settings compatibility was lost.');
    $assert(str_contains($contentOf($settings), 'Localization'), 'Localization settings compatibility was lost.');
    $assert(str_contains($contentOf($settings), 'Upload Logo'), 'Logo compatibility was lost.');
    $assert(str_contains($contentOf($settings), 'Upload Favicon'), 'Favicon compatibility was lost.');
    $assert(str_contains($contentOf($settings), 'action="' . $settingsPath . '"'), 'Settings form ignored configured Admin path.');

    $originalTagline = (string) $app->settings()->get('site', 'tagline');
    $invalidCsrf = $app->run(new Request('POST', $settingsPath, [], [
        '_token' => 'invalid-token',
        'site_name' => 'Batch 1',
        'site_tagline' => 'blocked',
    ]));
    $assert($statusOf($invalidCsrf) === 419, 'Invalid Settings CSRF was not rejected.');
    $assert(
        $app->settings()->get('site', 'tagline') === $originalTagline,
        'Invalid Settings CSRF changed persistent state.'
    );

    $invalid = $app->run(new Request('POST', $settingsPath, [], [
        '_token' => $app->session()->csrfToken(),
        'site_name' => '',
        'site_tagline' => 'must-not-persist',
        'localization_timezone' => 'UTC',
        'localization_locale' => 'en_US',
        'localization_date_format' => 'Y-m-d',
        'localization_time_format' => 'H:i',
    ]));
    $assert($statusOf($invalid) === 422, 'Invalid Settings values did not return 422.');
    $assert(str_contains($contentOf($invalid), 'admin-shell'), 'Settings validation left the Admin shell.');
    $assert(
        $app->settings()->get('site', 'tagline') === $originalTagline,
        'Validation failure changed persistent state.'
    );

    $savedTagline = 'Batch 1 saved ' . bin2hex(random_bytes(4));
    $valid = $app->run(new Request('POST', $settingsPath, [], [
        '_token' => $app->session()->csrfToken(),
        'site_name' => 'Copot Batch 1',
        'site_tagline' => $savedTagline,
        'localization_timezone' => 'UTC',
        'localization_locale' => 'en_US',
        'localization_date_format' => 'Y-m-d',
        'localization_time_format' => 'H:i',
    ]));
    $assert(
        $statusOf($valid) === 302,
        'Valid Settings POST returned ' . $statusOf($valid) . ' instead of redirecting. Body: '
            . substr(trim(strip_tags($contentOf($valid))), 0, 240)
    );
    $assert(
        ($headersOf($valid)['Location'] ?? null) === $settingsPath . '?saved=1',
        'Valid Settings redirect ignored configured Admin path.'
    );
    $assert($app->settings()->get('site', 'tagline') === $savedTagline, 'Valid Settings POST did not persist.');

    $reloaded = $app->run(new Request('GET', $settingsPath));
    $assert($statusOf($reloaded) === 200, 'Settings reload failed after save.');
    $assert(str_contains($contentOf($reloaded), $savedTagline), 'Saved Settings value did not survive reload.');

    $enabledModule = $connection->prepare('SELECT status FROM modules WHERE name = :name LIMIT 1');
    $enabledModule->execute(['name' => 'settings-manager']);
    $assert($enabledModule->fetchColumn() === 'enabled', 'Settings Manager route ownership is not lifecycle-enabled.');

    echo "M3.2 Batch 1 settings integration passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    $connection->beginTransaction();

    try {
        foreach ($createdUserIds as $userId) {
            $connection->prepare('DELETE FROM user_roles WHERE user_id = :user_id')
                ->execute(['user_id' => $userId]);
            $connection->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
        }

        foreach ($createdRoleIds as $roleId) {
            $connection->prepare('DELETE FROM role_permissions WHERE role_id = :role_id')
                ->execute(['role_id' => $roleId]);
            $connection->prepare('DELETE FROM roles WHERE id = :id')->execute(['id' => $roleId]);
        }

        foreach ($settingsSnapshot as $identifier => $row) {
            [$namespace, $key] = explode('.', $identifier, 2);
            $connection->prepare(
                'DELETE FROM settings WHERE namespace = :namespace AND setting_key = :setting_key'
            )->execute(['namespace' => $namespace, 'setting_key' => $key]);

            if (is_array($row)) {
                $connection->prepare(
                    'INSERT INTO settings (
                        id, namespace, setting_key, setting_value, value_type, created_at, updated_at
                    ) VALUES (
                        :id, :namespace, :setting_key, :setting_value, :value_type, :created_at, :updated_at
                    )'
                )->execute([
                    'id' => $row['id'],
                    'namespace' => $row['namespace'],
                    'setting_key' => $row['setting_key'],
                    'setting_value' => $row['setting_value'],
                    'value_type' => $row['value_type'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                ]);
            }
        }

        if ($moduleSnapshot === null) {
            $connection->prepare('DELETE FROM module_permissions WHERE module_name = :name')
                ->execute(['name' => 'settings-manager']);
            $connection->prepare('DELETE FROM modules WHERE name = :name')
                ->execute(['name' => 'settings-manager']);
        } else {
            $connection->prepare(
                'UPDATE modules SET
                    title = :title,
                    version = :version,
                    path = :path,
                    status = :status,
                    installed_at = :installed_at,
                    enabled_at = :enabled_at,
                    disabled_at = :disabled_at,
                    created_at = :created_at,
                    updated_at = :updated_at
                WHERE name = :name'
            )->execute([
                'name' => 'settings-manager',
                'title' => $moduleSnapshot['title'],
                'version' => $moduleSnapshot['version'],
                'path' => $moduleSnapshot['path'],
                'status' => $moduleSnapshot['status'],
                'installed_at' => $moduleSnapshot['installed_at'],
                'enabled_at' => $moduleSnapshot['enabled_at'],
                'disabled_at' => $moduleSnapshot['disabled_at'],
                'created_at' => $moduleSnapshot['created_at'],
                'updated_at' => $moduleSnapshot['updated_at'],
            ]);
        }

        $connection->commit();
    } catch (Throwable $cleanupFailure) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        throw new RuntimeException('Batch 1 integration cleanup failed.', 0, $cleanupFailure);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
