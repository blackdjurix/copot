<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\PasswordHasher;

$basePath = dirname(__DIR__);

require $basePath . '/bootstrap/autoload.php';
require $basePath . '/modules/users-access/Services/ManagedUser.php';
require $basePath . '/modules/users-access/Services/ManagedRole.php';
require $basePath . '/modules/users-access/Services/UsersRepository.php';
require $basePath . '/modules/users-access/Services/RolesRepository.php';
require $basePath . '/modules/users-access/Services/AccessInvariantGuard.php';
require $basePath . '/modules/users-access/Services/UsersValidationException.php';
require $basePath . '/modules/users-access/Services/RolesValidationException.php';
require $basePath . '/modules/users-access/Services/UsersService.php';
require $basePath . '/modules/users-access/Services/RolesService.php';

Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$rolesValidationErrors = static function (callable $operation): array {
    try {
        $operation();
    } catch (RolesValidationException $exception) {
        return $exception->errors();
    }

    throw new RuntimeException('Expected RolesValidationException was not thrown.');
};
$usersValidationErrors = static function (callable $operation): array {
    try {
        $operation();
    } catch (UsersValidationException $exception) {
        return $exception->errors();
    }

    throw new RuntimeException('Expected UsersValidationException was not thrown.');
};

$database = new Database(new Config($basePath . '/config'));
$repository = new RolesRepository($database);
$usersRepository = new UsersRepository($database);
$invariant = new AccessInvariantGuard($repository, $usersRepository);
$rolesService = new RolesService($repository, $usersRepository, $invariant, $database);
$usersService = new UsersService($usersRepository, new PasswordHasher(), $invariant, $database);
$connection = $database->connection();
$connection->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(8));
    $adminRole = $repository->findBySlug('admin');
    $userRole = $repository->findBySlug('user');

    $assert($adminRole instanceof ManagedRole && $adminRole->isSeeded(), 'Seeded admin role was not resolved safely.');
    $assert($userRole instanceof ManagedRole && $userRole->isSeeded(), 'Seeded user role was not resolved safely.');
    $assert($adminRole?->slug() === 'admin', 'ManagedRole returned the wrong seeded slug.');
    $assert($repository->lockInvariantMutex() === $adminRole?->id(), 'Invariant mutex did not lock the seeded admin row.');

    $primarySlug = "m31-b3-primary-{$suffix}";
    $secondarySlug = "m31-b3-secondary-{$suffix}";
    $primaryRoleId = $repository->create('Batch 3 Primary', $primarySlug);
    $secondaryRoleId = $repository->create('Batch 3 Secondary', $secondarySlug);
    $primaryRole = $repository->findById($primaryRoleId);

    $assert($primaryRole instanceof ManagedRole, 'Created role was not readable by ID.');
    $assert($primaryRole?->name() === 'Batch 3 Primary', 'Created role name was incorrect.');
    $assert($primaryRole?->slug() === $primarySlug, 'Created role slug was incorrect.');
    $assert($primaryRole?->isSeeded() === false, 'Custom role was marked as seeded.');
    $assert($repository->findBySlug($secondarySlug)?->id() === $secondaryRoleId, 'Role lookup by slug failed.');
    $assert($repository->slugExists($primarySlug), 'Slug existence check missed an existing role.');
    $assert(!$repository->slugExists($primarySlug, $primaryRoleId), 'Slug ignore-ID check included the same role.');

    $repository->updateName($primaryRoleId, 'Batch 3 Primary Updated');
    $assert($repository->findById($primaryRoleId)?->name() === 'Batch 3 Primary Updated',
        'Role display-name update was not persisted.');
    $assert($repository->findById($primaryRoleId)?->slug() === $primarySlug,
        'Role display-name update changed the immutable slug.');
    $assert($repository->findByIdForUpdate($primaryRoleId)?->id() === $primaryRoleId,
        'Role FOR UPDATE lookup failed inside the active transaction.');

    $listedRoleIds = array_map(static fn (ManagedRole $role): int => $role->id(), $repository->paginate(100));
    $assert(in_array($primaryRoleId, $listedRoleIds, true), 'Role listing omitted the created role.');
    $assert(in_array($secondaryRoleId, $listedRoleIds, true), 'Role listing omitted the secondary role.');

    $recoveryPermissions = [
        'admin.access' => 'Access admin shell',
        'users.read' => 'Read users',
        'users.status.manage' => 'Manage user status',
        'roles.read' => 'Read roles and permissions',
        'roles.manage' => 'Manage roles',
        'users.roles.manage' => 'Manage user roles',
        'roles.permissions.manage' => 'Manage role permissions',
    ];

    $insertPermission = $connection->prepare(
        'INSERT INTO permissions (name, slug, created_at, updated_at)
        SELECT :name, :slug, NOW(), NOW()
        WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE slug = :existing_slug)'
    );

    foreach ($recoveryPermissions as $slug => $name) {
        $insertPermission->execute(['name' => $name, 'slug' => $slug, 'existing_slug' => $slug]);
    }

    $permissions = $repository->permissions();
    $assert($permissions !== [], 'Runtime permission listing was empty.');
    $assert(array_keys($permissions[0]) === ['id', 'name', 'slug'],
        'Runtime permission listing exposed unexpected columns.');
    $permissionIdsBySlug = [];

    foreach ($permissions as $permission) {
        $permissionIdsBySlug[(string) $permission['slug']] = (int) $permission['id'];
    }

    $recoverySlugs = array_keys($recoveryPermissions);

    foreach ($recoverySlugs as $slug) {
        $assert(isset($permissionIdsBySlug[$slug]), "Recovery permission [{$slug}] is unavailable.");
    }

    $primaryPermissionIds = array_map(
        static fn (string $slug): int => $permissionIdsBySlug[$slug],
        array_slice($recoverySlugs, 0, 3)
    );
    $secondaryPermissionIds = array_map(
        static fn (string $slug): int => $permissionIdsBySlug[$slug],
        array_slice($recoverySlugs, 3)
    );
    $repository->addRolePermissions($primaryRoleId, $primaryPermissionIds);
    $repository->addRolePermissions($secondaryRoleId, $secondaryPermissionIds);
    $assert($repository->permissionIdsForRole($primaryRoleId) === $primaryPermissionIds,
        'Role-permission set read did not return the primary assignments.');
    $assert($repository->permissionIdsForRole($secondaryRoleId) === $secondaryPermissionIds,
        'Role-permission set read did not return the secondary assignments.');

    $existingRoleIds = $repository->existingRoleIds([$secondaryRoleId, PHP_INT_MAX, $primaryRoleId, $primaryRoleId]);
    $assert($existingRoleIds === [$primaryRoleId, $secondaryRoleId], 'Existing role-ID validation returned the wrong set.');
    $existingPermissionIds = $repository->existingPermissionIds([
        $primaryPermissionIds[0],
        PHP_INT_MAX,
        $secondaryPermissionIds[0],
    ]);
    sort($existingPermissionIds);
    $expectedExistingPermissionIds = [$primaryPermissionIds[0], $secondaryPermissionIds[0]];
    sort($expectedExistingPermissionIds);
    $assert($existingPermissionIds === $expectedExistingPermissionIds,
        'Existing permission-ID validation returned the wrong set.');

    $insertUser = $connection->prepare(
        'INSERT INTO users (name, email, password_hash, status, created_at, updated_at)
        VALUES (:name, :email, :password_hash, :status, NOW(), NOW())'
    );
    $passwordHash = (new PasswordHasher())->make('Batch 3 foundation password');
    $createUser = static function (string $name, string $status) use (
        $connection,
        $insertUser,
        $passwordHash,
        $suffix
    ): int {
        $insertUser->execute([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '-', $name)) . '-' . bin2hex(random_bytes(3)) . '-' . $suffix . '@example.test',
            'password_hash' => $passwordHash,
            'status' => $status,
        ]);

        return (int) $connection->lastInsertId();
    };
    $activeCapableUserId = $createUser('Active Capable', 'active');
    $inactiveCapableUserId = $createUser('Inactive Capable', 'inactive');
    $adminAccessOnlyUserId = $createUser('Admin Access Only', 'active');
    $connection->exec("UPDATE users SET status = 'inactive'
        WHERE id NOT IN ({$activeCapableUserId}, {$inactiveCapableUserId}, {$adminAccessOnlyUserId})");
    $baselineCapableCount = $repository->activeUsersMatchingAllPermissionsCount($recoverySlugs);

    $repository->addUserRoles($activeCapableUserId, [$primaryRoleId, $secondaryRoleId]);
    $repository->addUserRoles($inactiveCapableUserId, [$primaryRoleId, $secondaryRoleId]);
    $adminAccessOnlyRoleId = $repository->create('Admin Access Only', "m31-b3-admin-access-{$suffix}");
    $repository->addRolePermissions($adminAccessOnlyRoleId, [$permissionIdsBySlug['admin.access']]);
    $repository->addUserRoles($adminAccessOnlyUserId, [$adminAccessOnlyRoleId]);

    $assert($repository->roleIdsForUser($activeCapableUserId) === [$primaryRoleId, $secondaryRoleId],
        'User-role set read did not return both custom roles.');
    $assert(!in_array($adminRole->id(), $repository->roleIdsForUser($activeCapableUserId), true),
        'Capability fixture unexpectedly depended on admin role membership.');
    $assert($repository->assignedUserCount($primaryRoleId) === 2, 'Assigned-user count was incorrect.');
    $assert($repository->effectivePermissionMatchCount($activeCapableUserId, $recoverySlugs) === 7,
        'Effective multi-role recovery union did not contain all capabilities.');
    $assert($repository->effectivePermissionMatchCount($inactiveCapableUserId, $recoverySlugs) === 7,
        'Inactive user recovery capability evidence was calculated incorrectly.');
    $assert($repository->effectivePermissionMatchCount($adminAccessOnlyUserId, $recoverySlugs) === 1,
        'admin.access alone was incorrectly treated as full recovery capability.');
    $assert($repository->activeUsersMatchingAllPermissionsCount($recoverySlugs) === $baselineCapableCount + 1,
        'Active administrator-capable count included an inactive or partial-capability user.');
    $assert($invariant->isAdministratorCapable($activeCapableUserId),
        'AccessInvariantGuard did not recognize a full custom multi-role union.');
    $assert(!$invariant->isAdministratorCapable($adminAccessOnlyUserId),
        'AccessInvariantGuard treated admin.access alone as administrator-capable.');
    $assert(!$invariant->isAdministratorCapable($inactiveCapableUserId),
        'AccessInvariantGuard treated an inactive full-capability user as administrator-capable.');
    $assert($invariant->hasActiveAdministratorCapableUser(),
        'AccessInvariantGuard did not find the active capable fixture.');
    $assert($repository->effectivePermissionMatchCount(
        $activeCapableUserId,
        [' users.read ', 'users.read', '', null]
    ) === 1, 'Permission slug normalization did not trim, filter, and deduplicate deterministically.');

    foreach (['effective', 'active'] as $emptySetQuery) {
        $emptySetRejected = false;

        try {
            if ($emptySetQuery === 'effective') {
                $repository->effectivePermissionMatchCount($activeCapableUserId, []);
            } else {
                $repository->activeUsersMatchingAllPermissionsCount([' ', null]);
            }
        } catch (InvalidArgumentException $exception) {
            $emptySetRejected = $exception->getMessage() === 'Permission slug set must not be empty.';
        }

        $assert($emptySetRejected, "Empty permission slug set was accepted by [{$emptySetQuery}] query.");
    }

    $repository->removeUserRoles($activeCapableUserId, [$secondaryRoleId]);
    $assert($repository->roleIdsForUser($activeCapableUserId) === [$primaryRoleId],
        'Targeted user-role removal changed the wrong assignment set.');
    $assert($repository->effectivePermissionMatchCount($activeCapableUserId, $recoverySlugs) === 3,
        'User-role removal did not change effective capability evidence.');
    $repository->addUserRoles($activeCapableUserId, [$secondaryRoleId]);
    $assert($repository->effectivePermissionMatchCount($activeCapableUserId, $recoverySlugs) === 7,
        'Targeted user-role addition did not restore effective capability evidence.');

    $removedPermissionId = $secondaryPermissionIds[0];
    $repository->removeRolePermissions($secondaryRoleId, [$removedPermissionId]);
    $assert(!in_array($removedPermissionId, $repository->permissionIdsForRole($secondaryRoleId), true),
        'Targeted role-permission removal failed.');
    $assert($repository->effectivePermissionMatchCount($activeCapableUserId, $recoverySlugs) === 6,
        'Role-permission removal did not update effective capability evidence.');
    $repository->addRolePermissions($secondaryRoleId, [$removedPermissionId]);
    $assert($repository->effectivePermissionMatchCount($activeCapableUserId, $recoverySlugs) === 7,
        'Targeted role-permission addition did not restore capability evidence.');

    $serviceRoleId = $rolesService->create([
        'name' => '  Service Role  ',
        'slug' => "SERVICE.ROLE-{$suffix}",
    ]);
    $serviceRole = $repository->findById($serviceRoleId);
    $assert($serviceRole?->name() === 'Service Role', 'RolesService did not normalize role name.');
    $assert($serviceRole?->slug() === "service.role-{$suffix}", 'RolesService did not normalize role slug.');
    $rolesService->updateName($serviceRoleId, ['name' => '  Service Role Updated  ']);
    $assert($repository->findById($serviceRoleId)?->name() === 'Service Role Updated',
        'RolesService display-name update failed.');
    $assert($repository->findById($serviceRoleId)?->slug() === "service.role-{$suffix}",
        'RolesService display-name update changed the slug.');

    $seededAdminDelete = $rolesValidationErrors(
        fn () => $rolesService->delete($activeCapableUserId, $adminRole->id())
    );
    $assert(isset($seededAdminDelete['role']), 'Seeded admin role deletion was accepted.');
    $seededUserDelete = $rolesValidationErrors(
        fn () => $rolesService->delete($activeCapableUserId, $userRole->id())
    );
    $assert(isset($seededUserDelete['role']), 'Seeded user role deletion was accepted.');

    $assignedRoleId = $rolesService->create([
        'name' => 'Assigned Service Role',
        'slug' => "assigned-service-{$suffix}",
    ]);
    $assignedRoleUserId = $createUser('Assigned Role User', 'active');
    $repository->addUserRoles($assignedRoleUserId, [$assignedRoleId]);
    $assignedRoleDelete = $rolesValidationErrors(
        fn () => $rolesService->delete($activeCapableUserId, $assignedRoleId)
    );
    $assert(isset($assignedRoleDelete['role']), 'Assigned custom role deletion was accepted.');
    $assert($repository->findById($assignedRoleId) instanceof ManagedRole,
        'Failed assigned-role deletion did not roll back.');

    $unassignedRoleId = $rolesService->create([
        'name' => 'Unassigned Service Role',
        'slug' => "unassigned-service-{$suffix}",
    ]);
    $rolesService->delete($activeCapableUserId, $unassignedRoleId);
    $assert($repository->findById($unassignedRoleId) === null, 'Unassigned custom role deletion failed.');

    $replacementTargetId = $createUser('Replacement Target', 'active');
    $repository->addUserRoles($replacementTargetId, [$adminAccessOnlyRoleId]);
    $rolesService->replaceUserRoles(
        $activeCapableUserId,
        $replacementTargetId,
        [$secondaryRoleId, $primaryRoleId, $primaryRoleId]
    );
    $assert($repository->roleIdsForUser($replacementTargetId) === [$primaryRoleId, $secondaryRoleId],
        'Desired user-role final set was not applied by targeted diff.');
    $rolesService->replaceUserRoles(
        $activeCapableUserId,
        $replacementTargetId,
        [$primaryRoleId, $secondaryRoleId]
    );
    $assert($repository->roleIdsForUser($replacementTargetId) === [$primaryRoleId, $secondaryRoleId],
        'No-op user-role replacement changed the assignment set.');
    $beforeUnknownRoleSet = $repository->roleIdsForUser($replacementTargetId);
    $unknownRoleErrors = $rolesValidationErrors(
        fn () => $rolesService->replaceUserRoles(
            $activeCapableUserId,
            $replacementTargetId,
            [$primaryRoleId, PHP_INT_MAX]
        )
    );
    $assert(isset($unknownRoleErrors['roles']), 'Unknown desired role ID was accepted.');
    $assert($repository->roleIdsForUser($replacementTargetId) === $beforeUnknownRoleSet,
        'Failed user-role replacement changed assignments.');

    foreach ([
        ['bad'],
        [0],
        [-1],
        [$primaryRoleId, 'bad'],
    ] as $malformedRoleIds) {
        $beforeMalformedRoleSet = $repository->roleIdsForUser($replacementTargetId);
        $malformedRoleErrors = $rolesValidationErrors(
            fn () => $rolesService->replaceUserRoles(
                $activeCapableUserId,
                $replacementTargetId,
                $malformedRoleIds
            )
        );
        $assert(isset($malformedRoleErrors['roles']), 'Malformed desired role ID set was not rejected safely.');
        $assert($repository->roleIdsForUser($replacementTargetId) === $beforeMalformedRoleSet,
            'Malformed desired role ID set changed assignments.');
    }

    $actorSelfRoleErrors = $rolesValidationErrors(
        fn () => $rolesService->replaceUserRoles(
            $activeCapableUserId,
            $activeCapableUserId,
            [$primaryRoleId]
        )
    );
    $assert(isset($actorSelfRoleErrors['role']), 'Capable actor removed its own recovery role.');
    $assert($repository->roleIdsForUser($activeCapableUserId) === [$primaryRoleId, $secondaryRoleId],
        'Actor self-protection failure did not roll back user roles.');

    $nonCapableActorId = $createUser('Non Capable Actor', 'active');
    $ordinaryTargetId = $createUser('Ordinary Assignment Target', 'active');
    $rolesService->replaceUserRoles($nonCapableActorId, $ordinaryTargetId, [$adminAccessOnlyRoleId]);
    $assert($repository->roleIdsForUser($ordinaryTargetId) === [$adminAccessOnlyRoleId],
        'Non-capable actor was automatically rejected from a safe mutation.');
    $rolesService->replaceUserRoles($activeCapableUserId, $replacementTargetId, [$adminAccessOnlyRoleId]);
    $finalUserRoleErrors = $rolesValidationErrors(
        fn () => $rolesService->replaceUserRoles($nonCapableActorId, $activeCapableUserId, [])
    );
    $assert(isset($finalUserRoleErrors['role']), 'Final active capable user roles were removable.');
    $assert($repository->roleIdsForUser($activeCapableUserId) === [$primaryRoleId, $secondaryRoleId],
        'Final-invariant user-role failure did not roll back assignments.');

    $servicePermissionSet = [$primaryPermissionIds[0], $secondaryPermissionIds[0]];
    sort($servicePermissionSet);
    $rolesService->replaceRolePermissions($activeCapableUserId, $serviceRoleId, $servicePermissionSet);
    $assert($repository->permissionIdsForRole($serviceRoleId) === $servicePermissionSet,
        'Desired role-permission final set was not applied by targeted diff.');
    $rolesService->replaceRolePermissions($activeCapableUserId, $serviceRoleId, $servicePermissionSet);
    $assert($repository->permissionIdsForRole($serviceRoleId) === $servicePermissionSet,
        'No-op role-permission replacement changed the assignment set.');
    $rolesService->replaceRolePermissions(
        $activeCapableUserId,
        $serviceRoleId,
        [$servicePermissionSet[1], $servicePermissionSet[0], $servicePermissionSet[0]]
    );
    $assert($repository->permissionIdsForRole($serviceRoleId) === $servicePermissionSet,
        'Duplicate valid permission IDs were not normalized to a sorted set.');
    $rolesService->replaceRolePermissions($activeCapableUserId, $serviceRoleId, []);
    $assert($repository->permissionIdsForRole($serviceRoleId) === [],
        'Empty desired permission set was not accepted.');
    $rolesService->replaceRolePermissions($activeCapableUserId, $serviceRoleId, $servicePermissionSet);
    $assert($repository->permissionIdsForRole($serviceRoleId) === $servicePermissionSet,
        'Permission fixture was not restored after empty desired set.');
    $beforeUnknownPermissionSet = $repository->permissionIdsForRole($serviceRoleId);
    $unknownPermissionErrors = $rolesValidationErrors(
        fn () => $rolesService->replaceRolePermissions(
            $activeCapableUserId,
            $serviceRoleId,
            [$primaryPermissionIds[0], PHP_INT_MAX]
        )
    );
    $assert(isset($unknownPermissionErrors['permissions']), 'Unknown desired permission ID was accepted.');
    $assert($repository->permissionIdsForRole($serviceRoleId) === $beforeUnknownPermissionSet,
        'Failed role-permission replacement changed assignments.');

    foreach ([
        ['bad'],
        [0],
        [-1],
        [$primaryPermissionIds[0], 'bad'],
    ] as $malformedPermissionIds) {
        $beforeMalformedPermissionSet = $repository->permissionIdsForRole($serviceRoleId);
        $malformedPermissionErrors = $rolesValidationErrors(
            fn () => $rolesService->replaceRolePermissions(
                $activeCapableUserId,
                $serviceRoleId,
                $malformedPermissionIds
            )
        );
        $assert(isset($malformedPermissionErrors['permissions']),
            'Malformed desired permission ID set was not rejected safely.');
        $assert($repository->permissionIdsForRole($serviceRoleId) === $beforeMalformedPermissionSet,
            'Malformed desired permission ID set changed assignments.');
    }

    $secondaryBeforeSelfProtection = $repository->permissionIdsForRole($secondaryRoleId);
    $actorSelfPermissionErrors = $rolesValidationErrors(
        fn () => $rolesService->replaceRolePermissions(
            $activeCapableUserId,
            $secondaryRoleId,
            array_values(array_diff($secondaryBeforeSelfProtection, [$secondaryPermissionIds[0]]))
        )
    );
    $assert(isset($actorSelfPermissionErrors['role']),
        'Capable actor removed its own effective recovery permission.');
    $assert($repository->permissionIdsForRole($secondaryRoleId) === $secondaryBeforeSelfProtection,
        'Actor self-protection failure did not roll back role permissions.');

    $backupRoleId = $rolesService->create([
        'name' => 'Backup Recovery Role',
        'slug' => "backup-recovery-{$suffix}",
    ]);
    $allRecoveryPermissionIds = array_map(
        static fn (string $slug): int => $permissionIdsBySlug[$slug],
        $recoverySlugs
    );
    sort($allRecoveryPermissionIds);
    $rolesService->replaceRolePermissions($activeCapableUserId, $backupRoleId, $allRecoveryPermissionIds);
    $backupCapableUserId = $createUser('Backup Capable', 'active');
    $rolesService->replaceUserRoles($activeCapableUserId, $backupCapableUserId, [$backupRoleId]);
    $assert($invariant->isAdministratorCapable($backupCapableUserId),
        'Backup custom-role capability contribution was not recognized.');

    $usersService->changeStatus($backupCapableUserId, 'inactive', $activeCapableUserId);
    $assert($usersRepository->findById($backupCapableUserId)?->status() === 'inactive',
        'Capable target was not deactivated while another capable user remained.');

    $finalPermissionErrors = $rolesValidationErrors(
        fn () => $rolesService->replaceRolePermissions(
            $nonCapableActorId,
            $secondaryRoleId,
            array_values(array_diff($secondaryBeforeSelfProtection, [$secondaryPermissionIds[0]]))
        )
    );
    $assert(isset($finalPermissionErrors['role']), 'Final active capability permission was removable.');
    $assert($repository->permissionIdsForRole($secondaryRoleId) === $secondaryBeforeSelfProtection,
        'Final-invariant permission failure did not roll back assignments.');

    $selfStatusErrors = $usersValidationErrors(
        fn () => $usersService->changeStatus($activeCapableUserId, 'inactive', $activeCapableUserId)
    );
    $assert(isset($selfStatusErrors['status']), 'Self-deactivation was accepted by final status policy.');
    $assert($usersRepository->findById($activeCapableUserId)?->status() === 'active',
        'Self-deactivation failure changed actor status.');

    $usersService->changeStatus($adminAccessOnlyUserId, 'inactive', $activeCapableUserId);
    $assert($usersRepository->findById($adminAccessOnlyUserId)?->status() === 'inactive',
        'admin.access-only target was incorrectly protected as administrator-capable.');
    $finalStatusErrors = $usersValidationErrors(
        fn () => $usersService->changeStatus($activeCapableUserId, 'inactive', $nonCapableActorId)
    );
    $assert(isset($finalStatusErrors['status']), 'Final active administrator-capable user was deactivated.');
    $assert($usersRepository->findById($activeCapableUserId)?->status() === 'active',
        'Failed final status mutation did not roll back target status.');

    $transientRoleId = $repository->create('Transient Role', "m31-b3-transient-{$suffix}");
    $repository->delete($transientRoleId);
    $assert($repository->findById($transientRoleId) === null, 'Role delete persistence primitive failed.');

    $exception = new RolesValidationException(
        ['name' => ' Name is required. ', '' => 'ignored', 'unsafe' => ['ignored']],
        ['name' => 'Safe name', 'slug' => 'safe-slug', 'password' => 'must-not-survive', 'nested' => ['unsafe']]
    );
    $assert($exception->errors() === ['name' => 'Name is required.'],
        'Roles validation errors were not normalized safely.');
    $assert($exception->safeValues() === ['name' => 'Safe name', 'slug' => 'safe-slug'],
        'Roles validation safe values retained an unsafe field.');
    $assert($exception->getMessage() === 'Role validation failed.',
        'Roles validation exception exposed uncontrolled detail.');

    echo "M3.1 Batch 3 domain foundation passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
}
