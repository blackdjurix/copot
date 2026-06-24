<?php

namespace Copot\Core;

class ModuleRepository
{
    public function __construct(private Database $database)
    {
    }

    public function all(): array
    {
        $statement = $this->database->connection()->query(
            'SELECT * FROM modules ORDER BY name ASC'
        );

        return $statement->fetchAll();
    }

    public function enabled(): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT * FROM modules WHERE status = :status ORDER BY name ASC'
        );

        $statement->execute(['status' => 'enabled']);

        return $statement->fetchAll();
    }

    public function findByName(string $name): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT * FROM modules WHERE name = :name LIMIT 1'
        );

        $statement->execute(['name' => $name]);
        $module = $statement->fetch();

        return is_array($module) ? $module : null;
    }

    public function create(ModuleDefinition $module, string $status = 'disabled'): void
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO modules (
                name,
                title,
                version,
                path,
                status,
                installed_at,
                created_at,
                updated_at
            ) VALUES (
                :name,
                :title,
                :version,
                :path,
                :status,
                NOW(),
                NOW(),
                NOW()
            )'
        );

        $statement->execute([
            'name' => $module->name(),
            'title' => $module->title(),
            'version' => $module->version(),
            'path' => $module->path(),
            'status' => $status,
        ]);
    }

    public function updateStatus(string $name, string $status): void
    {
        $enabledAt = $status === 'enabled' ? 'NOW()' : 'enabled_at';
        $disabledAt = $status === 'disabled' ? 'NOW()' : 'disabled_at';

        $statement = $this->database->connection()->prepare(
            "UPDATE modules
            SET status = :status,
                enabled_at = {$enabledAt},
                disabled_at = {$disabledAt},
                updated_at = NOW()
            WHERE name = :name"
        );

        $statement->execute([
            'name' => $name,
            'status' => $status,
        ]);
    }

    public function delete(string $name): void
    {
        $statement = $this->database->connection()->prepare(
            'DELETE FROM modules WHERE name = :name'
        );

        $statement->execute(['name' => $name]);
    }

    public function replacePermissions(ModuleDefinition $module): void
    {
        $this->deletePermissions($module->name());

        foreach ($module->permissions() as $permission) {
            if (!isset($permission['slug'], $permission['name'])) {
                continue;
            }

            $this->createPermission(
                $module->name(),
                (string) $permission['slug'],
                (string) $permission['name']
            );
        }
    }

    public function permissionsFor(string $moduleName): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT * FROM module_permissions WHERE module_name = :module_name ORDER BY permission_slug ASC'
        );

        $statement->execute(['module_name' => $moduleName]);

        return $statement->fetchAll();
    }

    public function deletePermissions(string $moduleName): void
    {
        $statement = $this->database->connection()->prepare(
            'DELETE FROM module_permissions WHERE module_name = :module_name'
        );

        $statement->execute(['module_name' => $moduleName]);
    }

    private function createPermission(string $moduleName, string $slug, string $name): void
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO module_permissions (
                module_name,
                permission_slug,
                permission_name,
                created_at
            ) VALUES (
                :module_name,
                :permission_slug,
                :permission_name,
                NOW()
            )'
        );

        $statement->execute([
            'module_name' => $moduleName,
            'permission_slug' => $slug,
            'permission_name' => $name,
        ]);
    }
}
