<?php

declare(strict_types=1);

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

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_m34_content_provision_' . bin2hex(random_bytes(6));
$databaseIdentifier = '`' . str_replace('`', '``', $databaseName) . '`';
$configuration = [
    'host' => $host,
    'port' => $port,
    'database' => $databaseName,
    'username' => $username,
    'password' => $password,
];

$server = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $username,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);
$server->exec('CREATE DATABASE ' . $databaseIdentifier . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

try {
    $statements = (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install($configuration);
    $fresh = new PDO(
        "mysql:host={$host};port={$port};dbname={$databaseName};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $assert($statements > 0, 'Fresh schema did not execute.');
    $assert((int) $fresh->query("SELECT COUNT(*) FROM permissions WHERE slug = 'content.read'")->fetchColumn() === 1, 'Fresh schema omitted content.read.');
    $assert((int) $fresh->query(
        "SELECT COUNT(*) FROM role_permissions
        INNER JOIN roles ON roles.id = role_permissions.role_id
        INNER JOIN permissions ON permissions.id = role_permissions.permission_id
        WHERE roles.slug = 'admin' AND permissions.slug = 'content.read'"
    )->fetchColumn() === 1, 'Fresh schema omitted the admin content.read mapping.');

    $upgrade = (string) file_get_contents($basePath . '/database/upgrades/m3_4_content_manager_permission.sql');
    $fresh->exec(
        "DELETE role_permissions
        FROM role_permissions
        INNER JOIN roles ON roles.id = role_permissions.role_id
        INNER JOIN permissions ON permissions.id = role_permissions.permission_id
        WHERE roles.slug = 'admin' AND permissions.slug = 'content.read'"
    );
    $fresh->exec("DELETE FROM permissions WHERE slug = 'content.read'");
    $executeScript($fresh, $upgrade);
    $executeScript($fresh, $upgrade);
    $assert((int) $fresh->query("SELECT COUNT(*) FROM permissions WHERE slug = 'content.read'")->fetchColumn() === 1, 'Upgrade was not idempotent for content.read.');
    $assert((int) $fresh->query(
        "SELECT COUNT(*) FROM role_permissions
        INNER JOIN roles ON roles.id = role_permissions.role_id
        INNER JOIN permissions ON permissions.id = role_permissions.permission_id
        WHERE roles.slug = 'admin' AND permissions.slug = 'content.read'"
    )->fetchColumn() === 1, 'Upgrade was not idempotent for the admin mapping.');

    $manifest = json_decode((string) file_get_contents($basePath . '/modules/content/module.json'), true, 512, JSON_THROW_ON_ERROR);
    $permissionSlugs = array_column($manifest['permissions'] ?? [], 'slug');
    $assert(in_array('content.read', $permissionSlugs, true), 'Content manifest omitted content.read.');

    $routes = (string) file_get_contents($basePath . '/modules/content/routes.php');
    $assert(str_contains($routes, "adminNavigation()->add('Content'") && str_contains($routes, "    'content.read',"), 'Content navigation did not declare content.read.');
    $assert(str_contains($routes, 'contentRequireAdmin($request, [\'content.read\'])'), 'Content listing did not require content.read.');
    $assert(str_contains($routes, "childUrl('content/{id}/restore')") && str_contains($routes, 'contentRequireAdmin($request, [\'content.delete\'])'), 'Content restore did not retain content.delete authorization.');

    echo "M3.4 Content permission provisioning/authorization baseline passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $databaseIdentifier);
}
