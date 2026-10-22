<?php

declare(strict_types=1);

use Copot\Core\BackupRecovery\ApplicationWriteProtectionEvidence;
use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\BackupRecovery\RecoveryArtifactRecord;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\RecoveryDomainIdentity;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryLifecycleCoordinator;
use Copot\Core\BackupRecovery\RecoveryLifecycleRecord;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;
use Copot\Core\BackupRecovery\RecoveryManifest;
use Copot\Core\BackupRecovery\RecoveryStorageRoot;
use Copot\Core\BackupRecovery\ScopedDatabaseConsistencyEvidence;
use Copot\Core\BackupRecovery\UnavailableDatabaseQuiescence;
use Copot\Core\CommittedLifecycleMetadataReconciliationEvidence;
use Copot\Core\CommittedLifecycleMetadataReconciliationService;
use Copot\Core\CommittedLifecycleState;
use Copot\Core\CommittedLifecycleStateStore;
use Copot\Core\CoreMigrationHealthVerifier;
use Copot\Core\CoreMigrationLedger;
use Copot\Core\CoreMigrationRegistry;
use Copot\Core\DatabaseHealthVerifier;
use Copot\Core\DatabaseTableNames;
use Copot\Core\DatabaseTableOwnershipCatalog;
use Copot\Core\InstallationIdentity;
use Copot\Core\InstallationMutex;
use Copot\Core\InstallationState;
use Copot\Core\LifecycleOperationRecord;
use Copot\Core\LifecycleOperationStore;

$base = dirname(__DIR__);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$remove = static function (string $path) use (&$remove): void {
    if (is_dir($path) && !is_link($path)) { foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry); @rmdir($path); }
    elseif (is_file($path) || is_link($path)) @unlink($path);
};

$fixture = static function (?string $faultStage) use ($base): array {
    $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-metadata-fault-' . bin2hex(random_bytes(6));
    $storage = $root . DIRECTORY_SEPARATOR . 'storage';
    mkdir($storage, 0700, true); mkdir($root . DIRECTORY_SEPARATOR . 'recovery', 0700, true); mkdir($root . DIRECTORY_SEPARATOR . 'guard-lock', 0700, true);
    $pdo = new PDO('sqlite:' . $root . DIRECTORY_SEPARATOR . 'fixture.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $tables = new DatabaseTableNames();
    foreach (DatabaseTableOwnershipCatalog::materializedWebcoreLogicalNames() as $logical) $pdo->exec('CREATE TABLE ' . $tables->resolve($logical) . ' (id INTEGER)');
    $pdo->exec('DROP TABLE core_migration_history');
    $pdo->exec('CREATE TABLE core_migration_history (migration_id TEXT, sequence_number INTEGER, target_webcore_version TEXT, target_schema_identity TEXT, migration_checksum TEXT, applied_at TEXT)');
    $registry = CoreMigrationRegistry::forProject($base); $migration = $registry->migrations()[0];
    $pdo->exec("INSERT INTO core_migration_history VALUES (" . implode(',', array_map(static fn (string $value): string => $pdo->quote($value), [$migration->id(), (string) $migration->sequence(), $migration->targetWebcoreVersion(), $migration->targetSchemaIdentity(), $migration->checksum(), gmdate('Y-m-d H:i:s')])) . ')');
    $installation = InstallationIdentity::generate(); $installationState = new InstallationState($storage); $installationState->createMarker('0.13.0'); $originalMarker = $installationState->readMarker();
    $store = new CommittedLifecycleStateStore($storage);
    $before = new CommittedLifecycleState('0.13.0', 'copot-v0.13.0', null, 1, 'stale-schema', hash('sha256', 'stale-migration'), new DateTimeImmutable('2025-01-01T00:00:00+00:00')); $store->write($before);
    $scope = DatabaseScope::fromOwnershipCatalog($installation->value(), 'fixture_db', '', DatabaseTableOwnershipCatalog::current()); $recovery = new RecoveryIdentity('metadata-fault-' . bin2hex(random_bytes(4))); $now = time();
    $application = new ApplicationWriteProtectionEvidence($scope, $recovery, hash('sha256', 'writers'), hash('sha256', 'drain'), ['background', 'cli', 'http', 'scheduled'], true, true, $now - 1, $now + 600);
    $database = new ScopedDatabaseConsistencyEvidence($scope, $recovery, hash('sha256', 'writers'), hash('sha256', 'schema'), $now - 1, $now + 600);
    $manifestEvidenceIdentity = hash('sha256', 'evidence-manifest-' . bin2hex(random_bytes(4)));
    $evidence = new CommittedLifecycleMetadataReconciliationEvidence($installation, CommittedLifecycleMetadataReconciliationService::stateIdentity($before), CommittedLifecycleMetadataReconciliationService::markerIdentity($installationState->readMarker()), $scope, $recovery, $manifestEvidenceIdentity, $application, $database, $now - 1, $now + 600);
    $operations = new LifecycleOperationStore($storage);
    $faultStages = $faultStage === null ? [] : explode(',', $faultStage);
    $injector = $faultStages === [] ? null : static function (string $stage) use ($faultStages): void { if (in_array($stage, $faultStages, true)) throw new RuntimeException('Injected fault at ' . $stage); };
    $service = new CommittedLifecycleMetadataReconciliationService($installationState, $store, $operations, new InstallationMutex($storage), $registry, new CoreMigrationLedger($tables), new DatabaseHealthVerifier($tables), new CoreMigrationHealthVerifier(new CoreMigrationLedger($tables)), static fn (): PDO => $pdo, $injector);
    $plan = $service->plan($evidence);
    $recoveryRoot = new RecoveryStorageRoot($root, $root . DIRECTORY_SEPARATOR . 'recovery', hash('sha256', $root)); $recoveryStore = new RecoveryLifecycleStore($recoveryRoot); $artifacts = new RecoveryArtifactStore($recoveryRoot);
    $bytes = 'metadata-recovery-fixture'; $artifact = new RecoveryArtifactRecord('database.webcore', hash('sha256', $bytes), strlen($bytes));
    $manifest = new RecoveryManifest($recovery, $plan->operation()->operationId(), 'metadata-target', 'copot-v0.13.0', hash('sha256', 'metadata-archive'), $plan->identity(), [new RecoveryDomainIdentity('database.webcore', 'database.webcore', $scope->identity(), $artifact->artifactIdentity())], $evidence->committedStateIdentity(), $plan->migrationIdentity());
    $artifacts->publish($manifest, [['record' => $artifact, 'bytes' => $bytes]]);
    $recoveryStore->create(new RecoveryLifecycleRecord($recovery, $manifestEvidenceIdentity, $plan->operation()->operationId(), RecoveryLifecycleState::READY, false, true, $recovery->value(), $manifestEvidenceIdentity, $manifestEvidenceIdentity));
    $guard = new Copot\Core\BackupRecovery\InstallationScopedMaintenanceGuard(new InstallationMutex($root . DIRECTORY_SEPARATOR . 'guard-lock'), $storage);
    $coordinator = new RecoveryLifecycleCoordinator($recoveryStore, new InstallationMutex($root . DIRECTORY_SEPARATOR . 'guard-lock'), new UnavailableDatabaseQuiescence(), $guard); $coordinator->enterScopedMaintenance($recovery, $application, $database);
    return compact('root', 'service', 'plan', 'coordinator', 'recoveryStore', 'artifacts', 'operations', 'installationState', 'store', 'guard', 'recovery', 'application', 'originalMarker');
};

$execute = static function (?string $faultStage) use ($fixture, $remove, $assert): array {
    $f = $fixture($faultStage);
    try { $thrown = false; try { $f['result'] = $f['service']->execute($f['plan'], $f['coordinator'], $f['recoveryStore'], $f['artifacts']); } catch (Throwable) { $thrown = true; $f['result'] = null; } $f['thrown'] = $thrown; $f['operation'] = $f['operations']->read(); $f['recoveryRecord'] = $f['recoveryStore']->read($f['recovery']); return $f; }
    catch (Throwable $exception) { $remove($f['root']); throw $exception; }
};

$f = $execute(null); $assert($f['result']?->phase() === LifecycleOperationRecord::COMPLETED, 'Successful execute did not complete through the public service boundary.'); $assert($f['operations']->read() === null, 'Successful operation was not cleared after completion.'); $assert($f['store']->read()?->schemaStateIdentity() !== 'stale-schema', 'Successful execute did not persist corrected metadata.'); $assert($f['recoveryRecord']->postReconciliationVerified(), 'Successful execute did not durably record post-reconciliation verification.'); $assert($f['recoveryRecord']->state() === RecoveryLifecycleState::CLEANUP_PENDING, 'Successful execute did not use the canonical recovery cleanup disposition.'); $remove($f['root']);
$f = $execute('before_metadata_write'); $assert($f['thrown'], 'Pre-write fault was not injected.'); $assert($f['operation']?->phase() === LifecycleOperationRecord::BLOCKED, 'Pre-write fault did not persist BLOCKED: ' . ($f['operation']?->phase() ?? 'null') . ' / ' . ($f['operation']?->toArray()['reason'] ?? '')); $assert($f['store']->read()?->schemaStateIdentity() === 'stale-schema', 'Pre-write fault changed original state.'); $assert($f['installationState']->readMarker() === $f['originalMarker'], 'Pre-write fault changed original marker.'); $assert($f['recoveryRecord']->mutationStarted(), 'Pre-write fault did not retain recovery mutation boundary.'); $remove($f['root']);
$f = $execute('after_metadata_write'); $assert($f['thrown'], 'Post-write fault was not injected.'); $assert($f['operation']?->phase() === LifecycleOperationRecord::BLOCKED, 'Post-write failure did not persist BLOCKED.'); $assert($f['store']->read()?->schemaStateIdentity() === 'stale-schema', 'Post-write failure did not rollback committed state.'); $assert($f['installationState']->readMarker() === $f['originalMarker'], 'Successful rollback did not restore original marker evidence.'); $assert($f['recoveryRecord']->mutationStarted(), 'Post-write failure discarded recovery evidence.'); $remove($f['root']);
$f = $execute('after_metadata_write,before_metadata_rollback'); $assert($f['thrown'], 'Rollback fault was not injected.'); $assert($f['operation']?->phase() === LifecycleOperationRecord::INDETERMINATE, 'Rollback failure did not persist INDETERMINATE.'); $assert($f['store']->read()?->schemaStateIdentity() !== 'stale-schema', 'Rollback failure falsely claimed original state was restored.'); $assert($f['installationState']->readMarker() !== $f['originalMarker'], 'Rollback failure unexpectedly claimed original marker restoration.'); $assert($f['recoveryRecord']->mutationStarted(), 'Rollback failure discarded recovery evidence.'); $remove($f['root']);
$f = $execute('after_journal_before_mutation'); $assert($f['thrown'], 'Interruption fault was not injected.'); $assert($f['operation']?->phase() === LifecycleOperationRecord::BLOCKED && !$f['operation']->isTerminal(), 'Interrupted operation disposition was not fail-closed.'); $assert($f['installationState']->readMarker() === $f['originalMarker'], 'Pre-mutation interruption changed original marker.'); $assert($f['recoveryRecord']->state() === RecoveryLifecycleState::READY && !$f['recoveryRecord']->mutationStarted(), 'Pre-mutation interruption lost recovery eligibility.'); $assert($f['guard']->scopedActive($f['recovery'], $f['application']), 'Guard was not retained fail-closed after interruption.'); $remove($f['root']);
$f = $execute('after_recovery_post_verification'); $assert($f['thrown'], 'Post-verification interruption was not injected.'); $assert($f['operation']?->phase() === LifecycleOperationRecord::CLEANUP_PENDING, 'Post-verification interruption did not retain cleanup-pending operation evidence.'); $assert($f['store']->read()?->schemaStateIdentity() !== 'stale-schema', 'Post-verification interruption incorrectly rolled back committed metadata.'); $assert($f['recoveryRecord']->postReconciliationVerified() && $f['recoveryRecord']->state() === RecoveryLifecycleState::READY, 'Post-verification interruption lost durable recovery evidence.'); $assert($f['guard']->scopedActive($f['recovery'], $f['application']), 'Post-verification interruption released protection prematurely.'); $remove($f['root']);
$f = $execute('before_operation_clear'); $assert($f['thrown'], 'Operation-clear interruption was not injected.'); $assert($f['operation']?->phase() === LifecycleOperationRecord::CLEANUP_PENDING, 'Operation-clear interruption did not retain cleanup-pending journal evidence.'); $assert($f['store']->read()?->schemaStateIdentity() !== 'stale-schema', 'Operation-clear interruption incorrectly rolled back committed metadata.'); $assert($f['recoveryRecord']->postReconciliationVerified() && $f['recoveryRecord']->state() === RecoveryLifecycleState::CLEANUP_PENDING, 'Operation-clear interruption lost canonical recovery cleanup disposition.'); $assert($f['guard']->scopedActive($f['recovery'], $f['application']), 'Operation-clear interruption released protection prematurely.'); $remove($f['root']);

echo "Metadata reconciliation behavioral fault-injection assertions: {$assertions}" . PHP_EOL;
