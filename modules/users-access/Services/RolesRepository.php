<?php

use Copot\Core\Database;

class RolesRepository
{
    private const SAFE_COLUMNS = 'id, name, slug, created_at, updated_at';

    public function __construct(private Database $database)
    {
    }

    public function paginate(int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);
        $statement = $this->database->connection()->prepare(
            'SELECT ' . self::SAFE_COLUMNS . '
            FROM roles
            ORDER BY name ASC, id ASC
            LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn (array $row): ManagedRole => $this->hydrate($row), $statement->fetchAll());
    }

    public function all(): array
    {
        $statement = $this->database->connection()->query(
            'SELECT ' . self::SAFE_COLUMNS . ' FROM roles ORDER BY name ASC, slug ASC, id ASC'
        );

        return array_map(fn (array $row): ManagedRole => $this->hydrate($row), $statement->fetchAll());
    }

    public function findById(int $id): ?ManagedRole
    {
        return $this->findByIdQuery($id, false);
    }

    public function findByIdForUpdate(int $id): ?ManagedRole
    {
        return $this->findByIdQuery($id, true);
    }

    public function findBySlug(string $slug): ?ManagedRole
    {
        $statement = $this->database->connection()->prepare(
            'SELECT ' . self::SAFE_COLUMNS . ' FROM roles WHERE slug = :slug LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);
        $role = $statement->fetch();

        return is_array($role) ? $this->hydrate($role) : null;
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT 1 FROM roles WHERE slug = :slug';
        $parameters = ['slug' => $slug];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
            $parameters['ignore_id'] = $ignoreId;
        }

        $statement = $this->database->connection()->prepare($sql . ' LIMIT 1');
        $statement->execute($parameters);

        return (bool) $statement->fetchColumn();
    }

    public function create(string $name, string $slug): int
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO roles (name, slug, created_at, updated_at)
            VALUES (:name, :slug, NOW(), NOW())'
        );
        $statement->execute(['name' => $name, 'slug' => $slug]);

        return (int) $this->database->connection()->lastInsertId();
    }

    public function updateName(int $id, string $name): void
    {
        $statement = $this->database->connection()->prepare(
            'UPDATE roles SET name = :name, updated_at = NOW() WHERE id = :id'
        );
        $statement->execute(['id' => $id, 'name' => $name]);
    }

    public function delete(int $id): void
    {
        $statement = $this->database->connection()->prepare('DELETE FROM roles WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function assignedUserCount(int $roleId): int
    {
        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(*) FROM user_roles WHERE role_id = :role_id'
        );
        $statement->execute(['role_id' => $roleId]);

        return (int) $statement->fetchColumn();
    }

    public function permissions(): array
    {
        return $this->database->connection()->query(
            'SELECT id, name, slug FROM permissions ORDER BY name ASC, id ASC'
        )->fetchAll();
    }

    public function permissionIdsForRole(int $roleId): array
    {
        return $this->integerColumn(
            'SELECT permission_id FROM role_permissions WHERE role_id = :id ORDER BY permission_id ASC',
            $roleId
        );
    }

    public function roleIdsForUser(int $userId): array
    {
        return $this->integerColumn(
            'SELECT role_id FROM user_roles WHERE user_id = :id ORDER BY role_id ASC',
            $userId
        );
    }

    public function existingRoleIds(array $ids): array
    {
        return $this->existingIds('roles', $ids);
    }

    public function existingPermissionIds(array $ids): array
    {
        return $this->existingIds('permissions', $ids);
    }

    public function addUserRoles(int $userId, array $roleIds): void
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
        );

        foreach ($roleIds as $roleId) {
            $statement->execute(['user_id' => $userId, 'role_id' => $roleId]);
        }
    }

    public function removeUserRoles(int $userId, array $roleIds): void
    {
        $this->deleteAssignments('user_roles', 'user_id', $userId, 'role_id', $roleIds);
    }

    public function addRolePermissions(int $roleId, array $permissionIds): void
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)'
        );

        foreach ($permissionIds as $permissionId) {
            $statement->execute(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }

    public function removeRolePermissions(int $roleId, array $permissionIds): void
    {
        $this->deleteAssignments('role_permissions', 'role_id', $roleId, 'permission_id', $permissionIds);
    }

    public function effectivePermissionMatchCount(int $userId, array $permissionSlugs): int
    {
        $permissionSlugs = $this->normalizePermissionSlugs($permissionSlugs);
        $placeholders = implode(', ', array_fill(0, count($permissionSlugs), '?'));
        $statement = $this->database->connection()->prepare(
            "SELECT COUNT(DISTINCT permissions.slug)
            FROM user_roles
            INNER JOIN role_permissions ON role_permissions.role_id = user_roles.role_id
            INNER JOIN permissions ON permissions.id = role_permissions.permission_id
            WHERE user_roles.user_id = ?
                AND permissions.slug IN ({$placeholders})"
        );
        $statement->execute([$userId, ...$permissionSlugs]);

        return (int) $statement->fetchColumn();
    }

    public function activeUsersMatchingAllPermissionsCount(array $permissionSlugs): int
    {
        $permissionSlugs = $this->normalizePermissionSlugs($permissionSlugs);
        $placeholders = implode(', ', array_fill(0, count($permissionSlugs), '?'));
        $requiredCount = count($permissionSlugs);
        $statement = $this->database->connection()->prepare(
            "SELECT COUNT(*)
            FROM (
                SELECT users.id
                FROM users
                INNER JOIN user_roles ON user_roles.user_id = users.id
                INNER JOIN role_permissions ON role_permissions.role_id = user_roles.role_id
                INNER JOIN permissions ON permissions.id = role_permissions.permission_id
                WHERE users.status = 'active'
                    AND permissions.slug IN ({$placeholders})
                GROUP BY users.id
                HAVING COUNT(DISTINCT permissions.slug) = {$requiredCount}
            ) AS matching_active_users"
        );
        $statement->execute($permissionSlugs);

        return (int) $statement->fetchColumn();
    }

    public function lockInvariantMutex(): int
    {
        $connection = $this->database->connection();

        if (!$connection->inTransaction()) {
            throw new RuntimeException('Administrator invariant mutex requires an active transaction.');
        }

        $statement = $connection->query("SELECT id FROM roles WHERE slug = 'admin' FOR UPDATE");
        $roleId = $statement->fetchColumn();

        if (!is_numeric($roleId)) {
            throw new RuntimeException('Seeded admin role is unavailable for invariant serialization.');
        }

        return (int) $roleId;
    }

    private function findByIdQuery(int $id, bool $forUpdate): ?ManagedRole
    {
        $sql = 'SELECT ' . self::SAFE_COLUMNS . ' FROM roles WHERE id = :id LIMIT 1';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $statement = $this->database->connection()->prepare($sql);
        $statement->execute(['id' => $id]);
        $role = $statement->fetch();

        return is_array($role) ? $this->hydrate($role) : null;
    }

    private function integerColumn(string $sql, int $id): array
    {
        $statement = $this->database->connection()->prepare($sql);
        $statement->execute(['id' => $id]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function existingIds(string $table, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn ($id): bool => is_int($id) && $id > 0)));

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $statement = $this->database->connection()->prepare(
            "SELECT id FROM {$table} WHERE id IN ({$placeholders}) ORDER BY id ASC"
        );
        $statement->execute($ids);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function deleteAssignments(
        string $table,
        string $ownerColumn,
        int $ownerId,
        string $targetColumn,
        array $targetIds
    ): void {
        $targetIds = array_values(array_unique($targetIds));

        if ($targetIds === []) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($targetIds), '?'));
        $statement = $this->database->connection()->prepare(
            "DELETE FROM {$table}
            WHERE {$ownerColumn} = ? AND {$targetColumn} IN ({$placeholders})"
        );
        $statement->execute([$ownerId, ...$targetIds]);
    }

    private function normalizePermissionSlugs(array $permissionSlugs): array
    {
        $normalized = [];

        foreach ($permissionSlugs as $permissionSlug) {
            if (!is_string($permissionSlug)) {
                continue;
            }

            $permissionSlug = trim($permissionSlug);

            if ($permissionSlug !== '' && !in_array($permissionSlug, $normalized, true)) {
                $normalized[] = $permissionSlug;
            }
        }

        if ($normalized === []) {
            throw new InvalidArgumentException('Permission slug set must not be empty.');
        }

        sort($normalized, SORT_STRING);

        return $normalized;
    }

    private function hydrate(array $row): ManagedRole
    {
        return new ManagedRole(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['slug'],
            (string) $row['created_at'],
            (string) $row['updated_at']
        );
    }
}
