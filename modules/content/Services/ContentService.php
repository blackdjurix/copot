<?php

use Copot\Core\Database;

class ContentWriteException extends RuntimeException
{
}

class ContentDuplicateSlugException extends InvalidArgumentException
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
        private ?TaxonomyAssignmentRepository $taxonomyAssignments = null
    ) {
    }

    public function create(array $data, array $taxonomy = []): int
    {
        return $this->withinTransaction(function () use ($data, $taxonomy): int {
            $contentId = $this->repository->create($data);
            $this->syncTaxonomy($contentId, $taxonomy);

            return $contentId;
        });
    }

    public function update(int $id, array $data, array $taxonomy = [], string $expectedUpdatedAt = ''): void
    {
        $this->withinTransaction(function () use ($id, $data, $taxonomy, $expectedUpdatedAt): void {
            $current = $this->repository->findById($id);

            if (!$current) {
                throw new InvalidArgumentException('Content entry was not found.');
            }

            if ($expectedUpdatedAt === '') {
                throw new InvalidArgumentException('Content version token is required.');
            }

            if ($current->status() !== ($data['status'] ?? null) && in_array($current->status(), ['archived'], true)) {
                throw new InvalidArgumentException('Archived content must be restored through the restore action.');
            }

            if (($data['status'] ?? null) === 'archived' && $current->status() !== 'archived') {
                throw new InvalidArgumentException('Content must be archived through the archive action.');
            }

            $this->assertTransition($current->status(), $data['status'] ?? null, true);
            $this->repository->update($id, $data, $expectedUpdatedAt);
            $this->syncTaxonomy($id, $taxonomy);
        });
    }

    public function publish(int $id): void
    {
        $this->transition($id, 'published', ['draft']);
    }

    public function draft(int $id): void
    {
        $this->transition($id, 'draft', ['published']);
    }

    public function archive(int $id): void
    {
        $this->transition($id, 'archived', ['draft', 'published']);
    }

    public function restore(int $id): void
    {
        $this->transition($id, 'draft', ['archived']);
    }

    private function transition(int $id, string $target, array $expectedFrom): void
    {
        $current = $this->repository->findById($id);

        if (!$current) {
            throw new InvalidArgumentException('Content entry was not found.');
        }

        $this->assertTransition($current->status(), $target);

        if (!in_array($current->status(), $expectedFrom, true)) {
            throw new InvalidArgumentException("Content transition [{$current->status()}] to [{$target}] is not allowed.");
        }

        $this->repository->transition($id, $current->status(), $target);
    }

    private function assertTransition(string $from, ?string $to, bool $allowSame = false): void
    {
        if (!is_string($to) || !in_array($to, ['draft', 'published', 'archived'], true)) {
            throw new InvalidArgumentException('Content status must be [draft], [published], or [archived].');
        }

        if ($from === $to && $allowSame) {
            return;
        }

        $allowed = [
            'draft' => ['published', 'archived'],
            'published' => ['draft', 'archived'],
            'archived' => ['draft'],
        ];

        if (!in_array($to, $allowed[$from] ?? [], true)) {
            throw new InvalidArgumentException("Content transition [{$from}] to [{$to}] is not allowed.");
        }
    }

    private function syncTaxonomy(int $contentId, array $taxonomy): void
    {
        if (!$this->taxonomyAssignments) {
            return;
        }

        $this->taxonomyAssignments->syncForType(
            'content',
            $contentId,
            'category',
            $taxonomy['category_ids'] ?? []
        );
        $this->taxonomyAssignments->syncForType(
            'content',
            $contentId,
            'tag',
            $taxonomy['tag_ids'] ?? []
        );
    }

    private function withinTransaction(callable $operation): mixed
    {
        $connection = $this->database->connection();
        $ownsTransaction = !$connection->inTransaction();

        if ($ownsTransaction) {
            $connection->beginTransaction();
        }

        try {
            $result = $operation();

            if ($ownsTransaction && $connection->inTransaction()) {
                $connection->commit();
            }

            return $result;
        } catch (\PDOException $exception) {
            if ($this->isDuplicateKey($exception)) {
                if ($ownsTransaction && $connection->inTransaction()) {
                    $connection->rollBack();
                }

                throw new ContentDuplicateSlugException();
            }

            if ($ownsTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw new ContentWriteException('Content could not be saved.', 0, $exception);
        } catch (InvalidArgumentException $exception) {
            if ($ownsTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw new ContentWriteException('Content could not be saved.', 0, $exception);
        }
    }

    private function isDuplicateKey(\PDOException $exception): bool
    {
        $driverCode = $exception->errorInfo[1] ?? null;

        return (int) $driverCode === 1062;
    }
}
