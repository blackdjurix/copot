<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\PasswordHasher;
use Copot\Core\PermissionChecker;

$basePath = dirname(__DIR__);

require $basePath . '/bootstrap/autoload.php';
require $basePath . '/modules/users-access/Services/ManagedUser.php';
require $basePath . '/modules/users-access/Services/UsersRepository.php';
require $basePath . '/modules/users-access/Services/UsersValidationException.php';
require $basePath . '/modules/users-access/Services/UsersService.php';

Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$validationErrors = static function (callable $operation) use ($assert): array {
    try {
        $operation();
    } catch (UsersValidationException $exception) {
        $assert(!array_key_exists('password', $exception->safeValues()), 'Validation safe values contain password.');
        $assert(!array_key_exists('password_confirmation', $exception->safeValues()), 'Validation safe values contain confirmation.');

        return $exception->errors();
    }

    throw new RuntimeException('Expected UsersValidationException was not thrown.');
};

$config = new Config($basePath . '/config');
$database = new Database($config);
$passwords = new PasswordHasher();
$repository = new UsersRepository($database);
$service = new UsersService($repository, $passwords, new PermissionChecker($database), $database);
$connection = $database->connection();
$connection->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(8));
    $password = 'M3.1 domain password ' . $suffix;
    $createdId = $service->create([
        'name' => '  Domain User  ',
        'email' => '  DOMAIN-' . strtoupper($suffix) . '@EXAMPLE.TEST  ',
        'password' => $password,
        'password_confirmation' => $password,
    ]);
    $created = $repository->findById($createdId);

    $assert($created instanceof ManagedUser, 'Created user was not readable through UsersRepository.');
    $assert($created?->name() === 'Domain User', 'Created user name was not normalized.');
    $assert($created?->email() === 'domain-' . $suffix . '@example.test', 'Created email was not normalized.');
    $assert($created?->status() === 'inactive', 'Omitted create status did not default to inactive.');
    $assert($repository->findByEmail('domain-' . $suffix . '@example.test')?->id() === $createdId,
        'Normalized email lookup did not return the created user.');

    $roleCount = $connection->prepare('SELECT COUNT(*) FROM user_roles WHERE user_id = :user_id');
    $roleCount->execute(['user_id' => $createdId]);
    $assert((int) $roleCount->fetchColumn() === 0, 'Create assigned a role during Batch 2.');

    $storedHash = $connection->prepare('SELECT password_hash FROM users WHERE id = :id');
    $storedHash->execute(['id' => $createdId]);
    $hash = (string) $storedHash->fetchColumn();
    $assert($hash !== $password, 'Create persisted plaintext password.');
    $assert($passwords->verify($password, $hash), 'Created password hash is incompatible with PasswordHasher.');

    $managedProperties = array_map(
        static fn (ReflectionProperty $property): string => $property->getName(),
        (new ReflectionClass(ManagedUser::class))->getProperties()
    );
    $assert(!in_array('password_hash', $managedProperties, true), 'ManagedUser contains password_hash.');
    $assert(!in_array('passwordHash', $managedProperties, true), 'ManagedUser contains a password hash field.');

    $listedIds = array_map(static fn (ManagedUser $user): int => $user->id(), $repository->paginate(100));
    $assert(in_array($createdId, $listedIds, true), 'Paginated user list omitted the created user.');

    $activeId = $service->create([
        'name' => 'Active User',
        'email' => "active-{$suffix}@example.test",
        'password' => $password,
        'password_confirmation' => $password,
        'status' => 'active',
    ], true);
    $assert($repository->findById($activeId)?->isActive() === true, 'Authorized active creation failed.');

    $unauthorizedActiveErrors = $validationErrors(fn () => $service->create([
        'name' => 'Unauthorized Active',
        'email' => "unauthorized-active-{$suffix}@example.test",
        'password' => $password,
        'password_confirmation' => $password,
        'status' => 'active',
    ]));
    $assert(isset($unauthorizedActiveErrors['status']), 'Unauthorized active creation was not rejected.');

    $duplicateErrors = $validationErrors(fn () => $service->create([
        'name' => 'Duplicate User',
        'email' => "DOMAIN-{$suffix}@EXAMPLE.TEST",
        'password' => $password,
        'password_confirmation' => $password,
    ]));
    $assert(isset($duplicateErrors['email']), 'Duplicate create was not rejected safely.');

    $raceRepository = new class($database) extends UsersRepository {
        public function emailExists(string $normalizedEmail, ?int $ignoreId = null): bool
        {
            return false;
        }
    };
    $raceService = new UsersService($raceRepository, $passwords, new PermissionChecker($database), $database);
    $raceErrors = $validationErrors(fn () => $raceService->create([
        'name' => 'Duplicate Race User',
        'email' => "domain-{$suffix}@example.test",
        'password' => $password,
        'password_confirmation' => $password,
    ]));
    $assert(isset($raceErrors['email']), 'Duplicate-key race was not translated to a safe email error.');

    $invalidEmail = $validationErrors(fn () => $service->create([
        'name' => 'Invalid Email',
        'email' => 'not-an-email',
        'password' => $password,
        'password_confirmation' => $password,
    ]));
    $assert(isset($invalidEmail['email']), 'Invalid email was accepted.');

    $invalidName = $validationErrors(fn () => $service->create([
        'name' => '',
        'email' => "invalid-name-{$suffix}@example.test",
        'password' => $password,
        'password_confirmation' => $password,
    ]));
    $assert(isset($invalidName['name']), 'Invalid name was accepted.');

    $shortPassword = $validationErrors(fn () => $service->create([
        'name' => 'Short Password',
        'email' => "short-password-{$suffix}@example.test",
        'password' => 'short',
        'password_confirmation' => 'short',
    ]));
    $assert(isset($shortPassword['password']), 'Short password was accepted.');

    $longPasswordValue = str_repeat('x', 4097);
    $longPassword = $validationErrors(fn () => $service->create([
        'name' => 'Long Password',
        'email' => "long-password-{$suffix}@example.test",
        'password' => $longPasswordValue,
        'password_confirmation' => $longPasswordValue,
    ]));
    $assert(isset($longPassword['password']), 'Password over 4096 bytes was accepted.');

    $mismatch = $validationErrors(fn () => $service->create([
        'name' => 'Mismatch',
        'email' => "mismatch-{$suffix}@example.test",
        'password' => $password,
        'password_confirmation' => $password . '-different',
    ]));
    $assert(isset($mismatch['password_confirmation']), 'Password confirmation mismatch was accepted.');

    $service->updateIdentity($createdId, [
        'name' => '  Updated Domain User ',
        'email' => " UPDATED-{$suffix}@EXAMPLE.TEST ",
    ]);
    $updated = $repository->findById($createdId);
    $assert($updated?->name() === 'Updated Domain User', 'Identity name update failed.');
    $assert($updated?->email() === "updated-{$suffix}@example.test", 'Identity email update was not normalized.');

    $duplicateUpdate = $validationErrors(fn () => $service->updateIdentity($createdId, [
        'name' => 'Duplicate Update',
        'email' => "active-{$suffix}@example.test",
    ]));
    $assert(isset($duplicateUpdate['email']), 'Duplicate email update was accepted.');

    $newPassword = 'M3.1 changed password ' . $suffix;
    $service->changePassword($createdId, [
        'password' => $newPassword,
        'password_confirmation' => $newPassword,
    ]);
    $storedHash->execute(['id' => $createdId]);
    $changedHash = (string) $storedHash->fetchColumn();
    $assert($changedHash !== $newPassword, 'Password change persisted plaintext.');
    $assert($passwords->verify($newPassword, $changedHash), 'Password change hash is incompatible.');

    $service->changeStatus($createdId, 'active', $activeId);
    $assert($repository->findById($createdId)?->status() === 'active', 'Ordinary user activation failed.');
    $service->changeStatus($createdId, 'inactive', $activeId);
    $assert($repository->findById($createdId)?->status() === 'inactive', 'Ordinary user deactivation failed.');

    $invalidStatus = $validationErrors(fn () => $service->changeStatus($createdId, 'Active', $activeId));
    $assert(isset($invalidStatus['status']), 'Differently cased status was accepted.');
    $arrayStatus = $validationErrors(fn () => $service->changeStatus($createdId, ['active'], $activeId));
    $assert(isset($arrayStatus['status']), 'Array-valued status was accepted.');
    $nullStatus = $validationErrors(fn () => $service->changeStatus($createdId, null, $activeId));
    $assert(isset($nullStatus['status']), 'Null status was accepted.');

    $selfDeactivation = $validationErrors(fn () => $service->changeStatus($activeId, 'inactive', $activeId));
    $assert(isset($selfDeactivation['status']), 'Self-deactivation was accepted.');

    $adminAccessPermission = $connection->query(
        "SELECT id FROM permissions WHERE slug = 'admin.access' LIMIT 1"
    )->fetchColumn();
    $assert(is_numeric($adminAccessPermission), 'Canonical admin.access permission is unavailable.');
    $connection->prepare(
        'INSERT INTO roles (name, slug, created_at, updated_at) VALUES (:name, :slug, NOW(), NOW())'
    )->execute(['name' => 'Domain Admin Access', 'slug' => "domain-admin-access-{$suffix}"]);
    $adminRoleId = (int) $connection->lastInsertId();
    $connection->prepare(
        'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)'
    )->execute(['role_id' => $adminRoleId, 'permission_id' => (int) $adminAccessPermission]);
    $connection->prepare(
        'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
    )->execute(['user_id' => $activeId, 'role_id' => $adminRoleId]);

    $adminDeactivation = $validationErrors(fn () => $service->changeStatus($activeId, 'inactive', $createdId));
    $assert(isset($adminDeactivation['status']), 'User with effective admin.access was deactivated.');

    echo "M3.1 Batch 2 domain tests passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
}
