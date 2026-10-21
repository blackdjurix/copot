<?php

declare(strict_types=1);

use Copot\Core\BackupRecovery\ApplicationWriteProtectionEvidence;
use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\BackupRecovery\ScopedDatabaseConsistencyEvidence;
use Copot\Core\BackupRecovery\RecoveryIdentity;
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
use Copot\Core\LifecycleOperationStore;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-metadata-reconciliation-' . bin2hex(random_bytes(6));
mkdir($root, 0700, true);
try {
    $storage = $root . DIRECTORY_SEPARATOR . 'storage';
    mkdir($storage, 0700, true);
    $pdo = new PDO('sqlite:' . $root . DIRECTORY_SEPARATOR . 'fixture.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $tables = new DatabaseTableNames();
    foreach (DatabaseTableOwnershipCatalog::materializedWebcoreLogicalNames() as $logical) {
        $pdo->exec('CREATE TABLE ' . $tables->resolve($logical) . ' (id INTEGER)');
    }
    $pdo->exec('DROP TABLE core_migration_history');
    $pdo->exec('CREATE TABLE core_migration_history (migration_id TEXT, sequence_number INTEGER, target_webcore_version TEXT, target_schema_identity TEXT, migration_checksum TEXT, applied_at TEXT)');
    $registry = CoreMigrationRegistry::forProject($base);
    $migration = $registry->migrations()[0];
    $pdo->exec("INSERT INTO core_migration_history VALUES (" . implode(',', array_map(static fn (string $value): string => $pdo->quote($value), [$migration->id(), (string) $migration->sequence(), $migration->targetWebcoreVersion(), $migration->targetSchemaIdentity(), $migration->checksum(), gmdate('Y-m-d H:i:s')])) . ')');

    $installation = InstallationIdentity::generate();
    $installationState = new InstallationState($storage);
    $installationState->createMarker('0.13.0');
    $committedStore = new CommittedLifecycleStateStore($storage);
    $committed = new CommittedLifecycleState('0.13.0', 'copot-v0.13.0', null, 1, 'canonical-current', hash('sha256', 'stale'), new DateTimeImmutable('2026-10-09T13:39:39+02:00'));
    $committedStore->write($committed);
    $scope = DatabaseScope::fromOwnershipCatalog($installation->value(), 'fixture_db', '', DatabaseTableOwnershipCatalog::current());
    $recovery = new RecoveryIdentity('metadata-test-recovery');
    $now = time();
    $guard = new ApplicationWriteProtectionEvidence($scope, $recovery, hash('sha256', 'writers'), hash('sha256', 'drain'), ['background','cli','http','scheduled'], true, true, $now, $now + 300);
    $database = new ScopedDatabaseConsistencyEvidence($scope, $recovery, hash('sha256', 'writers'), hash('sha256', 'schema'), $now, $now + 300);
    $marker = $installationState->readMarker();
    $evidence = new CommittedLifecycleMetadataReconciliationEvidence(
        $installation, CommittedLifecycleMetadataReconciliationService::stateIdentity($committed), CommittedLifecycleMetadataReconciliationService::markerIdentity($marker),
        $scope, $recovery, hash('sha256', 'recovery-manifest'), $guard, $database, $now, $now + 300
    );
    $service = new CommittedLifecycleMetadataReconciliationService(
        $installationState, $committedStore, new LifecycleOperationStore($storage), new InstallationMutex($storage), $registry,
        new CoreMigrationLedger($tables), new DatabaseHealthVerifier($tables), new CoreMigrationHealthVerifier(new CoreMigrationLedger($tables)), static fn (): PDO => $pdo
    );
    $plan = $service->plan($evidence);
    $assert($plan->operation()->classification() === CommittedLifecycleMetadataReconciliationService::classify(), 'Wrong metadata operation classification.');
    $assert($plan->after()->schemaStateIdentity() === $migration->targetSchemaIdentity(), 'Schema identity was not derived from the applied registry ledger.');
    $assert($plan->after()->migrationStateIdentity() !== $committed->migrationStateIdentity(), 'Stale migration identity was not corrected in the plan.');
    $assert($plan->after()->releaseIdentity() === $committed->releaseIdentity(), 'Release identity changed during metadata planning.');
    $assert($plan->after()->sourceInventoryReference() === null, 'Metadata planning invented source authority.');
    $assert($plan->identity() === $service->plan($evidence)->identity(), 'Metadata plan identity is not deterministic.');

    $stale = new CommittedLifecycleMetadataReconciliationEvidence(
        $installation, hash('sha256', 'other-state'), $evidence->installedMarkerIdentity(), $scope, $recovery,
        $evidence->recoveryManifestIdentity(), $guard, $database, $now, $now + 300
    );
    try { $service->plan($stale); throw new RuntimeException('Stale evidence was accepted.'); } catch (RuntimeException $exception) { $assert(str_contains($exception->getMessage(), 'pre-state'), 'Unexpected stale-evidence failure.'); }
    $assert((new LifecycleOperationStore($storage))->read() === null, 'Planning persisted an operation record.');
    echo "committed_lifecycle_metadata_reconciliation: {$assertions} assertions passed\n";
} finally {
    $remove = static function (string $path) use (&$remove): void {
        if (is_dir($path) && !is_link($path)) { foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry); @rmdir($path); }
        elseif (is_file($path) || is_link($path)) @unlink($path);
    };
    $remove($root);
}
