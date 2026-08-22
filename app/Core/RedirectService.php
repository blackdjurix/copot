<?php

namespace Copot\Core;


use Copot\Core\Database;
use Copot\Core\Redirect\RedirectContract;

final class RedirectService
{
    public function __construct(
        private Database $database,
        private RedirectRepository $repository,
        private string $adminBase = '/admin'
    ) {
    }

    public function create(array $data): Redirect
    {
        [$source, $target, $status] = $this->validated($data);

        return $this->withinTransaction(function () use ($source, $target, $status): Redirect {
            $this->assertAvailable($source, $target);
            try {
                $id = $this->repository->create($source, $target, $status);
            } catch (\PDOException $failure) {
                if ($failure->getCode() === '23000') {
                    throw new InvalidArgumentException('Redirect source is already in use.', 0, $failure);
                }
                throw $failure;
            }

            return $this->repository->findById($id) ?? throw new RuntimeException('Created redirect could not be reloaded.');
        });
    }

    public function findById(int $id): ?Redirect
    {
        return $this->repository->findById($this->positiveId($id));
    }

    public function findBySource(string $source): ?Redirect
    {
        try {
            $source = RedirectContract::source($source, $this->adminBase);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $this->repository->findBySource($source);
    }

    public function update(int $id, array $data, string $expectedUpdatedAt): Redirect
    {
        $id = $this->positiveId($id);
        [$source, $target, $status] = $this->validated($data);

        return $this->withinTransaction(function () use ($id, $source, $target, $status, $expectedUpdatedAt): Redirect {
            if ($this->repository->findById($id, true) === null) {
                throw new RedirectNotFoundException('Redirect does not exist.');
            }
            $this->assertAvailable($source, $target, $id);
            $this->repository->update($id, $source, $target, $status, $expectedUpdatedAt);

            return $this->repository->findById($id) ?? throw new RuntimeException('Updated redirect could not be reloaded.');
        });
    }

    public function delete(int $id, string $expectedUpdatedAt): void
    {
        $id = $this->positiveId($id);

        $this->withinTransaction(function () use ($id, $expectedUpdatedAt): void {
            if ($this->repository->findById($id, true) === null) {
                throw new RedirectNotFoundException('Redirect does not exist.');
            }
            $this->repository->delete($id, $expectedUpdatedAt);
        });
    }

    private function validated(array $data): array
    {
        $source = RedirectContract::source((string) ($data['source_path'] ?? ''), $this->adminBase);
        $target = RedirectContract::target((string) ($data['target'] ?? ''));
        $status = RedirectContract::status($data['status_code'] ?? RedirectContract::DEFAULT_STATUS);
        RedirectContract::assertNotSelfRedirect($source, $target);

        return [$source, $target, $status];
    }

    private function assertAvailable(string $source, string $target, ?int $ignoreId = null): void
    {
        if ($this->repository->sourceExists($source, $ignoreId)) {
            throw new InvalidArgumentException('Redirect source is already in use.');
        }

        if (!str_starts_with($target, '/')) {
            return;
        }

        $targetPath = RedirectContract::canonicalPath(parse_url($target, PHP_URL_PATH) ?: '/');
        foreach ($this->repository->all() as $redirect) {
            if ($ignoreId !== null && $redirect->id() === $ignoreId) {
                continue;
            }

            $existingTarget = $redirect->target();
            $existingTargetPath = str_starts_with($existingTarget, '/')
                ? RedirectContract::canonicalPath(parse_url($existingTarget, PHP_URL_PATH) ?: '/')
                : null;

            if ($redirect->sourcePath() === $targetPath) {
                throw new InvalidArgumentException('Redirect target must not be another managed source.');
            }

            if ($existingTargetPath === $source) {
                throw new InvalidArgumentException('Redirect source is already targeted by another managed redirect.');
            }
        }
    }

    private function positiveId(int $id): int
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Redirect ID must be positive.');
        }

        return $id;
    }

    private function withinTransaction(callable $operation): mixed
    {
        $connection = $this->database->connection();
        $ownsTransaction = !$connection->inTransaction();
        $savepoint = null;

        if ($ownsTransaction) {
            $connection->beginTransaction();
        } else {
            $savepoint = 'redirects_' . bin2hex(random_bytes(6));
            $connection->exec('SAVEPOINT ' . $savepoint);
        }

        try {
            $result = $operation();
            if ($ownsTransaction) {
                $connection->commit();
            } else {
                $connection->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            return $result;
        } catch (Throwable $failure) {
            if ($ownsTransaction) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }
            } elseif ($connection->inTransaction()) {
                $connection->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                $connection->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            throw $failure;
        }
    }
}
