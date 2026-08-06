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

        return self::fromCanonical($canonical);
    }

    public static function fromRecordsAndDescriptors(array $records, array $descriptors): string
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
        foreach ($descriptors as $descriptor) {
            if (!$descriptor instanceof CoreMigrationDescriptor) {
                throw new \InvalidArgumentException('Migration state identity contains an invalid descriptor.');
            }
            $canonical[] = [
                'migration_id' => $descriptor->id(),
                'sequence_number' => $descriptor->sequence(),
                'target_webcore_version' => $descriptor->targetWebcoreVersion(),
                'target_schema_identity' => $descriptor->targetSchemaIdentity(),
                'migration_checksum' => $descriptor->checksum(),
            ];
        }

        return self::fromCanonical($canonical);
    }

    private static function fromCanonical(array $canonical): string
    {
        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
