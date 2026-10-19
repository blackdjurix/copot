<?php

require __DIR__ . '/legacy_to_v3_bootstrap_wu2.php';
require_once dirname(__DIR__) . '/app/Core/NormalWebcoreProtectedMutationBoundary.php';

use Copot\Core\BackupRecovery\ApplicationWriteProtectionEvidence;
use Copot\Core\BackupRecovery\DatabaseCaptureContext;
use Copot\Core\BackupRecovery\DatabaseRecoveryArtifact;
use Copot\Core\BackupRecovery\DatabaseRecoveryProvider;
use Copot\Core\BackupRecovery\DatabaseRestoreContext;
use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\BackupRecovery\DatabaseVerificationResult;
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
use Copot\Core\LegacyBootstrapExecutionAdmission;
use Copot\Core\LegacyBootstrapRecoveryBinder;
use Copot\Core\LegacyBootstrapRecoveryBindingResult;
use Copot\Core\LegacyBootstrapScopedProtectionComposer;
use Copot\Core\LegacyBootstrapTargetFreshnessResult;
use Copot\Core\LegacyBootstrapV3ExecutionPlanner;
use Copot\Core\LifecycleOperationRecord;
use Copot\Core\LifecycleOperationStore;
use Copot\Core\PackageV3SourceRelativePlanner;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$root = $tmp . DIRECTORY_SEPARATOR . 'admission-recovery-root';
mkdir($root, 0700, true);
$recoveryRoot = (new RecoveryRootResolver($tmp . '/app', $root, [$tmp . '/app/storage'], [$tmp . '/public']))->resolve();
$artifacts = new RecoveryArtifactStore($recoveryRoot);
$recoveryStore = new RecoveryLifecycleStore($recoveryRoot);
$pathGuard = new FilesystemRecoveryPathGuard(new Copot\Core\LiveTreePathGuard($tmp . '/app'));
$mutex = new Copot\Core\InstallationMutex($tmp . '/app/storage');
$guard = new InstallationScopedMaintenanceGuard($mutex, $tmp . '/app/storage');
$coordinator = new RecoveryLifecycleCoordinator($recoveryStore, $mutex, new UnavailableDatabaseQuiescence(), $guard);
$filesystem = new FilesystemRecoveryDomain($pathGuard, $artifacts);
$lifecycle = new LifecycleRecoveryDomain(new Copot\Core\CommittedLifecycleStateStore($tmp . '/app/storage'), $pathGuard);
$installedLock = new InstalledLockRecoveryDomain(new Copot\Core\InstallationState($tmp . '/app/storage'), $pathGuard);
$databaseProvider = new class implements DatabaseRecoveryProvider {
    public function capture(DatabaseCaptureContext $context): DatabaseRecoveryArtifact
    {
        $bytes = 'isolated-admission-database';
        return new DatabaseRecoveryArtifact(new RecoveryArtifactRecord('database.webcore', hash('sha256', $bytes), strlen($bytes)), $bytes, $context->databaseIdentity(), hash('sha256', 'schema'), hash('sha256', 'data'), hash('sha256', 'ledger'), $context->scope());
    }
    public function verifyCaptured(DatabaseRecoveryArtifact $artifact): DatabaseVerificationResult { return DatabaseVerificationResult::valid([]); }
    public function restore(DatabaseRecoveryArtifact $artifact, DatabaseRestoreContext $context): void {}
    public function verifyRestored(DatabaseRecoveryArtifact $artifact, DatabaseRestoreContext $context): DatabaseVerificationResult { return DatabaseVerificationResult::valid([]); }
};
$pdo = static fn (): PDO => (new ReflectionClass(PDO::class))->newInstanceWithoutConstructor();
$capture = new NormalWebcoreRecoveryCaptureService($coordinator, $recoveryStore, $artifacts, $filesystem, $lifecycle, $installedLock, $databaseProvider, new UnavailableDatabaseQuiescence(), $pdo, $pdo);
$operations = new LifecycleOperationStore($tmp . '/app/storage');
$recovery = NormalWebcoreRecoveryCaptureService::forOperation($handoff->artifact()->operationIdentity(), $handoff->applyPlan()->identity());
$operation = new LifecycleOperationRecord($handoff->artifact()->operationIdentity(), 'LEGACY_TO_V3_BOOTSTRAP', $handoff->target()->contract()->targetWebcoreVersion(), $handoff->target()->contract()->releaseIdentity(), $handoff->target()->archiveIdentity(), $tmp . '/staging', $handoff->target()->payloadIdentity(), $handoff->applyPlan()->identity(), LifecycleOperationRecord::PREPARING, 0, null, hash('sha256', 'migration'), null, gmdate(DATE_ATOM), gmdate(DATE_ATOM));
$operations->create($operation);
$inventoryBeforeLifecycleWrite = $liveInventory->capture();
$activeOperationPath = $tmp . '/app/storage/.copot-lifecycle/active-operation.json';
$activeOperationBytes = file_get_contents($activeOperationPath);
file_put_contents($activeOperationPath, $activeOperationBytes . 'lifecycle-owned-update');
$inventoryAfterLifecycleWrite = $liveInventory->capture();
$assert($inventoryBeforeLifecycleWrite->fingerprint() === $inventoryAfterLifecycleWrite->fingerprint(), 'Lifecycle-owned active operation metadata changed source freshness.');
file_put_contents($activeOperationPath, $activeOperationBytes);
$assert(!array_filter($inventoryAfterLifecycleWrite->entries(), static fn (Copot\Core\LegacyBootstrapSourceEntry $entry): bool => strtolower($entry->path()) === 'storage/installed.lock'), 'Canonical installed marker leaked into source inventory.');
$plan = (new PackageV3SourceRelativePlanner())->plan($manifest);
$scope = new DatabaseScope($handoff->artifact()->installationIdentity(), 'test', 'site_admission', ['site_admission_core_migration_history']);
$now = time();
$application = new ApplicationWriteProtectionEvidence($scope, $recovery, str_repeat('1', 64), str_repeat('2', 64), ['http', 'cli', 'scheduled', 'background'], true, true, $now - 1, $now + 300);
$database = new ScopedDatabaseConsistencyEvidence($scope, $recovery, str_repeat('3', 64), str_repeat('4', 64), $now - 1, $now + 300);
$request = new NormalWebcoreRecoveryCaptureRequest($operation->operationId(), $handoff->artifact()->installationIdentity(), $scope->namespaceIdentity(), 'deployment-admission', $handoff->target()->packageIdentity(), $handoff->target()->contract()->releaseIdentity(), $handoff->target()->archiveIdentity(), $handoff->applyPlan(), 'pre-lifecycle-admission', hash('sha256', 'ledger'), $scope->databaseIdentity(), FilesystemRecoveryPlan::fromApplyPlan($handoff->applyPlan()), $scope, $database, $application, null);
$binder = new LegacyBootstrapRecoveryBinder($capture, $artifacts, $operations, new Copot\Core\LegacyBootstrapRecoveryIdentityCrossReferenceComposer());
$bound = $binder->bind($handoff, $request, $matched, $scope, $application, $database, 'deployment-admission');
$assert($bound->status() === LegacyBootstrapRecoveryBindingResult::BOUND, 'Canonical fixture binder did not produce BOUND evidence: ' . implode('; ', $bound->diagnostics()));
$persisted = $operations->read();
$context = new Copot\Core\LegacyBootstrapV3ExecutionContext($handoff->artifact()->installationIdentity(), $persisted, $handoff, $plan, $recovery, $bound);
$session = new Copot\Core\NormalWebcoreProtectedMutationSession($capture->resume($request, $recovery, $persisted->recoveryManifestIdentity()));
$protection = new LegacyBootstrapScopedProtectionComposer($freshness, $guard, $scope);
try {
    $admission = LegacyBootstrapExecutionAdmission::issue($context, $bound, $session, $artifacts, $operations, $protection, $application, $database);
    $assert($admission->identity() !== '' && !$admission->authorized(), 'Canonical admission was not issued as a single-use proof.');
    $bridge = new Copot\Core\LegacyBootstrapV3ExecutionBridge(new Copot\Core\V3ExplicitApplyExecutor());
    $result = $bridge->execute($context, $admission, $manifest->payload(), $operations, new Copot\Core\PackageOwnedFileApplier(new Copot\Core\LiveTreePathGuard($tmp . '/app'), Copot\Core\LiveFileActivationCapability::current(), $tmp . '/apply-temp'), new Copot\Core\LiveTreePathGuard($tmp . '/app'));
    $assert($admission->authorized() && $result->status() === Copot\Core\WebcoreApplyResult::AWAITING_WU6, 'Valid canonical admission did not delegate to the existing executor.');
    $admission->fail('isolated admission fixture complete');
} catch (Throwable $exception) {
    $assert(str_contains(strtolower($exception->getMessage()), 'protected') || str_contains(strtolower($exception->getMessage()), 'mismatch'), 'Admission failure was not classified as a protected-boundary rejection.');
    $assert($operations->read()?->fileCursor() === 0, 'Failed admission advanced the lifecycle operation.');
    $session->fail('isolated admission rejection fixture complete');
}

echo "Bootstrap execution admission behavioral assertions: {$assertions}" . PHP_EOL;
