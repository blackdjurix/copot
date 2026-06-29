<?php

declare(strict_types=1);

use Copot\Core\Request;
use Copot\Core\Response;

$basePath = dirname(__DIR__);

chdir($basePath);
session_save_path(sys_get_temp_dir());
session_id('copotm2batch1' . bin2hex(random_bytes(4)));

$passedAssertions = 0;
$failedAssertions = 0;
$skippedChecks = [];

$assert = static function (bool $condition, string $message) use (&$passedAssertions, &$failedAssertions): bool {
    if ($condition) {
        $passedAssertions++;

        return true;
    }

    $failedAssertions++;
    echo "FAIL: {$message}" . PHP_EOL;

    return false;
};

$fail = static function (string $message) use (&$failedAssertions): void {
    $failedAssertions++;
    echo "FAIL: {$message}" . PHP_EOL;
};

$skip = static function (string $check, string $reason) use (&$skippedChecks): void {
    $skippedChecks[] = ['check' => $check, 'reason' => $reason];
    echo "SKIP: {$check} — {$reason}" . PHP_EOL;
};

$responseValue = static function (Response $response, string $property): mixed {
    $reflection = new ReflectionProperty($response, $property);

    return $reflection->getValue($response);
};

$findUserWithPermissions = static function (PDO $connection, array $permissions): ?int {
    $permissions = array_values(array_unique($permissions));
    $placeholders = implode(', ', array_fill(0, count($permissions), '?'));
    $statement = $connection->prepare("SELECT users.id
        FROM users
        INNER JOIN user_roles ON user_roles.user_id = users.id
        INNER JOIN role_permissions ON role_permissions.role_id = user_roles.role_id
        INNER JOIN permissions ON permissions.id = role_permissions.permission_id
        WHERE users.status = 'active'
            AND permissions.slug IN ({$placeholders})
        GROUP BY users.id
        HAVING COUNT(DISTINCT permissions.slug) = ?
        ORDER BY users.id
        LIMIT 1");
    $statement->execute([...$permissions, count($permissions)]);
    $userId = $statement->fetchColumn();

    return is_numeric($userId) ? (int) $userId : null;
};

$findUserWithAnyPermission = static function (
    PDO $connection,
    array $requiredPermissions,
    array $candidatePermissions
) use ($findUserWithPermissions): ?int {
    foreach ($candidatePermissions as $candidatePermission) {
        $userId = $findUserWithPermissions($connection, [
            ...$requiredPermissions,
            $candidatePermission,
        ]);

        if ($userId !== null) {
            return $userId;
        }
    }

    return null;
};

$app = null;
$connection = null;
$schemaAvailable = false;

try {
    try {
        $app = require $basePath . '/bootstrap/app.php';
    } catch (PDOException $exception) {
        $skip('database availability', 'Application database connection is unavailable: ' . $exception->getMessage());
    } catch (Throwable $exception) {
        $fail('Application bootstrap failed: ' . $exception->getMessage());
    }

    if ($app !== null) {
        $adminBase = $app->adminUrl()->baseUrl();
        $sessionKey = (string) $app->config()->get('auth.session_key', '_copot_user_id');

        $login = $app->run(new Request('GET', $adminBase));
        $assert($responseValue($login, 'status') === 200, 'Guest admin login route must remain available.');
        $assert(
            str_contains((string) $responseValue($login, 'content'), 'action="' . $adminBase . '"'),
            'Admin login form must post to the centralized admin base URL.'
        );

        $invalidLoginCsrf = $app->run(new Request('POST', $adminBase, [], [
            '_token' => 'invalid',
            'email' => 'admin@example.test',
            'password' => 'not-used',
        ]));
        $assert($responseValue($invalidLoginCsrf, 'status') === 419, 'Admin login CSRF regression detected.');

        $invalidLogoutCsrf = $app->run(new Request(
            'POST',
            $app->adminUrl()->childUrl('logout'),
            [],
            ['_token' => 'invalid']
        ));
        $assert($responseValue($invalidLogoutCsrf, 'status') === 419, 'Admin logout CSRF regression detected.');

        try {
            $connection = $app->database()->connection();
            $assert(true, 'Database connection must be available.');
        } catch (PDOException $exception) {
            $skip('database availability', 'Database connection is unavailable: ' . $exception->getMessage());
        }

        if ($connection instanceof PDO) {
            $requiredTables = [
                'users',
                'roles',
                'permissions',
                'user_roles',
                'role_permissions',
                'settings',
                'modules',
                'content',
                'taxonomy_types',
                'taxonomy_terms',
                'taxonomy_assignments',
            ];

            try {
                foreach ($requiredTables as $table) {
                    $connection->query("SELECT 1 FROM `{$table}` LIMIT 1");
                }

                $schemaAvailable = true;
                $assert(true, 'Installed schema must be available.');
            } catch (PDOException $exception) {
                $skip('installed schema', 'Canonical installed schema is unavailable: ' . $exception->getMessage());
            }
        }

        if ($schemaAvailable) {
            $moduleStatus = static function (PDO $connection, string $module): ?string {
                $statement = $connection->prepare('SELECT status FROM modules WHERE name = :name LIMIT 1');
                $statement->execute(['name' => $module]);
                $status = $statement->fetchColumn();

                return is_string($status) ? $status : null;
            };

            $contentEnabled = $moduleStatus($connection, 'content') === 'enabled';
            $taxonomyEnabled = $moduleStatus($connection, 'taxonomy') === 'enabled';

            if ($contentEnabled) {
                $assert(true, 'Content module must be enabled for Content route integration checks.');
            } else {
                $skip('Content route integration', 'Content module is not enabled.');
            }

            if ($taxonomyEnabled) {
                $assert(true, 'Taxonomy module must be enabled for Taxonomy route integration checks.');
            } else {
                $skip('Taxonomy route integration', 'Taxonomy module is not enabled.');
            }

            $guestRoutes = ['settings'];

            if ($contentEnabled) {
                $guestRoutes[] = 'content';
            }

            if ($taxonomyEnabled) {
                $guestRoutes[] = 'taxonomy';
            }

            foreach ($guestRoutes as $childPath) {
                $response = $app->run(new Request('GET', $app->adminUrl()->childUrl($childPath)));
                $assert(
                    $responseValue($response, 'status') === 302,
                    "Guest [{$childPath}] route must preserve its authentication redirect."
                );
                $headers = $responseValue($response, 'headers');
                $assert(
                    ($headers['Location'] ?? null) === $adminBase,
                    "Guest [{$childPath}] route must redirect to the centralized admin base URL."
                );
            }

            $switchUser = static function ($app, string $sessionKey, int $userId): void {
                $app->auth()->logout();
                $app->session()->set($sessionKey, $userId);
            };

            $dashboardUserId = $findUserWithPermissions($connection, ['admin.access']);

            if ($dashboardUserId === null) {
                $skip('Dashboard authorized route and valid logout', 'No active user with admin.access is available.');
            } else {
                $switchUser($app, $sessionKey, $dashboardUserId);
                $dashboard = $app->run(new Request('GET', $adminBase));
                $assert($responseValue($dashboard, 'status') === 200, 'Authorized admin access regression detected.');
                $assert(
                    str_contains(
                        (string) $responseValue($dashboard, 'content'),
                        'action="' . $app->adminUrl()->childUrl('logout') . '"'
                    ),
                    'Admin Shell logout action must use the centralized child URL.'
                );

                $logout = $app->run(new Request('POST', $app->adminUrl()->childUrl('logout'), [], [
                    '_token' => $app->session()->csrfToken(),
                ]));
                $assert($responseValue($logout, 'status') === 302, 'Valid admin logout must preserve its redirect response.');
                $logoutHeaders = $responseValue($logout, 'headers');
                $assert(
                    ($logoutHeaders['Location'] ?? null) === $adminBase,
                    'Valid admin logout must redirect to the admin base URL.'
                );
            }

            $authorizedRoute = static function (
                string $label,
                string $childPath,
                ?int $userId
            ) use ($app, $assert, $responseValue, $sessionKey, $skip, $switchUser): void {
                if ($userId === null) {
                    $skip($label . ' authorized route', 'No active user with the required permissions is available.');

                    return;
                }

                $switchUser($app, $sessionKey, $userId);
                $response = $app->run(new Request('GET', $app->adminUrl()->childUrl($childPath)));
                $assert($responseValue($response, 'status') === 200, "Authorized [{$label}] route regression detected.");
                $assert(
                    str_contains(
                        (string) $responseValue($response, 'content'),
                        'action="' . $app->adminUrl()->childUrl('logout') . '"'
                    ),
                    "Authorized [{$label}] page did not use the centralized Admin Shell renderer."
                );
            };

            $settingsUserId = $findUserWithPermissions($connection, ['admin.access', 'settings.update']);
            $authorizedRoute('Settings', 'settings', $settingsUserId);

            if ($contentEnabled) {
                $contentUserId = $findUserWithAnyPermission(
                    $connection,
                    ['admin.access'],
                    ['content.create', 'content.update', 'content.delete', 'content.publish']
                );
                $authorizedRoute('Content', 'content', $contentUserId);
            }

            if ($taxonomyEnabled) {
                $taxonomyUserId = $findUserWithAnyPermission(
                    $connection,
                    ['admin.access'],
                    ['taxonomy.create', 'taxonomy.update', 'taxonomy.delete']
                );
                $authorizedRoute('Taxonomy', 'taxonomy', $taxonomyUserId);
            }

            $unauthorizedStatement = $connection->query("SELECT users.id
                FROM users
                WHERE users.status = 'active'
                    AND NOT EXISTS (
                        SELECT 1
                        FROM user_roles
                        INNER JOIN role_permissions ON role_permissions.role_id = user_roles.role_id
                        INNER JOIN permissions ON permissions.id = role_permissions.permission_id
                        WHERE user_roles.user_id = users.id
                            AND permissions.slug = 'admin.access'
                    )
                ORDER BY users.id
                LIMIT 1");
            $unauthorizedUserId = $unauthorizedStatement->fetchColumn();

            if (is_numeric($unauthorizedUserId)) {
                $switchUser($app, $sessionKey, (int) $unauthorizedUserId);
                $forbidden = $app->run(new Request('GET', $adminBase));
                $assert($responseValue($forbidden, 'status') === 403, 'Admin permission denial regression detected.');
            } else {
                $skip('admin.access denial (403)', 'No deterministic active user without admin.access is available.');
            }
        } elseif ($connection instanceof PDO) {
            $skip('Content module enabled', 'Installed schema prerequisite is unavailable.');
            $skip('Taxonomy module enabled', 'Installed schema prerequisite is unavailable.');
            $skip('permission fixtures', 'Installed schema prerequisite is unavailable.');
            $skip('admin.access denial (403)', 'Installed schema prerequisite is unavailable.');
        } else {
            $skip('installed schema', 'Database availability prerequisite is unavailable.');
            $skip('Content module enabled', 'Database availability prerequisite is unavailable.');
            $skip('Taxonomy module enabled', 'Database availability prerequisite is unavailable.');
            $skip('permission fixtures', 'Database availability prerequisite is unavailable.');
            $skip('admin.access denial (403)', 'Database availability prerequisite is unavailable.');
        }
    } else {
        $skip('installed schema', 'Application bootstrap prerequisite is unavailable.');
        $skip('Content module enabled', 'Application bootstrap prerequisite is unavailable.');
        $skip('Taxonomy module enabled', 'Application bootstrap prerequisite is unavailable.');
        $skip('permission fixtures', 'Application bootstrap prerequisite is unavailable.');
        $skip('admin.access denial (403)', 'Application bootstrap prerequisite is unavailable.');
    }
} catch (Throwable $exception) {
    $fail('Unexpected integration failure: ' . $exception->getMessage());
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

echo "Passed assertions: {$passedAssertions}" . PHP_EOL;
echo "Failed assertions: {$failedAssertions}" . PHP_EOL;
echo 'Skipped checks: ' . count($skippedChecks) . PHP_EOL;

exit($failedAssertions > 0 ? 1 : 0);
