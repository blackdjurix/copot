<?php

declare(strict_types=1);

use Copot\Core\{LifecycleOperationRecord, LifecycleOperationStore, MaintenanceCoordinator, PreMutationSettlementEvidenceStore, PreMutationSettlementRequest, PreMutationSettlementService};
use Copot\Core\BackupRecovery\{ApplicationWriteProtectionEvidence, DatabaseScope, InstallationScopedMaintenanceGuard, RecoveryArtifactRecord, RecoveryArtifactStore, RecoveryDomainIdentity, RecoveryIdentity, RecoveryLifecycleCoordinator, RecoveryLifecycleRecord, RecoveryLifecycleState, RecoveryLifecycleStore, RecoveryManifest, RecoveryStorageRoot, ScopedDatabaseConsistencyEvidence, UnavailableDatabaseQuiescence};
use Copot\Core\InstallationMutex;

require dirname(__DIR__) . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    if (!$condition) throw new RuntimeException($message);
    $assertions++;
};
$hash = static fn (string $value): string => hash('sha256', $value);
$remove = static function (string $path) use (&$remove): void {
    if (is_dir($path) && !is_link($path)) foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) $remove($path . DIRECTORY_SEPARATOR . $entry);
    if (is_file($path) || is_link($path)) @unlink($path); elseif (is_dir($path)) @rmdir($path);
};

/** @return array<string,mixed> */
$fixture = static function () use ($hash): array {
    $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-c3a-' . bin2hex(random_bytes(5));
    $storage = $root . DIRECTORY_SEPARATOR . 'storage'; mkdir($storage, 0700, true);
    $identity = new RecoveryIdentity('c3a-recovery-' . bin2hex(random_bytes(3)));
    $operationId = 'c3a-operation-' . bin2hex(random_bytes(3));
    $package = $hash('package-' . $operationId); $plan = $hash('plan-' . $operationId);
    $artifactBytes = 'c3a-artifact'; $artifact = new RecoveryArtifactRecord('lifecycle', $hash($artifactBytes), strlen($artifactBytes));
    $manifest = new RecoveryManifest($identity, $operationId, 'package-c3a', 'release-c3a', $package, $plan, [new RecoveryDomainIdentity('lifecycle', 'lifecycle', 'scope-c3a', $artifact->artifactIdentity())], $hash('committed'), $hash('ledger'));
    $recoveryRoot = new RecoveryStorageRoot($root, $root . DIRECTORY_SEPARATOR . 'recovery', 'c3a-project');
    $recoveryStore = new RecoveryLifecycleStore($recoveryRoot); $artifacts = new RecoveryArtifactStore($recoveryRoot);
    $artifacts->publish($manifest, [['record' => $artifact, 'bytes' => $artifactBytes]]);
    $recoveryStore->create(new RecoveryLifecycleRecord($identity, $manifest->identity(), $operationId, RecoveryLifecycleState::READY, false, true, $identity->value(), $manifest->identity(), $manifest->identity()));
    $now = gmdate(DATE_ATOM);
    $operation = new LifecycleOperationRecord($operationId, 'update', '0.13.0', 'release-c3a', $package, $root . DIRECTORY_SEPARATOR . 'staging', $hash('payload'), $plan, LifecycleOperationRecord::PREPARING, 0, null, $hash('migration'), null, $now, $now, '', $identity->value(), $manifest->identity(), RecoveryLifecycleState::READY);
    $operations = new LifecycleOperationStore($storage); $operations->create($operation);
    $scope = new DatabaseScope('inst_c3a', 'copotc3a', '', ['copotc3a_users']);
    $application = new ApplicationWriteProtectionEvidence($scope, $identity, $hash('writer'), $hash('drain'), ['background', 'cli', 'http', 'scheduled'], true, true, time() - 1, time() + 300);
    $database = new ScopedDatabaseConsistencyEvidence($scope, $identity, $application->writerExclusionIdentity(), $hash('database'), $application->capturedAt(), $application->validUntil());
    $mutex = new InstallationMutex($storage);
    $guard = new InstallationScopedMaintenanceGuard($mutex, $storage);
    $coordinator = new RecoveryLifecycleCoordinator($recoveryStore, $mutex, new UnavailableDatabaseQuiescence(), $guard);
    $coordinator->enterScopedMaintenance($identity, $application, $database);
    $request = new PreMutationSettlementRequest($operationId, $identity, $manifest->identity(), $package, $plan, $hash('policy'), $hash('target'), $hash('database'), $hash('journal'), static fn (LifecycleOperationRecord $record): bool => $record->phase() === LifecycleOperationRecord::PREPARING && $record->fileCursor() === 0 && $record->lastVerifiedPath() === null && $record->migrationOutcome() === null);
    return compact('root', 'storage', 'identity', 'operationId', 'manifest', 'operations', 'recoveryStore', 'artifacts', 'mutex', 'guard', 'coordinator', 'request', 'application', 'database');
};

$fresh = static function (array $state): array {
    $mutex = new InstallationMutex($state['storage']);
    $guard = new InstallationScopedMaintenanceGuard($mutex, $state['storage']);
    $coordinator = new RecoveryLifecycleCoordinator($state['recoveryStore'], $mutex, new UnavailableDatabaseQuiescence(), $guard);
    $service = new PreMutationSettlementService(new MaintenanceCoordinator($state['operations']), $state['recoveryStore'], $state['artifacts'], $coordinator, new PreMutationSettlementEvidenceStore($state['storage']));
    return compact('mutex', 'guard', 'coordinator', 'service');
};

// Simulate the process which captured recovery ending: persistent Guard survives, mutex does not.
$state = $fixture();
$state['guard'] = null; $state['coordinator'] = null; gc_collect_cycles();
$freshProcess = $fresh($state);
$assert(!$freshProcess['coordinator']->maintenanceActive($state['identity']), 'Fresh process unexpectedly inherited process-local Guard ownership.');
$settled = $freshProcess['service']->settle($state['request']);
$assert($settled->phase() === LifecycleOperationRecord::PRE_MUTATION_SETTLED, 'Fresh-process settlement did not persist the dedicated terminal disposition.');
$assert($state['operations']->read() === null, 'Fresh-process settlement did not clear the active operation.');
$assert(!$freshProcess['coordinator']->maintenanceActive($state['identity']), 'Fresh-process settlement retained process-local Guard ownership.');
$assert(!$freshProcess['coordinator']->durableScopedMaintenanceActive($state['identity']), 'Fresh-process settlement did not release durable Guard protection.');
$assert($state['recoveryStore']->read($state['identity'])->state() === RecoveryLifecycleState::READY, 'Fresh-process settlement altered recovery state.');
$freshProcess['service']->settle($state['request']);
$assert($state['operations']->read() === null, 'Idempotent fresh-process settlement recreated the operation.');

// A mutex held by another executor must block re-adoption without changing durable Guard state.
$contention = $fixture(); $contention['guard'] = null; $contention['coordinator'] = null; gc_collect_cycles();
$held = (new InstallationMutex($contention['storage']))->acquire();
$blocked = $fresh($contention); $failed = false;
try { $blocked['service']->settle($contention['request']); } catch (Throwable) { $failed = true; }
$assert($failed, 'Lock contention did not block fresh-process settlement.');
$assert($blocked['coordinator']->durableScopedMaintenanceActive($contention['identity']), 'Lock contention changed durable Guard state.');
$assert($contention['operations']->read()?->phase() === LifecycleOperationRecord::PREPARING, 'Lock contention changed the active operation.');
$held?->release();

// A durable state with in-flight writers is never eligible for ownership reconstruction.
$writers = $fixture(); $writers['guard'] = null; $writers['coordinator'] = null; gc_collect_cycles();
$guardPath = $writers['storage'] . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'application-write-guard.json';
$guardState = json_decode((string) file_get_contents($guardPath), true); $guardState['in_flight'] = 1; file_put_contents($guardPath, json_encode($guardState, JSON_UNESCAPED_SLASHES));
$writerProcess = $fresh($writers); $failed = false;
try { $writerProcess['service']->settle($writers['request']); } catch (Throwable) { $failed = true; }
$assert($failed, 'In-flight writer state did not block fresh-process settlement.');
$assert($writers['operations']->read()?->phase() === LifecycleOperationRecord::PREPARING, 'In-flight writer rejection changed the active operation.');

// A foreign durable Guard identity cannot be adopted for the bound recovery.
$foreignGuard = $fixture(); $foreignGuard['guard'] = null; $foreignGuard['coordinator'] = null; gc_collect_cycles();
$guardPath = $foreignGuard['storage'] . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'application-write-guard.json';
$guardState = json_decode((string) file_get_contents($guardPath), true); $guardState['identity'] = 'c3a-foreign-' . bin2hex(random_bytes(3)); file_put_contents($guardPath, json_encode($guardState, JSON_UNESCAPED_SLASHES));
$foreignProcess = $fresh($foreignGuard); $failed = false;
try { $foreignProcess['service']->settle($foreignGuard['request']); } catch (Throwable) { $failed = true; }
$assert($failed, 'Foreign durable Guard identity did not block settlement.');
$assert($foreignGuard['operations']->read()?->phase() === LifecycleOperationRecord::PREPARING, 'Foreign durable Guard identity changed the active operation.');

// Immutable identity mismatch is rejected before ownership adoption and preserves the original Guard.
$mismatch = $fixture(); $mismatch['guard'] = null; $mismatch['coordinator'] = null; gc_collect_cycles();
$wrong = new PreMutationSettlementRequest($mismatch['operationId'], new RecoveryIdentity('c3a-wrong-' . bin2hex(random_bytes(3))), $mismatch['manifest']->identity(), str_repeat('a', 64), str_repeat('b', 64), str_repeat('c', 64), str_repeat('d', 64), str_repeat('e', 64), str_repeat('f', 64), static fn (LifecycleOperationRecord $record): bool => true);
$mismatchProcess = $fresh($mismatch); $failed = false;
try { $mismatchProcess['service']->settle($wrong); } catch (Throwable) { $failed = true; }
$assert($failed, 'Recovery identity mismatch did not block fresh-process settlement.');
$assert($mismatchProcess['coordinator']->durableScopedMaintenanceActive($mismatch['identity']), 'Recovery identity mismatch changed durable Guard state.');

// Merely registering typed evidence is not ownership; the fresh process still
// needs an acquired mutex before it may settle or release the durable Guard.
$evidenceOnly = $fixture(); $evidenceOnly['guard'] = null; $evidenceOnly['coordinator'] = null; gc_collect_cycles();
$evidenceProcess = $fresh($evidenceOnly);
$evidenceProcess['coordinator']->adoptScopedMaintenance($evidenceOnly['identity'], $evidenceOnly['application'], $evidenceOnly['database']);
$assert(!$evidenceProcess['coordinator']->maintenanceActive($evidenceOnly['identity']), 'Typed evidence adoption incorrectly established mutex ownership.');
$assert($evidenceProcess['coordinator']->durableScopedMaintenanceActive($evidenceOnly['identity']), 'Typed evidence adoption lost durable Guard evidence.');

// An invalid operation cursor must fail before the fresh process adopts Guard ownership.
$invalidOperation = $fixture(); $invalidOperation['guard'] = null; $invalidOperation['coordinator'] = null; gc_collect_cycles();
$operationPath = $invalidOperation['storage'] . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'active-operation.json';
$operationState = json_decode((string) file_get_contents($operationPath), true); $operationState['file_cursor'] = 1; file_put_contents($operationPath, json_encode($operationState, JSON_UNESCAPED_SLASHES));
$invalidProcess = $fresh($invalidOperation); $failed = false;
try { $invalidProcess['service']->settle($invalidOperation['request']); } catch (Throwable) { $failed = true; }
$assert($failed, 'Nonzero mutation cursor did not block settlement.');
$assert($invalidProcess['coordinator']->durableScopedMaintenanceActive($invalidOperation['identity']), 'Nonzero mutation cursor changed durable Guard state.');

// Recovery that has crossed the durable mutation boundary is never settled.
$mutationStarted = $fixture(); $mutationStarted['guard'] = null; $mutationStarted['coordinator'] = null; gc_collect_cycles();
$mutationStarted['recoveryStore']->markMutationStarting($mutationStarted['identity']);
$mutationProcess = $fresh($mutationStarted); $failed = false;
try { $mutationProcess['service']->settle($mutationStarted['request']); } catch (Throwable) { $failed = true; }
$assert($failed, 'Mutation-started recovery did not block settlement.');
$assert($mutationProcess['coordinator']->durableScopedMaintenanceActive($mutationStarted['identity']), 'Mutation-started recovery changed durable Guard state.');

// Every durable settlement boundary may be interrupted; fresh-process retry is
// permitted only with the same request/evidence and must converge once.
$interruptions = [];
foreach (['after_evidence', 'after_terminal_persist', 'after_clear_before_guard_release'] as $faultStage) {
    $interrupted = $fixture(); $interrupted['guard'] = null; $interrupted['coordinator'] = null; gc_collect_cycles();
    $once = true;
    $interruptRequest = new PreMutationSettlementRequest($interrupted['operationId'], $interrupted['identity'], $interrupted['manifest']->identity(), $interrupted['operations']->read()->archiveSha256(), $interrupted['operations']->read()->applyPlanIdentity(), hash('sha256', 'policy'), hash('sha256', 'target'), hash('sha256', 'database'), hash('sha256', 'journal'), static fn (LifecycleOperationRecord $record): bool => $record->phase() === LifecycleOperationRecord::PREPARING, static function (string $stage) use (&$once, $faultStage): void { if ($stage === $faultStage && $once) { $once = false; throw new RuntimeException('injected interruption'); } });
    $interruptedProcess = $fresh($interrupted); $failed = false;
    try { $interruptedProcess['service']->settle($interruptRequest); } catch (Throwable) { $failed = true; }
    $assert($failed, "Settlement interruption at {$faultStage} did not stop execution.");
    $assert($interruptedProcess['coordinator']->durableScopedMaintenanceActive($interrupted['identity']), "Settlement interruption at {$faultStage} released durable Guard.");
    $interruptedProcess = null; gc_collect_cycles();
    $resumed = $fresh($interrupted); $resumed['service']->settle($interruptRequest);
    $assert($interrupted['operations']->read() === null, "Settlement continuation after {$faultStage} did not clear the operation.");
    $assert(!$resumed['coordinator']->durableScopedMaintenanceActive($interrupted['identity']), "Settlement continuation after {$faultStage} did not release durable Guard.");
    $interruptions[] = $interrupted;
}

foreach ([$state, $contention, $writers, $foreignGuard, $mismatch, $evidenceOnly, $invalidOperation, $mutationStarted, ...$interruptions] as $item) $remove($item['root']);
echo "package_lifecycle_pre_mutation_settlement_fresh_process: {$assertions} assertions passed\n";
