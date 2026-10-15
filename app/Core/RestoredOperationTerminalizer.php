<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\RecoveryLifecycleRecord;

final class RestoredOperationTerminalizer
{
    public function __construct(private MaintenanceCoordinator $maintenance, private RestoredOperationEvidenceStore $evidence)
    {
    }

    public function terminalize(string $operationId, RecoveryLifecycleRecord $recovery, string $preLifecycleIdentity, string $preMigrationLedgerIdentity, string $reason): PackageLifecycleResult
    {
        $operation = $this->maintenance->record();
        if (!$operation instanceof LifecycleOperationRecord || $operation->operationId() !== $operationId) return new PackageLifecycleResult(false, 'rejected', 'Requested lifecycle operation is not active.', operationId: $operationId);
        try {
            if ($operation->phase() === LifecycleOperationRecord::RESTORED_NOT_COMMITTED) {
                $this->evidence->read($operationId);
                return new PackageLifecycleResult(false, LifecycleOperationRecord::RESTORED_NOT_COMMITTED, 'Operation was already terminalized after verified restoration.', operationId: $operationId);
            }
            $this->evidence->publish($operation, $recovery, $preLifecycleIdentity, $preMigrationLedgerIdentity, $reason, gmdate(DATE_ATOM));
            $verified = $this->evidence->read($operationId);
            if (!is_array($verified) || ($verified['recovery_identity'] ?? null) !== $recovery->recoveryIdentity()->value() || ($verified['recovery_manifest_identity'] ?? null) !== $recovery->manifestIdentity()) throw new \RuntimeException('Restored-operation evidence verification failed.');
            $terminal = $operation->advance(LifecycleOperationRecord::RESTORED_NOT_COMMITTED, $operation->fileCursor(), $operation->lastVerifiedPath(), $operation->migrationOutcome(), $reason);
            $this->maintenance->clear($terminal);
            return new PackageLifecycleResult(false, LifecycleOperationRecord::RESTORED_NOT_COMMITTED, 'Operation terminalized after verified canonical restoration.', operationId: $operationId);
        } catch (\Throwable $exception) { return new PackageLifecycleResult(false, 'blocked', $exception->getMessage(), operationId: $operationId); }
    }
}
