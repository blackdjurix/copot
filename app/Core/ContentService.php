<?php

namespace Copot\Core;

class ContentWriteException extends \RuntimeException
{
}

class ContentDuplicateSlugException extends \InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('The content slug is already in use.');
    }
}

class ContentService
{
    public function __construct(
        private Database $database,
        private ContentRepository $repository,
        private mixed $taxonomyAssignments = null,
        private mixed $mediaReferences = null
    ) {
    }

    public function create(array $data, array $taxonomy = [], ?int $userId = null): int
    {
        $cleanup = [];
        $result = $this->withinTransaction(function () use ($data, $taxonomy, $userId, &$cleanup): int {
            $id = $this->repository->create($data);
            $cleanup = $this->mediaReferences?->sync($id, null, $data['featured_media_id'] ?? null, $data['featured_media_pending_token'] ?? null, $userId) ?? [];
            $this->syncTaxonomy($id, $taxonomy);
            return $id;
        });
        $this->mediaReferences?->finalize($cleanup);
        return $result;
    }

    public function update(int $id, array $data, array $taxonomy = [], string $expectedUpdatedAt = '', ?int $userId = null): void
    {
        $cleanup = [];
        $this->withinTransaction(function () use ($id, $data, $taxonomy, $expectedUpdatedAt, $userId, &$cleanup): void {
            $current = $this->repository->findById($id);
            if (!$current) throw new \InvalidArgumentException('Content entry was not found.');
            if ($expectedUpdatedAt === '') throw new \InvalidArgumentException('Content version token is required.');
            if ($current->status() !== ($data['status'] ?? null) && $current->isArchived()) throw new \InvalidArgumentException('Archived content must be restored through the restore action.');
            if (($data['status'] ?? null) === 'archived' && !$current->isArchived()) throw new \InvalidArgumentException('Content must be archived through the archive action.');
            $this->assertTransition($current->status(), $data['status'] ?? null, true);
            $this->repository->update($id, $data, $expectedUpdatedAt);
            $cleanup = $this->mediaReferences?->sync($id, $current->featuredMediaId(), $data['featured_media_id'] ?? null, $data['featured_media_pending_token'] ?? null, $userId) ?? [];
            $this->syncTaxonomy($id, $taxonomy);
        });
        $this->mediaReferences?->finalize($cleanup);
    }

    public function publish(int $id): void { $this->transition($id, 'published', ['draft']); }
    public function draft(int $id): void { $this->transition($id, 'draft', ['published']); }
    public function archive(int $id): void { $this->transition($id, 'archived', ['draft', 'published']); }
    public function restore(int $id): void { $this->transition($id, 'draft', ['archived']); }

    public function delete(int $id): void
    {
        $this->withinTransaction(function () use ($id): void {
            $current = $this->repository->findById($id);
            if (!$current) throw new \InvalidArgumentException('Content entry was not found.');
            $this->mediaReferences?->sync($id, $current->featuredMediaId(), null);
            $this->repository->delete($id);
        });
    }

    private function transition(int $id, string $target, array $expectedFrom): void
    {
        $this->withinTransaction(function () use ($id, $target, $expectedFrom): void {
            $current = $this->repository->findById($id);
            if (!$current) throw new \InvalidArgumentException('Content entry was not found.');
            $this->assertTransition($current->status(), $target);
            if (!in_array($current->status(), $expectedFrom, true)) throw new \InvalidArgumentException("Content transition [{$current->status()}] to [{$target}] is not allowed.");
            $this->repository->transition($id, $current->status(), $target);
        });
    }

    private function assertTransition(string $from, ?string $to, bool $allowSame = false): void
    {
        if (!is_string($to) || !in_array($to, ['draft','published','archived'], true)) throw new \InvalidArgumentException('Content status must be [draft], [published], or [archived].');
        if ($allowSame && $from === $to) return;
        $allowed = ['draft'=>['published','archived'],'published'=>['draft','archived'],'archived'=>['draft']];
        if (!in_array($to, $allowed[$from] ?? [], true)) throw new \InvalidArgumentException("Content transition [{$from}] to [{$to}] is not allowed.");
    }

    private function syncTaxonomy(int $id, array $taxonomy): void
    {
        if (!$this->taxonomyAssignments) return;
        $this->taxonomyAssignments->syncForType('content', $id, 'category', $taxonomy['category_ids'] ?? []);
        $this->taxonomyAssignments->syncForType('content', $id, 'tag', $taxonomy['tag_ids'] ?? []);
    }

    private function withinTransaction(callable $operation): mixed
    {
        $connection = $this->database->connection();
        $owns = !$connection->inTransaction();
        if ($owns) $connection->beginTransaction();
        try {
            $result = $operation();
            if ($owns && $connection->inTransaction()) $connection->commit();
            return $result;
        } catch (\PDOException $exception) {
            if ($owns && $connection->inTransaction()) $connection->rollBack();
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) throw new ContentDuplicateSlugException();
            throw new ContentWriteException('Content could not be saved.', 0, $exception);
        } catch (\InvalidArgumentException $exception) {
            if ($owns && $connection->inTransaction()) $connection->rollBack();
            throw $exception;
        } catch (\Throwable $exception) {
            if ($owns && $connection->inTransaction()) $connection->rollBack();
            throw new ContentWriteException('Content could not be saved.', 0, $exception);
        }
    }
}
