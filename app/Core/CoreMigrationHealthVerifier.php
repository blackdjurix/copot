<?php

namespace Copot\Core;

use PDO;

final class CoreMigrationHealthVerifier
{
    public function verify(PDO $connection, CoreMigrationRegistry $registry, ?string $expectedIdentity = null): HealthGateMatrix
    {
        try {
            $records = (new CoreMigrationLedger())->records($connection);
            $descriptors = $registry->migrations();
            if (count($records) > count($descriptors)) {
                return new HealthGateMatrix([HealthGateResult::fail('migration-ledger', 'Applied migration history is longer than the known registry.')]);
            }
            foreach ($records as $index => $record) {
                $descriptor = $descriptors[$index] ?? null;
                if (!$descriptor instanceof CoreMigrationDescriptor
                    || $record->migrationId() !== $descriptor->id()
                    || $record->sequence() !== $descriptor->sequence()
                    || $record->targetWebcoreVersion() !== $descriptor->targetWebcoreVersion()
                    || $record->targetSchemaIdentity() !== $descriptor->targetSchemaIdentity()
                    || $record->checksum() !== $descriptor->checksum()) {
                    return new HealthGateMatrix([HealthGateResult::fail('migration-ledger', 'Applied migration history is not a known checksum-valid registry prefix.')]);
                }
            }
            $identity = CoreMigrationStateIdentity::fromRecords($records);
            if ($expectedIdentity !== null && $identity !== $expectedIdentity) {
                return new HealthGateMatrix([HealthGateResult::fail('migration-identity', 'Verified migration identity does not match the expected final state.')]);
            }
            return new HealthGateMatrix([HealthGateResult::pass('migration-ledger'), HealthGateResult::pass('migration-identity')]);
        } catch (\Throwable $exception) {
            return new HealthGateMatrix([HealthGateResult::fail('migration-ledger', $exception->getMessage())]);
        }
    }

    public function identity(PDO $connection): string
    {
        return CoreMigrationStateIdentity::fromRecords((new CoreMigrationLedger())->records($connection));
    }
}
