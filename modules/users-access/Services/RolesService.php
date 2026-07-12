<?php

use Copot\Core\Database;

class RolesService
{
    private const SAVEPOINT = 'roles_service_mutation';

    public function __construct(
        private RolesRepository $roles,
        private UsersRepository $users,
        private AccessInvariantGuard $invariant,
        private Database $database
    ) {
    }

    public function create(array $input): int
    {
        $this->rejectUnexpectedFields($input, ['name', 'slug']);
        $name = $this->stringInput($input, 'name');
        $slug = strtolower($this->stringInput($input, 'slug'));
        $errors = $this->roleErrors($name, $slug);

        if (!isset($errors['slug']) && $this->roles->slugExists($slug)) {
            $errors['slug'] = 'Role slug is already in use.';
        }

        if ($errors !== []) {
            throw new RolesValidationException($errors, compact('name', 'slug'));
        }

        try {
            return $this->roles->create($name, $slug);
        } catch (PDOException $exception) {
            if ($this->isDuplicateKey($exception)) {
                throw new RolesValidationException(
                    ['slug' => 'Role slug is already in use.'],
                    compact('name', 'slug')
                );
            }

            throw $exception;
        }
    }

    public function updateName(int $roleId, array $input): void
    {
        $this->rejectUnexpectedFields($input, ['name']);
        $name = $this->stringInput($input, 'name');
        $errors = $this->nameErrors($name);

        if (!$this->roles->findById($roleId) instanceof ManagedRole) {
            $errors['role'] = 'Role is unavailable.';
        }

        if ($errors !== []) {
            throw new RolesValidationException($errors, compact('name'));
        }

        $this->roles->updateName($roleId, $name);
    }

    public function delete(int $actorUserId, int $roleId): void
    {
        $this->transactional(function () use ($actorUserId, $roleId): void {
            $this->invariant->lockInvariantMutex();
            $role = $this->roles->findByIdForUpdate($roleId);

            if (!$role instanceof ManagedRole) {
                throw new RolesValidationException(['role' => 'Role is unavailable.']);
            }

            $actorWasCapable = $this->invariant->isAdministratorCapable($actorUserId);

            if ($role->isSeeded()) {
                throw new RolesValidationException(['role' => 'Seeded roles cannot be deleted.']);
            }

            if ($this->roles->assignedUserCount($roleId) > 0) {
                throw new RolesValidationException(['role' => 'Assigned roles cannot be deleted.']);
            }

            $this->roles->delete($roleId);
            $this->assertResultingState($actorUserId, $actorWasCapable);
        });
    }

    public function replaceUserRoles(int $actorUserId, int $targetUserId, array $desiredRoleIds): void
    {
        $desiredRoleIds = $this->normalizeIds($desiredRoleIds, 'roles');

        $this->transactional(function () use ($actorUserId, $targetUserId, $desiredRoleIds): void {
            $this->invariant->lockInvariantMutex();
            $target = $this->users->findByIdForUpdate($targetUserId);

            if (!$target instanceof ManagedUser) {
                throw new RolesValidationException(['roles' => 'User account is unavailable.']);
            }

            $actorWasCapable = $this->invariant->isAdministratorCapable($actorUserId);
            $existingRoleIds = $this->roles->existingRoleIds($desiredRoleIds);

            if ($existingRoleIds !== $desiredRoleIds) {
                throw new RolesValidationException(['roles' => 'One or more selected roles are unavailable.']);
            }

            $currentRoleIds = $this->roles->roleIdsForUser($targetUserId);
            $toRemove = array_values(array_diff($currentRoleIds, $desiredRoleIds));
            $toAdd = array_values(array_diff($desiredRoleIds, $currentRoleIds));
            $this->roles->removeUserRoles($targetUserId, $toRemove);
            $this->roles->addUserRoles($targetUserId, $toAdd);
            $this->assertResultingState($actorUserId, $actorWasCapable);
        });
    }

    public function replaceRolePermissions(int $actorUserId, int $roleId, array $desiredPermissionIds): void
    {
        $desiredPermissionIds = $this->normalizeIds($desiredPermissionIds, 'permissions');

        $this->transactional(function () use ($actorUserId, $roleId, $desiredPermissionIds): void {
            $this->invariant->lockInvariantMutex();
            $role = $this->roles->findByIdForUpdate($roleId);

            if (!$role instanceof ManagedRole) {
                throw new RolesValidationException(['permissions' => 'Role is unavailable.']);
            }

            $actorWasCapable = $this->invariant->isAdministratorCapable($actorUserId);
            $existingPermissionIds = $this->roles->existingPermissionIds($desiredPermissionIds);

            if ($existingPermissionIds !== $desiredPermissionIds) {
                throw new RolesValidationException(['permissions' => 'One or more selected permissions are unavailable.']);
            }

            $currentPermissionIds = $this->roles->permissionIdsForRole($roleId);
            $toRemove = array_values(array_diff($currentPermissionIds, $desiredPermissionIds));
            $toAdd = array_values(array_diff($desiredPermissionIds, $currentPermissionIds));
            $this->roles->removeRolePermissions($roleId, $toRemove);
            $this->roles->addRolePermissions($roleId, $toAdd);
            $this->assertResultingState($actorUserId, $actorWasCapable);
        });
    }

    private function assertResultingState(int $actorUserId, bool $actorWasCapable): void
    {
        if ($actorWasCapable && !$this->invariant->isAdministratorCapable($actorUserId)) {
            throw new RolesValidationException(['role' => 'You cannot remove your own administrator recovery access.']);
        }

        if (!$this->invariant->hasActiveAdministratorCapableUser()) {
            throw new RolesValidationException(['role' => 'At least one active administrator-capable user is required.']);
        }
    }

    private function transactional(callable $operation): mixed
    {
        $connection = $this->database->connection();
        $ownsTransaction = !$connection->inTransaction();

        if ($ownsTransaction) {
            $connection->beginTransaction();
        } else {
            $connection->exec('SAVEPOINT ' . self::SAVEPOINT);
        }

        try {
            $result = $operation();

            if ($ownsTransaction) {
                $connection->commit();
            } else {
                $connection->exec('RELEASE SAVEPOINT ' . self::SAVEPOINT);
            }

            return $result;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            } elseif ($connection->inTransaction()) {
                $connection->exec('ROLLBACK TO SAVEPOINT ' . self::SAVEPOINT);
                $connection->exec('RELEASE SAVEPOINT ' . self::SAVEPOINT);
            }

            throw $exception;
        }
    }

    private function roleErrors(string $name, string $slug): array
    {
        $errors = $this->nameErrors($name);

        if (
            $slug === ''
            || strlen($slug) > 100
            || preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $slug) !== 1
        ) {
            $errors['slug'] = 'Role slug must use lowercase letters, numbers, dots, underscores, or hyphens.';
        }

        return $errors;
    }

    private function nameErrors(string $name): array
    {
        $length = preg_match_all('/./us', $name);

        return $name === ''
            || !is_int($length)
            || $length > 80
            || preg_match('/[\x00-\x1F\x7F]/', $name)
                ? ['name' => 'Role name is required and must not exceed 80 characters.']
                : [];
    }

    private function normalizeIds(array $ids, string $field): array
    {
        foreach ($ids as $id) {
            if (!is_int($id) || $id <= 0) {
                throw new RolesValidationException([
                    $field => 'One or more selected values are invalid.',
                ]);
            }
        }

        $normalized = array_values(array_unique($ids));
        sort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    private function rejectUnexpectedFields(array $input, array $allowed): void
    {
        foreach (array_keys($input) as $field) {
            if (!is_string($field) || !in_array($field, $allowed, true)) {
                throw new RolesValidationException(['form' => 'Unexpected role input was provided.']);
            }
        }
    }

    private function stringInput(array $input, string $field): string
    {
        $value = $input[$field] ?? null;

        return is_string($value) ? trim($value) : "\0";
    }

    private function isDuplicateKey(PDOException $exception): bool
    {
        return is_array($exception->errorInfo) && (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }
}
