<?php

use Copot\Core\BackupRecovery\ApplicationWriteProtectionEvidence;
use Copot\Core\BackupRecovery\InstallationScopedMaintenanceGuard;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryLifecycleCoordinator;
use Copot\Core\BackupRecovery\RecoveryLifecycleRecord;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;
use Copot\Core\BackupRecovery\RecoveryStorageRoot;
use Copot\Core\BackupRecovery\NormalWebcoreRecoverySession;
use Copot\Core\BackupRecovery\UnavailableDatabaseQuiescence;
use Copot\Core\InstallationMutex;

$base = dirname(__DIR__);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-filesystem-only-' . bin2hex(random_bytes(4));
$project = $root . DIRECTORY_SEPARATOR . 'project';
$recovery = $root . DIRECTORY_SEPARATOR . 'recovery';
mkdir($project, 0700, true);
mkdir($recovery, 0700, true);

$identity = new RecoveryIdentity('filesystem-only-test');
$store = new RecoveryLifecycleStore(new RecoveryStorageRoot($project, $recovery, hash('sha256', $project)));
$guard = new InstallationScopedMaintenanceGuard(new InstallationMutex($project), $project);
$coordinator = new RecoveryLifecycleCoordinator($store, new InstallationMutex($project), new UnavailableDatabaseQuiescence(), $guard);
$record = new RecoveryLifecycleRecord($identity, hash('sha256', 'manifest'), 'operation-filesystem-only', RecoveryLifecycleState::CREATED);
$coordinator->create($record);
$coordinator->transition($identity, RecoveryLifecycleState::CAPTURING);
$coordinator->transition($identity, RecoveryLifecycleState::CAPTURED);
$coordinator->recordCaptureComplete($identity);
$coordinator->transition($identity, RecoveryLifecycleState::READY);
$coordinator->confirm($identity, hash('sha256', 'manifest'), hash('sha256', 'manifest'));

$application = $guard->produceFilesystemEvidence($identity);
$assert($application->scope() === null, 'Filesystem-only evidence unexpectedly carried a database scope.');
$coordinator->enterFilesystemOnlyMaintenance($identity, $application);
$permit = $coordinator->authorizeMutation($identity, hash('sha256', 'manifest'), hash('sha256', 'manifest'), null, null, $application, true);
$assert($permit->isValid(), 'Filesystem-only mutation permit was not granted by the active Guard.');
$assert($store->read($identity)->mutationStarted(), 'Filesystem-only mutation boundary was not durably recorded.');
$assert($guard->admit('http') === null, 'HTTP writer admission was not blocked by the filesystem-only Guard.');
$permit->release();
$coordinator->leaveMaintenance($identity);
$after = $guard->admit('http');
$assert($after !== null, 'HTTP writer admission was not released after filesystem-only completion.');
$after->release();

$retryIdentity = new RecoveryIdentity('filesystem-only-session-regression');
$retryManifest = hash('sha256', 'session-regression-manifest');
$coordinator->create(new RecoveryLifecycleRecord($retryIdentity, $retryManifest, 'operation-session-regression', RecoveryLifecycleState::CREATED));
$coordinator->transition($retryIdentity, RecoveryLifecycleState::CAPTURING);
$coordinator->transition($retryIdentity, RecoveryLifecycleState::CAPTURED);
$coordinator->recordCaptureComplete($retryIdentity);
$coordinator->transition($retryIdentity, RecoveryLifecycleState::READY);
$coordinator->confirm($retryIdentity, $retryManifest, $retryManifest);
$retryEvidence = $guard->produceFilesystemEvidence($retryIdentity);
$coordinator->enterFilesystemOnlyMaintenance($retryIdentity, $retryEvidence);
$retrySession = new NormalWebcoreRecoverySession(
    $coordinator,
    $retryIdentity,
    $retryManifest,
    $store->read($retryIdentity),
    null,
    null,
    $retryEvidence,
    true,
    static function (): void {},
    static function (): void {}
);
$retrySession->authorizeMutation();
$failedSession = $retrySession->failMutation('injected post-boundary failure');
$assert($failedSession->state() === RecoveryLifecycleState::RESTORE_REQUIRED, 'Post-boundary failure was misclassified as pre-mutation.');
$restoredSession = $retrySession->restoreMutation();
$assert($restoredSession->state() === RecoveryLifecycleState::RESTORED, 'Post-boundary session did not reach RESTORED.');

$traceLines = [];
$trace = new \Copot\Core\LifecycleTrace(null, 'filesystem-only-test', static function (string $line) use (&$traceLines): void { $traceLines[] = $line; });
$runId = $trace->beginRun();
$trace->record($runId, 'operation-filesystem-only', 'mutation', 'apply', 'SUCCESS', 'password=should-redact');
$assert(count($traceLines) === 2, 'Temporary LifecycleTrace did not emit the expected events.');
$assert(!str_contains($traceLines[1], 'should-redact'), 'Temporary LifecycleTrace leaked sensitive summary content.');

$source = (string) file_get_contents($base . '/app/Core/BackupRecovery/NormalWebcoreRecoveryCaptureService.php');
$assert(str_contains($source, 'if (!$filesystemOnly) {'), 'Filesystem-only capture branch is missing.');
$assert(str_contains($source, 'database.webcore'), 'Legacy database domain support was unexpectedly removed from the shared service.');
$assert(str_contains($source, 'if (isset($records[\'database.webcore\']))'), 'Filesystem-only restore does not reject a database domain.');

$remove = static function (string $path) use (&$remove): void {
    if (!is_dir($path)) { @unlink($path); return; }
    foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) $remove($path . DIRECTORY_SEPARATOR . $entry);
    @rmdir($path);
};
$remove($root);
echo "Filesystem-only Guard assertions: {$assertions}\n";
