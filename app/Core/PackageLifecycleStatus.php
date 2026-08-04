<?php

namespace Copot\Core;

final class PackageLifecycleStatus
{
    public static function describe(InstalledStateInspection $inspection, ?LifecycleOperationRecord $record, string $operationState): array
    {
        return [
            'accepted' => true,
            'installed_state' => $inspection->status(),
            'reason' => $inspection->reason(),
            'maintenance' => $record instanceof LifecycleOperationRecord && !$record->isTerminal() ? 'active' : 'inactive',
            'operation' => $record instanceof LifecycleOperationRecord ? [
                'state' => $operationState,
                'phase' => $record->phase(),
                'operation_id' => $record->operationId(),
                'classification' => $record->classification(),
            ] : null,
        ];
    }
}
