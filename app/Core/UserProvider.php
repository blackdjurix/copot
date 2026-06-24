<?php

namespace Copot\Core;

class UserProvider
{
    private PermissionChecker $permissions;

    public function __construct(private Database $database)
    {
        $this->permissions = new PermissionChecker($database);
    }

    public function findById(int $id): ?User
    {
        $statement = $this->database->connection()->prepare(
            'SELECT * FROM users WHERE id = :id LIMIT 1'
        );

        $statement->execute(['id' => $id]);
        $user = $statement->fetch();

        return is_array($user) ? new User($user, $this->permissions) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $statement = $this->database->connection()->prepare(
            'SELECT * FROM users WHERE email = :email LIMIT 1'
        );

        $statement->execute(['email' => strtolower(trim($email))]);
        $user = $statement->fetch();

        return is_array($user) ? new User($user, $this->permissions) : null;
    }

    public function updateLastLogin(int $id): void
    {
        $statement = $this->database->connection()->prepare(
            'UPDATE users SET last_login_at = NOW(), updated_at = NOW() WHERE id = :id'
        );

        $statement->execute(['id' => $id]);
    }
}
