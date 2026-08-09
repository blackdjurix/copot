<?php

namespace Copot\Core;

use DateTimeImmutable;
use PDO;

final class CoreMigrationLedger
{
    public const TABLE = 'core_migration_history';

    public function __construct(private ?DatabaseTableNames $tables = null)
    {
        $this->tables ??= new DatabaseTableNames();
    }

    public function table(): string
    {
        return $this->tables->table(self::TABLE);
    }

    public function records(PDO $connection): array
    {
        $rows = $connection->query('SELECT migration_id, sequence_number, target_webcore_version, target_schema_identity, migration_checksum, applied_at FROM ' . $this->table() . ' ORDER BY sequence_number')->fetchAll(PDO::FETCH_ASSOC);
        $records = [];
        $ids = [];
        $sequences = [];

        foreach ($rows as $row) {
            $record = new AppliedMigrationRecord(
                (string) $row['migration_id'],
                (int) $row['sequence_number'],
                (string) $row['target_webcore_version'],
                (string) $row['target_schema_identity'],
                (string) $row['migration_checksum'],
                new DateTimeImmutable((string) $row['applied_at'])
            );

            if (isset($ids[$record->migrationId()]) || isset($sequences[$record->sequence()])) {
                throw new \RuntimeException('Applied migration history contains duplicate identity or sequence.');
            }

            $ids[$record->migrationId()] = true;
            $sequences[$record->sequence()] = true;
            $records[] = $record;
        }

        return $records;
    }

    public function record(PDO $connection, CoreMigrationDescriptor $migration, ?DateTimeImmutable $appliedAt = null): void
    {
        $statement = $connection->prepare('INSERT INTO ' . $this->table() . ' (migration_id, sequence_number, target_webcore_version, target_schema_identity, migration_checksum, applied_at) VALUES (:migration_id, :sequence_number, :target_webcore_version, :target_schema_identity, :migration_checksum, :applied_at)');
        $statement->execute([
            'migration_id' => $migration->id(),
            'sequence_number' => $migration->sequence(),
            'target_webcore_version' => $migration->targetWebcoreVersion(),
            'target_schema_identity' => $migration->targetSchemaIdentity(),
            'migration_checksum' => $migration->checksum(),
            'applied_at' => ($appliedAt ?? new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        ]);
    }
}
