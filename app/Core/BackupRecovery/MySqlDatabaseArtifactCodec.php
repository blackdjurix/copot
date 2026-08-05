<?php

namespace Copot\Core\BackupRecovery;

final class MySqlDatabaseArtifactCodec
{
    private const VERSION = 1;
    private const MAX_DEPTH = 64;

    /** @param array<string, mixed> $bundle */
    public function encode(array $bundle): string
    {
        $this->validate($bundle);
        try {
            return json_encode($bundle, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            throw new DatabaseRecoveryException('Database artifact encoding failed.', 0, $exception);
        }
    }

    /** @return array<string, mixed> */
    public function decode(string $bytes): array
    {
        if ($bytes === '' || strlen($bytes) > 67108864) {
            throw new DatabaseRecoveryException('Database artifact size is invalid.');
        }
        try {
            $bundle = json_decode($bytes, true, self::MAX_DEPTH, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            throw new DatabaseRecoveryException('Database artifact is malformed.', 0, $exception);
        }
        if (!is_array($bundle)) {
            throw new DatabaseRecoveryException('Database artifact is invalid.');
        }
        $this->validate($bundle);
        if ($this->encode($bundle) !== $bytes) {
            throw new DatabaseRecoveryException('Database artifact is not canonically encoded.');
        }
        return $bundle;
    }

    /** @param array<string, mixed> $bundle */
    private function validate(array $bundle): void
    {
        $this->keys($bundle, ['format_version', 'domain_identifier', 'database_identity', 'provider', 'schema', 'data', 'migration_ledger', 'identities']);
        if ($bundle['format_version'] !== self::VERSION || $bundle['domain_identifier'] !== 'database.webcore') {
            throw new DatabaseRecoveryException('Database artifact format or domain is unsupported.');
        }
        $this->technical((string) $bundle['database_identity'], 'Database identity');
        if (!is_array($bundle['provider']) || !is_array($bundle['schema']) || !is_array($bundle['data']) || !is_array($bundle['migration_ledger']) || !is_array($bundle['identities'])) {
            throw new DatabaseRecoveryException('Database artifact structure is invalid.');
        }
        $this->keys($bundle['provider'], ['product', 'version', 'charset', 'collation', 'sql_mode']);
        foreach (['product', 'version', 'charset', 'collation', 'sql_mode'] as $key) {
            if (!is_string($bundle['provider'][$key])) { throw new DatabaseRecoveryException('Database provider metadata is invalid.'); }
        }
        $this->keys($bundle['schema'], ['tables']);
        $this->keys($bundle['data'], ['tables']);
        if (!is_array($bundle['schema']['tables']) || !is_array($bundle['data']['tables'])) { throw new DatabaseRecoveryException('Database table data is invalid.'); }
        if (count($bundle['schema']['tables']) !== count($bundle['data']['tables'])) { throw new DatabaseRecoveryException('Database schema/data table counts differ.'); }
        $names = [];
        foreach ($bundle['schema']['tables'] as $table) {
            if (!is_array($table)) { throw new DatabaseRecoveryException('Database schema table is invalid.'); }
            $this->keys($table, ['name', 'create_sql', 'columns', 'indexes', 'foreign_keys', 'engine', 'collation', 'create_options', 'auto_increment']);
            $this->identifier($table['name'], 'Table name');
            foreach (['create_sql', 'engine', 'collation', 'create_options'] as $key) { if (!is_string($table[$key])) { throw new DatabaseRecoveryException('Database table metadata is invalid.'); } }
            if ($table['auto_increment'] !== null && (!is_int($table['auto_increment']) || $table['auto_increment'] < 1)) { throw new DatabaseRecoveryException('Database auto-increment metadata is invalid.'); }
            if (!is_array($table['columns']) || !is_array($table['indexes']) || !is_array($table['foreign_keys'])) { throw new DatabaseRecoveryException('Database table metadata arrays are invalid.'); }
            if (isset($names[$table['name']])) { throw new DatabaseRecoveryException('Database artifact contains duplicate tables.'); }
            $names[$table['name']] = true;
            $this->validateColumns($table['columns']);
            $this->validateIndexes($table['indexes']);
            $this->validateForeignKeys($table['foreign_keys']);
        }
        $dataNames = [];
        foreach ($bundle['data']['tables'] as $table) {
            if (!is_array($table)) { throw new DatabaseRecoveryException('Database row table is invalid.'); }
            $this->keys($table, ['name', 'columns', 'rows']);
            $this->identifier($table['name'], 'Data table name');
            if (!isset($names[$table['name']]) || isset($dataNames[$table['name']]) || !is_array($table['columns']) || !is_array($table['rows'])) { throw new DatabaseRecoveryException('Database row table binding is invalid.'); }
            $dataNames[$table['name']] = true;
            foreach ($table['columns'] as $column) { if (!is_string($column)) { throw new DatabaseRecoveryException('Database row column is invalid.'); } }
            foreach ($table['rows'] as $row) {
                if (!is_array($row) || count($row) !== count($table['columns'])) { throw new DatabaseRecoveryException('Database row shape is invalid.'); }
                foreach ($row as $value) { $this->validateValue($value); }
            }
        }
        if (count($dataNames) !== count($names)) { throw new DatabaseRecoveryException('Database artifact is missing table data.'); }
        foreach ($bundle['migration_ledger'] as $record) {
            if (!is_array($record)) { throw new DatabaseRecoveryException('Migration ledger record is invalid.'); }
            $this->keys($record, ['migration_id', 'sequence_number', 'target_webcore_version', 'target_schema_identity', 'migration_checksum', 'applied_at']);
            if (!is_string($record['migration_id']) || !is_int($record['sequence_number']) || $record['sequence_number'] < 1 || !is_string($record['target_webcore_version']) || !is_string($record['target_schema_identity']) || !is_string($record['migration_checksum']) || !is_string($record['applied_at'])) { throw new DatabaseRecoveryException('Migration ledger record fields are invalid.'); }
        }
        $this->keys($bundle['identities'], ['schema', 'data', 'migration_ledger']);
        foreach (['schema', 'data', 'migration_ledger'] as $key) {
            if (!is_string($bundle['identities'][$key]) || preg_match('/^[a-f0-9]{64}$/D', $bundle['identities'][$key]) !== 1) { throw new DatabaseRecoveryException('Database identity is invalid.'); }
        }
    }

    /** @param array<int, mixed> $columns */
    private function validateColumns(array $columns): void
    {
        foreach ($columns as $column) {
            if (!is_array($column)) { throw new DatabaseRecoveryException('Database column metadata is invalid.'); }
            $this->keys($column, ['name', 'ordinal', 'column_type', 'data_type', 'charset', 'collation', 'nullable', 'default', 'extra', 'generation']);
            $this->identifier($column['name'], 'Column name');
            if (!is_int($column['ordinal']) || $column['ordinal'] < 1 || !is_string($column['column_type']) || !is_string($column['data_type']) || !is_string($column['charset']) && $column['charset'] !== null || !is_string($column['collation']) && $column['collation'] !== null || !is_bool($column['nullable']) || !is_string($column['default']) && $column['default'] !== null || !is_string($column['extra']) || !is_string($column['generation'])) { throw new DatabaseRecoveryException('Database column fields are invalid.'); }
        }
    }

    /** @param array<int, mixed> $indexes */
    private function validateIndexes(array $indexes): void
    {
        foreach ($indexes as $index) {
            if (!is_array($index)) { throw new DatabaseRecoveryException('Database index metadata is invalid.'); }
            $this->keys($index, ['name', 'non_unique', 'sequence', 'column', 'prefix', 'index_type']);
            $this->identifier($index['name'], 'Index name');
            $this->identifier($index['column'], 'Index column');
            if (!is_int($index['non_unique']) || !is_int($index['sequence']) || !is_int($index['prefix']) && $index['prefix'] !== null || !is_string($index['index_type'])) { throw new DatabaseRecoveryException('Database index fields are invalid.'); }
        }
    }

    /** @param array<int, mixed> $foreignKeys */
    private function validateForeignKeys(array $foreignKeys): void
    {
        foreach ($foreignKeys as $foreignKey) {
            if (!is_array($foreignKey)) { throw new DatabaseRecoveryException('Database foreign key metadata is invalid.'); }
            $this->keys($foreignKey, ['name', 'column', 'referenced_table', 'referenced_column', 'sequence', 'on_update', 'on_delete']);
            foreach (['name', 'column', 'referenced_table', 'referenced_column'] as $key) { $this->identifier($foreignKey[$key], 'Foreign key identifier'); }
            if (!is_int($foreignKey['sequence']) || !is_string($foreignKey['on_update']) || !is_string($foreignKey['on_delete'])) { throw new DatabaseRecoveryException('Database foreign key fields are invalid.'); }
        }
    }

    private function validateValue(mixed $value): void
    {
        if (!is_array($value)) { throw new DatabaseRecoveryException('Database value encoding is invalid.'); }
        $this->keys($value, ['type', 'value']);
        if (!is_string($value['type']) || !in_array($value['type'], ['null', 'string', 'binary', 'integer', 'decimal', 'float'], true) || $value['type'] === 'null' && $value['value'] !== null || $value['type'] !== 'null' && !is_string($value['value'])) { throw new DatabaseRecoveryException('Database value encoding is invalid.'); }
        if ($value['type'] === 'binary' && base64_decode($value['value'], true) === false) { throw new DatabaseRecoveryException('Database binary value is invalid.'); }
    }

    private function keys(array $value, array $required): void
    {
        if (array_keys($value) !== $required) { throw new DatabaseRecoveryException('Database artifact contains unknown or out-of-order fields.'); }
    }

    private function identifier(mixed $value, string $label): void
    {
        if (!is_string($value) || preg_match('/^[A-Za-z0-9_]+$/D', $value) !== 1) { throw new DatabaseRecoveryException($label . ' is invalid.'); }
    }

    private function technical(string $value, string $label): void
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_]+$/D', $value) !== 1) { throw new DatabaseRecoveryException($label . ' is invalid.'); }
    }
}
