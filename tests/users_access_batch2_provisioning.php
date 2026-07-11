<?php

declare(strict_types=1);

use Copot\Core\Env;
use Copot\Core\ModuleDiscovery;

$basePath = dirname(__DIR__);

require $basePath . '/bootstrap/autoload.php';

Env::load($basePath . '/.env');

$expectedSlugs = [
    'roles.manage',
    'roles.permissions.manage',
    'roles.read',
    'users.create',
    'users.password.manage',
    'users.read',
    'users.roles.manage',
    'users.status.manage',
    'users.update',
];
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$m3SlugsFrom = static function (string $source): array {
    preg_match_all("/'((?:users|roles)\.[a-z.]+)'/", $source, $matches);
    $slugs = array_values(array_unique($matches[1] ?? []));
    sort($slugs, SORT_STRING);

    return $slugs;
};
$executeScript = static function (PDO $connection, string $sql): void {
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $connection->exec($statement);
    }
};
$createTables = static function (PDO $connection): void {
    $connection->exec('CREATE TABLE roles (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(80) NOT NULL,
        slug VARCHAR(100) NOT NULL UNIQUE,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $connection->exec('CREATE TABLE permissions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        slug VARCHAR(150) NOT NULL UNIQUE,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $connection->exec('CREATE TABLE role_permissions (
        role_id BIGINT UNSIGNED NOT NULL,
        permission_id BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (role_id, permission_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
};
$permissionCount = static function (PDO $connection): int {
    return (int) $connection->query(
        "SELECT COUNT(*) FROM permissions WHERE slug LIKE 'users.%' OR slug LIKE 'roles.%'"
    )->fetchColumn();
};
$mappingCount = static function (PDO $connection): int {
    return (int) $connection->query(
        "SELECT COUNT(*)
        FROM role_permissions
        INNER JOIN roles ON roles.id = role_permissions.role_id
        INNER JOIN permissions ON permissions.id = role_permissions.permission_id
        WHERE roles.slug = 'admin'
            AND (permissions.slug LIKE 'users.%' OR permissions.slug LIKE 'roles.%')"
    )->fetchColumn();
};

$manifestPath = $basePath . '/modules/users-access/module.json';
$schemaPath = $basePath . '/database/schema.sql';
$upgradePath = $basePath . '/database/upgrades/m3_1_users_access_permissions.sql';
$manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
$schema = (string) file_get_contents($schemaPath);
$upgrade = (string) file_get_contents($upgradePath);
$manifestSlugs = array_column($manifest['permissions'] ?? [], 'slug');
sort($manifestSlugs, SORT_STRING);

$assert($manifestSlugs === $expectedSlugs, 'Manifest does not declare the exact M3.1 permission matrix.');
$assert($m3SlugsFrom($schema) === $expectedSlugs, 'Canonical schema permission slugs do not match the manifest.');
$assert($m3SlugsFrom($upgrade) === $expectedSlugs, 'Upgrade permission slugs do not match the manifest.');
$assert(substr_count($schema, "WHERE roles.slug = 'admin';") >= 1, 'Canonical schema lacks admin role mapping statements.');

$schemaMappingBlocks = [];
preg_match_all('/INSERT INTO role_permissions.*?;/s', $schema, $schemaMappingBlocks);
$hasExactSchemaMapping = false;

foreach ($schemaMappingBlocks[0] ?? [] as $block) {
    if ($m3SlugsFrom($block) === $expectedSlugs && str_contains($block, "roles.slug = 'admin'")) {
        $hasExactSchemaMapping = true;
        break;
    }
}

$assert($hasExactSchemaMapping, 'Canonical schema lacks the exact nine admin mappings.');
$assert(!str_contains($upgrade, 'INSERT IGNORE'), 'Upgrade uses broad INSERT IGNORE.');
$assert(str_contains($upgrade, 'START TRANSACTION'), 'Upgrade lacks an explicit transaction.');
$assert(str_contains($upgrade, 'COMMIT'), 'Upgrade lacks an explicit commit.');
$assert(str_contains($upgrade, 'm3_1_users_access_permission_guard'), 'Upgrade lacks visible prerequisite/postcondition guards.');
$assert(preg_match('/\bCHECK\s*\(/i', $upgrade) !== 1,
    'Upgrade relies on a CHECK constraint for provisioning failure.');
$assert(str_contains($upgrade, 'PRIMARY KEY'), 'Upgrade guard lacks an enforced duplicate-key sentinel.');

$discovery = new ModuleDiscovery($basePath . '/modules');
$discovered = array_filter(
    $discovery->discover(),
    static fn ($module): bool => $module->name() === 'users-access'
);
$assert(count($discovered) === 1 && $discovery->errors() === [],
    'Users & Access manifest is not discoverable without the Pass 2 route file.');

foreach ([
    'bootstrap/app.php',
    'app/Core/ModuleDiscovery.php',
    'app/Core/ModuleLoader.php',
    'app/Core/ModuleManager.php',
    'app/Core/ModuleRepository.php',
] as $lifecycleFile) {
    $source = (string) file_get_contents($basePath . '/' . $lifecycleFile);
    $assert(!str_contains($source, 'm3_1_users_access_permissions.sql'),
        "Provisioning artifact is invoked automatically by [{$lifecycleFile}].");
}

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (string) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$suffix = bin2hex(random_bytes(6));
$successDatabase = 'copot_m31_provision_' . $suffix;
$partialDatabase = 'copot_m31_provision_partial_' . $suffix;
$failureDatabase = 'copot_m31_provision_missing_' . $suffix;
$server = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $username,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);

$createdDatabases = [];
try {
    foreach ([$successDatabase, $partialDatabase, $failureDatabase] as $databaseName) {
        if (preg_match('/^[a-z0-9_]+$/', $databaseName) !== 1) {
            throw new RuntimeException('Unsafe disposable database name.');
        }

        $server->exec("CREATE DATABASE `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $createdDatabases[] = $databaseName;
    }

    $success = new PDO(
        "mysql:host={$host};port={$port};dbname={$successDatabase};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $createTables($success);
    $success->exec("INSERT INTO roles (name, slug, created_at, updated_at)
        VALUES ('Administrator', 'admin', NOW(), NOW())");
    $executeScript($success, $upgrade);
    $assert($permissionCount($success) === 9, 'First upgrade run did not create exactly nine permissions.');
    $assert($mappingCount($success) === 9, 'First upgrade run did not create exactly nine admin mappings.');
    $executeScript($success, $upgrade);
    $assert($permissionCount($success) === 9, 'Second upgrade run duplicated permission rows.');
    $assert($mappingCount($success) === 9, 'Second upgrade run duplicated admin mappings.');

    $partial = new PDO(
        "mysql:host={$host};port={$port};dbname={$partialDatabase};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $createTables($partial);
    $partial->exec("INSERT INTO roles (name, slug, created_at, updated_at) VALUES
        ('Administrator', 'admin', NOW(), NOW()),
        ('Custom role', 'custom-role', NOW(), NOW())");
    $partial->exec("INSERT INTO permissions (name, slug, created_at, updated_at) VALUES
        ('Read users', 'users.read', NOW(), NOW()),
        ('Create users', 'users.create', NOW(), NOW()),
        ('Custom permission', 'custom.keep', NOW(), NOW())");
    $partial->exec("INSERT INTO role_permissions (role_id, permission_id)
        SELECT roles.id, permissions.id
        FROM roles
        INNER JOIN permissions ON permissions.slug = 'users.read'
        WHERE roles.slug = 'admin'");
    $partial->exec("INSERT INTO role_permissions (role_id, permission_id)
        SELECT roles.id, permissions.id
        FROM roles
        INNER JOIN permissions ON permissions.slug IN ('users.create', 'custom.keep')
        WHERE roles.slug = 'custom-role'");
    $existingIds = $partial->query(
        "SELECT slug, id FROM permissions WHERE slug IN ('users.read', 'users.create') ORDER BY slug"
    )->fetchAll(PDO::FETCH_KEY_PAIR);

    $executeScript($partial, $upgrade);
    $assert($permissionCount($partial) === 9, 'Partial state did not converge to nine permissions.');
    $assert($mappingCount($partial) === 9, 'Partial state did not converge to nine admin mappings.');
    $preservedIds = $partial->query(
        "SELECT slug, id FROM permissions WHERE slug IN ('users.read', 'users.create') ORDER BY slug"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
    $assert($preservedIds === $existingIds, 'Partial provisioning replaced existing permission IDs.');
    $customMappingCount = static function () use ($partial): int {
        return (int) $partial->query(
            "SELECT COUNT(*)
            FROM role_permissions
            INNER JOIN roles ON roles.id = role_permissions.role_id
            INNER JOIN permissions ON permissions.id = role_permissions.permission_id
            WHERE roles.slug = 'custom-role'
                AND permissions.slug IN ('users.create', 'custom.keep')"
        )->fetchColumn();
    };
    $assert($customMappingCount() === 2, 'Partial provisioning changed custom role mappings.');
    $executeScript($partial, $upgrade);
    $assert($permissionCount($partial) === 9, 'Partial-state rerun duplicated permission rows.');
    $assert($mappingCount($partial) === 9, 'Partial-state rerun duplicated admin mappings.');
    $assert($customMappingCount() === 2, 'Partial-state rerun changed custom role mappings.');

    $failure = new PDO(
        "mysql:host={$host};port={$port};dbname={$failureDatabase};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $createTables($failure);
    $failedVisibly = false;

    try {
        $executeScript($failure, $upgrade);
    } catch (PDOException) {
        $failedVisibly = true;

        if ($failure->inTransaction()) {
            $failure->rollBack();
        }
    }

    $assert($failedVisibly, 'Missing admin prerequisite did not fail visibly.');
    $assert($permissionCount($failure) === 0, 'Missing admin prerequisite left partial permission rows.');
    $assert($mappingCount($failure) === 0, 'Missing admin prerequisite left partial mappings.');
    $failure->exec("INSERT INTO roles (name, slug, created_at, updated_at)
        VALUES ('Administrator', 'admin', NOW(), NOW())");
    $executeScript($failure, $upgrade);
    $assert($permissionCount($failure) === 9,
        'Artifact could not recover from a failed guard on the same connection.');
    $assert($mappingCount($failure) === 9,
        'Recovered artifact run did not create all admin mappings.');

    echo "M3.1 Batch 2 provisioning tests passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    foreach ($createdDatabases as $databaseName) {
        $server->exec("DROP DATABASE IF EXISTS `{$databaseName}`");
    }
}
