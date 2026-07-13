<?php

declare(strict_types=1);

use Copot\Core\Env;

$basePath = dirname(__DIR__);

require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$executeScript = static function (PDO $connection, string $sql): void {
    $delimiter = ';';
    $buffer = '';

    foreach (preg_split('/\R/', $sql) ?: [] as $line) {
        $trimmed = trim($line);

        if (preg_match('/^DELIMITER\s+(\S+)$/i', $trimmed, $matches) === 1) {
            $delimiter = $matches[1];
            continue;
        }

        $buffer .= $line . PHP_EOL;

        while (($position = strpos($buffer, $delimiter)) !== false) {
            $statement = trim(substr($buffer, 0, $position));
            $buffer = substr($buffer, $position + strlen($delimiter));

            if ($statement !== '') {
                $connection->exec($statement);
            }
        }
    }

    if (trim($buffer) !== '') {
        $connection->exec(trim($buffer));
    }
};

$dropProvisionProcedure = static function (PDO $connection): void {
    $connection->exec('DROP PROCEDURE IF EXISTS m3_3_module_manager_permission_provision');
};

$permissionRows = static function (PDO $connection): array {
    return $connection->query(
        'SELECT id, name, slug FROM permissions ORDER BY id'
    )->fetchAll(PDO::FETCH_ASSOC);
};

$rolePermissionRows = static function (PDO $connection): array {
    return $connection->query(
        'SELECT role_id, permission_id FROM role_permissions ORDER BY role_id, permission_id'
    )->fetchAll(PDO::FETCH_ASSOC);
};

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (string) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$suffix = bin2hex(random_bytes(6));
$freshDatabase = 'copot_m33_provision_fresh_' . $suffix;
$existingDatabase = 'copot_m33_provision_existing_' . $suffix;
$schema = (string) file_get_contents($basePath . '/database/schema.sql');
$upgrade = (string) file_get_contents(
    $basePath . '/database/upgrades/m3_3_module_manager_permission.sql'
);

$server = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $username,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);
$createdDatabases = [];

try {
    foreach ([$freshDatabase, $existingDatabase] as $databaseName) {
        if (preg_match('/^[a-z0-9_]+$/', $databaseName) !== 1) {
            throw new RuntimeException('Unsafe disposable database name.');
        }

        $server->exec(
            "CREATE DATABASE `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
        $createdDatabases[] = $databaseName;
    }

    $fresh = new PDO(
        "mysql:host={$host};port={$port};dbname={$freshDatabase};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $executeScript($fresh, $schema);

    $freshPermission = $fresh->query(
        "SELECT name, slug FROM permissions WHERE slug = 'modules.manage'"
    )->fetch(PDO::FETCH_ASSOC);
    $assert(
        $freshPermission === ['name' => 'Manage modules', 'slug' => 'modules.manage'],
        'Fresh schema does not seed modules.manage with the approved display name.'
    );
    $freshMappingCount = (int) $fresh->query(
        "SELECT COUNT(*)
        FROM role_permissions
        INNER JOIN roles ON roles.id = role_permissions.role_id
        INNER JOIN permissions ON permissions.id = role_permissions.permission_id
        WHERE roles.slug = 'admin' AND permissions.slug = 'modules.manage'"
    )->fetchColumn();
    $assert($freshMappingCount === 1, 'Fresh schema does not map modules.manage to the seeded admin role.');

    $existing = new PDO(
        "mysql:host={$host};port={$port};dbname={$existingDatabase};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $existing->exec('CREATE TABLE roles (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(80) NOT NULL,
        slug VARCHAR(100) NOT NULL UNIQUE,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $existing->exec('CREATE TABLE permissions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        slug VARCHAR(150) NOT NULL UNIQUE,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $existing->exec('CREATE TABLE role_permissions (
        role_id BIGINT UNSIGNED NOT NULL,
        permission_id BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (role_id, permission_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $existing->exec("INSERT INTO roles (name, slug, created_at, updated_at) VALUES
        ('Administrator', 'admin', NOW(), NOW()),
        ('Custom role', 'custom', NOW(), NOW())");
    $existing->exec("INSERT INTO permissions (name, slug, created_at, updated_at) VALUES
        ('Access admin shell', 'admin.access', NOW(), NOW()),
        ('Keep this permission', 'custom.keep', NOW(), NOW())");
    $existing->exec("INSERT INTO role_permissions (role_id, permission_id)
        SELECT roles.id, permissions.id
        FROM roles
        INNER JOIN permissions ON permissions.slug = 'admin.access'
        WHERE roles.slug = 'admin'");
    $existing->exec("INSERT INTO role_permissions (role_id, permission_id)
        SELECT roles.id, permissions.id
        FROM roles
        INNER JOIN permissions ON permissions.slug = 'custom.keep'
        WHERE roles.slug = 'custom'");

    $beforePermissions = $permissionRows($existing);
    $beforeRolePermissions = $rolePermissionRows($existing);
    $executeScript($existing, $upgrade);
    $afterFirstPermissions = $permissionRows($existing);
    $afterFirstRolePermissions = $rolePermissionRows($existing);

    $assert(count($afterFirstPermissions) === count($beforePermissions) + 1,
        'First artifact run created more than the approved permission row.');
    $assert(count($afterFirstRolePermissions) === count($beforeRolePermissions) + 1,
        'First artifact run created more than the required admin mapping.');
    $assert((int) $existing->query(
        "SELECT COUNT(*) FROM permissions WHERE slug = 'modules.manage'"
    )->fetchColumn() === 1, 'First artifact run did not create modules.manage exactly once.');
    $assert((int) $existing->query(
        "SELECT COUNT(*)
        FROM role_permissions
        INNER JOIN roles ON roles.id = role_permissions.role_id
        INNER JOIN permissions ON permissions.id = role_permissions.permission_id
        WHERE roles.slug = 'admin' AND permissions.slug = 'modules.manage'"
    )->fetchColumn() === 1, 'First artifact run did not create the admin mapping exactly once.');

    $beforePermissionKeys = array_map(
        static fn (array $row): string => $row['id'] . ':' . $row['slug'],
        $beforePermissions
    );
    $newPermissionRows = array_values(array_filter(
        $afterFirstPermissions,
        static fn (array $row): bool => !in_array($row['id'] . ':' . $row['slug'], $beforePermissionKeys, true)
    ));
    $assert(count($newPermissionRows) === 1 && $newPermissionRows[0]['slug'] === 'modules.manage',
        'First artifact run created a permission other than modules.manage.');
    $assert(
        array_values(array_filter(
            $afterFirstPermissions,
            static fn (array $row): bool => $row['slug'] !== 'modules.manage'
        )) === $beforePermissions,
        'First artifact run changed unrelated permission rows.'
    );
    $beforeRolePermissionKeys = array_map(
        static fn (array $row): string => $row['role_id'] . ':' . $row['permission_id'],
        $beforeRolePermissions
    );
    $newRolePermissionRows = array_values(array_filter(
        $afterFirstRolePermissions,
        static fn (array $row): bool => !in_array(
            $row['role_id'] . ':' . $row['permission_id'],
            $beforeRolePermissionKeys,
            true
        )
    ));
    $assert(count($newRolePermissionRows) === 1, 'First artifact run created an unexpected role mapping.');
    $newMapping = $newRolePermissionRows[0];
    $newMappingDetails = $existing->prepare(
        'SELECT roles.slug AS role_slug, permissions.slug AS permission_slug
        FROM role_permissions
        INNER JOIN roles ON roles.id = role_permissions.role_id
        INNER JOIN permissions ON permissions.id = role_permissions.permission_id
        WHERE role_permissions.role_id = :role_id
            AND role_permissions.permission_id = :permission_id'
    );
    $newMappingDetails->execute([
        'role_id' => $newMapping['role_id'],
        'permission_id' => $newMapping['permission_id'],
    ]);
    $assert(
        $newMappingDetails->fetch(PDO::FETCH_ASSOC) === [
            'role_slug' => 'admin',
            'permission_slug' => 'modules.manage',
        ],
        'First artifact run created a mapping other than the seeded-admin modules.manage mapping.'
    );

    $executeScript($existing, $upgrade);
    $assert($permissionRows($existing) === $afterFirstPermissions,
        'Second artifact run duplicated or changed permission rows.');
    $assert($rolePermissionRows($existing) === $afterFirstRolePermissions,
        'Second artifact run duplicated or changed role mappings.');

    $missingAdmin = new PDO(
        "mysql:host={$host};port={$port};dbname={$existingDatabase};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $missingAdmin->exec("DELETE FROM roles WHERE slug = 'admin'");
    $missingAdmin->exec("DELETE FROM permissions WHERE slug = 'modules.manage'");
    $missingAdmin->exec("DELETE FROM role_permissions WHERE role_id NOT IN (SELECT id FROM roles)");
    $missingAdmin->exec("INSERT INTO roles (name, slug, created_at, updated_at)
        VALUES ('Custom role', 'custom-missing-admin', NOW(), NOW())");
    $missingAdmin->exec("INSERT INTO permissions (name, slug, created_at, updated_at)
        VALUES ('Keep missing-admin permission', 'custom.missing_admin', NOW(), NOW())");
    $missingAdmin->exec("INSERT INTO role_permissions (role_id, permission_id)
        SELECT roles.id, permissions.id
        FROM roles
        INNER JOIN permissions ON permissions.slug = 'custom.missing_admin'
        WHERE roles.slug = 'custom-missing-admin'");
    $missingAdminPermissions = $permissionRows($missingAdmin);
    $missingAdminMappings = $rolePermissionRows($missingAdmin);
    $missingAdminError = null;

    try {
        $executeScript($missingAdmin, $upgrade);
    } catch (Throwable $exception) {
        $missingAdminError = $exception;
    } finally {
        $dropProvisionProcedure($missingAdmin);
    }

    $assert($missingAdminError instanceof PDOException,
        'Missing-admin provisioning did not fail with a controlled SQL exception.');
    $missingAdminMessage = $missingAdminError instanceof Throwable
        ? $missingAdminError->getMessage()
        : '';
    $assert(
        str_contains($missingAdminMessage, 'requires exactly one admin role'),
        'Missing-admin provisioning did not propagate the controlled guard error.'
    );
    $assert(!$missingAdmin->inTransaction(),
        'Missing-admin provisioning left an active transaction open.');
    $assert((int) $missingAdmin->query(
        "SELECT COUNT(*) FROM permissions WHERE slug = 'modules.manage'"
    )->fetchColumn() === 0, 'Missing-admin failure retained modules.manage.');
    $assert((int) $missingAdmin->query(
        "SELECT COUNT(*)
        FROM role_permissions
        INNER JOIN permissions ON permissions.id = role_permissions.permission_id
        WHERE permissions.slug = 'modules.manage'"
    )->fetchColumn() === 0, 'Missing-admin failure retained a modules.manage mapping.');
    $assert($permissionRows($missingAdmin) === $missingAdminPermissions,
        'Missing-admin failure changed unrelated permission rows.');
    $assert($rolePermissionRows($missingAdmin) === $missingAdminMappings,
        'Missing-admin failure changed unrelated role mappings.');

    echo "M3.3 Batch 1 permission provisioning passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    foreach (array_reverse($createdDatabases) as $databaseName) {
        $server->exec("DROP DATABASE IF EXISTS `{$databaseName}`");
    }
}
