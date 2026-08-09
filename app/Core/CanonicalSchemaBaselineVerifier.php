<?php

namespace Copot\Core;

use PDO;

/**
 * Verifies the bounded Core schema shape without executing the canonical SQL.
 */
final class CanonicalSchemaBaselineVerifier
{
    public function __construct(private ?DatabaseTableNames $tables = null)
    {
        $this->tables ??= new DatabaseTableNames();
    }

    public function verify(PDO $connection, string $schemaPath, bool $requireMigrationLedger = true): HealthGateMatrix
    {
        try {
            $expected = $this->expectedColumns($schemaPath);
            if ($expected === []) {
                return new HealthGateMatrix([HealthGateResult::fail('canonical-schema', 'Canonical schema contains no Core tables.')]);
            }

            $actualTables = $this->actualTables($connection);
            $expected = array_combine(
                array_map(fn (string $table): string => $this->tables->table($table), array_keys($expected)),
                array_values($expected)
            );
            $expectedTables = array_keys($expected);
            sort($expectedTables);
            if ($actualTables !== $expectedTables) {
                return new HealthGateMatrix([HealthGateResult::fail('canonical-schema:tables', 'Database tables do not exactly match the canonical schema.')]);
            }

            $actual = $this->actualColumns($connection, array_keys($expected));
            $gates = [];
            foreach ($expected as $table => $columns) {
                $actualColumns = $actual[$table] ?? null;
                $gates[] = $actualColumns === $columns
                    ? HealthGateResult::pass('canonical-schema:' . $table)
                    : HealthGateResult::fail('canonical-schema:' . $table, 'Core table columns do not match the canonical schema: expected ' . json_encode($columns) . ', actual ' . json_encode($actualColumns));
            }

            if ($requireMigrationLedger) {
                $gates[] = $this->ledgerIsEmpty($connection)
                    ? HealthGateResult::pass('canonical-migration-baseline')
                    : HealthGateResult::fail('canonical-migration-baseline', 'Canonical baseline requires an empty Core migration ledger.');
            }

            return new HealthGateMatrix($gates);
        } catch (\Throwable $exception) {
            return new HealthGateMatrix([HealthGateResult::fail('canonical-schema', $exception->getMessage())]);
        }
    }

    public function identity(string $schemaPath): string
    {
        if (is_link($schemaPath) || !is_file($schemaPath) || !is_readable($schemaPath)) {
            throw new \RuntimeException('Canonical schema is unavailable.');
        }

        $hash = hash_file('sha256', $schemaPath);
        if (!is_string($hash)) {
            throw new \RuntimeException('Canonical schema identity could not be calculated.');
        }

        return 'canonical-schema:' . $hash;
    }

    /** @return array<string, list<string>> */
    private function expectedColumns(string $schemaPath): array
    {
        $schema = is_file($schemaPath) && is_readable($schemaPath) ? file_get_contents($schemaPath) : false;
        if (!is_string($schema)) {
            throw new \RuntimeException('Canonical schema is unavailable.');
        }

        $statements = (new InstallerSchemaRunner($schemaPath))->statements($schema);
        $tables = [];
        foreach ($statements as $statement) {
            if (preg_match('/\ACREATE\s+TABLE\s+`?([A-Za-z0-9_]+)`?\s*\((.*)\)\s*(?:ENGINE|$)/is', $statement, $match) !== 1) {
                continue;
            }

            $columns = [];
            foreach (preg_split('/\R/', $match[2]) ?: [] as $line) {
                $line = trim($line, " \t,\r\n");
                if ($line === '' || preg_match('/\A(?:PRIMARY|UNIQUE|INDEX|KEY|CONSTRAINT|FOREIGN|CHECK|ON)\b/i', $line) === 1) {
                    continue;
                }
                if (preg_match('/\A`?([A-Za-z0-9_]+)`?\s+/i', $line, $column) === 1) {
                    $definition = trim(substr($line, strlen($column[0])));
                    if (preg_match('/\A([A-Za-z]+(?:\s+UNSIGNED)?(?:\(\d+(?:,\d+)?\))?)/i', $definition, $type) !== 1) {
                        throw new \RuntimeException('Canonical schema column definition is invalid.');
                    }
                    $nullable = preg_match('/\bNOT\s+NULL\b/i', $definition) === 1
                        || preg_match('/\bPRIMARY\s+KEY\b/i', $definition) === 1 ? 'no' : 'yes';
                    $columns[] = strtolower($column[1]) . '|' . $this->normalizeType($type[1]) . '|' . $nullable;
                }
            }
            if ($columns !== []) {
                $tables[strtolower($match[1])] = $columns;
            }
        }

        return $tables;
    }

    /** @return array<string, list<string>> */
    private function actualColumns(PDO $connection, array $tables): array
    {
        $driver = (string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME);
        $actual = [];
        foreach ($tables as $table) {
            if ($driver === 'sqlite') {
                $statement = $connection->query('PRAGMA table_info(' . $table . ')');
                $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
                $actual[$table] = array_map(fn (array $row): string => strtolower((string) $row['name']) . '|' . $this->normalizeType((string) $row['type']) . '|' . ((int) $row['notnull'] === 1 ? 'no' : 'yes'), $rows);
                continue;
            }

            $statement = $connection->prepare('SELECT column_name, column_type, is_nullable FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table ORDER BY ordinal_position');
            $statement->execute(['table' => $table]);
            $actual[$table] = array_map(fn (array $row): string => strtolower((string) $row['column_name']) . '|' . $this->normalizeType((string) $row['column_type']) . '|' . (strtoupper((string) $row['is_nullable']) === 'NO' ? 'no' : 'yes'), $statement->fetchAll(PDO::FETCH_ASSOC));
        }

        return $actual;
    }

    private function ledgerIsEmpty(PDO $connection): bool
    {
        $statement = $connection->query('SELECT COUNT(*) FROM ' . $this->tables->table(CoreMigrationLedger::TABLE));
        return (int) $statement->fetchColumn() === 0;
    }

    private function normalizeType(string $type): string
    {
        $type = strtolower(preg_replace('/\s+/', ' ', trim($type)));
        return preg_replace('/\b(bigint|int|mediumint|smallint)\(\d+\)(\s+unsigned)?/', '$1$2', $type) ?? $type;
    }

    /** @return list<string> */
    private function actualTables(PDO $connection): array
    {
        $driver = (string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $statement = $connection->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        } else {
            $statement = $connection->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY table_name");
        }
        $tables = array_map(static fn (array $row): string => strtolower((string) ($row['name'] ?? $row['table_name'])), $statement->fetchAll(PDO::FETCH_ASSOC));
        sort($tables);
        return $tables;
    }
}
