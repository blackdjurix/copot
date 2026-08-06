<?php

declare(strict_types=1);

use Copot\Core\BackupRecovery\RecoveryDomainIdentity;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryLifecycleRecord;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;
use Copot\Core\BackupRecovery\RecoveryManifest;
use Copot\Core\BackupRecovery\RecoveryStorageRoot;
use Copot\Core\CoreMigrationDescriptor;
use Copot\Core\CoreMigrationHealthVerifier;
use Copot\Core\CoreMigrationLedger;
use Copot\Core\CoreMigrationPlan;
use Copot\Core\CoreMigrationRegistry;
use Copot\Core\CoreMigrationRunner;
use Copot\Core\CoreMigrationStateIdentity;
use Copot\Core\FilesystemReconciliationAction;
use Copot\Core\LegacyClassificationResult;
use Copot\Core\LegacyReconciliationDatabaseReconciler;
use Copot\Core\LegacyReconciliationDatabaseResult;
use Copot\Core\LegacyReconciliationPlan;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageContract;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageInventoryVerifier;
use Copot\Core\PackageManifest;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageOwnership;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\ReconciliationConfirmation;
use Copot\Core\ReconciliationMutationEligibility;
use Copot\Core\StagedFile;
use Copot\Core\StagedPayload;
use Copot\Core\StagingSession;
use Copot\Core\TrustedWebcorePackageTarget;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void { $assertions++; if (!$condition) throw new RuntimeException($message); };
$throws = static function (callable $operation, string $message) use ($assert): void { try { $operation(); $assert(false, $message); } catch (Throwable) { $assert(true, $message . ' (rejected)'); } };
$remove = static function (string $path) use (&$remove): void { if (is_link($path) || is_file($path)) { @unlink($path); return; } if (is_dir($path)) { foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry); @rmdir($path); } };

final class IU2WU4Lease implements \Copot\Core\BackupRecovery\DatabaseQuiescenceLease
{
    public bool $active = true;
    public function isActive(): bool { return $this->active; }
    public function release(): void { $this->active = false; }
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-iu2-wu4-' . bin2hex(random_bytes(6));
$live = $root . DIRECTORY_SEPARATOR . 'live';
$recoveryRoot = $root . DIRECTORY_SEPARATOR . 'recovery';
$stagingRoot = $root . DIRECTORY_SEPARATOR . 'staging';
$schemaPath = $root . DIRECTORY_SEPARATOR . 'canonical.sql';
mkdir($live, 0700, true); mkdir($recoveryRoot, 0700, true); mkdir($stagingRoot, 0700, true);

try {
    $canonicalSql = "CREATE TABLE `users` (\n  `id` TEXT\n);\nCREATE TABLE `core_migration_history` (\n  `migration_id` TEXT,\n  `sequence_number` INTEGER,\n  `target_webcore_version` TEXT,\n  `target_schema_identity` TEXT,\n  `migration_checksum` TEXT,\n  `applied_at` TEXT\n);";
    file_put_contents($schemaPath, $canonicalSql);
    $canonicalIdentity = 'canonical-schema:' . hash('sha256', $schemaPath === '' ? '' : (string)file_get_contents($schemaPath));
    $makeConnection = static function (): PDO { $pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); $pdo->exec('CREATE TABLE users (id TEXT); CREATE TABLE core_migration_history (migration_id TEXT, sequence_number INTEGER, target_webcore_version TEXT, target_schema_identity TEXT, migration_checksum TEXT, applied_at TEXT);'); return $pdo; };
    $descriptor = static function (string $id, int $sequence, string $source, string $target, string $schema, callable $execute, ?callable $post = null): CoreMigrationDescriptor { return new CoreMigrationDescriptor($id, $sequence, $source, null, $target, $schema, CoreMigrationDescriptor::TRANSACTIONAL, 'source:' . $id, $execute, null, $post); };
    $migrationA = $descriptor('core.a', 10, '0.13.0', '0.14.0', 'schema-14', static function (PDO $db): void { $db->exec('CREATE TABLE migrated_a (id INTEGER)'); }, static function (PDO $db): bool { return (bool)$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='migrated_a'")->fetchColumn(); });
    $migrationB = $descriptor('core.b', 20, '0.14.0', '0.15.0', 'schema-15', static function (PDO $db): void { $db->exec('CREATE TABLE migrated_b (id INTEGER)'); }, static function (PDO $db): bool { return (bool)$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='migrated_b'")->fetchColumn(); });
    $registry = new CoreMigrationRegistry('core-iu2-wu4', [$migrationA, $migrationB]);
    $session = StagingSession::create($live, $stagingRoot);
    mkdir($session->payloadPath() . DIRECTORY_SEPARATOR . 'app', 0700, true);
    file_put_contents($session->payloadPath() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'marker.txt', 'marker');
    $payload = new StagedPayload($session, str_repeat('a', 64), [new StagedFile('app/marker.txt', 6, hash('sha256', 'marker'))]);
    $package = new PackageContract(PackageContract::WEBCORE_PACKAGE_TYPE, PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION, '0.15.0', 'trusted-release', null, new PackageCompatibility('0.0.0'), new PackageRuntimeCompatibility('8.0.0', ['sqlite' => '3.0.0'], ['json']), [new PackageInventoryEntry('app/marker.txt', 6, hash('sha256', 'marker'), PackageOwnership::PACKAGE_OWNED)], new PackageMigrationDeclaration(true, 'core-iu2-wu4'));
    $target = TrustedWebcorePackageTarget::fromManifest(new PackageManifest($package, '.copot/package.json', $payload), new PackageInventoryVerifier());
    $store = new RecoveryLifecycleStore(new RecoveryStorageRoot($live, $recoveryRoot, str_repeat('c', 64)));
    $ledger = new CoreMigrationLedger();
    $reconciler = new LegacyReconciliationDatabaseReconciler($ledger, new CoreMigrationRunner($ledger), new \Copot\Core\CanonicalSchemaBaselineVerifier(), new CoreMigrationHealthVerifier());
    $schemaIdentity = static function (PDO $db) use ($canonicalIdentity): string { $a = (bool)$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='migrated_a'")->fetchColumn(); $b = (bool)$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='migrated_b'")->fetchColumn(); return $b ? 'schema-15' : ($a ? 'schema-14' : $canonicalIdentity); };
    $makePlan = static function (TrustedWebcorePackageTarget $trusted, LegacyClassificationResult $classification, CoreMigrationPlan $migrationPlan): LegacyReconciliationPlan { return new LegacyReconciliationPlan($trusted, $classification, [], $migrationPlan, 'pre-db', 'post-db', CoreMigrationStateIdentity::fromRecordsAndDescriptors($classification->records(), $migrationPlan->migrations())); };
    $manifestFor = static function (LegacyReconciliationPlan $plan, RecoveryIdentity $identity): RecoveryManifest { $target = $plan->target(); return new RecoveryManifest($identity, $plan->operationIdentity(), $target->packageIdentity(), $target->contract()->releaseIdentity(), $target->archiveIdentity(), $target->payloadIdentity(), [new RecoveryDomainIdentity('database', 'database.webcore', 'db', hash('sha256', 'db')), new RecoveryDomainIdentity('filesystem.package-owned', 'filesystem.package-owned', $plan->identity(), hash('sha256', 'files')), new RecoveryDomainIdentity('lifecycle.committed', 'filesystem.lifecycle.committed', 'lifecycle', hash('sha256', 'lifecycle')), new RecoveryDomainIdentity('lifecycle.installed-lock', 'filesystem.lifecycle.installed-lock', 'installed-lock', hash('sha256', 'lock'))], $plan->preStateIdentity(), CoreMigrationStateIdentity::fromRecords($plan->classification()->records())); };
    $eligibilityFor = static function (LegacyReconciliationPlan $plan, RecoveryManifest $manifest, RecoveryLifecycleStore $lifecycleStore): ReconciliationMutationEligibility { $confirmation = ReconciliationConfirmation::forPlan($plan, $manifest->recoveryIdentity()); $lifecycleStore->create(new RecoveryLifecycleRecord($manifest->recoveryIdentity(), $manifest->identity(), $plan->operationIdentity(), RecoveryLifecycleState::READY, false, true, $manifest->recoveryIdentity()->value(), $manifest->identity(), $confirmation->bindingIdentity())); $lease = new IU2WU4Lease(); return new ReconciliationMutationEligibility($manifest->recoveryIdentity(), $plan->operationIdentity(), $plan->identity(), $manifest->identity(), $plan->target()->packageIdentity(), $confirmation->bindingIdentity(), $lease, static function (): void {}); };

    $canonicalDb = $makeConnection();
    $canonicalPlan = $makePlan($target, LegacyClassificationResult::canonicalBaseline($canonicalIdentity, '0.13.0'), CoreMigrationPlan::allow('0.13.0', '0.15.0', $canonicalIdentity, 'schema-15', [$migrationA, $migrationB]));
    $canonicalIdentityForRecovery = new RecoveryIdentity('wu4-canonical-' . bin2hex(random_bytes(4)));
    $canonicalManifest = $manifestFor($canonicalPlan, $canonicalIdentityForRecovery);
    $canonicalEligibility = $eligibilityFor($canonicalPlan, $canonicalManifest, $store);
    $canonicalResult = $reconciler->reconcile($canonicalDb, $canonicalPlan, $canonicalManifest, $canonicalEligibility, $registry, $schemaPath, $schemaIdentity, $store);
    $assert($canonicalResult->status() === LegacyReconciliationDatabaseResult::COMPLETED, 'Canonical baseline reconciliation did not complete.');
    $assert($canonicalResult->schemaIdentity() === 'schema-15' && $canonicalResult->migrationStateIdentity() === $canonicalPlan->expectedMigrationStateIdentity(), 'Canonical final identities were not verified.');
    $assert((int)$canonicalDb->query('SELECT COUNT(*) FROM core_migration_history')->fetchColumn() === 2, 'Canonical reconciliation did not record only forward migrations.');
    $canonicalEligibility->release();

    $emptyUnproven = $makeConnection(); $emptyUnproven->exec('DROP TABLE users');
    $unknownPlan = $makePlan($target, LegacyClassificationResult::canonicalBaseline($canonicalIdentity, '0.13.0'), CoreMigrationPlan::allow('0.13.0', '0.15.0', $canonicalIdentity, 'schema-15', [$migrationA, $migrationB]));
    $unknownId = new RecoveryIdentity('wu4-unproven-' . bin2hex(random_bytes(4))); $unknownManifest = $manifestFor($unknownPlan, $unknownId); $unknownEligibility = $eligibilityFor($unknownPlan, $unknownManifest, $store);
    $throws(static fn (): LegacyReconciliationDatabaseResult => $reconciler->reconcile($emptyUnproven, $unknownPlan, $unknownManifest, $unknownEligibility, $registry, $schemaPath, $schemaIdentity, $store), 'Unproven empty history was accepted.');
    $assert(!$store->read($unknownId)->mutationStarted(), 'Unproven baseline crossed mutation-start.');

    $prefixDb = $makeConnection(); $prefixDb->exec('CREATE TABLE migrated_a (id INTEGER)'); $ledger->record($prefixDb, $migrationA);
    $prefixRecords = $ledger->records($prefixDb);
    $prefixPlan = $makePlan($target, LegacyClassificationResult::knownMigrationPrefix($prefixRecords), CoreMigrationPlan::allow('0.14.0', '0.15.0', 'schema-14', 'schema-15', [$migrationB]));
    $prefixId = new RecoveryIdentity('wu4-prefix-' . bin2hex(random_bytes(4))); $prefixManifest = $manifestFor($prefixPlan, $prefixId); $prefixEligibility = $eligibilityFor($prefixPlan, $prefixManifest, $store);
    $prefixResult = $reconciler->reconcile($prefixDb, $prefixPlan, $prefixManifest, $prefixEligibility, $registry, $schemaPath, $schemaIdentity, $store);
    $assert($prefixResult->status() === LegacyReconciliationDatabaseResult::COMPLETED && $prefixResult->appliedMigrationIds() === ['core.b'], 'Known prefix did not execute only the forward suffix.');
    $assert((int)$prefixDb->query('SELECT COUNT(*) FROM core_migration_history')->fetchColumn() === 2, 'Known prefix ledger was not preserved and extended.');
    $prefixEligibility->release();

    $badDb = $makeConnection(); $badDb->exec("INSERT INTO core_migration_history VALUES ('core.unknown', 10, '0.14.0', 'schema-14', '" . str_repeat('b', 64) . "', '2026-01-01')");
    $badId = new RecoveryIdentity('wu4-bad-prefix-' . bin2hex(random_bytes(4))); $badManifest = $manifestFor($prefixPlan, $badId); $badEligibility = $eligibilityFor($prefixPlan, $badManifest, $store);
    $throws(static fn (): LegacyReconciliationDatabaseResult => $reconciler->reconcile($badDb, $prefixPlan, $badManifest, $badEligibility, $registry, $schemaPath, $schemaIdentity, $store), 'Unknown migration history was accepted.');
    $assert(!$store->read($badId)->mutationStarted(), 'Invalid prefix crossed mutation-start.');

    $failureDb = $makeConnection(); $failureDb->exec('CREATE TABLE migrated_a (id INTEGER)'); $ledger->record($failureDb, $migrationA);
    $failingB = $descriptor('core.b-failing', 20, '0.14.0', '0.15.0', 'schema-15', static function (PDO $db): void { $db->exec('CREATE TABLE failed_b (id INTEGER)'); }, static function (): bool { return false; });
    $failureRegistry = new CoreMigrationRegistry('core-iu2-wu4', [$migrationA, $failingB]);
    $failurePlan = $makePlan($target, LegacyClassificationResult::knownMigrationPrefix($ledger->records($failureDb)), CoreMigrationPlan::allow('0.14.0', '0.15.0', 'schema-14', 'schema-15', [$failingB]));
    $failureId = new RecoveryIdentity('wu4-failure-' . bin2hex(random_bytes(4))); $failureManifest = $manifestFor($failurePlan, $failureId); $failureEligibility = $eligibilityFor($failurePlan, $failureManifest, $store);
    $failureResult = $reconciler->reconcile($failureDb, $failurePlan, $failureManifest, $failureEligibility, $failureRegistry, $schemaPath, $schemaIdentity, $store);
    $assert($failureResult->status() === LegacyReconciliationDatabaseResult::FAILED, 'Failed migration did not return FAILED.');
    $assert($store->read($failureId)->state() === RecoveryLifecycleState::RESTORE_REQUIRED && $store->read($failureId)->mutationStarted(), 'Failed migration did not preserve recovery-required state.');
    $assert((int)$failureDb->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='failed_b'")->fetchColumn() === 0, 'Failed transactional migration left schema mutation behind.');
    $assert((int)$failureDb->query('SELECT COUNT(*) FROM core_migration_history')->fetchColumn() === 1, 'Failed migration advanced the ledger.');

    $releasedId = new RecoveryIdentity('wu4-released-' . bin2hex(random_bytes(4))); $releasedManifest = $manifestFor($prefixPlan, $releasedId); $releasedEligibility = $eligibilityFor($prefixPlan, $releasedManifest, $store); $releasedEligibility->release(); $throws(static fn (): LegacyReconciliationDatabaseResult => $reconciler->reconcile($prefixDb, $prefixPlan, $releasedManifest, $releasedEligibility, $registry, $schemaPath, $schemaIdentity, $store), 'Released eligibility was accepted.');
    $assert(!$store->read($releasedId)->mutationStarted(), 'Released eligibility crossed mutation-start.');
    $payload->cleanup();
} finally { $remove($root); }

echo "IU2 WU4 schema and migration reconciliation: {$assertions} assertions passed\n";
