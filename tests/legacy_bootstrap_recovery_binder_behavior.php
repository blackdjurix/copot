<?php

/* Reuse the accepted isolated bootstrap fixture to obtain a real handoff. */
require __DIR__ . '/legacy_to_v3_bootstrap_wu2.php';

use Copot\Core\BackupRecovery\ApplicationWriteProtectionEvidence;
use Copot\Core\BackupRecovery\DatabaseCaptureContext;
use Copot\Core\BackupRecovery\DatabaseRecoveryArtifact;
use Copot\Core\BackupRecovery\DatabaseRecoveryProvider;
use Copot\Core\BackupRecovery\DatabaseRestoreContext;
use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\BackupRecovery\DatabaseVerificationResult;
use Copot\Core\BackupRecovery\FilesystemRecoveryActionLinkageCodec;
use Copot\Core\BackupRecovery\FilesystemRecoveryDomain;
use Copot\Core\BackupRecovery\FilesystemRecoveryPathGuard;
use Copot\Core\BackupRecovery\FilesystemRecoveryPlan;
use Copot\Core\BackupRecovery\InstallationScopedMaintenanceGuard;
use Copot\Core\BackupRecovery\InstalledLockRecoveryDomain;
use Copot\Core\BackupRecovery\LifecycleRecoveryDomain;
use Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureRequest;
use Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureService;
use Copot\Core\BackupRecovery\RecoveryArtifactRecord;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\RecoveryLifecycleCoordinator;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;
use Copot\Core\BackupRecovery\RecoveryRootResolver;
use Copot\Core\BackupRecovery\ScopedDatabaseConsistencyEvidence;
use Copot\Core\BackupRecovery\UnavailableDatabaseQuiescence;
use Copot\Core\BackupRecovery\RecoveryStoragePathPolicy;
use Copot\Core\LegacyBootstrapRecoveryBinder;
use Copot\Core\LegacyBootstrapRecoveryBindingResult;
use Copot\Core\LifecycleOperationRecord;
use Copot\Core\LifecycleOperationStore;
use Copot\Core\V3SourceRelativeAction;
use Copot\Core\V3SourceRelativePlan;

$behaviorAssertions = 0;
$behaviorAssert = static function (bool $condition, string $message) use (&$behaviorAssertions): void {
    $behaviorAssertions++;
    if (!$condition) throw new RuntimeException($message);
};

$behaviorRootPath = $tmp . DIRECTORY_SEPARATOR . 'binder-recovery-root';
mkdir($behaviorRootPath, 0700, true);
$recoveryRoot = (new RecoveryRootResolver($tmp . '/app', $behaviorRootPath, [$tmp . '/app/storage'], [$tmp . '/public']))->resolve();
$artifactStore = new RecoveryArtifactStore($recoveryRoot);
$recoveryStore = new RecoveryLifecycleStore($recoveryRoot);
$pathGuard = new FilesystemRecoveryPathGuard(new Copot\Core\LiveTreePathGuard($tmp . '/app'));
$mutex = new Copot\Core\InstallationMutex($tmp . '/app/storage');
$maintenance = new InstallationScopedMaintenanceGuard($mutex, $tmp . '/app/storage');
$coordinator = new RecoveryLifecycleCoordinator($recoveryStore, $mutex, new UnavailableDatabaseQuiescence(), $maintenance);
$filesystem = new FilesystemRecoveryDomain($pathGuard, $artifactStore);
$lifecycle = new LifecycleRecoveryDomain(new Copot\Core\CommittedLifecycleStateStore($tmp . '/app/storage'), $pathGuard);
$installedLock = new InstalledLockRecoveryDomain(new Copot\Core\InstallationState($tmp . '/app/storage'), $pathGuard);

$databaseProvider = new class implements DatabaseRecoveryProvider {
    public function capture(DatabaseCaptureContext $context): DatabaseRecoveryArtifact
    {
        $bytes = 'isolated-database-snapshot';
        return new DatabaseRecoveryArtifact(new RecoveryArtifactRecord('database.webcore', hash('sha256', $bytes), strlen($bytes)), $bytes, $context->databaseIdentity(), hash('sha256', 'schema'), hash('sha256', 'data'), hash('sha256', 'ledger'), $context->scope());
    }
    public function verifyCaptured(DatabaseRecoveryArtifact $artifact): DatabaseVerificationResult { return DatabaseVerificationResult::valid(['artifact' => $artifact->record()->artifactIdentity()]); }
    public function restore(DatabaseRecoveryArtifact $artifact, DatabaseRestoreContext $context): void {}
    public function verifyRestored(DatabaseRecoveryArtifact $artifact, DatabaseRestoreContext $context): DatabaseVerificationResult { return DatabaseVerificationResult::valid(['artifact' => $artifact->record()->artifactIdentity()]); }
};
$pdo = static fn (): PDO => (new ReflectionClass(PDO::class))->newInstanceWithoutConstructor();
$capture = new NormalWebcoreRecoveryCaptureService($coordinator, $recoveryStore, $artifactStore, $filesystem, $lifecycle, $installedLock, $databaseProvider, new UnavailableDatabaseQuiescence(), $pdo, $pdo);
$operationStore = new LifecycleOperationStore($tmp . '/app/storage');
$recoveryIdentity = NormalWebcoreRecoveryCaptureService::forOperation($handoff->artifact()->operationIdentity(), $handoff->applyPlan()->identity());
$now = time();
$scope = new DatabaseScope($handoff->artifact()->installationIdentity(), 'test', 'site_a', ['site_a_core_migration_history', 'site_a_users']);
$application = new ApplicationWriteProtectionEvidence($scope, $recoveryIdentity, str_repeat('1', 64), str_repeat('2', 64), ['http', 'cli', 'scheduled', 'background'], true, true, $now - 1, $now + 300);
$database = new ScopedDatabaseConsistencyEvidence($scope, $recoveryIdentity, str_repeat('3', 64), str_repeat('4', 64), $now - 1, $now + 300);
$action = new V3SourceRelativeAction(V3SourceRelativeAction::REPLACE, 'APP_ROOT', 'future.php', 5, hash('sha256', 'later'), 'APP_ROOT', 'future.php', 'replaced');
$orderedPlan = new V3SourceRelativePlan($package, $handoff->target()->packageIdentity(), $handoff->artifact()->sourceInventoryFingerprint(), null, [$action], $target->fingerprint(), 'full', []);
$request = new NormalWebcoreRecoveryCaptureRequest($handoff->artifact()->operationIdentity(), $handoff->artifact()->installationIdentity(), $scope->namespaceIdentity(), 'deployment-test', $handoff->target()->packageIdentity(), $handoff->target()->contract()->releaseIdentity(), $handoff->target()->archiveIdentity(), $handoff->applyPlan(), 'pre-lifecycle-test', hash('sha256', 'ledger'), $scope->databaseIdentity(), FilesystemRecoveryPlan::fromV3Plan($orderedPlan), $scope, $database, $application, $orderedPlan);
$operation = new LifecycleOperationRecord($handoff->artifact()->operationIdentity(), Copot\Core\TransitionPlan::UPGRADE, $handoff->target()->contract()->targetWebcoreVersion(), $handoff->target()->contract()->releaseIdentity(), $handoff->target()->archiveIdentity(), $tmp . '/staging', $handoff->target()->payloadIdentity(), $handoff->applyPlan()->identity(), LifecycleOperationRecord::PREPARING, 0, null, hash('sha256', 'migration'), null, gmdate(DATE_ATOM), gmdate(DATE_ATOM));
$operationStore->create($operation);
$binder = new LegacyBootstrapRecoveryBinder($capture, $artifactStore, $operationStore, new Copot\Core\LegacyBootstrapRecoveryIdentityCrossReferenceComposer());

$bound = $binder->bind($handoff, $request, $matched, $scope, $application, $database, 'deployment-test');
$behaviorAssert($bound->status() === LegacyBootstrapRecoveryBindingResult::BOUND, 'Real binder did not complete canonical recovery binding: ' . implode('; ', $bound->diagnostics()));
$behaviorAssert(!$bound->mutationAuthorized() && !$bound->operationalReady(), 'Successful binding granted mutation or readiness authority.');
$boundOperation = $operationStore->read();
$behaviorAssert($boundOperation?->recoveryIdentity() === $recoveryIdentity->value() && $boundOperation?->recoveryManifestIdentity() === $bound->toArray()['manifest_identity'], 'Successful binding was not durably reread on the operation record.');

$retry = $binder->bind($handoff, $request, $matched, $scope, $application, $database, 'deployment-test');
$behaviorAssert($retry->status() === LegacyBootstrapRecoveryBindingResult::BOUND && $retry->identity() === $bound->identity(), 'Canonical retry was not deterministic.');

$beforeFailure = $operationStore->read()?->toArray();
$expired = new ApplicationWriteProtectionEvidence($scope, $recoveryIdentity, str_repeat('1', 64), str_repeat('2', 64), ['http', 'cli', 'scheduled', 'background'], true, true, $now - 10, $now - 2);
$expiredRequest = new NormalWebcoreRecoveryCaptureRequest($handoff->artifact()->operationIdentity(), $handoff->artifact()->installationIdentity(), $scope->namespaceIdentity(), 'deployment-test', $handoff->target()->packageIdentity(), $handoff->target()->contract()->releaseIdentity(), $handoff->target()->archiveIdentity(), $handoff->applyPlan(), 'pre-lifecycle-test', hash('sha256', 'ledger'), $scope->databaseIdentity(), FilesystemRecoveryPlan::fromV3Plan($orderedPlan), $scope, $database, $expired, $orderedPlan);
$expiredResult = $binder->bind($handoff, $expiredRequest, $matched, $scope, $expired, $database, 'deployment-test');
$behaviorAssert($expiredResult->status() === LegacyBootstrapRecoveryBindingResult::STALE, 'Expired protection was not classified stale.');
$behaviorAssert($operationStore->read()?->toArray() === $beforeFailure, 'Expired protection changed persisted operation state.');

$wrongRecovery = new Copot\Core\BackupRecovery\RecoveryIdentity('webcore-' . hash('sha256', 'wrong-recovery'));
$wrongDatabase = new ScopedDatabaseConsistencyEvidence($scope, $wrongRecovery, str_repeat('3', 64), str_repeat('4', 64), $now - 1, $now + 300);
$wrongRequest = new NormalWebcoreRecoveryCaptureRequest($handoff->artifact()->operationIdentity(), $handoff->artifact()->installationIdentity(), $scope->namespaceIdentity(), 'deployment-test', $handoff->target()->packageIdentity(), $handoff->target()->contract()->releaseIdentity(), $handoff->target()->archiveIdentity(), $handoff->applyPlan(), 'pre-lifecycle-test', hash('sha256', 'ledger'), $scope->databaseIdentity(), FilesystemRecoveryPlan::fromV3Plan($orderedPlan), $scope, $wrongDatabase, $application, $orderedPlan);
$wrongResult = $binder->bind($handoff, $wrongRequest, $matched, $scope, $application, $wrongDatabase, 'deployment-test');
$behaviorAssert($wrongResult->status() === LegacyBootstrapRecoveryBindingResult::STALE || $wrongResult->status() === LegacyBootstrapRecoveryBindingResult::UNVERIFIED, 'Recovery identity mismatch was accepted.');
$behaviorAssert($operationStore->read()?->toArray() === $beforeFailure, 'Recovery identity mismatch changed persisted operation state.');

$manifest = $artifactStore->readManifest($recoveryIdentity);
$linkRecord = null;
foreach ($manifest['artifacts'] as $record) if ($record->domainIdentifier() === 'filesystem.action-linkage') $linkRecord = $record;
$behaviorAssert($linkRecord instanceof RecoveryArtifactRecord, 'Successful binder did not publish action linkage.');
$linkPath = (new RecoveryStoragePathPolicy($recoveryRoot))->artifactPath($recoveryIdentity, $linkRecord);
$linkBytes = file_get_contents($linkPath);
file_put_contents($linkPath, 'corrupt-linkage');
$corruptResult = $binder->bind($handoff, $request, $matched, $scope, $application, $database, 'deployment-test');
$behaviorAssert($corruptResult->status() === LegacyBootstrapRecoveryBindingResult::INCOMPLETE || $corruptResult->status() === LegacyBootstrapRecoveryBindingResult::UNVERIFIED, 'Corrupt action linkage was accepted.');
$behaviorAssert($operationStore->read()?->toArray() === $beforeFailure, 'Corrupt evidence changed persisted operation state.');
file_put_contents($linkPath, $linkBytes);

$actionsProperty = new ReflectionProperty(V3SourceRelativePlan::class, 'actions');
$actionsProperty->setValue($orderedPlan, [new V3SourceRelativeAction(V3SourceRelativeAction::REMOVE, 'APP_ROOT', 'future.php', 5, hash('sha256', 'later'), 'APP_ROOT', 'future.php', 'obsolete')]);
$ordinalResult = $binder->bind($handoff, $request, $matched, $scope, $application, $database, 'deployment-test');
$behaviorAssert(in_array($ordinalResult->status(), [LegacyBootstrapRecoveryBindingResult::STALE, LegacyBootstrapRecoveryBindingResult::UNVERIFIED, LegacyBootstrapRecoveryBindingResult::INCOMPLETE], true), 'Action-ordinal/action identity mismatch was accepted: ' . $ordinalResult->status() . ' ' . implode('; ', $ordinalResult->diagnostics()));
$behaviorAssert($operationStore->read()?->toArray() === $beforeFailure, 'Action-linkage mismatch changed persisted operation state.');
$actionsProperty->setValue($orderedPlan, [$action]);

$interruptStorage = $tmp . DIRECTORY_SEPARATOR . 'binder-interruption-storage';
mkdir($interruptStorage, 0700, true);
$interruptStore = new LifecycleOperationStore($interruptStorage);
$interruptOperation = new LifecycleOperationRecord($handoff->artifact()->operationIdentity(), Copot\Core\TransitionPlan::UPGRADE, $handoff->target()->contract()->targetWebcoreVersion(), $handoff->target()->contract()->releaseIdentity(), $handoff->target()->archiveIdentity(), $tmp . '/staging', $handoff->target()->payloadIdentity(), $handoff->applyPlan()->identity(), LifecycleOperationRecord::PREPARING, 0, null, hash('sha256', 'migration'), null, gmdate(DATE_ATOM), gmdate(DATE_ATOM));
$interruptStore->create($interruptOperation);
mkdir($tmp . '/app/future.php');
$interrupted = $binder = new LegacyBootstrapRecoveryBinder($capture, $artifactStore, $interruptStore, new Copot\Core\LegacyBootstrapRecoveryIdentityCrossReferenceComposer());
$interruptedResult = $binder->bind($handoff, $request, $matched, $scope, $application, $database, 'deployment-test');
$behaviorAssert(in_array($interruptedResult->status(), [LegacyBootstrapRecoveryBindingResult::INCOMPLETE, LegacyBootstrapRecoveryBindingResult::STALE, LegacyBootstrapRecoveryBindingResult::UNVERIFIED], true), 'Interrupted capture was not fail-closed: ' . $interruptedResult->status() . ' ' . implode('; ', $interruptedResult->diagnostics()));
$behaviorAssert($interruptStore->read()?->recoveryIdentity() === null && $interruptStore->read()?->recoveryManifestIdentity() === null, 'Interrupted capture produced a divergent operation binding.');
rmdir($tmp . '/app/future.php');

echo "Bootstrap recovery binder behavioral assertions: {$behaviorAssertions}" . PHP_EOL;
