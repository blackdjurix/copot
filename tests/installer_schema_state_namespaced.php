<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\InstallerSchemaState;

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

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', 3306);
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$databaseNames = [
    'empty' => 'copot_schema_state_empty_' . bin2hex(random_bytes(5)),
    'namespaced' => 'copot_schema_state_ns_' . bin2hex(random_bytes(5)),
];

$configFor = static function (string $databaseName, string $namespace) use ($basePath, $host, $port, $username, $password): Config {
    $config = new Config($basePath . '/config');
    $reflection = new ReflectionClass($config);
    $property = $reflection->getProperty('items');
    $property->setAccessible(true);
    $items = $property->getValue($config);
    $items['database']['connections']['mysql']['host'] = $host;
    $items['database']['connections']['mysql']['port'] = $port;
    $items['database']['connections']['mysql']['database'] = $databaseName;
    $items['database']['connections']['mysql']['username'] = $username;
    $items['database']['connections']['mysql']['password'] = $password;
    $items['database']['connections']['mysql']['namespace'] = $namespace;
    $property->setValue($config, $items);

    return $config;
};

try {
    foreach ($databaseNames as $databaseName) {
        $server->exec('CREATE DATABASE `' . $databaseName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    $runner = new InstallerSchemaRunner($basePath . '/database/schema.sql');
    $runner->install([
        'host' => $host,
        'port' => $port,
        'database' => $databaseNames['empty'],
        'username' => $username,
        'password' => $password,
        'namespace' => '',
    ]);
    $runner->install([
        'host' => $host,
        'port' => $port,
        'database' => $databaseNames['namespaced'],
        'username' => $username,
        'password' => $password,
        'namespace' => 'cp1',
    ]);

    $emptyDatabase = new Database($configFor($databaseNames['empty'], ''));
    $namespacedDatabase = new Database($configFor($databaseNames['namespaced'], 'cp1'));
    $emptyState = new InstallerSchemaState($emptyDatabase);
    $namespacedState = new InstallerSchemaState($namespacedDatabase);

    $assert($emptyDatabase->table('users') === 'users', 'Empty namespace changed Core table resolution.');
    $assert($emptyDatabase->tables()->moduleTable('content') === 'content', 'Empty namespace changed Module table resolution.');
    $assert($namespacedDatabase->table('users') === 'cp1_users', 'Namespaced Core table resolution is incorrect.');
    $assert($namespacedDatabase->tables()->moduleTable('content') === 'cp1_content', 'Namespaced Module table resolution is incorrect.');
    $assert($emptyState->isReady(), 'Unnamespaced installer schema was not ready.');
    $assert($namespacedState->isReady(), 'Namespaced installer schema was not ready.');

    $namespacedTables = $namespacedDatabase->connection()
        ->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'")
        ->fetchAll(PDO::FETCH_COLUMN);
    $assert(in_array('cp1_modules', $namespacedTables, true), 'Namespaced Module table was not materialized.');
    $assert(!in_array('modules', $namespacedTables, true), 'Unnamespaced Module table was incorrectly required or created.');
} finally {
    foreach ($databaseNames as $databaseName) {
        $server->exec('DROP DATABASE IF EXISTS `' . $databaseName . '`');
    }
}

echo "Installer schema-state namespace tests passed ({$assertions} assertions)." . PHP_EOL;
