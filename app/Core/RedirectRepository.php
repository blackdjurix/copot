<?php

namespace Copot\Core;


use Copot\Core\Database;

final class RedirectRepository
{
    public function __construct(private Database $database)
    {
    }

    public function findById(int $id, bool $forUpdate = false): ?Redirect
    {
        $sql = 'SELECT * FROM ' . $this->database->tables()->table('redirects') . ' WHERE id = :id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
        return $this->hydrate($this->row($sql, ['id' => $id]));
    }

    public function findBySource(string $source): ?Redirect
    {
        return $this->hydrate($this->row(
            'SELECT * FROM ' . $this->database->tables()->table('redirects') . ' WHERE source_path = :source_path LIMIT 1',
            ['source_path' => $source]
        ));
    }

    public function all(): array
    {
        $statement = $this->database->queryModule('SELECT * FROM ' . $this->database->tables()->table('redirects') . ' ORDER BY source_path ASC, id ASC');

        return array_map(fn (array $row): Redirect => $this->hydrate($row), $statement->fetchAll());
    }

    public function sourceExists(string $source, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT 1 FROM ' . $this->database->tables()->table('redirects') . ' WHERE source_path = :source_path';
        $parameters = ['source_path' => $source];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
            $parameters['ignore_id'] = $ignoreId;
        }

        return $this->row($sql . ' LIMIT 1', $parameters) !== null;
    }

    public function create(string $source, string $target, int $status): int
    {
        $statement = $this->database->prepareModule(
            'INSERT INTO ' . $this->database->tables()->table('redirects') . ' (source_path, target, status_code, created_at, updated_at)
             VALUES (:source_path, :target, :status_code, NOW(), NOW())'
        );
        $statement->execute([
            'source_path' => $source,
            'target' => $target,
            'status_code' => $status,
        ]);

        return (int) $this->database->connection()->lastInsertId();
    }

    public function update(int $id, string $source, string $target, int $status, string $expectedUpdatedAt): void
    {
        $statement = $this->database->prepareModule(
            'UPDATE ' . $this->database->tables()->table('redirects') . '
             SET source_path = :source_path,
                 target = :target,
                 status_code = :status_code,
                 updated_at = GREATEST(NOW(), DATE_ADD(updated_at, INTERVAL 1 SECOND))
             WHERE id = :id AND updated_at = :expected_updated_at'
        );
        $statement->execute([
            'id' => $id,
            'source_path' => $source,
            'target' => $target,
            'status_code' => $status,
            'expected_updated_at' => $expectedUpdatedAt,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new RedirectStaleWriteException('Redirect was changed by another operation.');
        }
    }

    public function delete(int $id, string $expectedUpdatedAt): void
    {
        $statement = $this->database->prepareModule(
            'DELETE FROM ' . $this->database->tables()->table('redirects') . ' WHERE id = :id AND updated_at = :expected_updated_at'
        );
        $statement->execute(['id' => $id, 'expected_updated_at' => $expectedUpdatedAt]);

        if ($statement->rowCount() !== 1) {
            throw new RedirectStaleWriteException('Redirect was changed by another operation.');
        }
    }

    private function row(string $sql, array $parameters): ?array
    {
        $statement = $this->database->prepareModule($sql);
        $statement->execute($parameters);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function hydrate(?array $row): ?Redirect
    {
        if ($row === null) {
            return null;
        }

        return new Redirect(
            (int) $row['id'],
            (string) $row['source_path'],
            (string) $row['target'],
            (int) $row['status_code'],
            (string) $row['created_at'],
            (string) $row['updated_at']
        );
    }
}
