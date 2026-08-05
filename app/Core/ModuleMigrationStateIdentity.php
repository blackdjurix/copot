<?php

namespace Copot\Core;

final class ModuleMigrationStateIdentity
{
    public static function from(string $module, string $baseline, array $records): string
    {
        $canonical = ['module' => $module, 'baseline' => $baseline, 'records' => []];
        foreach ($records as $record) {
            if (!$record instanceof ModuleMigrationLedgerRecord) throw new \InvalidArgumentException('Invalid Module migration record.');
            $canonical['records'][] = [
                'id' => $record->migrationId(), 'sequence' => $record->sequence(),
                'target_package_version' => $record->targetPackageVersion(),
                'target_schema_identity' => $record->targetSchemaIdentity(),
                'checksum' => $record->checksum(), 'executable_identity' => $record->executableIdentity(),
            ];
        }
        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
