<?php

namespace Copot\Core;


use Copot\Core\Database;

final class NavigationRepository
{
    public function __construct(private Database $database)
    {
    }

    public function findMenu(int $id): ?NavigationMenu
    {
        $row = $this->row('SELECT * FROM ' . $this->database->tables()->table('navigation_menus') . ' WHERE id = :id LIMIT 1', ['id' => $id]);
        return $row === null ? null : new NavigationMenu($row);
    }

    public function menus(): array
    {
        $statement = $this->database->queryModule(
            'SELECT * FROM ' . $this->database->tables()->table('navigation_menus') . ' ORDER BY name ASC, id ASC'
        );

        return array_map(
            static fn (array $row): NavigationMenu => new NavigationMenu($row),
            $statement->fetchAll()
        );
    }

    public function primaryMenu(): ?NavigationMenu
    {
        $row = $this->row('SELECT * FROM ' . $this->database->tables()->table('navigation_menus') . " WHERE slug = 'primary' LIMIT 1", []);
        if ($row !== null) return new NavigationMenu($row);
        return $this->menus()[0] ?? null;
    }

    public function canonicalPrimaryMenu(): ?NavigationMenu
    {
        $row = $this->row('SELECT * FROM ' . $this->database->tables()->table('navigation_menus') . " WHERE slug = 'primary' LIMIT 1", []);
        return $row === null ? null : new NavigationMenu($row);
    }

    public function lockMenu(int $id): ?array
    {
        return $this->row('SELECT * FROM ' . $this->database->tables()->table('navigation_menus') . ' WHERE id = :id LIMIT 1 FOR UPDATE', ['id' => $id]);
    }

    public function menuSlugExists(string $slug, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT 1 FROM ' . $this->database->tables()->table('navigation_menus') . ' WHERE slug = :slug';
        $parameters = ['slug' => $slug];
        if ($ignoreId !== null) { $sql .= ' AND id <> :ignore_id'; $parameters['ignore_id'] = $ignoreId; }
        return $this->row($sql . ' LIMIT 1', $parameters) !== null;
    }

    public function createMenu(array $data): int
    {
        $statement = $this->database->prepareModule(
            'INSERT INTO ' . $this->database->tables()->table('navigation_menus') . ' (name, slug, created_at, updated_at) VALUES (:name, :slug, NOW(), NOW())'
        );
        $statement->execute($data);
        return (int) $this->database->connection()->lastInsertId();
    }

    public function updateMenu(int $id, array $data): void
    {
        $statement = $this->database->prepareModule(
            'UPDATE ' . $this->database->tables()->table('navigation_menus') . ' SET name = :name, slug = :slug, updated_at = NOW() WHERE id = :id'
        );
        $statement->execute($data + ['id' => $id]);
    }

    public function deleteMenu(int $id): void
    {
        $statement = $this->database->prepareModule('DELETE FROM ' . $this->database->tables()->table('navigation_menus') . ' WHERE id = :id');
        $statement->execute(['id' => $id]);
        if ($statement->rowCount() !== 1) { throw new RuntimeException('Navigation menu could not be deleted.'); }
    }

    public function lockItem(int $id): ?array
    {
        return $this->row('SELECT * FROM ' . $this->database->tables()->table('navigation_items') . ' WHERE id = :id LIMIT 1 FOR UPDATE', ['id' => $id]);
    }

    public function findItem(int $id): ?NavigationItem
    {
        $row = $this->row('SELECT * FROM ' . $this->database->tables()->table('navigation_items') . ' WHERE id = :id LIMIT 1', ['id' => $id]);
        return $row === null ? null : new NavigationItem($row);
    }

    public function siblingRows(int $menuId, ?int $parentId, bool $lock = false): array
    {
        $sql = 'SELECT * FROM ' . $this->database->tables()->table('navigation_items') . ' WHERE menu_id = :menu_id AND ' . ($parentId === null ? 'parent_id IS NULL' : 'parent_id = :parent_id') . ' ORDER BY sort_order ASC, id ASC';
        if ($lock) { $sql .= ' FOR UPDATE'; }
        $statement = $this->database->prepareModule($sql);
        $parameters = ['menu_id' => $menuId];
        if ($parentId !== null) { $parameters['parent_id'] = $parentId; }
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function orderedItems(int $menuId): array
    {
        $statement = $this->database->prepareModule('SELECT * FROM ' . $this->database->tables()->table('navigation_items') . ' WHERE menu_id = :menu_id ORDER BY parent_id ASC, sort_order ASC, id ASC');
        $statement->execute(['menu_id' => $menuId]);
        return array_map(fn (array $row): NavigationItem => new NavigationItem($row), $statement->fetchAll());
    }

    public function hierarchyRows(int $menuId): array
    {
        $statement = $this->database->prepareModule('SELECT id, parent_id FROM ' . $this->database->tables()->table('navigation_items') . ' WHERE menu_id = :menu_id FOR UPDATE');
        $statement->execute(['menu_id' => $menuId]);
        return $statement->fetchAll();
    }

    public function createItem(array $data): int
    {
        $statement = $this->database->prepareModule(
            'INSERT INTO ' . $this->database->tables()->table('navigation_items') . ' (menu_id, parent_id, label, target_kind, target_reference, custom_url, sort_order, is_visible, created_at, updated_at)
             VALUES (:menu_id, :parent_id, :label, :target_kind, :target_reference, :custom_url, :sort_order, :is_visible, NOW(), NOW())'
        );
        $statement->execute($data);
        return (int) $this->database->connection()->lastInsertId();
    }

    public function updateItem(int $id, array $data): void
    {
        $statement = $this->database->prepareModule(
            'UPDATE ' . $this->database->tables()->table('navigation_items') . ' SET parent_id = :parent_id, label = :label, target_kind = :target_kind, target_reference = :target_reference, custom_url = :custom_url, sort_order = :sort_order, is_visible = :is_visible, updated_at = NOW() WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'parent_id' => $data['parent_id'],
            'label' => $data['label'],
            'target_kind' => $data['target_kind'],
            'target_reference' => $data['target_reference'],
            'custom_url' => $data['custom_url'],
            'sort_order' => $data['sort_order'],
            'is_visible' => $data['is_visible'],
        ]);
    }

    public function updateItemOrder(int $id, int $sortOrder): void
    {
        $statement = $this->database->prepareModule('UPDATE ' . $this->database->tables()->table('navigation_items') . ' SET sort_order = :sort_order, updated_at = NOW() WHERE id = :id');
        $statement->execute(['id' => $id, 'sort_order' => $sortOrder]);
    }

    public function deleteItem(int $id): void
    {
        $statement = $this->database->prepareModule('DELETE FROM ' . $this->database->tables()->table('navigation_items') . ' WHERE id = :id');
        $statement->execute(['id' => $id]);
        if ($statement->rowCount() !== 1) { throw new RuntimeException('Navigation item could not be deleted.'); }
    }

    public function assignmentCount(int $menuId): int
    {
        $statement = $this->database->prepareModule('SELECT COUNT(*) FROM ' . $this->database->tables()->moduleTable('navigation_menu_assignments') . ' WHERE menu_id = :menu_id FOR UPDATE');
        $statement->execute(['menu_id' => $menuId]);
        return (int) $statement->fetchColumn();
    }

    public function assignedMenu(string $themeId, string $locationKey): ?NavigationMenu
    {
        $statement = $this->database->prepareModule(
            'SELECT navigation_menus.*
             FROM ' . $this->database->tables()->moduleTable('navigation_menu_assignments') . ' navigation_menu_assignments
             INNER JOIN ' . $this->database->tables()->table('navigation_menus') . ' navigation_menus ON navigation_menus.id = navigation_menu_assignments.menu_id
             WHERE navigation_menu_assignments.theme_id = :theme_id
               AND navigation_menu_assignments.location_key = :location_key
             LIMIT 1'
        );
        $statement->execute(['theme_id' => $themeId, 'location_key' => $locationKey]);
        $row = $statement->fetch();

        return is_array($row) ? new NavigationMenu($row) : null;
    }

    private function row(string $sql, array $parameters): ?array
    {
        $statement = $this->database->prepareModule($sql);
        $statement->execute($parameters);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }
}
