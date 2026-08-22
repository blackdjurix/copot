<?php

namespace Copot\Core;

use PDO;

class ContentStaleWriteException extends \InvalidArgumentException
{
}

class ContentRepository
{
    public function __construct(private Database $database)
    {
    }

    private function table(): string
    {
        return $this->database->table('content');
    }

    /** @return array{items:list<Content>,total:int,limit:int,offset:int} */
    public function workspace(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);
        $where = [];
        $parameters = [];
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(title LIKE :search_title OR slug LIKE :search_slug)';
            $parameters['search_title'] = '%' . $search . '%';
            $parameters['search_slug'] = '%' . $search . '%';
        }
        if (in_array($filters['type'] ?? null, ['page', 'article'], true)) { $where[] = 'type = :type'; $parameters['type'] = $filters['type']; }
        if (in_array($filters['status'] ?? null, ['draft', 'published', 'archived'], true)) { $where[] = 'status = :status'; $parameters['status'] = $filters['status']; }
        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $connection = $this->database->connection();
        $count = $connection->prepare("SELECT COUNT(*) FROM {$this->table()} {$whereSql}");
        $count->execute($parameters);
        $statement = $connection->prepare("SELECT * FROM {$this->table()} {$whereSql} ORDER BY updated_at DESC, id DESC LIMIT :limit OFFSET :offset");
        foreach ($parameters as $name => $value) $statement->bindValue($name, $value, PDO::PARAM_STR);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        return ['items' => array_map(fn (array $row): Content => new Content($row), $statement->fetchAll()), 'total' => (int) $count->fetchColumn(), 'limit' => $limit, 'offset' => $offset];
    }

    /** @return list<Content> */
    public function paginate(int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);
        $statement = $this->database->connection()->prepare("SELECT * FROM {$this->table()} ORDER BY updated_at DESC, id DESC LIMIT :limit OFFSET :offset");
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        return array_map(fn (array $row): Content => new Content($row), $statement->fetchAll());
    }

    public function findById(int $id): ?Content
    {
        $statement = $this->database->connection()->prepare("SELECT * FROM {$this->table()} WHERE id = :id LIMIT 1");
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? new Content($row) : null;
    }

    public function findBySlug(string $slug): ?Content
    {
        $statement = $this->database->connection()->prepare("SELECT * FROM {$this->table()} WHERE slug = :slug LIMIT 1");
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();
        return is_array($row) ? new Content($row) : null;
    }

    public function findPublishedBySlug(string $slug): ?Content
    {
        $statement = $this->database->connection()->prepare("SELECT * FROM {$this->table()} WHERE slug = :slug AND status = 'published' LIMIT 1");
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();
        return is_array($row) ? new Content($row) : null;
    }

    public function create(array $data): int
    {
        $data = $this->normalizePayload($data);
        $statement = $this->database->connection()->prepare("INSERT INTO {$this->table()} (type,title,slug,excerpt,body,status,author_id,published_at,archived_at,featured_media_id,created_at,updated_at) VALUES (:type,:title,:slug,:excerpt,:body,:status,:author_id,:published_at,NULL,:featured_media_id,NOW(),NOW())");
        $statement->execute(['type'=>$data['type'],'title'=>$data['title'],'slug'=>$data['slug'],'excerpt'=>$data['excerpt'],'body'=>$data['body'],'status'=>$data['status'],'author_id'=>$data['author_id'],'published_at'=>$data['status'] === 'published' ? date('Y-m-d H:i:s') : null,'featured_media_id'=>$data['featured_media_id']]);
        return (int) $this->database->connection()->lastInsertId();
    }

    public function update(int $id, array $data, string $expectedUpdatedAt): void
    {
        $data = $this->normalizePayload($data, false);
        $statement = $this->database->connection()->prepare("UPDATE {$this->table()} SET type=:type,title=:title,slug=:slug,excerpt=:excerpt,body=:body,status=:status,featured_media_id=:featured_media_id,author_id=:author_id,published_at=CASE WHEN :publish='published' AND published_at IS NULL THEN NOW() WHEN :clear <> 'published' THEN NULL ELSE published_at END,archived_at=CASE WHEN :archive='archived' AND archived_at IS NULL THEN NOW() WHEN :unarchive <> 'archived' THEN NULL ELSE archived_at END,updated_at=GREATEST(NOW(),DATE_ADD(updated_at,INTERVAL 1 SECOND)) WHERE id=:id AND updated_at=:expected");
        $statement->execute(['type'=>$data['type'],'title'=>$data['title'],'slug'=>$data['slug'],'excerpt'=>$data['excerpt'],'body'=>$data['body'],'status'=>$data['status'],'featured_media_id'=>$data['featured_media_id'],'author_id'=>$data['author_id'],'publish'=>$data['status'],'clear'=>$data['status'],'archive'=>$data['status'],'unarchive'=>$data['status'],'id'=>$id,'expected'=>$expectedUpdatedAt]);
        if ($statement->rowCount() !== 1) throw new ContentStaleWriteException('Content changed after it was loaded. Refresh and try again.');
    }

    public function transition(int $id, string $from, string $to): void
    {
        $allowed = ['draft'=>['published','archived'],'published'=>['draft','archived'],'archived'=>['draft']];
        if (!in_array($to, $allowed[$from] ?? [], true)) throw new \InvalidArgumentException("Content transition [{$from}] to [{$to}] is not allowed.");
        $published = $to === 'published' ? 'NOW()' : 'NULL';
        $archived = $to === 'archived' ? 'NOW()' : 'NULL';
        $statement = $this->database->connection()->prepare("UPDATE {$this->table()} SET status=:to,published_at={$published},archived_at={$archived},updated_at=GREATEST(NOW(),DATE_ADD(updated_at,INTERVAL 1 SECOND)) WHERE id=:id AND status=:from");
        $statement->execute(['id'=>$id,'from'=>$from,'to'=>$to]);
        if ($statement->rowCount() !== 1) throw new ContentStaleWriteException('Content changed before the transition could be applied. Refresh and try again.');
    }

    public function delete(int $id): void
    {
        $statement = $this->database->connection()->prepare("DELETE FROM {$this->table()} WHERE id=:id");
        $statement->execute(['id'=>$id]);
        if ($statement->rowCount() !== 1) throw new \InvalidArgumentException('Content entry was not found.');
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $sql = "SELECT 1 FROM {$this->table()} WHERE slug=:slug";
        $parameters = ['slug'=>$slug];
        if ($ignoreId !== null) { $sql .= ' AND id <> :ignore_id'; $parameters['ignore_id'] = $ignoreId; }
        $statement = $this->database->connection()->prepare($sql . ' LIMIT 1');
        $statement->execute($parameters);
        return (bool) $statement->fetchColumn();
    }

    private function normalizePayload(array $data, bool $requireAuthor = true): array
    {
        foreach (['type','title','slug','body','status'] as $field) {
            if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '') throw new \InvalidArgumentException("Content field [{$field}] must be a non-empty string.");
            $data[$field] = trim($data[$field]);
        }
        if (strlen($data['slug']) > 190) throw new \InvalidArgumentException('Content slug cannot exceed 190 characters.');
        if (!in_array($data['type'], ['page','article'], true)) throw new \InvalidArgumentException('Content type must be [page] or [article].');
        if (!in_array($data['status'], ['draft','published','archived'], true)) throw new \InvalidArgumentException('Content status must be [draft], [published], or [archived].');
        $data['excerpt'] = isset($data['excerpt']) && is_string($data['excerpt']) && trim($data['excerpt']) !== '' ? trim($data['excerpt']) : null;
        $featured = $data['featured_media_id'] ?? null;
        if ($featured === '' || $featured === null) $data['featured_media_id'] = null;
        elseif (is_int($featured) || (is_string($featured) && preg_match('/^[1-9][0-9]*$/', $featured))) $data['featured_media_id'] = (int) $featured;
        else throw new \InvalidArgumentException('Featured Media reference is invalid.');
        $author = $data['author_id'] ?? null;
        if ($author === '' || $author === null) $data['author_id'] = null;
        elseif (is_numeric($author)) $data['author_id'] = (int) $author;
        else throw new \InvalidArgumentException('Content author_id must be numeric or null.');
        return $data;
    }
}
