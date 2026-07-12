<?php

declare(strict_types=1);

use Copot\Core\Auth;
use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\PasswordHasher;
use Copot\Core\Session;
use Copot\Core\User;
use Copot\Core\UserProvider;

$basePath = dirname(__DIR__);

require $basePath . '/bootstrap/autoload.php';

Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$config = new Config($basePath . '/config');
$database = new Database($config);
$passwords = new PasswordHasher();
$session = new Session($config);
$connection = null;
$transactionStarted = false;

session_save_path(sys_get_temp_dir());
session_id('copotm31baseline' . bin2hex(random_bytes(6)));
$session->start();

try {
    $connection = $database->connection();
    $connection->beginTransaction();
    $transactionStarted = true;

    $suffix = bin2hex(random_bytes(8));
    $activeEmail = "m31-active-{$suffix}@example.test";
    $inactiveEmail = "m31-inactive-{$suffix}@example.test";
    $password = 'M3.1 baseline password ' . $suffix;
    $passwordHash = $passwords->make($password);

    $assert($passwordHash !== $password, 'PasswordHasher::make() must not return the plaintext password.');
    $assert($passwords->verify($password, $passwordHash), 'PasswordHasher hash must verify with the original password.');
    $assert(!$passwords->verify($password . '-wrong', $passwordHash), 'PasswordHasher must reject a different password.');

    $insertRole = $connection->prepare(
        'INSERT INTO roles (name, slug, created_at, updated_at)
        VALUES (:name, :slug, NOW(), NOW())'
    );
    $insertRole->execute([
        'name' => 'M3.1 Baseline Primary',
        'slug' => "m31-baseline-primary-{$suffix}",
    ]);
    $primaryRoleId = (int) $connection->lastInsertId();
    $insertRole->execute([
        'name' => 'M3.1 Baseline Secondary',
        'slug' => "m31-baseline-secondary-{$suffix}",
    ]);
    $secondaryRoleId = (int) $connection->lastInsertId();

    $insertPermission = $connection->prepare(
        'INSERT INTO permissions (name, slug, created_at, updated_at)
        VALUES (:name, :slug, NOW(), NOW())'
    );
    $insertPermission->execute([
        'name' => 'M3.1 Baseline Primary Permission',
        'slug' => "m31.baseline.primary.{$suffix}",
    ]);
    $primaryPermissionId = (int) $connection->lastInsertId();
    $insertPermission->execute([
        'name' => 'M3.1 Baseline Secondary Permission',
        'slug' => "m31.baseline.secondary.{$suffix}",
    ]);
    $secondaryPermissionId = (int) $connection->lastInsertId();

    $insertUser = $connection->prepare(
        'INSERT INTO users (name, email, password_hash, status, created_at, updated_at)
        VALUES (:name, :email, :password_hash, :status, NOW(), NOW())'
    );
    $insertUser->execute([
        'name' => 'M3.1 Active Fixture',
        'email' => $activeEmail,
        'password_hash' => $passwordHash,
        'status' => 'active',
    ]);
    $activeUserId = (int) $connection->lastInsertId();
    $insertUser->execute([
        'name' => 'M3.1 Inactive Fixture',
        'email' => $inactiveEmail,
        'password_hash' => $passwordHash,
        'status' => 'inactive',
    ]);

    $assignRole = $connection->prepare(
        'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
    );
    $assignRole->execute(['user_id' => $activeUserId, 'role_id' => $primaryRoleId]);
    $assignRole->execute(['user_id' => $activeUserId, 'role_id' => $secondaryRoleId]);

    $assignPermission = $connection->prepare(
        'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)'
    );
    $assignPermission->execute(['role_id' => $primaryRoleId, 'permission_id' => $primaryPermissionId]);
    $assignPermission->execute(['role_id' => $secondaryRoleId, 'permission_id' => $secondaryPermissionId]);

    $users = new UserProvider($database);
    $normalizedUser = $users->findByEmail('  ' . strtoupper($activeEmail) . '  ');

    $assert($normalizedUser instanceof User, 'UserProvider must normalize email case and surrounding whitespace.');
    $assert($normalizedUser?->id() === $activeUserId, 'Normalized email lookup returned the wrong user.');
    $assert(!array_key_exists('password_hash', $normalizedUser?->toArray() ?? []), 'User::toArray() exposed password_hash.');
    $assert($normalizedUser?->hasRole("m31-baseline-primary-{$suffix}") === true, 'Actual role lookup did not find an assigned role.');
    $assert($normalizedUser?->hasRole("m31-baseline-missing-{$suffix}") === false, 'Actual role lookup accepted an unassigned role.');
    $assert($normalizedUser?->can("m31.baseline.primary.{$suffix}") === true, 'Actual permission lookup did not find the primary role permission.');
    $assert($normalizedUser?->can("m31.baseline.secondary.{$suffix}") === true, 'Multi-role effective permission union did not include the secondary role permission.');
    $assert($normalizedUser?->can("m31.baseline.missing.{$suffix}") === false, 'Missing permission was not denied.');

    $auth = new Auth($config, $session, $users, $passwords);
    $assert($auth->attempt('  ' . strtoupper($activeEmail) . '  ', $password), 'Active user authentication failed.');
    $assert($auth->id() === $activeUserId, 'Successful authentication resolved the wrong user.');

    $auth->logout();
    $inactiveAuth = new Auth($config, $session, new UserProvider($database), $passwords);
    $assert(!$inactiveAuth->attempt($inactiveEmail, $password), 'Inactive user authentication was accepted.');
    $assert(!$inactiveAuth->check(), 'Inactive authentication attempt created an authenticated session.');

    $boundaryAuth = new Auth($config, $session, new UserProvider($database), $passwords);
    $assert($boundaryAuth->attempt($activeEmail, $password), 'Active session setup failed.');

    $deactivate = $connection->prepare(
        "UPDATE users SET status = 'inactive', updated_at = NOW() WHERE id = :id"
    );
    $deactivate->execute(['id' => $activeUserId]);

    $nextBoundaryAuth = new Auth($config, $session, new UserProvider($database), $passwords);
    $assert(!$nextBoundaryAuth->check(), 'Next request/application boundary retained an inactive user session.');
    $assert(
        !$session->has((string) $config->get('auth.session_key', '_copot_user_id')),
        'Inactive user session key was not invalidated.'
    );

    echo "M3.1 Batch 1 focused baseline passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if ($transactionStarted && $connection instanceof PDO && $connection->inTransaction()) {
        $connection->rollBack();
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        $session->destroy();
    }
}
