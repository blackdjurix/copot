<?php

namespace Copot\Core;

final class AuthorizedMigrationContext
{
    public function __construct(private \PDO $connection, private MigrationAuthorizationContext $authorization, private DatabaseTableOwnershipCatalog $catalog)
    {
    }
    public function table(string $logical): string { $this->authorization->authorizeTable($this->catalog, $logical); return $this->authorization->tables()->resolve($logical); }
    public function query(string $logical, string $sql): array { $this->authorization->authorizeTable($this->catalog, $logical); return $this->connection->query($this->authorization->tables()->namespaceStatement($sql))->fetchAll(\PDO::FETCH_ASSOC); }
    public function addColumn(string $logical, string $name, string $definition): void { $this->authorization->authorizeExtension($this->catalog, $logical, DatabaseTableExtensionGrant::ADD_COLUMN, $name); if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1 || !preg_match('/^[A-Za-z0-9(), _]+$/', $definition)) throw new \InvalidArgumentException('Column definition is invalid.'); $this->connection->exec('ALTER TABLE ' . $this->table($logical) . ' ADD COLUMN ' . $name . ' ' . $definition); }
    public function addIndex(string $logical, string $name, string $columns): void { $this->authorization->authorizeExtension($this->catalog, $logical, DatabaseTableExtensionGrant::ADD_INDEX, $name); if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1 || preg_match('/^[a-z][a-z0-9_, ]*$/', $columns) !== 1) throw new \InvalidArgumentException('Index definition is invalid.'); $this->connection->exec('CREATE INDEX ' . $name . ' ON ' . $this->table($logical) . ' (' . $columns . ')'); }
}
