<?php

declare(strict_types=1);

use Copot\Core\BackupRecovery\DatabaseQuiescenceCapability;
use Copot\Core\BackupRecovery\DatabaseQuiescenceLease;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryLifecycleCoordinator;
use Copot\Core\BackupRecovery\RecoveryLifecycleException;
use Copot\Core\BackupRecovery\RecoveryLifecycleRecord;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;
use Copot\Core\BackupRecovery\RecoveryMaintenanceBoundary;
use Copot\Core\BackupRecovery\RecoveryMutationPermit;
use Copot\Core\BackupRecovery\RecoveryStorageRoot;
use Copot\Core\InstallationMutex;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void { $assertions++; if (!$condition) throw new RuntimeException($message); };
$remove = static function (string $path) use (&$remove): void { if (is_link($path) || is_file($path)) { @unlink($path); return; } if (is_dir($path)) { foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry); @rmdir($path); } };
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu6-' . bin2hex(random_bytes(6));
$project = $root . DIRECTORY_SEPARATOR . 'project'; $storage = $root . DIRECTORY_SEPARATOR . 'recovery'; $lock = $root . DIRECTORY_SEPARATOR . 'lock';
mkdir($project, 0700, true); mkdir($storage, 0700, true); mkdir($lock, 0700, true);

final class WU6Lease implements DatabaseQuiescenceLease { private bool $active = true; public function isActive(): bool { return $this->active; } public function release(): void { $this->active = false; } }
final class WU6Quiescence implements DatabaseQuiescenceCapability { public bool $available = true; public int $acquires = 0; public function isAvailable(): bool { return $this->available; } public function acquire(): ?DatabaseQuiescenceLease { $this->acquires++; return $this->available ? new WU6Lease() : null; } }
final class WU6Maintenance implements RecoveryMaintenanceBoundary { public bool $active = false; public function enter(RecoveryIdentity $id): bool { $this->active = true; return true; } public function isActive(RecoveryIdentity $id): bool { return $this->active; } public function leave(RecoveryIdentity $id): bool { $this->active = false; return true; } }

try {
    $identity = new RecoveryIdentity('wu6-' . bin2hex(random_bytes(4)));
    $manifest = str_repeat('a', 64);
    $record = new RecoveryLifecycleRecord($identity, $manifest, 'operation-wu6', RecoveryLifecycleState::CREATED);
    $store = new RecoveryLifecycleStore(new RecoveryStorageRoot($project, $storage, str_repeat('b', 64)));
    $store->create($record);
    $assert($store->read($identity)->state() === RecoveryLifecycleState::CREATED, 'Created lifecycle record did not round-trip.');
    foreach ([RecoveryLifecycleState::CAPTURING, RecoveryLifecycleState::CAPTURED, RecoveryLifecycleState::READY] as $state) $store->transition($identity, $state);
    $record = $store->read($identity)->withCaptureComplete(); $store->save($record);
    $coordinator = new RecoveryLifecycleCoordinator($store, new InstallationMutex($lock), $q = new WU6Quiescence(), $m = new WU6Maintenance());
    $coordinator->confirm($identity, $manifest, 'target-identity');
    try { $coordinator->authorizeMutation($identity, $manifest, 'wrong'); $assert(false, 'Mismatched confirmation was accepted.'); } catch (Throwable) { $assert(true, 'Mismatched confirmation rejected.'); }
    try { $coordinator->authorizeMutation($identity, $manifest, 'target-identity'); $assert(false, 'Mutation was authorized without maintenance.'); } catch (Throwable) { $assert(true, 'Missing maintenance rejected.'); }
    $coordinator->enterMaintenance($identity);
    $permit = $coordinator->authorizeMutation($identity, $manifest, 'target-identity');
    $assert($permit instanceof RecoveryMutationPermit && $permit->isValid(), 'Mutation permit was not returned after durable boundary.');
    $assert($store->read($identity)->mutationStarted(), 'Mutation boundary was not persisted.');
    $permit->release();
    $assert(!$permit->isValid(), 'Released mutation permit remained valid.');
    try { $store->transition($identity, RecoveryLifecycleState::CAPTURING); $assert(false, 'Illegal transition was accepted.'); } catch (Throwable) { $assert(true, 'Illegal transition rejected.'); }
    $assert(RecoveryLifecycleState::canTransition(RecoveryLifecycleState::READY, RecoveryLifecycleState::RESTORING), 'READY restore transition was not legal.');
    $assert(RecoveryLifecycleState::canTransition(RecoveryLifecycleState::RESTORED, RecoveryLifecycleState::CLEANUP_PENDING), 'RESTORED cleanup transition was not legal.');
    $assert(!RecoveryLifecycleState::canTransition(RecoveryLifecycleState::CLEANED, RecoveryLifecycleState::READY), 'CLEANED record could be reopened.');
    $restoreIdentity = new RecoveryIdentity('wu6-' . bin2hex(random_bytes(4)));
    $restoreRecord = new RecoveryLifecycleRecord($restoreIdentity, str_repeat('d', 64), 'operation-restore', RecoveryLifecycleState::READY, true, true, $restoreIdentity->value(), str_repeat('d', 64), 'target');
    $store->create($restoreRecord);
    $restoreCoordinator = new RecoveryLifecycleCoordinator($store, new InstallationMutex($lock), new WU6Quiescence(), $m);
    $restoreCoordinator->beginRestore($restoreIdentity);
    $restoreCoordinator->databaseRestoreAttempt($restoreIdentity, 'attempt-1', str_repeat('e', 64), str_repeat('f', 64), 'PREPARED', []);
    $assert($store->read($restoreIdentity)->restoreStage() === 'PREPARED', 'WU4 restore stage was not durably integrated.');
    $restoreCoordinator->beginVerification($restoreIdentity);
    $restoreCoordinator->markRestored($restoreIdentity);
    $restoreCoordinator->markCleanupPending($restoreIdentity, 'target');
    $restoreCoordinator->markCleaned($restoreIdentity);
    $assert($store->read($restoreIdentity)->state() === RecoveryLifecycleState::CLEANED, 'Successful restore cleanup did not reach CLEANED.');
    $forwardIdentity = new RecoveryIdentity('wu6-' . bin2hex(random_bytes(4)));
    $forward = new RecoveryLifecycleRecord($forwardIdentity, str_repeat('1', 64), 'operation-forward', RecoveryLifecycleState::READY, true, true, $forwardIdentity->value(), str_repeat('1', 64), 'target');
    $store->create($forward);
    $forwardCoordinator = new RecoveryLifecycleCoordinator($store, new InstallationMutex($lock), new WU6Quiescence(), $m);
    try { $forwardCoordinator->markReconciliationCleanupPending($forwardIdentity, str_repeat('1', 64), 'target'); $assert(false, 'Cleanup was allowed without verification.'); } catch (Throwable) { $assert(true, 'Cleanup without verification rejected.'); }
    $forwardCoordinator->recordPostReconciliationVerification($forwardIdentity, str_repeat('1', 64), 'target');
    $forwardCoordinator->markReconciliationCleanupPending($forwardIdentity, str_repeat('1', 64), 'target');
    $assert($store->read($forwardIdentity)->state() === RecoveryLifecycleState::CLEANUP_PENDING, 'Verified forward cleanup was not allowed.');
    $orchestratorIdentity = new RecoveryIdentity('wu6-' . bin2hex(random_bytes(4)));
    $orchestratorRecord = new RecoveryLifecycleRecord($orchestratorIdentity, str_repeat('2', 64), 'operation-orchestrator', RecoveryLifecycleState::CREATED);
    $orchestratorQuiescence = new WU6Quiescence();
    $orchestrator = new Copot\Core\BackupRecovery\RecoveryOrchestrator($store, new InstallationMutex($lock), $orchestratorQuiescence, $m);
    try { $orchestrator->captureAndAuthorize($orchestratorRecord, static function (): void {}, static function (): void {}, static function (): void {}, 'target', static function (): void {}, static function (): void {}); $assert(false, 'Orchestrator synthesized confirmation.'); } catch (Throwable) { $assert(true, 'Orchestrator required external confirmation.'); }
    $assert($orchestratorQuiescence->acquires === 0, 'Orchestrator acquired quiescence before confirmation.');
    $mismatchIdentity = new RecoveryIdentity('wu6-' . bin2hex(random_bytes(4)));
    $mismatchRecord = new RecoveryLifecycleRecord($mismatchIdentity, str_repeat('3', 64), 'operation-mismatch', RecoveryLifecycleState::READY, true, true, $mismatchIdentity->value(), str_repeat('4', 64), 'target');
    $store->create($mismatchRecord);
    $mismatchCoordinator = new RecoveryLifecycleCoordinator($store, new InstallationMutex($lock), new WU6Quiescence(), $m);
    try { $mismatchCoordinator->recordPostReconciliationVerification($mismatchIdentity, str_repeat('3', 64), 'target'); $assert(false, 'Mismatched manifest confirmation was accepted.'); } catch (Throwable) { $assert(true, 'Mismatched manifest confirmation rejected.'); }
    $successIdentity = new RecoveryIdentity('wu6-' . bin2hex(random_bytes(4)));
    $successManifest = str_repeat('5', 64);
    $successRecord = new RecoveryLifecycleRecord($successIdentity, $successManifest, 'operation-success', RecoveryLifecycleState::CREATED, false, false, $successIdentity->value(), $successManifest, 'target');
    $successMaintenance = new WU6Maintenance(); $successQuiescence = new WU6Quiescence();
    $successOrchestrator = new Copot\Core\BackupRecovery\RecoveryOrchestrator($store, new InstallationMutex($lock), $successQuiescence, $successMaintenance);
    $successOrchestrator->captureAndAuthorize($successRecord, static function (): void {}, static function (): void {}, static function (): void {}, 'target', static function (RecoveryMutationPermit $permit): void { if (!$permit->isValid()) throw new RuntimeException('Mutation permit was invalid.'); }, static function (): void {});
    $assert($store->read($successIdentity)->state() === RecoveryLifecycleState::CLEANED, 'Correct external confirmation did not complete guarded cleanup.');
    $path = $store->path($identity); $bytes = (string)file_get_contents($path); file_put_contents($path, str_replace('"mutation_started":true', '"mutation_started":"tampered"', $bytes));
    try { $store->read($identity); $assert(false, 'Malformed lifecycle state was accepted.'); } catch (RecoveryLifecycleException) { $assert(true, 'Malformed lifecycle state failed closed.'); }
    $unavailable = new WU6Quiescence(); $unavailable->available = false;
    $identity2 = new RecoveryIdentity('wu6-' . bin2hex(random_bytes(4))); $record2 = new RecoveryLifecycleRecord($identity2, str_repeat('c', 64), 'operation-wu6-2', RecoveryLifecycleState::READY, false, true, $identity2->value(), str_repeat('c', 64), 'target'); $store->create($record2);
    $blocked = new RecoveryLifecycleCoordinator($store, new InstallationMutex($lock), $unavailable, $m);
    try { $blocked->authorizeMutation($identity2, str_repeat('c', 64), 'target'); $assert(false, 'Unavailable database quiescence did not fail closed.'); } catch (Throwable) { $assert(true, 'Unavailable database quiescence failed closed.'); }
} finally { $remove($root); }

fwrite(STDOUT, "WU6 assertions: {$assertions}\n");
