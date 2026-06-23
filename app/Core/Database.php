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

        $host = $this->config->get('database.connections.mysql.host', '127.0.0.1');
        $port = $this->config->get('database.connections.mysql.port', '3306');
        $database = $this->config->get('database.connections.mysql.database', '');
        $username = $this->config->get('database.connections.mysql.username', 'root');
        $password = $this->config->get('database.connections.mysql.password', '');
        $charset = $this->config->get('database.connections.mysql.charset', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        $this->connection = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $this->connection;
    }
}
