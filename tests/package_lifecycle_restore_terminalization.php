<?php

declare(strict_types=1);

use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryLifecycleRecord;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\LifecycleOperationRecord;
use Copot\Core\LifecycleOperationStore;
use Copot\Core\MaintenanceCoordinator;
use Copot\Core\RestoredOperationEvidenceStore;
use Copot\Core\RestoredOperationTerminalizer;

require dirname(__DIR__) . '/bootstrap/autoload.php';

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-restore-terminalization-' . bin2hex(random_bytes(6));
mkdir($root, 0700, true);
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) { throw new RuntimeException($message); }
};

$remove = static function (string $path) use (&$remove): void {
    if (!is_dir($path) || is_link($path)) { @unlink($path); return; }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') { continue; }
        $remove($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
};

try {
    $operationId = 'restore-test-operation';
    $recoveryId = new RecoveryIdentity('webcore-' . bin2hex(random_bytes(16)));
    $manifestIdentity = hash('sha256', 'manifest');
    $hash = static fn (string $value): string => hash('sha256', $value);
    $now = gmdate(DATE_ATOM);
    $operation = new LifecycleOperationRecord(
        $operationId,
        'database_update',
        '0.13.0',
        'release-test',
        $hash('archive'),
        $root . DIRECTORY_SEPARATOR . 'staging',
        $hash('payload'),
        $hash('apply'),
        LifecycleOperationRecord::AWAITING_WU6,
        1,
        'app/Core/Example.php',
        $hash('migration-plan'),
        'completed',
        $now,
        $now,
        'health finalization failed',
        $recoveryId->value(),
        $manifestIdentity,
        RecoveryLifecycleState::READY
    );
    $store = new LifecycleOperationStore($root);
    $maintenance = new MaintenanceCoordinator($store);
    $maintenance->enter($operation);
    $recovery = new RecoveryLifecycleRecord(
        $recoveryId,
        $manifestIdentity,
        $operationId,
        RecoveryLifecycleState::RESTORED,
        true,
        true
    );
    $evidence = new RestoredOperationEvidenceStore($root);
    $result = (new RestoredOperationTerminalizer($maintenance, $evidence))->terminalize(
        $operationId,
        $recovery,
        $hash('pre-lifecycle'),
        $hash('pre-ledger'),
        'operator-authorized restore'
    );

    $assert($result->status() === LifecycleOperationRecord::RESTORED_NOT_COMMITTED, 'Terminal disposition was not returned.');
    $assert(!$result->accepted(), 'Restored disposition must remain non-success.');
    $record = $evidence->read($operationId);
    $assert(is_array($record) && $record['disposition'] === LifecycleOperationRecord::RESTORED_NOT_COMMITTED, 'Durable terminal evidence is missing.');
    $assert(($record['recovery_identity'] ?? null) === $recoveryId->value(), 'Terminal evidence recovery identity mismatch.');
    $assert($maintenance->record() === null, 'Active operation was not cleared after evidence publication.');
    $assert(is_file($evidence->path($operationId)), 'Terminal evidence path was not published.');
    echo "PASS: {$assertions} assertions\n";
} finally {
    $remove($root);
}
