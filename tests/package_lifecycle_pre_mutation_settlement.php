<?php

declare(strict_types=1);

use Copot\Core\{InstallationMutex, LifecycleOperationRecord, LifecycleOperationStore, MaintenanceCoordinator, PreMutationSettlementEvidenceStore, PreMutationSettlementRequest, PreMutationSettlementService};
use Copot\Core\BackupRecovery\{InstallationRecoveryMaintenance, RecoveryArtifactRecord, RecoveryArtifactStore, RecoveryDomainIdentity, RecoveryIdentity, RecoveryLifecycleCoordinator, RecoveryLifecycleRecord, RecoveryLifecycleState, RecoveryLifecycleStore, RecoveryManifest, RecoveryStorageRoot, UnavailableDatabaseQuiescence};

require dirname(__DIR__) . '/bootstrap/autoload.php';

$n = 0;
$assert = static function (bool $ok, string $message) use (&$n): void { if (!$ok) throw new RuntimeException($message); $n++; };
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-c2m-' . bin2hex(random_bytes(5));
$storage = $root . DIRECTORY_SEPARATOR . 'storage';
mkdir($storage, 0700, true);
$hash = static fn (string $v): string => hash('sha256', $v);
$now = gmdate(DATE_ATOM);
$operationId = 'c2m-' . bin2hex(random_bytes(3));
$recoveryIdentity = new RecoveryIdentity('c2m-recovery-' . bin2hex(random_bytes(3)));
$operationHash = $hash($operationId);
$planHash = $hash('plan');
$artifactBytes = 'c2m-artifact'; $artifactRecord = new RecoveryArtifactRecord('lifecycle', $hash($artifactBytes), strlen($artifactBytes));
$manifest = new RecoveryManifest($recoveryIdentity, $operationId, 'package-c2m', 'release-c2m', $operationHash, $planHash, [new RecoveryDomainIdentity('lifecycle', 'lifecycle', 'scope-c2m', $artifactRecord->artifactIdentity())], $hash('committed'), $hash('ledger'));
$manifestIdentity = $manifest->identity();
$operation = new LifecycleOperationRecord($operationId, 'update', '0.13.0', 'release-c2m', $operationHash, $root . DIRECTORY_SEPARATOR . 'staging', $hash('payload'), $planHash, LifecycleOperationRecord::PREPARING, 0, null, $hash('migration'), null, $now, $now, '', $recoveryIdentity->value(), $manifestIdentity, RecoveryLifecycleState::READY);
$ops = new LifecycleOperationStore($storage);
$maintenance = new MaintenanceCoordinator($ops);
$recoveryRoot = new RecoveryStorageRoot($root, $root . DIRECTORY_SEPARATOR . 'recovery', 'c2m-project');
$recoveryStore = new RecoveryLifecycleStore($recoveryRoot);
$artifacts = new RecoveryArtifactStore($recoveryRoot);
$artifacts->publish($manifest, [['record' => $artifactRecord, 'bytes' => $artifactBytes]]);
$recoveryStore->create(new RecoveryLifecycleRecord($recoveryIdentity, $manifestIdentity, $operationId, RecoveryLifecycleState::READY, false, true, $recoveryIdentity->value(), $manifestIdentity, $manifestIdentity));
$mutex = new InstallationMutex($storage);
$recoveryMaintenance = new InstallationRecoveryMaintenance($mutex);
$coordinator = new RecoveryLifecycleCoordinator($recoveryStore, $mutex, new UnavailableDatabaseQuiescence(), $recoveryMaintenance);
$coordinator->enterMaintenance($recoveryIdentity);
$ops->create($operation);
$request = new PreMutationSettlementRequest($operationId, $recoveryIdentity, $manifestIdentity, $operationHash, $planHash, $hash('policy'), $hash('target'), $hash('database'), $hash('journal'), static fn (LifecycleOperationRecord $record): bool => $record->phase() === LifecycleOperationRecord::PREPARING && $record->fileCursor() === 0 && $record->migrationOutcome() === null);
$service = new PreMutationSettlementService($maintenance, $recoveryStore, $artifacts, $coordinator, new PreMutationSettlementEvidenceStore($storage));
$settled = $service->settle($request);
$assert($settled->phase() === LifecycleOperationRecord::PRE_MUTATION_SETTLED, 'Successful settlement did not use the dedicated terminal disposition.');
$assert($ops->read() === null, 'Settlement did not clear the terminal operation.');
$assert(!$coordinator->maintenanceActive($recoveryIdentity), 'Settlement did not release the guard.');
$assert($recoveryStore->read($recoveryIdentity)->state() === RecoveryLifecycleState::READY, 'Settlement changed recovery state.');
$service->settle($request);
$assert($ops->read() === null, 'Idempotent settlement recreated the operation.');

$retryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-c2m-retry-' . bin2hex(random_bytes(4));
$retryStorage = $retryRoot . DIRECTORY_SEPARATOR . 'storage'; mkdir($retryStorage, 0700, true);
$retryOps = new LifecycleOperationStore($retryStorage); $retryMaintenance = new MaintenanceCoordinator($retryOps);
$retryIdentity = new RecoveryIdentity('c2m-retry-' . bin2hex(random_bytes(3)));
$retryArtifactBytes = 'c2m-retry-artifact'; $retryArtifactRecord = new RecoveryArtifactRecord('lifecycle', $hash($retryArtifactBytes), strlen($retryArtifactBytes));
$retryManifest = new RecoveryManifest($retryIdentity, 'c2m-retry-op', 'package-c2m', 'release-c2m', $operationHash, $planHash, [new RecoveryDomainIdentity('lifecycle', 'lifecycle', 'scope-c2m-retry', $retryArtifactRecord->artifactIdentity())], $hash('committed'), $hash('ledger'));
$retryRecoveryRoot = new RecoveryStorageRoot($retryRoot, $retryRoot . DIRECTORY_SEPARATOR . 'recovery', 'c2m-retry');
$retryRecoveryStore = new RecoveryLifecycleStore($retryRecoveryRoot); $retryArtifacts = new RecoveryArtifactStore($retryRecoveryRoot); $retryArtifacts->publish($retryManifest, [['record' => $retryArtifactRecord, 'bytes' => $retryArtifactBytes]]);
$retryRecoveryStore->create(new RecoveryLifecycleRecord($retryIdentity, $retryManifest->identity(), 'c2m-retry-op', RecoveryLifecycleState::READY, false, true, $retryIdentity->value(), $retryManifest->identity(), $retryManifest->identity()));
$retryMutex = new InstallationMutex($retryStorage); $retryGuard = new InstallationRecoveryMaintenance($retryMutex); $retryCoordinator = new RecoveryLifecycleCoordinator($retryRecoveryStore, $retryMutex, new UnavailableDatabaseQuiescence(), $retryGuard); $retryCoordinator->enterMaintenance($retryIdentity);
$retryOperation = new LifecycleOperationRecord('c2m-retry-op', 'update', '0.13.0', 'release-c2m', $operationHash, $retryRoot . DIRECTORY_SEPARATOR . 'staging', $hash('payload'), $planHash, LifecycleOperationRecord::PREPARING, 0, null, $hash('migration'), null, $now, $now, '', $retryIdentity->value(), $retryManifest->identity(), RecoveryLifecycleState::READY); $retryOps->create($retryOperation);
$interrupt = true;
$retryRequest = new PreMutationSettlementRequest('c2m-retry-op', $retryIdentity, $retryManifest->identity(), $operationHash, $planHash, $hash('policy'), $hash('target'), $hash('database'), $hash('journal'), static fn (LifecycleOperationRecord $record): bool => true, static function (string $stage) use (&$interrupt): void { if ($stage === 'after_evidence' && $interrupt) { $interrupt = false; throw new RuntimeException('injected interruption'); } });
$retryService = new PreMutationSettlementService($retryMaintenance, $retryRecoveryStore, $retryArtifacts, $retryCoordinator, new PreMutationSettlementEvidenceStore($retryStorage));
$interrupted = false; try { $retryService->settle($retryRequest); } catch (RuntimeException) { $interrupted = true; }
$assert($interrupted && $retryOps->read()?->phase() === LifecycleOperationRecord::PREPARING, 'Interrupted settlement did not preserve active operation.');
$retryService->settle($retryRequest); $assert($retryOps->read() === null && !$retryCoordinator->maintenanceActive($retryIdentity), 'Settlement retry did not converge safely.');

foreach ([$root, $retryRoot] as $path) {
    $remove = static function (string $p) use (&$remove): void { if (is_dir($p) && !is_link($p)) foreach (array_diff(scandir($p) ?: [], ['.', '..']) as $entry) $remove($p . DIRECTORY_SEPARATOR . $entry); if (is_file($p) || is_link($p)) @unlink($p); elseif (is_dir($p)) @rmdir($p); };
    $remove($path);
}
echo "package_lifecycle_pre_mutation_settlement: {$n} assertions passed\n";
