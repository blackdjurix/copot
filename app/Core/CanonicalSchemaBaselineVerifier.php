<?php

namespace Copot\Core;

use PDO;

/**
 * Verifies the bounded Core schema shape without executing the canonical SQL.
 */
final class CanonicalSchemaBaselineVerifier
{
    public function verify(PDO $connection, string $schemaPath): HealthGateMatrix
    {
        try {
            $expected = $this->expectedColumns($schemaPath);
            if ($expected === []) {
                return new HealthGateMatrix([HealthGateResult::fail('canonical-schema', 'Canonical schema contains no Core tables.')]);
            }

            $actual = $this->actualColumns($connection, array_keys($expected));
            $gates = [];
            foreach ($expected as $table => $columns) {
                $actualColumns = $actual[$table] ?? null;
                $gates[] = $actualColumns === $columns
                    ? HealthGateResult::pass('canonical-schema:' . $table)
                    : HealthGateResult::fail('canonical-schema:' . $table, 'Core table columns do not match the canonical schema: expected ' . json_encode($columns) . ', actual ' . json_encode($actualColumns));
            }

            $gates[] = $this->ledgerIsEmpty($connection)
                ? HealthGateResult::pass('canonical-migration-baseline')
                : HealthGateResult::fail('canonical-migration-baseline', 'Canonical baseline requires an empty Core migration ledger.');

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
                    $columns[] = strtolower($column[1]) . '|' . strtolower(preg_replace('/\s+/', ' ', $type[1])) . '|' . $nullable;
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
                $actual[$table] = array_map(static fn (array $row): string => strtolower((string) $row['name']) . '|' . strtolower((string) $row['type']) . '|' . ((int) $row['notnull'] === 1 ? 'no' : 'yes'), $rows);
                continue;
            }

            $statement = $connection->prepare('SELECT column_name, column_type, is_nullable FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table ORDER BY ordinal_position');
            $statement->execute(['table' => $table]);
            $actual[$table] = array_map(static fn (array $row): string => strtolower((string) $row['column_name']) . '|' . strtolower((string) $row['column_type']) . '|' . (strtoupper((string) $row['is_nullable']) === 'NO' ? 'no' : 'yes'), $statement->fetchAll(PDO::FETCH_ASSOC));
        }

        return $actual;
    }

    private function ledgerIsEmpty(PDO $connection): bool
    {
        $statement = $connection->query('SELECT COUNT(*) FROM ' . CoreMigrationLedger::TABLE);
        return (int) $statement->fetchColumn() === 0;
    }
}
