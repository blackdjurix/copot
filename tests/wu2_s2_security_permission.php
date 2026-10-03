<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$executeSql = static function (PDO $connection, string $sql): void {
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $connection->exec($statement);
    }
};
$permissionCount = static function (PDO $connection, string $slug): int {
    $statement = $connection->prepare('SELECT COUNT(*) FROM permissions WHERE slug = :slug');
    $statement->execute(['slug' => $slug]);
    return (int) $statement->fetchColumn();
};
$adminGrantCount = static function (PDO $connection, string $slug): int {
    $statement = $connection->prepare(
        'SELECT COUNT(*)
         FROM role_permissions
         INNER JOIN roles ON roles.id = role_permissions.role_id
         INNER JOIN permissions ON permissions.id = role_permissions.permission_id
         WHERE roles.slug = \'admin\' AND permissions.slug = :slug'
    );
    $statement->execute(['slug' => $slug]);
    return (int) $statement->fetchColumn();
};

$schema = (string) file_get_contents($basePath . '/database/schema.sql');
$upgrade = (string) file_get_contents($basePath . '/database/upgrades/wu2_s2_security_permission.sql');
$assert(str_contains($schema, "'Manage Security', 'security.manage'"), 'Fresh schema does not declare security.manage.');
$assert(str_contains($schema, "permissions.slug = 'security.manage'"), 'Fresh schema does not grant security.manage to Administrator.');
$assert(str_contains($upgrade, "'Manage Security', 'security.manage'"), 'Existing-install upgrade does not declare security.manage.');
$assert(str_contains($upgrade, 'LEFT JOIN role_permissions'), 'Existing-install upgrade is not idempotent for Administrator mapping.');
$assert(!str_contains($upgrade, 'settings.update'), 'security.manage upgrade is coupled to settings.update.');

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_wu2_s2_permission_' . bin2hex(random_bytes(5));
$identifier = '`' . str_replace('`', '``', $databaseName) . '`';
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

try {
    $server->exec('CREATE DATABASE ' . $identifier . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $configuration = ['host' => $host, 'port' => $port, 'database' => $databaseName, 'username' => $username, 'password' => $password];
    (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install($configuration);
    $connection = new PDO("mysql:host={$host};port={$port};dbname={$databaseName};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $assert($permissionCount($connection, 'security.manage') === 1, 'Fresh install did not create security.manage exactly once.');
    $assert($adminGrantCount($connection, 'security.manage') === 1, 'Fresh install did not grant security.manage to Administrator.');
    $assert($permissionCount($connection, 'settings.update') === 1, 'Unrelated settings.update permission was not preserved.');
    $assert($adminGrantCount($connection, 'settings.update') === 1, 'Unrelated settings.update Administrator grant was not preserved.');

    $upgradeDatabaseName = 'copot_wu2_s2_upgrade_' . bin2hex(random_bytes(5));
    $upgradeIdentifier = '`' . str_replace('`', '``', $upgradeDatabaseName) . '`';
    $server->exec('CREATE DATABASE ' . $upgradeIdentifier . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    try {
        $upgradeConnection = new PDO("mysql:host={$host};port={$port};dbname={$upgradeDatabaseName};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $upgradeConnection->exec('CREATE TABLE roles (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL, slug VARCHAR(100) NOT NULL UNIQUE, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL) ENGINE=InnoDB');
        $upgradeConnection->exec('CREATE TABLE permissions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, slug VARCHAR(150) NOT NULL UNIQUE, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL) ENGINE=InnoDB');
        $upgradeConnection->exec('CREATE TABLE role_permissions (role_id BIGINT UNSIGNED NOT NULL, permission_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY (role_id, permission_id)) ENGINE=InnoDB');
        $upgradeConnection->exec("INSERT INTO roles (name, slug, created_at, updated_at) VALUES ('Administrator', 'admin', NOW(), NOW())");
        $upgradeConnection->exec("INSERT INTO permissions (name, slug, created_at, updated_at) VALUES ('Update site settings', 'settings.update', NOW(), NOW()), ('Keep existing permission', 'existing.keep', NOW(), NOW())");

        $executeSql($upgradeConnection, $upgrade);
        $assert($permissionCount($upgradeConnection, 'security.manage') === 1, 'Existing-install upgrade did not create security.manage.');
        $assert($adminGrantCount($upgradeConnection, 'security.manage') === 1, 'Existing-install upgrade did not create the Administrator mapping.');
        $assert($permissionCount($upgradeConnection, 'existing.keep') === 1, 'Existing unrelated permission was not preserved.');
        $assert($permissionCount($upgradeConnection, 'settings.update') === 1, 'Existing settings.update permission was not preserved.');

        $executeSql($upgradeConnection, $upgrade);
        $assert($permissionCount($upgradeConnection, 'security.manage') === 1, 'Repeated reconciliation duplicated security.manage.');
        $assert($adminGrantCount($upgradeConnection, 'security.manage') === 1, 'Repeated reconciliation duplicated the Administrator mapping.');

        $upgradeConnection->exec("DELETE role_permissions FROM role_permissions INNER JOIN permissions ON permissions.id = role_permissions.permission_id WHERE permissions.slug = 'security.manage'");
        $executeSql($upgradeConnection, $upgrade);
        $assert($permissionCount($upgradeConnection, 'security.manage') === 1, 'Reconciliation changed the existing permission unexpectedly.');
        $assert($adminGrantCount($upgradeConnection, 'security.manage') === 1, 'Reconciliation did not restore a missing Administrator mapping.');
    } finally {
        $server->exec('DROP DATABASE IF EXISTS ' . $upgradeIdentifier);
    }

    echo "WU2-S2 security permission lifecycle passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $identifier);
}
