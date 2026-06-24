<?php

namespace Copot\Core;

class PermissionChecker
{
    public function __construct(private Database $database)
    {
    }

    public function userHasRole(int $userId, string $role): bool
    {
        $sql = 'SELECT 1
            FROM user_roles
            INNER JOIN roles ON roles.id = user_roles.role_id
            WHERE user_roles.user_id = :user_id
                AND roles.slug = :role
            LIMIT 1';

        $statement = $this->database->connection()->prepare($sql);
        $statement->execute([
            'user_id' => $userId,
            'role' => $role,
        ]);

        return (bool) $statement->fetchColumn();
    }

    public function userCan(int $userId, string $permission): bool
    {
        $sql = 'SELECT 1
            FROM user_roles
            INNER JOIN role_permissions ON role_permissions.role_id = user_roles.role_id
            INNER JOIN permissions ON permissions.id = role_permissions.permission_id
            WHERE user_roles.user_id = :user_id
                AND permissions.slug = :permission
            LIMIT 1';

        $statement = $this->database->connection()->prepare($sql);
        $statement->execute([
            'user_id' => $userId,
            'permission' => $permission,
        ]);

        return (bool) $statement->fetchColumn();
    }
}
