<?php

use Copot\Core\Database;

class TaxonomyRepository
{
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
    }

    public function updateTerm(int $id, array $data): void
    {
        $data = $this->normalizeTermPayload($data);
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
    }

    public function deleteTermIfUnused(int $id, TaxonomyAssignmentRepository $assignments): void
    {
        if ($assignments->usageCount($id) > 0) {
            throw new RuntimeException('Taxonomy term cannot be deleted while it is assigned.');
        }

        $statement = $this->database->connection()->prepare(
            'DELETE FROM taxonomy_terms WHERE id = :id'
        );

        $statement->execute(['id' => $id]);
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

    private function normalizeTermPayload(array $data): array
    {
        if (!isset($data['taxonomy_type_id']) || !is_numeric($data['taxonomy_type_id'])) {
            throw new InvalidArgumentException('Taxonomy term taxonomy_type_id must be numeric.');
        }

        foreach (['name', 'slug'] as $field) {
            if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '') {
                throw new InvalidArgumentException("Taxonomy term field [{$field}] must be a non-empty string.");
            }

            $data[$field] = trim($data[$field]);
        }

        $parentId = $data['parent_id'] ?? null;

        if ($parentId === '' || $parentId === null) {
            $parentId = null;
        } elseif (!is_numeric($parentId)) {
            throw new InvalidArgumentException('Taxonomy term parent_id must be numeric or null.');
        } else {
            $parentId = (int) $parentId;
        }

        $description = isset($data['description']) && is_string($data['description']) && trim($data['description']) !== ''
            ? trim($data['description'])
            : null;

        $sortOrder = $data['sort_order'] ?? 0;

        if (!is_numeric($sortOrder)) {
            throw new InvalidArgumentException('Taxonomy term sort_order must be numeric.');
        }

        return [
            'taxonomy_type_id' => (int) $data['taxonomy_type_id'],
            'parent_id' => $parentId,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $description,
            'sort_order' => (int) $sortOrder,
        ];
    }
}
