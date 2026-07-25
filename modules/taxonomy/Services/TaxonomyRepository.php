<?php

use Copot\Core\Database;

class TaxonomyRepository
{
    private const MAX_ANCESTOR_DEPTH = 1000;

    public function __construct(private Database $database)
    {
    }

    public function allTypes(): array
    {
        $statement = $this->database->connection()->query(
            'SELECT * FROM taxonomy_types ORDER BY name ASC, id ASC'
        );

        return array_map(fn (array $row): TaxonomyType => new TaxonomyType($row), $statement->fetchAll());
    }

    public function findTypeBySlug(string $slug): ?TaxonomyType
    {
        $statement = $this->database->connection()->prepare(
            'SELECT * FROM taxonomy_types WHERE slug = :slug LIMIT 1'
        );

        $statement->execute(['slug' => trim($slug)]);
        $type = $statement->fetch();

        return is_array($type) ? new TaxonomyType($type) : null;
    }

    public function termsByType(string $typeSlug): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT taxonomy_terms.*
            FROM taxonomy_terms
            INNER JOIN taxonomy_types ON taxonomy_types.id = taxonomy_terms.taxonomy_type_id
            WHERE taxonomy_types.slug = :type_slug
            ORDER BY taxonomy_terms.sort_order ASC, taxonomy_terms.name ASC, taxonomy_terms.id ASC'
        );

        $statement->execute(['type_slug' => trim($typeSlug)]);

        return array_map(fn (array $row): TaxonomyTerm => new TaxonomyTerm($row), $statement->fetchAll());
    }

    public function findTermById(int $id): ?TaxonomyTerm
    {
        $statement = $this->database->connection()->prepare(
            'SELECT * FROM taxonomy_terms WHERE id = :id LIMIT 1'
        );

        $statement->execute(['id' => $id]);
        $term = $statement->fetch();

        return is_array($term) ? new TaxonomyTerm($term) : null;
    }

    public function createTerm(array $data): int
    {
        $data = $this->normalizeTermPayload($data);

        return $this->withinTransaction(function () use ($data): int {
            $type = $this->managedType($data['taxonomy_type_id']);
            $this->validateParent($type, $data['parent_id'], null);

            $statement = $this->database->connection()->prepare(
            'INSERT INTO taxonomy_terms (
                taxonomy_type_id,
                parent_id,
                name,
                slug,
                description,
                sort_order,
                created_at,
                updated_at
            ) VALUES (
                :taxonomy_type_id,
                :parent_id,
                :name,
                :slug,
                :description,
                :sort_order,
                NOW(),
                NOW()
            )'
            );

            $statement->execute($data);

            return (int) $this->database->connection()->lastInsertId();
        });
    }

    public function updateTerm(int $id, array $data): void
    {
        $id = $this->positiveId($id, 'Taxonomy term ID');
        $data = $this->normalizeTermPayload($data);

        $this->withinTransaction(function () use ($id, $data): void {
            $target = $this->lockedTerm($id);

            if ($target === null) {
                throw new InvalidArgumentException('Taxonomy term does not exist.');
            }

            if ((int) $target['taxonomy_type_id'] !== $data['taxonomy_type_id']) {
                throw new InvalidArgumentException('Taxonomy term type cannot be changed.');
            }

            $type = $this->managedType($data['taxonomy_type_id']);
            $this->validateParent($type, $data['parent_id'], $id);
            $data['id'] = $id;

            $statement = $this->database->connection()->prepare(
            'UPDATE taxonomy_terms
            SET taxonomy_type_id = :taxonomy_type_id,
                parent_id = :parent_id,
                name = :name,
                slug = :slug,
                description = :description,
                sort_order = :sort_order,
                updated_at = NOW()
            WHERE id = :id'
            );

            $statement->execute($data);
        });
    }

    public function deleteTermIfUnused(int $id, TaxonomyAssignmentRepository $assignments): void
    {
        $id = $this->positiveId($id, 'Taxonomy term ID');

        $this->withinTransaction(function () use ($id, $assignments): void {
            $target = $this->lockedTerm($id);

            if ($target === null) {
                throw new RuntimeException('Taxonomy term does not exist.');
            }

            $type = $this->managedType((int) $target['taxonomy_type_id']);

            if ($assignments->usageCount($id) > 0) {
                throw new RuntimeException('Taxonomy term cannot be deleted while it is assigned.');
            }

            if ($type['slug'] === 'category') {
                $children = $this->database->connection()->prepare(
                    'SELECT id FROM taxonomy_terms WHERE parent_id = :parent_id LIMIT 1 FOR UPDATE'
                );
                $children->execute(['parent_id' => $id]);

                if ($children->fetchColumn()) {
                    throw new RuntimeException('Taxonomy category cannot be deleted while it has children.');
                }
            }

            $statement = $this->database->connection()->prepare(
                'DELETE FROM taxonomy_terms WHERE id = :id'
            );
            $statement->execute(['id' => $id]);

            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('Taxonomy term could not be deleted.');
            }
        });
    }

    public function termSlugExists(int $taxonomyTypeId, string $slug, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT 1 FROM taxonomy_terms
            WHERE taxonomy_type_id = :taxonomy_type_id
                AND slug = :slug';
        $parameters = [
            'taxonomy_type_id' => $taxonomyTypeId,
            'slug' => trim($slug),
        ];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
            $parameters['ignore_id'] = $ignoreId;
        }

        $sql .= ' LIMIT 1';

        $statement = $this->database->connection()->prepare($sql);
        $statement->execute($parameters);

        return (bool) $statement->fetchColumn();
    }

    private function validateParent(array $type, ?int $parentId, ?int $targetId): void
    {
        if ($type['slug'] === 'tag') {
            if ($parentId !== null) {
                throw new InvalidArgumentException('Taxonomy tags cannot have a parent.');
            }

            return;
        }

        if ($parentId === null) {
            return;
        }

        if ($targetId !== null && $parentId === $targetId) {
            throw new InvalidArgumentException('Taxonomy category cannot be its own parent.');
        }

        $visited = [];
        $ancestorId = $parentId;

        for ($depth = 0; $depth < self::MAX_ANCESTOR_DEPTH; $depth++) {
            if ($targetId !== null && $ancestorId === $targetId) {
                throw new InvalidArgumentException('Taxonomy category cannot use a descendant as its parent.');
            }

            if (isset($visited[$ancestorId])) {
                throw new InvalidArgumentException('Taxonomy category hierarchy contains a cycle.');
            }

            $visited[$ancestorId] = true;
            $ancestor = $this->lockedTerm($ancestorId);

            if ($ancestor === null) {
                throw new InvalidArgumentException('Taxonomy category parent does not exist.');
            }

            if ((int) $ancestor['taxonomy_type_id'] !== (int) $type['id']) {
                throw new InvalidArgumentException('Taxonomy category parent must belong to the category type.');
            }

            if ($ancestor['parent_id'] === null) {
                return;
            }

            $ancestorId = (int) $ancestor['parent_id'];
        }

        throw new InvalidArgumentException('Taxonomy category hierarchy exceeds the safe depth limit.');
    }

    private function managedType(int $id): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT id, slug, is_hierarchical FROM taxonomy_types WHERE id = :id LIMIT 1 FOR UPDATE'
        );
        $statement->execute(['id' => $id]);
        $type = $statement->fetch();

        if (!is_array($type)) {
            throw new InvalidArgumentException('Taxonomy type does not exist.');
        }

        $valid = ($type['slug'] === 'category' && (int) $type['is_hierarchical'] === 1)
            || ($type['slug'] === 'tag' && (int) $type['is_hierarchical'] === 0);

        if (!$valid) {
            throw new InvalidArgumentException('Taxonomy type is not supported or is inconsistent.');
        }

        return $type;
    }

    private function lockedTerm(int $id): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT id, taxonomy_type_id, parent_id FROM taxonomy_terms WHERE id = :id LIMIT 1 FOR UPDATE'
        );
        $statement->execute(['id' => $id]);
        $term = $statement->fetch();

        return is_array($term) ? $term : null;
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

            if ($ownsTransaction) {
                $connection->commit();
            }

            return $result;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    private function positiveId(mixed $value, string $label): int
    {
        if (is_int($value)) {
            $id = $value;
        } elseif (is_string($value) && preg_match('/^[0-9]+$/D', $value) === 1) {
            $id = (int) $value;
            $digits = ltrim($value, '0');

            if ($digits === '' || (string) $id !== $digits) {
                throw new InvalidArgumentException($label . ' must be a positive integer.');
            }
        } else {
            throw new InvalidArgumentException($label . ' must be a positive integer.');
        }

        if ($id <= 0) {
            throw new InvalidArgumentException($label . ' must be a positive integer.');
        }

        return $id;
    }

    private function normalizeTermPayload(array $data): array
    {
        if (!array_key_exists('taxonomy_type_id', $data)) {
            throw new InvalidArgumentException('Taxonomy term taxonomy_type_id is required.');
        }

        $taxonomyTypeId = $this->positiveId(
            $data['taxonomy_type_id'],
            'Taxonomy term taxonomy_type_id'
        );

        foreach (['name', 'slug'] as $field) {
            if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '') {
                throw new InvalidArgumentException("Taxonomy term field [{$field}] must be a non-empty string.");
            }

            $data[$field] = trim($data[$field]);
        }

        $parentId = $data['parent_id'] ?? null;

        if ($parentId === null || $parentId === '') {
            $parentId = null;
        } else {
            $parentId = $this->positiveId($parentId, 'Taxonomy term parent_id');
        }

        $description = isset($data['description']) && is_string($data['description']) && trim($data['description']) !== ''
            ? trim($data['description'])
            : null;

        $sortOrder = $data['sort_order'] ?? 0;

        if (!is_int($sortOrder)
            && !(is_string($sortOrder) && preg_match('/^-?[0-9]+$/D', $sortOrder) === 1)) {
            throw new InvalidArgumentException('Taxonomy term sort_order must be an integer.');
        }

        return [
            'taxonomy_type_id' => $taxonomyTypeId,
            'parent_id' => $parentId,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $description,
            'sort_order' => (int) $sortOrder,
        ];
    }
}
