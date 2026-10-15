<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\RecoveryLifecycleRecord;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;

final class RestoredOperationEvidenceStore
{
    private string $root;

    public function __construct(string $storageRoot)
    {
        $this->root = rtrim($storageRoot, '/\\') . DIRECTORY_SEPARATOR . 'restored-not-committed';
        if (!is_dir($this->root) && !mkdir($this->root, 0700, true) && !is_dir($this->root)) throw new \RuntimeException('Restored-operation evidence directory is unavailable.');
        if (is_link($this->root) || !is_writable($this->root)) throw new \RuntimeException('Restored-operation evidence directory is unsafe.');
    }

    public function publish(LifecycleOperationRecord $operation, RecoveryLifecycleRecord $recovery, string $preLifecycleIdentity, string $preMigrationLedgerIdentity, string $reason, string $timestamp): void
    {
        if ($recovery->state() !== RecoveryLifecycleState::RESTORED || !$recovery->mutationStarted() || $recovery->operationIdentity() !== $operation->operationId()) throw new \RuntimeException('Verified RESTORED recovery evidence is required.');
        if ($operation->recoveryIdentity() !== $recovery->recoveryIdentity()->value() || $operation->recoveryManifestIdentity() !== $recovery->manifestIdentity()) throw new \RuntimeException('Restored-operation recovery identity does not match.');
        $body = [
            'operation_id' => $operation->operationId(),
            'classification' => $operation->classification(),
            'target_webcore_version' => $operation->targetWebcoreVersion(),
            'release_identity' => $operation->releaseIdentity(),
            'archive_sha256' => $operation->archiveSha256(),
            'payload_identity' => $operation->payloadIdentity(),
            'apply_plan_identity' => $operation->applyPlanIdentity(),
            'migration_plan_identity' => $operation->migrationPlanIdentity(),
            'file_cursor' => $operation->fileCursor(),
            'last_verified_path' => $operation->lastVerifiedPath(),
            'recovery_identity' => $recovery->recoveryIdentity()->value(),
            'recovery_manifest_identity' => $recovery->manifestIdentity(),
            'recovery_state' => $recovery->state(),
            'pre_operation_lifecycle_identity' => $preLifecycleIdentity,
            'pre_operation_migration_ledger_identity' => $preMigrationLedgerIdentity,
            'reason' => $reason,
            'timestamp' => $timestamp,
            'disposition' => LifecycleOperationRecord::RESTORED_NOT_COMMITTED,
        ];
        $bytes = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        $path = $this->path($operation->operationId());
        if (is_file($path)) { if (hash('sha256', (string) file_get_contents($path)) !== hash('sha256', $bytes)) throw new \RuntimeException('Conflicting restored-operation evidence already exists.'); return; }
        $tmp = $this->root . DIRECTORY_SEPARATOR . '.restored-' . bin2hex(random_bytes(8)) . '.tmp';
        $handle = @fopen($tmp, 'xb');
        if (!is_resource($handle)) throw new \RuntimeException('Restored-operation evidence could not be prepared.');
        try { if (@fwrite($handle, $bytes) !== strlen($bytes) || !@fflush($handle) || (function_exists('fsync') && !@fsync($handle))) throw new \RuntimeException('Restored-operation evidence could not be durably written.'); } finally { fclose($handle); }
        @chmod($tmp, 0600);
        if (!@rename($tmp, $path)) { @unlink($tmp); throw new \RuntimeException('Restored-operation evidence could not be published.'); }
        if ((string) @file_get_contents($path) !== $bytes) throw new \RuntimeException('Restored-operation evidence read-back failed.');
    }

    public function read(string $operationId): ?array
    {
        $path = $this->path($operationId); if (!is_file($path) || is_link($path) || !is_readable($path)) return null;
        $data = json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
        $required = ['operation_id','classification','target_webcore_version','release_identity','archive_sha256','payload_identity','apply_plan_identity','migration_plan_identity','file_cursor','last_verified_path','recovery_identity','recovery_manifest_identity','recovery_state','pre_operation_lifecycle_identity','pre_operation_migration_ledger_identity','reason','timestamp','disposition'];
        if (!is_array($data) || array_keys($data) !== $required || $data['operation_id'] !== $operationId || $data['disposition'] !== LifecycleOperationRecord::RESTORED_NOT_COMMITTED || $data['recovery_state'] !== RecoveryLifecycleState::RESTORED || !is_int($data['file_cursor']) || !is_string($data['timestamp']) || \DateTimeImmutable::createFromFormat(DATE_ATOM, $data['timestamp']) === false) {
            throw new \RuntimeException('Restored-operation evidence is invalid.');
        }
        return $data;
    }

    public function path(string $operationId): string { return $this->root . DIRECTORY_SEPARATOR . $operationId . '.json'; }
}
