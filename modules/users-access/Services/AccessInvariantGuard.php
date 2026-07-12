<?php

class AccessInvariantGuard
{
    private const RECOVERY_PERMISSIONS = [
        'admin.access',
        'users.read',
        'users.status.manage',
        'roles.read',
        'roles.manage',
        'users.roles.manage',
        'roles.permissions.manage',
    ];

    public function __construct(
        private RolesRepository $roles,
        private UsersRepository $users
    ) {
    }

    public function lockInvariantMutex(): void
    {
        $this->roles->lockInvariantMutex();
    }

    public function isAdministratorCapable(int $userId): bool
    {
        $user = $this->users->findById($userId);

        return $user?->isActive() === true
            && $this->roles->effectivePermissionMatchCount($userId, self::RECOVERY_PERMISSIONS)
                === count(self::RECOVERY_PERMISSIONS);
    }

    public function hasActiveAdministratorCapableUser(): bool
    {
        return $this->roles->activeUsersMatchingAllPermissionsCount(self::RECOVERY_PERMISSIONS) > 0;
    }
}
