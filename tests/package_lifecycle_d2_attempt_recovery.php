<?php

declare(strict_types=1);

use Copot\Core\{InstallationLock, InstallationMutex, LifecycleExecutionAttemptIdentity, LifecycleOperationRecord, LifecycleOperationStore, MaintenanceCoordinator, PreCaptureAbandonmentEvidenceStore, PreCaptureAbandonmentRequest, PreCaptureAbandonmentService};
use Copot\Core\BackupRecovery\{RecoveryArtifactRecord, RecoveryArtifactStore, RecoveryDomainIdentity, RecoveryIdentity, RecoveryLifecycleRecord, RecoveryLifecycleState, RecoveryManifest, RecoveryManifestCodec, RecoveryStorageRoot};

require dirname(__DIR__) . '/bootstrap/autoload.php';

$n = 0;
$assert = static function (bool $ok, string $message) use (&$n): void { if (!$ok) throw new RuntimeException($message); $n++; };
$hash = static fn (string $value): string => hash('sha256', $value);
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-d2-attempt-' . bin2hex(random_bytes(5));
$storage = $root . DIRECTORY_SEPARATOR . 'storage';
mkdir($storage, 0700, true);
$now = gmdate(DATE_ATOM);
$attemptA = LifecycleExecutionAttemptIdentity::create();
$attemptB = LifecycleExecutionAttemptIdentity::create();
$assert($attemptA !== $attemptB, 'Fresh execution attempts are not unique.');
$operationId = 'd2-' . bin2hex(random_bytes(3));
$staging = $root . DIRECTORY_SEPARATOR . 'staging';
$operation = new LifecycleOperationRecord($operationId, 'COMMITTED_SOURCE_AUTHORITY_RECONCILIATION', '0.13.0', 'release-d2', $hash('archive'), $staging, $hash('payload'), $hash('plan'), LifecycleOperationRecord::PREPARING, 0, null, $hash('migration'), null, $now, $now, '', null, null, null, null, null, null, null, null, $attemptA);
$serialized = $operation->toArray();
$assert(($serialized['execution_attempt_identity'] ?? null) === $attemptA, 'Attempt identity was not serialized immutably.');
$roundTrip = LifecycleOperationRecord::fromArray($serialized);
$assert($roundTrip->executionAttemptIdentity() === $attemptA, 'Attempt-aware operation did not round-trip.');
$legacy = $serialized; unset($legacy['execution_attempt_identity']);
$legacyRoundTrip = LifecycleOperationRecord::fromArray($legacy);
$assert($legacyRoundTrip->executionAttemptIdentity() === null, 'Historical operation format was not accepted explicitly.');

$ops = new LifecycleOperationStore($storage);
$ops->create($operation);
$next = $operation->advance(LifecycleOperationRecord::AWAITING_WU6, 0, null, null, 'test');
$ops->save($next);
$assert($ops->read()?->executionAttemptIdentity() === $attemptA, 'Save lost the immutable attempt identity.');
$terminal = $next->advance(LifecycleOperationRecord::PRE_CAPTURE_ABANDONED, 0, null, null, 'test terminal');
$ops->clear($terminal);
$assert($ops->read() === null, 'Terminal clear did not remove the operation after reread verification.');

$recoveryRoot = new RecoveryStorageRoot($root, $root . DIRECTORY_SEPARATOR . 'recovery', 'd2-project');
$artifactBytes = 'd2-artifact';
$artifact = new RecoveryArtifactRecord('lifecycle', $hash($artifactBytes), strlen($artifactBytes));
$manifest = new RecoveryManifest(new RecoveryIdentity('d2-recovery-' . bin2hex(random_bytes(3))), $operationId, 'package-d2', 'release-d2', $hash('archive'), $hash('plan'), [new RecoveryDomainIdentity('lifecycle', 'lifecycle', 'scope-d2', $artifact->artifactIdentity())], $hash('committed'), $hash('ledger'), $attemptA);
$codec = new RecoveryManifestCodec();
$decoded = $codec->decode($codec->encode($manifest, [$artifact]));
$assert($decoded['manifest']->executionAttemptIdentity() === $attemptA, 'Attempt-aware manifest codec round-trip failed.');
$artifacts = new RecoveryArtifactStore($recoveryRoot);
$artifacts->publish($manifest, [['record' => $artifact, 'bytes' => $artifactBytes]]);
$found = $artifacts->findManifestByOperationIdentity($operationId, $attemptA);
$assert($found['manifest']->identity() === $manifest->identity(), 'Attempt-aware manifest lookup failed.');
$crossAttemptRejected = false;
try { $artifacts->findManifestByOperationIdentity($operationId, $attemptB); } catch (Throwable) { $crossAttemptRejected = true; }
$assert($crossAttemptRejected, 'Cross-attempt manifest lookup was not rejected.');
$recoveryRecord = new RecoveryLifecycleRecord($manifest->recoveryIdentity(), $manifest->identity(), $operationId, RecoveryLifecycleState::READY, false, true, $manifest->recoveryIdentity()->value(), $manifest->identity(), $manifest->identity(), null, null, '', '', null, null, false, $attemptA);
$recoveryRoundTrip = RecoveryLifecycleRecord::fromArray($recoveryRecord->toArray());
$assert($recoveryRoundTrip->executionAttemptIdentity() === $attemptA, 'Attempt-aware recovery lifecycle record did not round-trip.');

$abandonRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-d2-abandon-' . bin2hex(random_bytes(4));
$abandonStorage = $abandonRoot . DIRECTORY_SEPARATOR . 'storage'; mkdir($abandonStorage, 0700, true);
$abandonOps = new LifecycleOperationStore($abandonStorage);
$abandonMaintenance = new MaintenanceCoordinator($abandonOps);
$abandonOperation = new LifecycleOperationRecord('d2-abandon-' . bin2hex(random_bytes(3)), 'COMMITTED_SOURCE_AUTHORITY_RECONCILIATION', '0.13.0', 'release-d2', $hash('archive-abandon'), $abandonRoot . DIRECTORY_SEPARATOR . 'missing-staging', $hash('payload-abandon'), $hash('plan-abandon'), LifecycleOperationRecord::PREPARING, 0, null, $hash('migration-abandon'), null, gmdate(DATE_ATOM), gmdate(DATE_ATOM), '', null, null, null, null, null, null, null, null, LifecycleExecutionAttemptIdentity::create());
$abandonOps->create($abandonOperation);
$abandonService = new PreCaptureAbandonmentService($abandonMaintenance, new InstallationMutex($abandonStorage), new PreCaptureAbandonmentEvidenceStore($abandonStorage));
$abandonRequest = new PreCaptureAbandonmentRequest($abandonOperation->operationId(), static fn (LifecycleOperationRecord $record): bool => $record->phase() === LifecycleOperationRecord::PREPARING && $record->fileCursor() === 0 && $record->recoveryIdentity() === null);
$abandoned = $abandonService->abandon($abandonRequest);
$assert($abandoned->phase() === LifecycleOperationRecord::PRE_CAPTURE_ABANDONED && $abandonOps->read() === null, 'Pre-capture abandonment did not terminalize and clear safely.');
$assert($abandonService->abandon($abandonRequest)->phase() === LifecycleOperationRecord::PRE_CAPTURE_ABANDONED, 'Pre-capture abandonment was not idempotent after clear.');

$faultRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-d2-fault-' . bin2hex(random_bytes(4));
$faultStorage = $faultRoot . DIRECTORY_SEPARATOR . 'storage'; mkdir($faultStorage, 0700, true);
$faultOps = new LifecycleOperationStore($faultStorage); $faultMaintenance = new MaintenanceCoordinator($faultOps);
$faultOperation = new LifecycleOperationRecord('d2-fault-' . bin2hex(random_bytes(3)), 'COMMITTED_SOURCE_AUTHORITY_RECONCILIATION', '0.13.0', 'release-d2', $hash('archive-fault'), $faultRoot . DIRECTORY_SEPARATOR . 'missing-staging', $hash('payload-fault'), $hash('plan-fault'), LifecycleOperationRecord::PREPARING, 0, null, $hash('migration-fault'), null, gmdate(DATE_ATOM), gmdate(DATE_ATOM), '', null, null, null, null, null, null, null, null, LifecycleExecutionAttemptIdentity::create());
$faultOps->create($faultOperation); $interrupted = false;
$faultService = new PreCaptureAbandonmentService($faultMaintenance, new InstallationMutex($faultStorage), new PreCaptureAbandonmentEvidenceStore($faultStorage));
$faultRequest = new PreCaptureAbandonmentRequest($faultOperation->operationId(), static fn (LifecycleOperationRecord $record): bool => true, static function (string $stage) use (&$interrupted): void { if ($stage === 'after_evidence' && !$interrupted) { $interrupted = true; throw new RuntimeException('injected'); } });
try { $faultService->abandon($faultRequest); } catch (RuntimeException) {}
$assert($interrupted && $faultOps->read()?->phase() === LifecycleOperationRecord::PREPARING, 'Fault injection did not preserve the pre-capture operation.');
$assert($faultService->abandon(new PreCaptureAbandonmentRequest($faultOperation->operationId(), static fn (LifecycleOperationRecord $record): bool => true))->phase() === LifecycleOperationRecord::PRE_CAPTURE_ABANDONED, 'Abandonment continuation did not converge after interruption.');
$terminalFaultOperation = new LifecycleOperationRecord('d2-terminal-fault-' . bin2hex(random_bytes(3)), 'COMMITTED_SOURCE_AUTHORITY_RECONCILIATION', '0.13.0', 'release-d2', $hash('archive-terminal-fault'), $faultRoot . DIRECTORY_SEPARATOR . 'missing-staging-2', $hash('payload-terminal-fault'), $hash('plan-terminal-fault'), LifecycleOperationRecord::PREPARING, 0, null, $hash('migration-terminal-fault'), null, gmdate(DATE_ATOM), gmdate(DATE_ATOM), '', null, null, null, null, null, null, null, null, LifecycleExecutionAttemptIdentity::create());
$faultOps->create($terminalFaultOperation); $terminalPersistInterrupted = false;
$terminalFaultRequest = new PreCaptureAbandonmentRequest($terminalFaultOperation->operationId(), static fn (LifecycleOperationRecord $record): bool => true, static function (string $stage) use (&$terminalPersistInterrupted): void { if ($stage === 'after_terminal_persist' && !$terminalPersistInterrupted) { $terminalPersistInterrupted = true; throw new RuntimeException('injected terminal interruption'); } });
try { $faultService->abandon($terminalFaultRequest); } catch (RuntimeException) {}
$assert($terminalPersistInterrupted && $faultOps->read()?->phase() === LifecycleOperationRecord::PRE_CAPTURE_ABANDONED, 'Terminal-persist fault did not preserve the terminal record.');
$assert($faultService->abandon(new PreCaptureAbandonmentRequest($terminalFaultOperation->operationId(), static fn (LifecycleOperationRecord $record): bool => true))->phase() === LifecycleOperationRecord::PRE_CAPTURE_ABANDONED && $faultOps->read() === null, 'Terminal-persist continuation did not clear idempotently.');

foreach ([$root, $abandonRoot, $faultRoot] as $path) {
    $remove = static function (string $item) use (&$remove): void { if (is_dir($item) && !is_link($item)) foreach (array_diff(scandir($item) ?: [], ['.', '..']) as $entry) $remove($item . DIRECTORY_SEPARATOR . $entry); if (is_file($item) || is_link($item)) @unlink($item); elseif (is_dir($item)) @rmdir($item); };
    $remove($path);
}
echo "package_lifecycle_d2_attempt_recovery: {$n} assertions passed\n";
