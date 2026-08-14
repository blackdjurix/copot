<?php

namespace Copot\Core;

final class DatabaseTableExtensionGrant
{
    public const ADD_COLUMN = 'add_column';
    public const ADD_INDEX = 'add_index';

    public function __construct(
        private ModuleIdentity|string $module,
        private string $table,
        private DatabaseTableOwner $targetOwner,
        private string $kind,
        private string $element,
        private string $migrationIdentity,
        private string $lifecycleOperation
    ) {
        $this->module = $module instanceof ModuleIdentity ? $module : new ModuleIdentity($module);
        if (!in_array($kind, [self::ADD_COLUMN, self::ADD_INDEX], true)) {
            throw new \InvalidArgumentException('Database table extension kind is not permitted.');
        }
        foreach ([$table, $element, $migrationIdentity, $lifecycleOperation] as $value) {
            if ($value === '' || trim($value) !== $value || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                throw new \InvalidArgumentException('Database table extension identity is invalid.');
            }
        }
    }

    public function module(): ModuleIdentity { return $this->module; }
    public function table(): string { return $this->table; }
    public function targetOwner(): DatabaseTableOwner { return $this->targetOwner; }
    public function kind(): string { return $this->kind; }
    public function element(): string { return $this->element; }
    public function migrationIdentity(): string { return $this->migrationIdentity; }
    public function lifecycleOperation(): string { return $this->lifecycleOperation; }
    public function key(): string { return $this->module->value() . ':' . $this->table . ':' . $this->kind . ':' . $this->element; }
}
