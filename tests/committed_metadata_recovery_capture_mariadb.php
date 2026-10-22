<?php

declare(strict_types=1);

use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\BackupRecovery\ApplicationWriteProtectionEvidence;
use Copot\Core\BackupRecovery\FilesystemRecoveryPathGuard;
use Copot\Core\BackupRecovery\InstalledLockRecoveryDomain;
use Copot\Core\BackupRecovery\InstallationScopedMaintenanceGuard;
use Copot\Core\BackupRecovery\LifecycleRecoveryDomain;
use Copot\Core\BackupRecovery\MetadataRecoveryCaptureRequest;
use Copot\Core\BackupRecovery\MySqlRecoveryProvider;
use Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureService;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\RecoveryLifecycleCoordinator;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;
use Copot\Core\BackupRecovery\RecoveryRootResolver;
use Copot\Core\BackupRecovery\RecoveryStorageRoot;
use Copot\Core\BackupRecovery\ScopedDatabaseConsistencyEvidence;
use Copot\Core\BackupRecovery\UnavailableDatabaseQuiescence;
use Copot\Core\BackupRecovery\FilesystemRecoveryDomain;
use Copot\Core\CommittedLifecycleMetadataReconciliationService;
use Copot\Core\CommittedLifecycleState;
use Copot\Core\CommittedLifecycleStateStore;
use Copot\Core\CoreMigrationLedger;
use Copot\Core\CoreMigrationRegistry;
use Copot\Core\CoreMigrationStateIdentity;
use Copot\Core\DatabaseTableOwnershipCatalog;
use Copot\Core\InstallationIdentityStore;
use Copot\Core\InstallationMutex;
use Copot\Core\InstallationState;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\LiveTreePathGuard;

$base = dirname(__DIR__);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;
    if (!$condition) throw new RuntimeException($message);
};
$remove = static function (string $path) use (&$remove): void {
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry);
        @rmdir($path);
    } elseif (is_file($path) || is_link($path)) @unlink($path);
};

$host = (string) (getenv('COPOT_TEST_DB_HOST') ?: '127.0.0.1');
$port = (int) (getenv('COPOT_TEST_DB_PORT') ?: 3306);
$username = (string) (getenv('COPOT_TEST_DB_USERNAME') ?: 'root');
$password = (string) (getenv('COPOT_TEST_DB_PASSWORD') ?: '');
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'copot_metadata_capture_' . bin2hex(random_bytes(6));
$quotedDatabase = '`' . $database . '`';
$server->exec("CREATE DATABASE {$quotedDatabase} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$sandbox = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-metadata-capture-' . bin2hex(random_bytes(6));
$root = $sandbox . DIRECTORY_SEPARATOR . 'app';
$storage = $root . DIRECTORY_SEPARATOR . 'storage';
$public = $sandbox . DIRECTORY_SEPARATOR . 'public';
$recovery = $sandbox . DIRECTORY_SEPARATOR . 'recovery';
mkdir($storage, 0700, true); mkdir($public, 0700, true); mkdir($recovery, 0700, true);
$pdo = null;
try {
    $config = ['host' => $host, 'port' => $port, 'database' => $database, 'username' => $username, 'password' => $password, 'namespace' => ''];
    $installedStatements = (new InstallerSchemaRunner($base . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql'))->install($config);
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $registry = CoreMigrationRegistry::forProject($base);
    $migration = $registry->migrations()[0];
    (new CoreMigrationLedger())->record($pdo, $migration);
    $identities = new InstallationIdentityStore($storage);
    $installation = $identities->getOrCreate();
    $installationState = new InstallationState($storage);
    $installationState->createMarker('0.13.0');
    $committedStore = new CommittedLifecycleStateStore($storage);
    $committedStore->write(new CommittedLifecycleState('0.13.0', 'copot-v0.13.0', null, 1, 'stale-schema', hash('sha256', 'stale-migration'), new DateTimeImmutable('2025-01-01T00:00:00+00:00')));
    $scope = DatabaseScope::fromOwnershipCatalog($installation->value(), $database, '', DatabaseTableOwnershipCatalog::current());
    $storageRoot = (new RecoveryRootResolver($root, $recovery, [$storage], [$public]))->resolve();
    $rootPath = $storageRoot->path();
    $recoveryStore = new RecoveryLifecycleStore($storageRoot);
    $artifacts = new RecoveryArtifactStore($storageRoot);
    $mutex = new InstallationMutex($storage);
    $guard = new InstallationScopedMaintenanceGuard($mutex, $storage);
    $coordinator = new RecoveryLifecycleCoordinator($recoveryStore, $mutex, new UnavailableDatabaseQuiescence(), $guard);
    $pathGuard = new FilesystemRecoveryPathGuard(new LiveTreePathGuard($root));
    $connection = static fn (): PDO => new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $capture = new NormalWebcoreRecoveryCaptureService($coordinator, $recoveryStore, $artifacts, new FilesystemRecoveryDomain($pathGuard, $artifacts), new LifecycleRecoveryDomain($committedStore, $pathGuard), new InstalledLockRecoveryDomain($installationState, $pathGuard), new MySqlRecoveryProvider(), new UnavailableDatabaseQuiescence(), $connection, $connection);
    $operationId = 'metadata-reconciliation-mariadb-' . bin2hex(random_bytes(8));
    $recoveryPlanIdentity = hash('sha256', 'mariadb-recovery-plan:' . $operationId);
    $recoveryIdentity = NormalWebcoreRecoveryCaptureService::forOperation($operationId, $recoveryPlanIdentity);
    $application = $guard->produceEvidence($recoveryIdentity, $scope, 300);
    $databaseEvidence = new ScopedDatabaseConsistencyEvidence($scope, $recoveryIdentity, $application->writerExclusionIdentity(), hash('sha256', 'schema-stability:' . $scope->identity()), $application->capturedAt(), $application->validUntil());
    $ledgerIdentity = CoreMigrationStateIdentity::fromRecords((new CoreMigrationLedger())->records($pdo));
    $request = new MetadataRecoveryCaptureRequest($operationId, $installation->value(), '', hash('sha256', 'deployment:' . $root), hash('sha256', 'target-package:' . $operationId), 'copot-v0.13.0', hash('sha256', 'archive:' . $operationId), $recoveryPlanIdentity, CommittedLifecycleMetadataReconciliationService::stateIdentity($committedStore->read()), $ledgerIdentity, $database, $scope, $databaseEvidence, $application);
    $session = $capture->captureMetadata($request);
    $record = $recoveryStore->read($recoveryIdentity);
    $manifest = $artifacts->readManifest($recoveryIdentity);
    $assert($record->state() === 'READY', 'Actual MariaDB capture did not persist READY.');
    $assert($record->manifestIdentity() === $manifest['manifest']->identity(), 'Persisted recovery manifest identity mismatch.');
    $assert($record->captureComplete() && !$record->mutationStarted(), 'Capture boundary was not preserved.');
    $assert($record->operationIdentity() === $operationId, 'Operation identity was not preserved.');
    $assert($record->recoveryIdentity()->value() === $recoveryIdentity->value(), 'Recovery identity was not preserved.');
    $assert($session->recoveryIdentity()->equals($recoveryIdentity), 'Preparation recovery identity mismatch.');
    $assert($application->isValidAt() && $databaseEvidence->isValidAt(), 'Scoped evidence was not valid after actual capture.');
    $assert($coordinator->scopedProtectionMatches($recoveryIdentity, $databaseEvidence), 'Canonical scoped protection was not retained.');
    $assert(is_dir($rootPath) && count(glob($rootPath . DIRECTORY_SEPARATOR . '*') ?: []) > 0, 'Recovery root did not receive persisted evidence.');
    $session->close();
    $assert(!$guard->isActive($recoveryIdentity), 'Successful capture did not release writer exclusion.');
    $interruptedOperation = 'metadata-recovery-interrupted-' . bin2hex(random_bytes(8));
    $interruptedPlan = hash('sha256', 'interrupted-plan:' . $interruptedOperation);
    $interruptedRecovery = NormalWebcoreRecoveryCaptureService::forOperation($interruptedOperation, $interruptedPlan);
    $interruptedApplication = $guard->produceEvidence($interruptedRecovery, $scope, 300);
    $interruptedDatabase = new ScopedDatabaseConsistencyEvidence($scope, $interruptedRecovery, $interruptedApplication->writerExclusionIdentity(), hash('sha256', 'schema-stability:' . $scope->identity()), $interruptedApplication->capturedAt(), $interruptedApplication->validUntil());
    $interruptedRequest = new MetadataRecoveryCaptureRequest($interruptedOperation, $request->installationIdentity(), $request->namespaceIdentity(), $request->deploymentIdentity(), hash('sha256', 'target-package:' . $interruptedOperation), $request->targetReleaseIdentity(), hash('sha256', 'archive:' . $interruptedOperation), $interruptedPlan, $request->preLifecycleIdentity(), $request->migrationLedgerIdentity(), $request->databaseIdentity(), $scope, $interruptedDatabase, $interruptedApplication);
    $beforeInterruptedStateIdentity = CommittedLifecycleMetadataReconciliationService::stateIdentity($committedStore->read());
    $beforeInterruptedMarkerIdentity = CommittedLifecycleMetadataReconciliationService::markerIdentity($installationState->readMarker());
    $captureWithFault = new NormalWebcoreRecoveryCaptureService($coordinator, $recoveryStore, $artifacts, new FilesystemRecoveryDomain($pathGuard, $artifacts), new LifecycleRecoveryDomain($committedStore, $pathGuard), new InstalledLockRecoveryDomain($installationState, $pathGuard), new MySqlRecoveryProvider(), new UnavailableDatabaseQuiescence(), $connection, $connection, static function (string $stage): void { if ($stage === 'after_recovery_record_persisted') throw new RuntimeException('Injected interruption after recovery record persistence.'); });
    $interrupted = false;
    try { $captureWithFault->captureMetadata($interruptedRequest); } catch (Throwable) { $interrupted = true; }
    $interruptedRecord = $recoveryStore->read($interruptedRecovery);
    $assert($interrupted && $interruptedRecord->state() === 'FAILED_BEFORE_MUTATION', 'Interruption did not persist FAILED_BEFORE_MUTATION.');
    $assert($interruptedRecord->operationIdentity() === $interruptedOperation && $interruptedRecord->recoveryIdentity()->value() === $interruptedRecovery->value(), 'Interrupted operation/recovery identity binding changed.');
    $assert(!$interruptedRecord->captureComplete() && !$interruptedRecord->mutationStarted(), 'Interrupted capture crossed the mutation or READY boundary.');
    $assert(CommittedLifecycleMetadataReconciliationService::stateIdentity($committedStore->read()) === $beforeInterruptedStateIdentity, 'Interrupted capture changed committed metadata.');
    $assert(CommittedLifecycleMetadataReconciliationService::markerIdentity($installationState->readMarker()) === $beforeInterruptedMarkerIdentity, 'Interrupted capture changed installed marker.');
    $assert(!$guard->isActive($interruptedRecovery), 'Guard was not released after pre-mutation capture interruption.');
    $assert(!is_dir($rootPath . DIRECTORY_SEPARATOR . 'recovery-sets' . DIRECTORY_SEPARATOR . $interruptedRecovery->value() . DIRECTORY_SEPARATOR . 'artifacts'), 'Interrupted capture left a false artifact publication.');
    $resumeApplication = $guard->produceEvidence($interruptedRecovery, $scope, 300);
    $resumeDatabase = new ScopedDatabaseConsistencyEvidence($scope, $interruptedRecovery, $resumeApplication->writerExclusionIdentity(), hash('sha256', 'schema-stability:' . $scope->identity()), $resumeApplication->capturedAt(), $resumeApplication->validUntil());
    $resumeRequest = new MetadataRecoveryCaptureRequest($interruptedOperation, $request->installationIdentity(), $request->namespaceIdentity(), $request->deploymentIdentity(), hash('sha256', 'target-package:' . $interruptedOperation), $request->targetReleaseIdentity(), hash('sha256', 'archive:' . $interruptedOperation), $interruptedPlan, $request->preLifecycleIdentity(), $request->migrationLedgerIdentity(), $request->databaseIdentity(), $scope, $resumeDatabase, $resumeApplication);
    $resume = $capture->resumeMetadata($resumeRequest);
    $resumedRecord = $recoveryStore->read($interruptedRecovery);
    $assert($resume->ready() && $resumedRecord->state() === 'READY' && $resumedRecord->captureComplete() && !$resumedRecord->mutationStarted(), 'Canonical metadata capture resume did not reach READY safely.');
    $resume->close();
    $assert(!$guard->isActive($interruptedRecovery), 'Guard was not released after successful canonical resume.');
    $staleApplication = new ApplicationWriteProtectionEvidence($scope, $recoveryIdentity, hash('sha256', 'stale-writer-evidence'), $application->drainIdentity(), ['background', 'cli', 'http', 'scheduled'], true, true, time() - 1, time() + 300);
    $staleRequest = new MetadataRecoveryCaptureRequest($request->operationId(), $request->installationIdentity(), $request->namespaceIdentity(), $request->deploymentIdentity(), $request->targetPackageIdentity(), $request->targetReleaseIdentity(), $request->archiveIdentity(), $request->recoveryPlanIdentity(), $request->preLifecycleIdentity(), $request->migrationLedgerIdentity(), $request->databaseIdentity(), $request->databaseScope(), $request->databaseConsistencyEvidence(), $staleApplication);
    $staleRejected = false;
    try { $capture->captureMetadata($staleRequest); } catch (Throwable) { $staleRejected = true; }
    $assert($staleRejected && !$guard->isActive($recoveryIdentity), 'Stale application evidence was not rejected fail-closed.');
    $failedOperation = 'metadata-recovery-failure-' . bin2hex(random_bytes(8));
    $failedPlan = hash('sha256', 'failure-plan:' . $failedOperation);
    $failedRecovery = NormalWebcoreRecoveryCaptureService::forOperation($failedOperation, $failedPlan);
    $failedApplication = $guard->produceEvidence($failedRecovery, $scope, 300);
    $failedDatabase = new ScopedDatabaseConsistencyEvidence($scope, $failedRecovery, $failedApplication->writerExclusionIdentity(), hash('sha256', 'schema-stability:' . $scope->identity()), $failedApplication->capturedAt(), $failedApplication->validUntil());
    $failedRequest = new MetadataRecoveryCaptureRequest($failedOperation, $request->installationIdentity(), $request->namespaceIdentity(), $request->deploymentIdentity(), hash('sha256', 'target-package:' . $failedOperation), $request->targetReleaseIdentity(), hash('sha256', 'archive:' . $failedOperation), $failedPlan, $request->preLifecycleIdentity(), $request->migrationLedgerIdentity(), $request->databaseIdentity(), $scope, $failedDatabase, $failedApplication);
    $pdo->exec('DROP TABLE core_migration_history');
    $captureFailed = false;
    try { $capture->captureMetadata($failedRequest); } catch (Throwable) { $captureFailed = true; }
    $assert($captureFailed && !$guard->isActive($failedRecovery), 'Actual database capture failure did not release writer exclusion.');
    $assert(!is_dir($rootPath . DIRECTORY_SEPARATOR . 'recovery-sets' . DIRECTORY_SEPARATOR . $failedRecovery->value()), 'Failed capture left recovery artifacts behind without a lifecycle record.');
    $missing = $root . DIRECTORY_SEPARATOR . 'missing-recovery';
    $threw = false; try { (new RecoveryRootResolver($root, $missing, [$storage], [$public]))->resolve(); } catch (Throwable) { $threw = true; }
    $assert($threw, 'Missing recovery root was accepted.');
    echo "committed_metadata_recovery_capture_mariadb: {$assertions} assertions passed; installed {$installedStatements} schema statements." . PHP_EOL;
} finally {
    try { $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase); } catch (Throwable) {}
    $remove($sandbox);
}
