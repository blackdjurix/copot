<?php

use Copot\Core\Database;

final class MediaRepository
{
    public function __construct(private Database $database)
    {
    }

    public function findById(MediaId|int $id, bool $forUpdate = false): ?Media
    {
        $id = $this->id($id);
        $sql = 'SELECT * FROM media WHERE id = :id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
        $statement = $this->database->prepareModule($sql);
        $statement->execute(['id' => $id->value()]);
        $row = $statement->fetch();
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findByStorageKey(string $storageKey): ?Media
    {
        $statement = $this->database->prepareModule('SELECT * FROM media WHERE storage_key = :storage_key LIMIT 1');
        $statement->execute(['storage_key' => $storageKey]);
        $row = $statement->fetch();
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function paginate(string $kind = '', int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);
        $where = $kind === '' ? '' : ' WHERE kind = :kind';
        $statement = $this->database->prepareModule(
            'SELECT * FROM media' . $where . ' ORDER BY updated_at DESC, id DESC LIMIT :limit OFFSET :offset'
        );
        if ($kind !== '') $statement->bindValue('kind', $kind, PDO::PARAM_STR);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        return array_map(fn (array $row): Media => $this->hydrate($row), $statement->fetchAll());
    }

    public function workspace(array $filters = [], int $limit = 24, int $offset = 0): array
    {
        $limit = max(1, min($limit, 24));
        $offset = max(0, $offset);
        $where = [];
        $parameters = [];
        $search = trim((string) ($filters['search'] ?? ''));
        $kind = $filters['kind'] ?? null;
        $capability = $filters['capability'] ?? null;

        if ($search !== '') {
            $where[] = '(title LIKE :search_title OR original_filename LIKE :search_filename)';
            $parameters['search_title'] = '%' . $search . '%';
            $parameters['search_filename'] = '%' . $search . '%';
        }

        if (in_array($kind, ['image', 'document'], true)) {
            $where[] = 'kind = :kind';
            $parameters['kind'] = $kind;
        }

        $editableSql = "(kind = 'image' AND mime_type IN ('image/jpeg', 'image/png', 'image/webp'))";
        if ($capability === 'editable') {
            $where[] = $editableSql;
        } elseif ($capability === 'manage-only') {
            $where[] = 'NOT ' . $editableSql;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $connection = $this->database->connection();
        $count = $this->database->prepareModule("SELECT COUNT(*) FROM media {$whereSql}");
        $count->execute($parameters);

        $statement = $this->database->prepareModule(
            "SELECT * FROM media {$whereSql}
             ORDER BY updated_at DESC, id DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value, PDO::PARAM_STR);
        }
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return [
            'items' => array_map(fn (array $row): Media => $this->hydrate($row), $statement->fetchAll()),
            'total' => (int) $count->fetchColumn(),
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    public function create(array $data): MediaId
    {
        $statement = $this->database->prepareModule(
            'INSERT INTO media (kind, original_filename, title, storage_key, mime_type, extension, byte_size, width, height, created_at, updated_at)
             VALUES (:kind, :original_filename, :title, :storage_key, :mime_type, :extension, :byte_size, :width, :height, NOW(), NOW())'
        );
        $statement->execute($data);
        return new MediaId((int) $this->database->connection()->lastInsertId());
    }

    public function updateTitle(MediaId|int $id, string $title): void
    {
        $id = $this->id($id);
        $statement = $this->database->prepareModule(
            'UPDATE media SET title = :title, updated_at = GREATEST(NOW(), DATE_ADD(updated_at, INTERVAL 1 SECOND)) WHERE id = :id'
        );
        $statement->execute(['id' => $id->value(), 'title' => $title]);
        if ($statement->rowCount() !== 1) throw new MediaNotFoundException('Media could not be updated.');
    }

    public function delete(MediaId|int $id): void
    {
        $id = $this->id($id);
        $statement = $this->database->prepareModule('DELETE FROM media WHERE id = :id');
        $statement->execute(['id' => $id->value()]);
        if ($statement->rowCount() !== 1) throw new MediaNotFoundException('Media could not be deleted.');
    }

    private function id(MediaId|int $id): MediaId { return $id instanceof MediaId ? $id : new MediaId($id); }

    private function hydrate(array $row): Media
    {
        return new Media(
            new MediaId((int) $row['id']), (string) $row['kind'], (string) $row['original_filename'],
            (string) $row['title'], (string) $row['storage_key'], (string) $row['mime_type'],
            (string) $row['extension'], (int) $row['byte_size'], $row['width'] === null ? null : (int) $row['width'],
            $row['height'] === null ? null : (int) $row['height'], (string) $row['created_at'], (string) $row['updated_at']
        );
    }
}

class MediaNotFoundException extends RuntimeException {}
