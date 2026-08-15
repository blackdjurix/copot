<?php

namespace Copot\Core;

final class AuthorizedMigrationContext
{
    public function __construct(private \PDO $connection, private MigrationAuthorizationContext $authorization, private DatabaseTableOwnershipCatalog $catalog)
    {
    }
    public function table(string $logical): string { $this->authorization->authorizeTable($this->catalog, $logical); return $this->authorization->tables()->resolve($logical); }
    public function tableExists(string $logical): bool { $physical = $this->table($logical); $statement = $this->connection->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'); $statement->execute(['table' => $physical]); return (int) $statement->fetchColumn() === 1; }
    public function columnExists(string $logical, string $column): bool { $physical = $this->table($logical); self::identifier($column, 'Column'); $statement = $this->connection->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column'); $statement->execute(['table' => $physical, 'column' => $column]); return (int) $statement->fetchColumn() === 1; }
    public function indexExists(string $logical, string $index): bool { $physical = $this->table($logical); self::identifier($index, 'Index'); $statement = $this->connection->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index'); $statement->execute(['table' => $physical, 'index' => $index]); return (int) $statement->fetchColumn() > 0; }
    public function addColumn(string $logical, string $name, string $definition): void { $this->authorization->authorizeExtension($this->catalog, $logical, DatabaseTableExtensionGrant::ADD_COLUMN, $name); self::identifier($name, 'Column'); if (!preg_match('/^[A-Za-z0-9(), _]+$/', $definition)) throw new \InvalidArgumentException('Column definition is invalid.'); $this->connection->exec('ALTER TABLE ' . $this->authorization->tables()->resolve($logical) . ' ADD COLUMN ' . $name . ' ' . $definition); }
    public function addIndex(string $logical, string $name, string $columns): void { $this->authorization->authorizeExtension($this->catalog, $logical, DatabaseTableExtensionGrant::ADD_INDEX, $name); self::identifier($name, 'Index'); if (preg_match('/^[a-z][a-z0-9_, ]*$/', $columns) !== 1) throw new \InvalidArgumentException('Index definition is invalid.'); $this->connection->exec('CREATE INDEX ' . $name . ' ON ' . $this->authorization->tables()->resolve($logical) . ' (' . $columns . ')'); }
    private static function identifier(string $value, string $label): void { if (preg_match('/^[a-z][a-z0-9_]*$/', $value) !== 1) throw new \InvalidArgumentException($label . ' identifier is invalid.'); }
}
