<?php

use Copot\Core\Database;

class ContentStaleWriteException extends InvalidArgumentException
{
}

class ContentRepository
{
    public function __construct(private Database $database)
    {
    }

    public function workspace(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);
        $where = [];
        $parameters = [];
        $search = trim((string) ($filters['search'] ?? ''));
        $type = ($filters['type'] ?? null);
        $status = ($filters['status'] ?? null);

        if ($search !== '') {
            $where[] = '(title LIKE :search_title OR slug LIKE :search_slug)';
            $parameters['search_title'] = '%' . $search . '%';
            $parameters['search_slug'] = '%' . $search . '%';
        }

        if (in_array($type, ['page', 'article'], true)) {
            $where[] = 'type = :type';
            $parameters['type'] = $type;
        }

        if (in_array($status, ['draft', 'published', 'archived'], true)) {
            $where[] = 'status = :status';
            $parameters['status'] = $status;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $connection = $this->database->connection();
        $count = $connection->prepare("SELECT COUNT(*) FROM content {$whereSql}");
        $count->execute($parameters);

        $statement = $connection->prepare(
            "SELECT * FROM content
            {$whereSql}
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
            'items' => array_map(fn (array $row): Content => new Content($row), $statement->fetchAll()),
            'total' => (int) $count->fetchColumn(),
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    public function paginate(int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $statement = $this->database->connection()->prepare(
            'SELECT * FROM content
            ORDER BY updated_at DESC, id DESC
            LIMIT :limit OFFSET :offset'
        );

        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn (array $row): Content => new Content($row), $statement->fetchAll());
    }

    public function findById(int $id): ?Content
    {
        $statement = $this->database->connection()->prepare(
            'SELECT * FROM content WHERE id = :id LIMIT 1'
        );

        $statement->execute(['id' => $id]);
        $content = $statement->fetch();

        return is_array($content) ? new Content($content) : null;
    }

    public function findPublishedBySlug(string $slug): ?Content
    {
        $statement = $this->database->connection()->prepare(
            'SELECT * FROM content
            WHERE slug = :slug
                AND status = :status
            LIMIT 1'
        );

        $statement->execute([
            'slug' => $slug,
            'status' => 'published',
        ]);

        $content = $statement->fetch();

        return is_array($content) ? new Content($content) : null;
    }

    public function create(array $data): int
    {
        $data = $this->normalizePayload($data);

        $statement = $this->database->connection()->prepare(
            'INSERT INTO content (
                type,
                title,
                slug,
                excerpt,
                body,
                status,
                author_id,
                published_at,
                archived_at,
                created_at,
                updated_at
            ) VALUES (
                :type,
                :title,
                :slug,
                :excerpt,
                :body,
                :status,
                :author_id,
                :published_at,
                NULL,
                NOW(),
                NOW()
            )'
        );

        $statement->execute([
            'type' => $data['type'],
            'title' => $data['title'],
            'slug' => $data['slug'],
            'excerpt' => $data['excerpt'],
            'body' => $data['body'],
            'status' => $data['status'],
            'author_id' => $data['author_id'],
            'published_at' => $data['status'] === 'published' ? $this->now() : null,
        ]);

        return (int) $this->database->connection()->lastInsertId();
    }

    public function update(int $id, array $data, string $expectedUpdatedAt): void
    {
        $data = $this->normalizePayload($data, false);

        $statement = $this->database->connection()->prepare(
            'UPDATE content
            SET type = :type,
                title = :title,
                slug = :slug,
                excerpt = :excerpt,
                body = :body,
                status = :status,
                author_id = :author_id,
                published_at = CASE
                    WHEN :status_for_publish = \'published\' AND published_at IS NULL THEN NOW()
                    WHEN :status_for_clear <> \'published\' THEN NULL
                    ELSE published_at
                END,
                archived_at = CASE
                    WHEN :status_for_archive = \'archived\' AND archived_at IS NULL THEN NOW()
                    WHEN :status_for_unarchive <> \'archived\' THEN NULL
                    ELSE archived_at
                END,
                updated_at = GREATEST(NOW(), DATE_ADD(updated_at, INTERVAL 1 SECOND))
            WHERE id = :id
              AND updated_at = :expected_updated_at'
        );

        $statement->execute([
            'id' => $id,
            'type' => $data['type'],
            'title' => $data['title'],
            'slug' => $data['slug'],
            'excerpt' => $data['excerpt'],
            'body' => $data['body'],
            'status' => $data['status'],
            'status_for_publish' => $data['status'],
            'status_for_clear' => $data['status'],
            'status_for_archive' => $data['status'],
            'status_for_unarchive' => $data['status'],
            'author_id' => $data['author_id'],
            'expected_updated_at' => $expectedUpdatedAt,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new ContentStaleWriteException('Content changed after it was loaded. Refresh and try again.');
        }
    }

    public function transition(int $id, string $from, string $to): void
    {
        $allowed = [
            'draft' => ['published', 'archived'],
            'published' => ['draft', 'archived'],
            'archived' => ['draft'],
        ];

        if (!in_array($to, $allowed[$from] ?? [], true)) {
            throw new InvalidArgumentException("Content transition [{$from}] to [{$to}] is not allowed.");
        }

        $publishedAt = $to === 'published' ? 'NOW()' : 'NULL';
        $archivedAt = $to === 'archived' ? 'NOW()' : 'NULL';
        $statement = $this->database->connection()->prepare(
            "UPDATE content
            SET status = :to,
                published_at = {$publishedAt},
                archived_at = {$archivedAt},
                updated_at = GREATEST(NOW(), DATE_ADD(updated_at, INTERVAL 1 SECOND))
            WHERE id = :id AND status = :from"
        );

        $statement->execute([
            'id' => $id,
            'from' => $from,
            'to' => $to,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new ContentStaleWriteException('Content changed before the transition could be applied. Refresh and try again.');
        }
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT 1 FROM content WHERE slug = :slug';
        $parameters = ['slug' => $slug];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
            $parameters['ignore_id'] = $ignoreId;
        }

        $sql .= ' LIMIT 1';

        $statement = $this->database->connection()->prepare($sql);
        $statement->execute($parameters);

        return (bool) $statement->fetchColumn();
    }

    private function normalizePayload(array $data, bool $requireAuthor = true): array
    {
        foreach (['type', 'title', 'slug', 'body', 'status'] as $field) {
            if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '') {
                throw new InvalidArgumentException("Content field [{$field}] must be a non-empty string.");
            }

            $data[$field] = trim($data[$field]);
        }

        if (strlen($data['slug']) > 190) {
            throw new InvalidArgumentException('Content slug cannot exceed 190 characters.');
        }

        if (!in_array($data['type'], ['page', 'article'], true)) {
            throw new InvalidArgumentException('Content type must be [page] or [article].');
        }

        if (!in_array($data['status'], ['draft', 'published', 'archived'], true)) {
            throw new InvalidArgumentException('Content status must be [draft], [published], or [archived].');
        }

        $data['excerpt'] = isset($data['excerpt']) && is_string($data['excerpt']) && trim($data['excerpt']) !== ''
            ? trim($data['excerpt'])
            : null;

        if (!array_key_exists('author_id', $data) || $data['author_id'] === null || $data['author_id'] === '') {
            if ($requireAuthor) {
                $data['author_id'] = null;
            } else {
                $data['author_id'] = null;
            }
        } elseif (!is_numeric($data['author_id'])) {
            throw new InvalidArgumentException('Content author_id must be numeric or null.');
        } else {
            $data['author_id'] = (int) $data['author_id'];
        }

        return $data;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
