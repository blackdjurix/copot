<?php

use Copot\Core\Database;

class UsersRepository
{
    private const SAFE_COLUMNS = 'id, name, email, status, last_login_at, created_at, updated_at';

    public function __construct(private Database $database)
    {
    }

    public function paginate(int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);
        $statement = $this->database->connection()->prepare(
            'SELECT ' . self::SAFE_COLUMNS . '
            FROM users
            ORDER BY updated_at DESC, id DESC
            LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn (array $row): ManagedUser => $this->hydrate($row), $statement->fetchAll());
    }

    public function findById(int $id): ?ManagedUser
    {
        return $this->findByIdQuery($id, false);
    }

    public function findByIdForUpdate(int $id): ?ManagedUser
    {
        return $this->findByIdQuery($id, true);
    }

    public function findByEmail(string $normalizedEmail): ?ManagedUser
    {
        $statement = $this->database->connection()->prepare(
            'SELECT ' . self::SAFE_COLUMNS . ' FROM users WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $normalizedEmail]);
        $user = $statement->fetch();

        return is_array($user) ? $this->hydrate($user) : null;
    }

    public function emailExists(string $normalizedEmail, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT 1 FROM users WHERE email = :email';
        $parameters = ['email' => $normalizedEmail];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
            $parameters['ignore_id'] = $ignoreId;
        }

        $statement = $this->database->connection()->prepare($sql . ' LIMIT 1');
        $statement->execute($parameters);

        return (bool) $statement->fetchColumn();
    }

    public function create(string $name, string $email, string $passwordHash, string $status): int
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO users (name, email, password_hash, status, created_at, updated_at)
            VALUES (:name, :email, :password_hash, :status, NOW(), NOW())'
        );
        $statement->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => $passwordHash,
            'status' => $status,
        ]);

        return (int) $this->database->connection()->lastInsertId();
    }

    public function updateIdentity(int $id, string $name, string $email): void
    {
        $statement = $this->database->connection()->prepare(
            'UPDATE users SET name = :name, email = :email, updated_at = NOW() WHERE id = :id'
        );
        $statement->execute(['id' => $id, 'name' => $name, 'email' => $email]);
    }

    public function updatePasswordHash(int $id, string $passwordHash): void
    {
        $statement = $this->database->connection()->prepare(
            'UPDATE users SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id'
        );
        $statement->execute(['id' => $id, 'password_hash' => $passwordHash]);
    }

    public function updateStatus(int $id, string $status): void
    {
        $statement = $this->database->connection()->prepare(
            'UPDATE users SET status = :status, updated_at = NOW() WHERE id = :id'
        );
        $statement->execute(['id' => $id, 'status' => $status]);
    }

    private function findByIdQuery(int $id, bool $forUpdate): ?ManagedUser
    {
        $sql = 'SELECT ' . self::SAFE_COLUMNS . ' FROM users WHERE id = :id LIMIT 1';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $statement = $this->database->connection()->prepare($sql);
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();

        return is_array($user) ? $this->hydrate($user) : null;
    }

    private function hydrate(array $row): ManagedUser
    {
        return new ManagedUser(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['email'],
            (string) $row['status'],
            $row['last_login_at'] === null ? null : (string) $row['last_login_at'],
            (string) $row['created_at'],
            (string) $row['updated_at']
        );
    }
}
