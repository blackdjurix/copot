<?php

namespace Copot\Core;

use PDO;

class Database
{
    private Config $config;
    private ?PDO $connection = null;
    private DatabaseTableNames $tables;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $connectionName = $this->config->get('database.default', 'mysql');
        $connectionKey = "database.connections.{$connectionName}";
        $this->tables = new DatabaseTableNames((string) $this->config->get("{$connectionKey}.namespace", ''));
    }

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $connectionName = $this->config->get('database.default', 'mysql');
        $connectionKey = "database.connections.{$connectionName}";
        $driver = $this->config->get("{$connectionKey}.driver", $connectionName);

        if ($driver !== 'mysql') {
            throw new \RuntimeException("Unsupported database driver [{$driver}].");
        }

        $host = $this->config->get("{$connectionKey}.host", '127.0.0.1');
        $port = $this->config->get("{$connectionKey}.port", '3306');
        $database = $this->config->get("{$connectionKey}.database", '');
        $username = $this->config->get("{$connectionKey}.username", 'root');
        $password = $this->config->get("{$connectionKey}.password", '');
        $charset = $this->config->get("{$connectionKey}.charset", 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        $this->connection = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $this->connection;
    }

    public function table(string $logicalName): string
    {
        return $this->tables->table($logicalName);
    }

    public function tables(): DatabaseTableNames
    {
        return $this->tables;
    }

    public function prepareModule(string $sql, array $options = []): \PDOStatement
    {
        return $this->connection()->prepare($this->moduleSql($sql), $options);
    }

    public function queryModule(string $sql): \PDOStatement
    {
        return $this->connection()->query($this->moduleSql($sql));
    }

    public function execModule(string $sql): int|false
    {
        return $this->connection()->exec($this->moduleSql($sql));
    }

    public function moduleSql(string $sql): string
    {
        return $this->tables->namespaceStatement($sql);
    }
}
