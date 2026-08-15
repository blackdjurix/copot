<?php

namespace Copot\Core;

final class MigrationAuthorizationContext
{
    public function __construct(
        private InstallationIdentity $installation,
        private DatabaseTableNames $tables,
        private string $operationId,
        private string $operationClassification,
        private DatabaseTableOwner $owner,
        private string $migrationId,
        private string $migrationChecksum,
        private string $sourceState,
        private string $targetState,
        private bool $compatible,
        private MigrationSchemaSurface $surface,
        private array $extensions = []
    ) {
        foreach ([$operationId, $operationClassification, $migrationId, $migrationChecksum, $sourceState, $targetState] as $value) {
            if ($value === '' || trim($value) !== $value) throw new \InvalidArgumentException('Migration authorization identity is invalid.');
        }
        if (preg_match('/^[a-f0-9]{64}$/', strtolower($migrationChecksum)) !== 1) throw new \InvalidArgumentException('Migration checksum is invalid.');
        foreach ($extensions as $extension) if (!$extension instanceof DatabaseTableExtensionGrant) throw new \InvalidArgumentException('Migration extension grant is invalid.');
        if (!$compatible) throw new \InvalidArgumentException('Incompatible migration authorization cannot be created.');
    }

    public function installation(): InstallationIdentity { return $this->installation; }
    public function tables(): DatabaseTableNames { return $this->tables; }
    public function operationId(): string { return $this->operationId; }
    public function operationClassification(): string { return $this->operationClassification; }
    public function owner(): DatabaseTableOwner { return $this->owner; }
    public function migrationId(): string { return $this->migrationId; }
    public function migrationChecksum(): string { return $this->migrationChecksum; }
    public function sourceState(): string { return $this->sourceState; }
    public function targetState(): string { return $this->targetState; }
    public function extensions(): array { return $this->extensions; }
    public function authorizeTable(DatabaseTableOwnershipCatalog $catalog, string $logical): void
    {
        if (!in_array($logical, $this->surface->tables(), true)) throw new \RuntimeException('Migration table is outside the declared schema surface.');
        $entry = $catalog->ownership($logical);
        if ($entry->owner()->key() === $this->owner->key()) return;
        throw new \RuntimeException('Migration table is outside the owner authority.');
    }
    public function authorizeExtension(DatabaseTableOwnershipCatalog $catalog, string $logical, string $kind, string $element): void
    {
        $owner = $catalog->owner($logical);
        if ($owner->key() === $this->owner->key()) { $this->authorizeTable($catalog, $logical); return; }
        foreach ($this->extensions as $grant) {
            if ($grant->module()->value() === $this->owner->moduleIdentity()?->value()
                && $grant->table() === $logical && $grant->kind() === $kind && $grant->element() === $element
                && $grant->targetOwner()->key() === $owner->key()) return;
        }
        throw new \RuntimeException('Migration extension is not authorized.');
    }
}
