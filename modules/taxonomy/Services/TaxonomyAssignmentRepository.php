<?php

use Copot\Core\Database;

class TaxonomyAssignmentRepository
{
    public function __construct(private Database $database)
    {
    }

    public function assign(string $entityType, int $entityId, int $termId): void
    {
        [$entityType, $entityId] = $this->normalizeEntity($entityType, $entityId);
        $termId = $this->normalizeTermId($termId);
        $this->ensureTermExists($termId);

        $this->withinTransaction(function () use ($entityType, $entityId, $termId): void {
            $statement = $this->database->connection()->prepare(
                'INSERT IGNORE INTO taxonomy_assignments (
                    taxonomy_term_id,
                    entity_type,
                    entity_id,
                    created_at
                ) VALUES (
                    :taxonomy_term_id,
                    :entity_type,
                    :entity_id,
                    NOW()
                )'
            );

            $statement->execute([
                'taxonomy_term_id' => $termId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);
        });
    }

    public function sync(string $entityType, int $entityId, array $termIds): void
    {
        [$entityType, $entityId] = $this->normalizeEntity($entityType, $entityId);
        $termIds = $this->normalizeTermIds($termIds);
        $connection = $this->database->connection();

        $this->withinTransaction(function () use ($connection, $entityType, $entityId, $termIds): void {
            $delete = $connection->prepare(
                'DELETE FROM taxonomy_assignments
                WHERE entity_type = :entity_type
                    AND entity_id = :entity_id'
            );
            $delete->execute([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);

            foreach ($termIds as $termId) {
                $this->assign($entityType, $entityId, $termId);
            }
        });
    }

    public function syncForType(string $entityType, int $entityId, string $typeSlug, array $termIds): void
    {
        [$entityType, $entityId] = $this->normalizeEntity($entityType, $entityId);
        $typeSlug = $this->normalizeTypeSlug($typeSlug);
        $termIds = $this->normalizeTermIds($termIds);
        $this->ensureTermsBelongToType($termIds, $typeSlug);
        $connection = $this->database->connection();

        $this->withinTransaction(function () use ($connection, $entityType, $entityId, $typeSlug, $termIds): void {
            $delete = $connection->prepare(
                'DELETE taxonomy_assignments
                FROM taxonomy_assignments
                INNER JOIN taxonomy_terms ON taxonomy_terms.id = taxonomy_assignments.taxonomy_term_id
                INNER JOIN taxonomy_types ON taxonomy_types.id = taxonomy_terms.taxonomy_type_id
                WHERE taxonomy_assignments.entity_type = :entity_type
                    AND taxonomy_assignments.entity_id = :entity_id
                    AND taxonomy_types.slug = :type_slug'
            );
            $delete->execute([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'type_slug' => $typeSlug,
            ]);

            foreach ($termIds as $termId) {
                $this->assign($entityType, $entityId, $termId);
            }
        });
    }

    private function withinTransaction(callable $operation): void
    {
        $connection = $this->database->connection();
        $ownsTransaction = !$connection->inTransaction();

        if ($ownsTransaction) {
            $connection->beginTransaction();
        }

        try {
            $operation();

            if ($ownsTransaction && $connection->inTransaction()) {
                $connection->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public function termsForEntity(string $entityType, int $entityId): array
    {
        [$entityType, $entityId] = $this->normalizeEntity($entityType, $entityId);

        $statement = $this->database->connection()->prepare(
            'SELECT taxonomy_terms.*
            FROM taxonomy_assignments
            INNER JOIN taxonomy_terms ON taxonomy_terms.id = taxonomy_assignments.taxonomy_term_id
            INNER JOIN taxonomy_types ON taxonomy_types.id = taxonomy_terms.taxonomy_type_id
            WHERE taxonomy_assignments.entity_type = :entity_type
                AND taxonomy_assignments.entity_id = :entity_id
            ORDER BY taxonomy_types.slug ASC, taxonomy_terms.sort_order ASC, taxonomy_terms.name ASC'
        );

        $statement->execute([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);

        return array_map(fn (array $row): TaxonomyTerm => new TaxonomyTerm($row), $statement->fetchAll());
    }

    public function termsForEntityByType(string $entityType, int $entityId, string $typeSlug): array
    {
        [$entityType, $entityId] = $this->normalizeEntity($entityType, $entityId);
        $typeSlug = $this->normalizeTypeSlug($typeSlug);

        $statement = $this->database->connection()->prepare(
            'SELECT taxonomy_terms.*
            FROM taxonomy_assignments
            INNER JOIN taxonomy_terms ON taxonomy_terms.id = taxonomy_assignments.taxonomy_term_id
            INNER JOIN taxonomy_types ON taxonomy_types.id = taxonomy_terms.taxonomy_type_id
            WHERE taxonomy_assignments.entity_type = :entity_type
                AND taxonomy_assignments.entity_id = :entity_id
                AND taxonomy_types.slug = :type_slug
            ORDER BY taxonomy_terms.sort_order ASC, taxonomy_terms.name ASC, taxonomy_terms.id ASC'
        );

        $statement->execute([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'type_slug' => $typeSlug,
        ]);

        return array_map(fn (array $row): TaxonomyTerm => new TaxonomyTerm($row), $statement->fetchAll());
    }

    public function usageCount(int $termId): int
    {
        $termId = $this->normalizeTermId($termId);

        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(*) FROM taxonomy_assignments WHERE taxonomy_term_id = :taxonomy_term_id'
        );

        $statement->execute(['taxonomy_term_id' => $termId]);

        return (int) $statement->fetchColumn();
    }

    private function normalizeEntity(string $entityType, int $entityId): array
    {
        $entityType = trim($entityType);

        if ($entityType === '' || !preg_match('/^[a-z0-9_-]+$/', $entityType)) {
            throw new InvalidArgumentException('Taxonomy assignment entity_type is invalid.');
        }

        if ($entityId <= 0) {
            throw new InvalidArgumentException('Taxonomy assignment entity_id must be positive.');
        }

        return [$entityType, $entityId];
    }

    private function normalizeTypeSlug(string $typeSlug): string
    {
        $typeSlug = trim($typeSlug);

        if ($typeSlug === '' || !preg_match('/^[a-z0-9_-]+$/', $typeSlug)) {
            throw new InvalidArgumentException('Taxonomy type slug is invalid.');
        }

        return $typeSlug;
    }

    private function normalizeTermIds(array $termIds): array
    {
        $normalized = [];

        foreach ($termIds as $termId) {
            $termId = $this->normalizeTermId($termId);

            if (!in_array($termId, $normalized, true)) {
                $normalized[] = $termId;
            }
        }

        foreach ($normalized as $termId) {
            $this->ensureTermExists($termId);
        }

        return $normalized;
    }

    private function normalizeTermId(mixed $termId): int
    {
        if (!is_numeric($termId) || (int) $termId <= 0) {
            throw new InvalidArgumentException('Taxonomy assignment term IDs must be positive integers.');
        }

        return (int) $termId;
    }

    private function ensureTermExists(int $termId): void
    {
        $statement = $this->database->connection()->prepare(
            'SELECT 1 FROM taxonomy_terms WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $termId]);

        if (!$statement->fetchColumn()) {
            throw new InvalidArgumentException('Taxonomy assignment term does not exist.');
        }
    }

    private function ensureTermsBelongToType(array $termIds, string $typeSlug): void
    {
        if ($termIds === []) {
            return;
        }

        $placeholders = [];
        $parameters = ['type_slug' => $typeSlug];

        foreach ($termIds as $index => $termId) {
            $key = 'term_id_' . $index;
            $placeholders[] = ':' . $key;
            $parameters[$key] = $termId;
        }

        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(*)
            FROM taxonomy_terms
            INNER JOIN taxonomy_types ON taxonomy_types.id = taxonomy_terms.taxonomy_type_id
            WHERE taxonomy_terms.id IN (' . implode(', ', $placeholders) . ')
                AND taxonomy_types.slug = :type_slug'
        );
        $statement->execute($parameters);

        if ((int) $statement->fetchColumn() !== count($termIds)) {
            throw new InvalidArgumentException('Taxonomy assignment terms must belong to the requested taxonomy type.');
        }
    }
}
