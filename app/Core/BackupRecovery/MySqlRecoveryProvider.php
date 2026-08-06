<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\CoreMigrationLedger;
use Copot\Core\CoreMigrationStateIdentity;
use PDO;

final class MySqlRecoveryProvider implements DatabaseRecoveryProvider
{
    private const DOMAIN = 'database.webcore';

    public function __construct(private ?MySqlDatabaseArtifactCodec $codec = null)
    {
        $this->codec ??= new MySqlDatabaseArtifactCodec();
    }

    public function capture(DatabaseCaptureContext $context): DatabaseRecoveryArtifact
    {
        $snapshot = $context->snapshotConnection();
        $lock = $context->lockConnection();
        $locked = false;
        $transaction = false;
        try {
            $this->assertConnectionDatabase($snapshot, $context->databaseIdentity());
            $this->assertConnectionDatabase($lock, $context->databaseIdentity());
            $lock->exec('FLUSH TABLES WITH READ LOCK');
            $locked = true;
            $snapshot->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $snapshot->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
            $transaction = true;
            $bundle = $this->captureBundle($snapshot, $context->databaseIdentity());
            $bytes = $this->codec->encode($bundle);
            if (strlen($bytes) > $context->maximumArtifactBytes()) {
                throw new DatabaseRecoveryException('Database recovery artifact exceeds the configured bound.');
            }
            $artifact = $this->artifactFromBytes($bytes);
            if (!$this->verifyCaptured($artifact)->isValid()) {
                throw new DatabaseRecoveryException('Database recovery artifact failed self-verification.');
            }
            return $artifact;
        } catch (DatabaseRecoveryException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new DatabaseRecoveryException('Database recovery capture failed.', 0, $exception);
        } finally {
            if ($transaction) {
                try { if ($snapshot->inTransaction()) { $snapshot->rollBack(); } } catch (\Throwable) {}
            }
            if ($locked) {
                try { $lock->exec('UNLOCK TABLES'); } catch (\Throwable) {}
            }
        }
    }

    public function verifyCaptured(DatabaseRecoveryArtifact $artifact): DatabaseVerificationResult
    {
        try {
            $bundle = $this->codec->decode($artifact->bytes());
            $schema = $this->identity($bundle['schema']);
            $data = $this->identity($bundle['data']);
            $ledger = $this->ledgerIdentity($bundle['migration_ledger']);
            if ($bundle['database_identity'] !== $artifact->databaseIdentity()
                || $bundle['identities']['schema'] !== $schema
                || $bundle['identities']['data'] !== $data
                || $bundle['identities']['migration_ledger'] !== $ledger
                || $schema !== $artifact->schemaIdentity()
                || $data !== $artifact->dataIdentity()
                || $ledger !== $artifact->migrationLedgerIdentity()) {
                return DatabaseVerificationResult::failed('Database artifact identity mismatch.');
            }
            return DatabaseVerificationResult::valid(['schema' => $schema, 'data' => $data, 'migration_ledger' => $ledger, 'artifact' => $artifact->record()->artifactIdentity()]);
        } catch (\Throwable $exception) {
            return DatabaseVerificationResult::failed($exception->getMessage());
        }
    }

    public function restore(DatabaseRecoveryArtifact $artifact, DatabaseRestoreContext $context): void
    {
        $verification = $this->verifyCaptured($artifact);
        if (!$verification->isValid()) { throw new DatabaseRecoveryException($verification->failure() ?? 'Database recovery artifact is invalid.'); }
        if ($artifact->databaseIdentity() !== $context->databaseIdentity()) { throw new DatabaseRecoveryException('Database recovery target identity does not match the artifact.'); }

        $restore = $context->restoreConnection();
        $locked = false;
        $foreignKeys = null;
        try {
            $this->assertConnectionDatabase($restore, $context->databaseIdentity());
            $bundle = $this->codec->decode($artifact->bytes());
            $tableNames = array_map(static fn (array $table): string => $table['name'], $bundle['schema']['tables']);

            $attempt = $context->attempt();
            $current = $this->classifyRestoreTarget($restore, $context->databaseIdentity(), $artifact, $attempt, $tableNames);
            if ($current === 'restored') {
                return;
            }
            if ($attempt === null) {
                throw new DatabaseRecoveryException('Database restore requires a recovery-bound attempt context.');
            }

            $currentNames = $this->baseTableNames($restore);
            if ($current === 'partial') {
                $this->validatePartialState($restore, $bundle, $currentNames, $attempt, $tableNames);
            } else {
                $this->validateExpectedTarget($restore, $context->databaseIdentity(), $attempt);
            }

            if ($currentNames !== []) {
                $restore->exec('LOCK TABLES ' . implode(', ', array_map(fn (string $name): string => $this->quoteIdentifier($name) . ' WRITE', $currentNames)));
                $locked = true;
            }
            $attempt->recordStage(DatabaseRestoreAttemptContext::LOCKED);
            $foreignKeys = (int) $restore->query('SELECT @@FOREIGN_KEY_CHECKS')->fetchColumn();
            $restore->exec('SET FOREIGN_KEY_CHECKS=0');
            $attempt->recordStage(DatabaseRestoreAttemptContext::DROPPING);
            foreach (array_reverse($currentNames) as $name) {
                $restore->exec('DROP TABLE ' . $this->quoteIdentifier($name));
            }
            $attempt->recordStage(DatabaseRestoreAttemptContext::CREATING);
            foreach ($bundle['schema']['tables'] as $table) {
                $restore->exec($table['create_sql']);
            }
            $attempt->recordStage(DatabaseRestoreAttemptContext::LOADING);
            foreach ($bundle['data']['tables'] as $table) {
                $this->insertRows($restore, $table);
            }
            $attempt->recordStage(DatabaseRestoreAttemptContext::RESTORING_METADATA);
            foreach ($bundle['schema']['tables'] as $table) {
                if ($table['auto_increment'] !== null) {
                    $restore->exec('ALTER TABLE ' . $this->quoteIdentifier($table['name']) . ' AUTO_INCREMENT=' . (int) $table['auto_increment']);
                }
            }
            $restore->exec('SET FOREIGN_KEY_CHECKS=1');
            $attempt->recordStage(DatabaseRestoreAttemptContext::VERIFYING);
            $result = $this->compareBundleToArtifact($this->captureBundle($restore, $context->databaseIdentity()), $artifact);
            if (!$result->isValid()) { throw new DatabaseRecoveryException($result->failure() ?? 'Restored database verification failed.'); }
            $attempt->recordStage(DatabaseRestoreAttemptContext::COMPLETED);
        } catch (DatabaseRecoveryException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new DatabaseRecoveryException('Database recovery restore failed.', 0, $exception);
        } finally {
            if ($foreignKeys !== null) { try { $restore->exec('SET FOREIGN_KEY_CHECKS=' . ($foreignKeys === 0 ? '0' : '1')); } catch (\Throwable) {} }
            if ($locked) { try { $restore->exec('UNLOCK TABLES'); } catch (\Throwable) {} }
        }
    }

    public function stateIdentity(PDO $connection, string $database): string
    {
        $this->assertConnectionDatabase($connection, $database);
        return $this->stateIdentityFromBundle($this->captureBundle($connection, $database));
    }

    public function tableSetIdentity(PDO $connection, string $database): string
    {
        $this->assertConnectionDatabase($connection, $database);
        return $this->tableSetIdentityFromNames($this->baseTableNames($connection));
    }

    /** @param array<string, mixed> $bundle */
    private function classifyRestoreTarget(PDO $connection, string $database, DatabaseRecoveryArtifact $artifact, ?DatabaseRestoreAttemptContext $attempt, array $tableNames): string
    {
        try {
            $current = $this->captureBundle($connection, $database);
            if ($this->stateIdentityFromBundle($current) === $this->stateIdentityFromArtifact($artifact)) {
                return 'restored';
            }
            if ($attempt !== null && $this->tableSetIdentityFromNames($current['schema']['tables'] !== [] ? array_map(static fn (array $table): string => $table['name'], $current['schema']['tables']) : []) === $attempt->expectedTableSetIdentity()
                && $this->stateIdentityFromBundle($current) === $attempt->expectedDatabaseStateIdentity()) {
                return 'expected';
            }
        } catch (\Throwable) {
            // An incomplete table set cannot produce a complete semantic bundle;
            // partial-state validation below handles only a bound retry stage.
        }

        if ($attempt !== null && $attempt->provesDestructiveRestoreBegan()) {
            return 'partial';
        }

        return 'unexpected';
    }

    /** @param array<string, mixed> $bundle @param array<int, string> $currentNames @param array<int, string> $tableNames */
    private function validatePartialState(PDO $connection, array $bundle, array $currentNames, DatabaseRestoreAttemptContext $attempt, array $tableNames): void
    {
        $scope = $attempt->providerCreatedObjectScope();
        sort($scope, SORT_STRING);
        $expected = $tableNames;
        sort($expected, SORT_STRING);
        if ($scope !== $expected) {
            throw new DatabaseRecoveryException('Database restore provider scope is not bound to the artifact.');
        }
        foreach ($currentNames as $name) {
            if (!in_array($name, $expected, true)) {
                throw new DatabaseRecoveryException('Database partial restore contains an unexpected table.');
            }
            $current = $this->schemaTable($connection, $name);
            $artifactTable = null;
            foreach ($bundle['schema']['tables'] as $candidate) {
                if ($candidate['name'] === $name) { $artifactTable = $candidate; break; }
            }
            if (!is_array($artifactTable) || $this->schemaIdentity($current) !== $this->schemaIdentity($artifactTable)) {
                throw new DatabaseRecoveryException('Database partial restore contains an unexpected schema identity.');
            }
        }
    }

    private function validateExpectedTarget(PDO $connection, string $database, DatabaseRestoreAttemptContext $attempt): void
    {
        if ($this->tableSetIdentity($connection, $database) !== $attempt->expectedTableSetIdentity()
            || $this->stateIdentity($connection, $database) !== $attempt->expectedDatabaseStateIdentity()) {
            throw new DatabaseRecoveryException('Database target identity is unexpected or ambiguous.');
        }
    }

    /** @param array<string, mixed> $bundle */
    private function stateIdentityFromBundle(array $bundle): string
    {
        return hash('sha256', json_encode([
            'database_identity' => $bundle['database_identity'],
            'schema' => $bundle['identities']['schema'],
            'data' => $bundle['identities']['data'],
            'migration_ledger' => $bundle['identities']['migration_ledger'],
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function stateIdentityFromArtifact(DatabaseRecoveryArtifact $artifact): string
    {
        return hash('sha256', json_encode([
            'database_identity' => $artifact->databaseIdentity(),
            'schema' => $artifact->schemaIdentity(),
            'data' => $artifact->dataIdentity(),
            'migration_ledger' => $artifact->migrationLedgerIdentity(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** @param array<int, string> $names */
    private function tableSetIdentityFromNames(array $names): string
    {
        sort($names, SORT_STRING);
        return hash('sha256', json_encode(array_values($names), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $table */
    private function schemaIdentity(array $table): string
    {
        unset($table['auto_increment']);
        return hash('sha256', json_encode($table, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function restoreFromStore(RecoveryIdentity $identity, RecoveryArtifactRecord $record, RecoveryArtifactStore $store, DatabaseRestoreContext $context): void
    {
        if ($context->attempt() !== null && $context->attempt()->recoveryIdentity()->value() !== $identity->value()) {
            throw new DatabaseRecoveryException('Database restore recovery identity does not match the attempt context.');
        }
        $this->restore($this->artifactFromBytes($store->readArtifact($identity, $record)), $context);
    }

    public function verifyRestored(DatabaseRecoveryArtifact $artifact, DatabaseRestoreContext $context): DatabaseVerificationResult
    {
        try {
            $current = $this->capture(new DatabaseCaptureContext($context->restoreConnection(), $context->lockConnection(), $context->databaseIdentity()));
            $expected = $this->verifyCaptured($artifact);
            $actual = $this->verifyCaptured($current);
            if (!$expected->isValid() || !$actual->isValid() || $expected->identities() !== $actual->identities()) {
                return DatabaseVerificationResult::failed('Restored database semantic identity does not match the artifact.');
            }
            return $actual;
        } catch (\Throwable $exception) {
            return DatabaseVerificationResult::failed($exception->getMessage());
        }
    }

    /** @param array<string, mixed> $bundle */
    private function compareBundleToArtifact(array $bundle, DatabaseRecoveryArtifact $artifact): DatabaseVerificationResult
    {
        $schema = $this->identity($bundle['schema']);
        $data = $this->identity($bundle['data']);
        $ledger = $this->ledgerIdentity($bundle['migration_ledger']);
        if ($bundle['database_identity'] !== $artifact->databaseIdentity()
            || $schema !== $artifact->schemaIdentity()
            || $data !== $artifact->dataIdentity()
            || $ledger !== $artifact->migrationLedgerIdentity()) {
            return DatabaseVerificationResult::failed('Restored database semantic identity does not match the artifact.');
        }
        return DatabaseVerificationResult::valid(['schema' => $schema, 'data' => $data, 'migration_ledger' => $ledger, 'artifact' => $artifact->record()->artifactIdentity()]);
    }

    /** @return array<string, mixed> */
    private function captureBundle(PDO $connection, string $database): array
    {
        $provider = $connection->query("SELECT @@version AS version, @@version_comment AS version_comment, @@character_set_database AS charset_name, @@collation_database AS collation_name, @@sql_mode AS sql_mode")->fetch(PDO::FETCH_ASSOC);
        $tables = $this->baseTableNames($connection);
        if (!in_array(CoreMigrationLedger::TABLE, $tables, true)) { throw new DatabaseRecoveryException('Required Core migration ledger is missing.'); }
        $schemaTables = [];
        $dataTables = [];
        foreach ($tables as $tableName) {
            $schemaTable = $this->schemaTable($connection, $tableName);
            if ($schemaTable['engine'] !== 'InnoDB') { throw new DatabaseRecoveryException('Database table engine is unsupported: ' . $tableName); }
            $primary = array_values(array_filter($schemaTable['indexes'], static fn (array $index): bool => $index['name'] === 'PRIMARY'));
            if ($primary === []) { throw new DatabaseRecoveryException('Database table has no deterministic primary-key ordering: ' . $tableName); }
            $schemaTables[] = $schemaTable;
            $dataTables[] = $this->dataTable($connection, $schemaTable, $primary);
        }
        $records = (new CoreMigrationLedger())->records($connection);
        $ledger = array_map(static fn ($record): array => [
            'migration_id' => $record->migrationId(), 'sequence_number' => $record->sequence(), 'target_webcore_version' => $record->targetWebcoreVersion(),
            'target_schema_identity' => $record->targetSchemaIdentity(), 'migration_checksum' => $record->checksum(), 'applied_at' => $record->appliedAt()->format('Y-m-d H:i:s'),
        ], $records);
        $schema = ['tables' => $schemaTables];
        $data = ['tables' => $dataTables];
        return [
            'format_version' => 1,
            'domain_identifier' => self::DOMAIN,
            'database_identity' => $database,
            'provider' => ['product' => (string) ($provider['version_comment'] ?? ''), 'version' => (string) ($provider['version'] ?? ''), 'charset' => (string) ($provider['charset_name'] ?? ''), 'collation' => (string) ($provider['collation_name'] ?? ''), 'sql_mode' => (string) ($provider['sql_mode'] ?? '')],
            'schema' => $schema,
            'data' => $data,
            'migration_ledger' => $ledger,
            'identities' => ['schema' => $this->identity($schema), 'data' => $this->identity($data), 'migration_ledger' => CoreMigrationStateIdentity::fromRecords($records)],
        ];
    }

    /** @return array<string, mixed> */
    private function schemaTable(PDO $connection, string $name): array
    {
        $statement = $connection->query('SHOW CREATE TABLE ' . $this->quoteIdentifier($name));
        $row = $statement->fetch(PDO::FETCH_NUM);
        if (!is_array($row) || !isset($row[1]) || !is_string($row[1])) { throw new DatabaseRecoveryException('Table definition could not be captured: ' . $name); }
        $columns = $connection->prepare('SELECT column_name AS name, ordinal_position AS ordinal, column_type AS column_type, data_type AS data_type, character_set_name AS charset_name, collation_name AS collation_name, is_nullable AS nullable_state, column_default AS default_value, extra AS extra_value, generation_expression AS generation_value FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table ORDER BY ordinal_position');
        $columns->execute(['table' => $name]);
        $columnRows = [];
        foreach ($columns->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $columnRows[] = ['name' => (string) $column['name'], 'ordinal' => (int) $column['ordinal'], 'column_type' => (string) $column['column_type'], 'data_type' => (string) $column['data_type'], 'charset' => $column['charset_name'] === null ? null : (string) $column['charset_name'], 'collation' => $column['collation_name'] === null ? null : (string) $column['collation_name'], 'nullable' => $column['nullable_state'] === 'YES', 'default' => $column['default_value'] === null ? null : (string) $column['default_value'], 'extra' => (string) $column['extra_value'], 'generation' => (string) ($column['generation_value'] ?? '')];
        }
        $indexes = $connection->prepare('SELECT index_name AS name, non_unique AS non_unique, seq_in_index AS sequence, column_name AS column_name, sub_part AS prefix, index_type AS index_type FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=:table AND column_name IS NOT NULL ORDER BY index_name, seq_in_index');
        $indexes->execute(['table' => $name]);
        $indexRows = [];
        foreach ($indexes->fetchAll(PDO::FETCH_ASSOC) as $index) { $indexRows[] = ['name' => (string) $index['name'], 'non_unique' => (int) $index['non_unique'], 'sequence' => (int) $index['sequence'], 'column' => (string) $index['column_name'], 'prefix' => $index['prefix'] === null ? null : (int) $index['prefix'], 'index_type' => (string) $index['index_type']]; }
        $foreign = $connection->prepare('SELECT kcu.constraint_name AS fk_name, kcu.column_name AS column_name, kcu.referenced_table_name AS referenced_table, kcu.referenced_column_name AS referenced_column, kcu.ordinal_position AS fk_sequence, rc.update_rule AS update_rule, rc.delete_rule AS delete_rule FROM information_schema.key_column_usage kcu INNER JOIN information_schema.referential_constraints rc ON rc.constraint_schema=kcu.constraint_schema AND rc.constraint_name=kcu.constraint_name AND rc.table_name=kcu.table_name WHERE kcu.constraint_schema=DATABASE() AND kcu.table_name=:table AND kcu.referenced_table_name IS NOT NULL ORDER BY kcu.constraint_name, kcu.ordinal_position');
        $foreign->execute(['table' => $name]);
        $foreignRows = [];
        foreach ($foreign->fetchAll(PDO::FETCH_ASSOC) as $key) { $foreignRows[] = ['name' => (string) $key['fk_name'], 'column' => (string) $key['column_name'], 'referenced_table' => (string) $key['referenced_table'], 'referenced_column' => (string) $key['referenced_column'], 'sequence' => (int) $key['fk_sequence'], 'on_update' => (string) $key['update_rule'], 'on_delete' => (string) $key['delete_rule']]; }
        $status = $connection->prepare('SELECT engine, table_collation, create_options, auto_increment FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');
        $status->execute(['table' => $name]);
        $table = $status->fetch(PDO::FETCH_ASSOC);
        if (!is_array($table)) { throw new DatabaseRecoveryException('Table metadata could not be captured: ' . $name); }
        return ['name' => $name, 'create_sql' => $row[1], 'columns' => $columnRows, 'indexes' => $indexRows, 'foreign_keys' => $foreignRows, 'engine' => (string) $table['engine'], 'collation' => (string) $table['table_collation'], 'create_options' => (string) $table['create_options'], 'auto_increment' => $table['auto_increment'] === null ? null : (int) $table['auto_increment']];
    }

    /** @return array<string, mixed> */
    private function dataTable(PDO $connection, array $schemaTable, array $primary): array
    {
        $columns = array_map(static fn (array $column): string => $column['name'], $schemaTable['columns']);
        $order = array_map(static fn (array $index): string => $index['column'], $primary);
        $query = 'SELECT * FROM ' . $this->quoteIdentifier($schemaTable['name']) . ' ORDER BY ' . implode(', ', array_map(fn (string $column): string => $this->quoteIdentifier($column) . ' ASC', $order));
        $statement = $connection->query($query);
        $rows = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) { $encoded = []; foreach ($schemaTable['columns'] as $column) { $encoded[] = $this->encodeValue($row[$column['name']], $column['data_type']); } $rows[] = $encoded; }
        return ['name' => $schemaTable['name'], 'columns' => $columns, 'rows' => $rows];
    }

    private function encodeValue(mixed $value, string $dataType): array
    {
        if ($value === null) { return ['type' => 'null', 'value' => null]; }
        $type = strtolower($dataType);
        if (in_array($type, ['binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob', 'bit'], true)) { return ['type' => 'binary', 'value' => base64_encode((string) $value)]; }
        if (in_array($type, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'year'], true)) { return ['type' => 'integer', 'value' => (string) $value]; }
        if (in_array($type, ['decimal', 'numeric'], true)) { return ['type' => 'decimal', 'value' => (string) $value]; }
        if (in_array($type, ['float', 'double', 'real'], true)) { return ['type' => 'float', 'value' => (string) $value]; }
        return ['type' => 'string', 'value' => (string) $value];
    }

    private function artifactFromBytes(string $bytes): DatabaseRecoveryArtifact
    {
        $bundle = $this->codec->decode($bytes);
        return new DatabaseRecoveryArtifact(new RecoveryArtifactRecord(self::DOMAIN, hash('sha256', $bytes), strlen($bytes)), $bytes, $bundle['database_identity'], $bundle['identities']['schema'], $bundle['identities']['data'], $bundle['identities']['migration_ledger']);
    }

    /** @return array<int, string> */
    private function baseTableNames(PDO $connection): array
    {
        $rows = $connection->query("SELECT table_name AS name, table_type AS type FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY table_name")->fetchAll(PDO::FETCH_ASSOC);
        $names = [];
        foreach ($rows as $row) {
            if ($row['type'] !== 'BASE TABLE') { throw new DatabaseRecoveryException('Unsupported non-table database object is present: ' . $row['name']); }
            if (preg_match('/^[A-Za-z0-9_]+$/D', (string) $row['name']) !== 1) { throw new DatabaseRecoveryException('Database table identifier is unsupported.'); }
            $names[] = (string) $row['name'];
        }
        return $names;
    }

    private function assertConnectionDatabase(PDO $connection, string $expected): void
    {
        $actual = (string) $connection->query('SELECT DATABASE()')->fetchColumn();
        if ($actual !== $expected) { throw new DatabaseRecoveryException('Database connection identity does not match the requested database.'); }
    }

    /** @param array<string, mixed> $table */
    private function insertRows(PDO $connection, array $table): void
    {
        $quoted = array_map(fn (string $column): string => $this->quoteIdentifier($column), $table['columns']);
        $placeholders = implode(', ', array_fill(0, count($quoted), '?'));
        $statement = $connection->prepare('INSERT INTO ' . $this->quoteIdentifier($table['name']) . ' (' . implode(', ', $quoted) . ') VALUES (' . $placeholders . ')');
        foreach ($table['rows'] as $row) {
            $values = array_map(static function (array $value): mixed {
                if ($value['type'] === 'null') { return null; }
                if ($value['type'] === 'binary') { return base64_decode($value['value'], true); }
                return $value['value'];
            }, $row);
            $statement->execute($values);
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/D', $identifier) !== 1) { throw new DatabaseRecoveryException('Unsafe database identifier.'); }
        return '`' . $identifier . '`';
    }

    private function identity(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function ledgerIdentity(array $records): string
    {
        $objects = [];
        foreach ($records as $record) {
            $objects[] = new \Copot\Core\AppliedMigrationRecord($record['migration_id'], $record['sequence_number'], $record['target_webcore_version'], $record['target_schema_identity'], $record['migration_checksum'], new \DateTimeImmutable($record['applied_at']));
        }
        return CoreMigrationStateIdentity::fromRecords($objects);
    }
}
