<?php

namespace Copot\Core;

final class CoreMigrationStateIdentity
{
    public static function fromRecords(array $records): string
    {
        $canonical = [];

        foreach ($records as $record) {
            if (!$record instanceof AppliedMigrationRecord) {
                throw new \InvalidArgumentException('Migration state identity contains an invalid record.');
            }

            $canonical[] = [
                'migration_id' => $record->migrationId(),
                'sequence_number' => $record->sequence(),
                'target_webcore_version' => $record->targetWebcoreVersion(),
                'target_schema_identity' => $record->targetSchemaIdentity(),
                'migration_checksum' => $record->checksum(),
            ];
        }

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
