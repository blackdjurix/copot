<?php

namespace Copot\Core;


use Copot\Core\Database;

final class NavigationService
{
    private const MAX_DEPTH = 5;

    public function __construct(private Database $database, private NavigationRepository $repository)
    {
    }

    public function createMenu(array $data): int
    {
        $data = $this->normalizeMenu($data);
        return $this->withinTransaction(function () use ($data): int {
            if ($this->repository->menuSlugExists($data['slug'])) { throw new InvalidArgumentException('Navigation menu slug is already in use.'); }
            return $this->repository->createMenu($data);
        });
    }

    public function updateMenu(int $id, array $data): void
    {
        $id = $this->positiveId($id, 'Navigation menu ID');
        $data = $this->normalizeMenu($data);
        $this->withinTransaction(function () use ($id, $data): void {
            if ($this->repository->lockMenu($id) === null) { throw new InvalidArgumentException('Navigation menu does not exist.'); }
            if ($this->repository->menuSlugExists($data['slug'], $id)) { throw new InvalidArgumentException('Navigation menu slug is already in use.'); }
            $this->repository->updateMenu($id, $data);
        });
    }

    public function deleteMenu(int $id): array
    {
        $id = $this->positiveId($id, 'Navigation menu ID');
        return $this->withinTransaction(function () use ($id): array {
            if ($this->repository->lockMenu($id) === null) { throw new InvalidArgumentException('Navigation menu does not exist.'); }
            $items = count($this->repository->hierarchyRows($id));
            $assignments = $this->repository->assignmentCount($id);
            $this->repository->deleteMenu($id);
            return ['menu_id' => $id, 'deleted_items' => $items, 'deleted_assignments' => $assignments];
        });
    }

    public function createItem(array $data): int
    {
        $data = $this->normalizeItem($data);
        return $this->withinTransaction(function () use ($data): int {
            $this->requireMenu($data['menu_id']);
            $this->validatePlacement($data['menu_id'], $data['parent_id'], null, 1);
            $data['sort_order'] = $this->nextSiblingOrder($data['menu_id'], $data['parent_id']);
            return $this->repository->createItem($data);
        });
    }

    public function updateItem(int $id, array $data): void
    {
        $id = $this->positiveId($id, 'Navigation item ID');
        $data = $this->normalizeItem($data);
        $this->withinTransaction(function () use ($id, $data): void {
            $current = $this->repository->lockItem($id);
            if ($current === null) { throw new InvalidArgumentException('Navigation item does not exist.'); }
            if ((int) $current['menu_id'] !== $data['menu_id']) { throw new InvalidArgumentException('Navigation item menu cannot be changed.'); }
            $height = $this->subtreeHeight($data['menu_id'], $id);
            $this->validatePlacement($data['menu_id'], $data['parent_id'], $id, $height);
            $data['sort_order'] = $data['parent_id'] === ($current['parent_id'] === null ? null : (int) $current['parent_id'])
                ? (int) $current['sort_order']
                : $this->nextSiblingOrder($data['menu_id'], $data['parent_id']);
            $this->repository->updateItem($id, $data);
        });
    }

    public function deleteItem(int $id): array
    {
        $id = $this->positiveId($id, 'Navigation item ID');
        return $this->withinTransaction(function () use ($id): array {
            $item = $this->repository->lockItem($id);
            if ($item === null) { throw new InvalidArgumentException('Navigation item does not exist.'); }
            $deleted = $this->subtreeHeight((int) $item['menu_id'], $id, true);
            $this->repository->deleteItem($id);
            return ['item_id' => $id, 'deleted_items' => $deleted];
        });
    }

    public function reorderSiblings(int $menuId, ?int $parentId, array $itemIds): void
    {
        $menuId = $this->positiveId($menuId, 'Navigation menu ID');
        $parentId = $this->nullableId($parentId, 'Navigation parent ID');
        $this->withinTransaction(function () use ($menuId, $parentId, $itemIds): void {
            $this->requireMenu($menuId);
            if ($parentId !== null) { $this->requireParent($menuId, $parentId); }
            $siblings = $this->repository->siblingRows($menuId, $parentId, true);
            $expected = array_map(fn (array $row): int => (int) $row['id'], $siblings);
            $provided = array_map(fn (mixed $id): int => $this->positiveId($id, 'Navigation reorder item ID'), $itemIds);
            if (count($provided) !== count(array_unique($provided)) || count($provided) !== count($expected) || $this->sorted($provided) !== $this->sorted($expected)) {
                throw new InvalidArgumentException('Navigation reorder must contain each sibling exactly once.');
            }
            foreach ($provided as $position => $id) { $this->repository->updateItemOrder($id, $position); }
        });
    }

    public function itemsForMenu(int $menuId): array
    {
        $menuId = $this->positiveId($menuId, 'Navigation menu ID');
        return $this->repository->orderedItems($menuId);
    }

    public function menus(): array
    {
        return $this->repository->menus();
    }

    public function findMenu(int $menuId): ?NavigationMenu
    {
        return $this->repository->findMenu($this->positiveId($menuId, 'Navigation menu ID'));
    }

    public function findItem(int $itemId): ?NavigationItem
    {
        return $this->repository->findItem($this->positiveId($itemId, 'Navigation item ID'));
    }

    private function requireMenu(int $id): void
    {
        if ($this->repository->lockMenu($id) === null) { throw new InvalidArgumentException('Navigation menu does not exist.'); }
    }

    private function validatePlacement(int $menuId, ?int $parentId, ?int $targetId, int $subtreeHeight): void
    {
        if ($parentId === null) {
            if ($subtreeHeight > self::MAX_DEPTH) { throw new InvalidArgumentException('Navigation hierarchy exceeds the maximum depth.'); }
            return;
        }
        if ($targetId !== null && $parentId === $targetId) { throw new InvalidArgumentException('Navigation item cannot be its own parent.'); }
        $depth = 1;
        $visited = [];
        $cursor = $parentId;
        while (true) {
            if (isset($visited[$cursor])) { throw new InvalidArgumentException('Navigation hierarchy contains a cycle.'); }
            if ($targetId !== null && $cursor === $targetId) { throw new InvalidArgumentException('Navigation item cannot use a descendant as its parent.'); }
            $visited[$cursor] = true;
            $parent = $this->repository->lockItem($cursor);
            if ($parent === null) { throw new InvalidArgumentException('Navigation item parent does not exist.'); }
            if ((int) $parent['menu_id'] !== $menuId) { throw new InvalidArgumentException('Navigation item parent must belong to the same menu.'); }
            $depth++;
            if ($parent['parent_id'] === null) { break; }
            $cursor = (int) $parent['parent_id'];
            if ($depth > 1000) { throw new InvalidArgumentException('Navigation hierarchy exceeds the safe traversal limit.'); }
        }
        if ($depth + $subtreeHeight - 1 > self::MAX_DEPTH) { throw new InvalidArgumentException('Navigation hierarchy exceeds the maximum depth.'); }
    }

    private function requireParent(int $menuId, int $parentId): void
    {
        $parent = $this->repository->lockItem($parentId);
        if ($parent === null || (int) $parent['menu_id'] !== $menuId) { throw new InvalidArgumentException('Navigation item parent must belong to the same menu.'); }
    }

    private function subtreeHeight(int $menuId, int $rootId, bool $countOnly = false): int
    {
        $children = [];
        foreach ($this->repository->hierarchyRows($menuId) as $row) {
            if ($row['parent_id'] !== null) { $children[(int) $row['parent_id']][] = (int) $row['id']; }
        }
        $maxDepth = 0;
        $seen = [];
        $stack = [[$rootId, 1, []]];
        while ($stack !== []) {
            [$id, $depth, $path] = array_pop($stack);
            if (isset($path[$id])) { throw new InvalidArgumentException('Navigation hierarchy contains a cycle.'); }
            if (isset($seen[$id])) { throw new InvalidArgumentException('Navigation hierarchy contains an invalid repeated descendant.'); }
            $seen[$id] = true;
            $maxDepth = max($maxDepth, $depth);
            $path[$id] = true;
            foreach ($children[$id] ?? [] as $childId) { $stack[] = [$childId, $depth + 1, $path]; }
            if ($maxDepth > 1000) { throw new InvalidArgumentException('Navigation hierarchy exceeds the safe traversal limit.'); }
        }
        return $countOnly ? count($seen) : $maxDepth;
    }

    private function nextSiblingOrder(int $menuId, ?int $parentId): int
    {
        $siblings = $this->repository->siblingRows($menuId, $parentId, true);
        if ($siblings === []) { return 0; }
        return max(array_map(fn (array $row): int => (int) $row['sort_order'], $siblings)) + 1;
    }

    private function normalizeMenu(array $data): array
    {
        if (!isset($data['name']) || !is_string($data['name']) || trim($data['name']) === '') { throw new InvalidArgumentException('Navigation menu name is required.'); }
        $slug = strtolower(trim((string) ($data['slug'] ?? '')));
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $slug), '-');
        if ($slug === '' || strlen($slug) > 150) { throw new InvalidArgumentException('Navigation menu slug is invalid.'); }
        return ['name' => trim($data['name']), 'slug' => $slug];
    }

    private function normalizeItem(array $data): array
    {
        $menuId = $this->positiveId($data['menu_id'] ?? null, 'Navigation item menu_id');
        if (!isset($data['label']) || !is_string($data['label']) || trim($data['label']) === '' || strlen(trim($data['label'])) > 190) { throw new InvalidArgumentException('Navigation item label is invalid.'); }
        $kind = isset($data['target_kind']) && is_string($data['target_kind']) ? trim($data['target_kind']) : '';
        $reference = $data['target_reference'] ?? null;
        $customUrl = $data['custom_url'] ?? null;
        if ($kind === 'custom') {
            if ($reference !== null) { throw new InvalidArgumentException('Custom navigation targets cannot have a target reference.'); }
            $customUrl = $this->validateCustomUrl($customUrl);
        } else {
            if (preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $kind) !== 1) { throw new InvalidArgumentException('Navigation target kind is invalid.'); }
            if (!is_string($reference) || trim($reference) === '') { throw new InvalidArgumentException('Navigation provider target reference is required.'); }
            if ($customUrl !== null) { throw new InvalidArgumentException('Navigation provider targets cannot have a custom URL.'); }
            $reference = trim($reference);
        }
        return ['menu_id' => $menuId, 'parent_id' => $this->nullableId($data['parent_id'] ?? null, 'Navigation item parent_id'), 'label' => trim($data['label']), 'target_kind' => $kind, 'target_reference' => $reference, 'custom_url' => $customUrl, 'is_visible' => $this->boolean($data['is_visible'] ?? true) ? 1 : 0];
    }

    private function validateCustomUrl(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') { throw new InvalidArgumentException('Navigation custom URL is required.'); }
        $url = trim($value);
        if (preg_match('/[\x00-\x1F\\\\]/', $url) === 1 || str_starts_with($url, '//')) { throw new InvalidArgumentException('Navigation custom URL is invalid.'); }
        if (str_starts_with($url, '/')) { return $url; }
        if (str_starts_with($url, '#')) { return $url; }
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? null, ['http', 'https'], true) || !isset($parts['host']) || $parts['host'] === '') { throw new InvalidArgumentException('Navigation custom URL is invalid.'); }
        return $url;
    }

    private function withinTransaction(callable $operation): mixed
    {
        $connection = $this->database->connection();
        $owns = !$connection->inTransaction();
        $savepoint = 'navigation_wu2_' . bin2hex(random_bytes(6));
        if ($owns) { $connection->beginTransaction(); } else { $connection->exec('SAVEPOINT ' . $savepoint); }
        try {
            $result = $operation();
            if ($owns) { $connection->commit(); } else { $connection->exec('RELEASE SAVEPOINT ' . $savepoint); }
            return $result;
        } catch (Throwable $exception) {
            if ($owns && $connection->inTransaction()) { $connection->rollBack(); }
            elseif (!$owns && $connection->inTransaction()) { $connection->exec('ROLLBACK TO SAVEPOINT ' . $savepoint); $connection->exec('RELEASE SAVEPOINT ' . $savepoint); }
            throw $exception;
        }
    }

    private function positiveId(mixed $value, string $label): int
    {
        if (is_int($value)) { $id = $value; }
        elseif (is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value) === 1) { $id = (int) $value; }
        else { throw new InvalidArgumentException($label . ' must be a positive integer.'); }
        if ($id <= 0) { throw new InvalidArgumentException($label . ' must be a positive integer.'); }
        return $id;
    }

    private function nullableId(mixed $value, string $label): ?int { return $value === null || $value === '' ? null : $this->positiveId($value, $label); }
    private function boolean(mixed $value): bool { if (is_bool($value)) return $value; if ($value === 1 || $value === '1' || $value === 'true') return true; if ($value === 0 || $value === '0' || $value === 'false') return false; throw new InvalidArgumentException('Navigation item is_visible is invalid.'); }
    private function sorted(array $values): array { sort($values, SORT_NUMERIC); return $values; }
}
