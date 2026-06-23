<?php

namespace Copot\Core;

use PDO;

class Database
{
    private Config $config;
    private ?PDO $connection = null;

    public function __construct(Config $config)
    {
        $this->config = $config;
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
}
