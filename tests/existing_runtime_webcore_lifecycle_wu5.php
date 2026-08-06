<?php

declare(strict_types=1);

use Copot\Core\BackupRecovery\RecoveryDomainIdentity;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryLifecycleRecord;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;
use Copot\Core\BackupRecovery\RecoveryManifest;
use Copot\Core\BackupRecovery\RecoveryStorageRoot;
use Copot\Core\CoreMigrationHealthVerifier;
use Copot\Core\CoreMigrationPlan;
use Copot\Core\CoreMigrationRegistry;
use Copot\Core\CoreMigrationStateIdentity;
use Copot\Core\FilesystemReconciliationAction;
use Copot\Core\InstallationState;
use Copot\Core\LegacyClassificationResult;
use Copot\Core\LegacyReconciliationDatabaseResult;
use Copot\Core\LegacyReconciliationFinalizationResult;
use Copot\Core\LegacyReconciliationFinalizer;
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
use Copot\Core\TargetPackageIntegrityVerifier;
use Copot\Core\TrustedWebcorePackageTarget;
use Copot\Core\WebcoreApplyResult;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void { $assertions++; if (!$condition) throw new RuntimeException($message); };
$throws = static function (callable $operation, string $message) use ($assert): void { try { $operation(); $assert(false, $message); } catch (Throwable) { $assert(true, $message . ' (rejected)'); } };
$remove = static function (string $path) use (&$remove): void { if (is_link($path) || is_file($path)) { @unlink($path); return; } if (is_dir($path)) { foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry); @rmdir($path); } };

final class IU2WU5Lease implements \Copot\Core\BackupRecovery\DatabaseQuiescenceLease
{
    public bool $active = true;
    public function isActive(): bool { return $this->active; }
    public function release(): void { $this->active = false; }
}

$makeScenario = static function (string $prefix): array {
    $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . '-' . bin2hex(random_bytes(6));
    $live = $root . DIRECTORY_SEPARATOR . 'live'; $staging = $root . DIRECTORY_SEPARATOR . 'staging'; $recovery = $root . DIRECTORY_SEPARATOR . 'recovery';
    mkdir($live . DIRECTORY_SEPARATOR . 'storage', 0700, true); mkdir($staging, 0700, true); mkdir($recovery, 0700, true);
    $session = StagingSession::create($live, $staging);
    mkdir($session->payloadPath() . DIRECTORY_SEPARATOR . 'app', 0700, true);
    file_put_contents($session->payloadPath() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'final.txt', 'final');
    file_put_contents($live . DIRECTORY_SEPARATOR . 'app-placeholder', 'unused');
    $payload = new StagedPayload($session, str_repeat('a', 64), [new StagedFile('app/final.txt', 5, hash('sha256', 'final'))]);
    $contract = new PackageContract(PackageContract::WEBCORE_PACKAGE_TYPE, PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION, '0.13.0', 'trusted-release', 'trusted-tree', new PackageCompatibility('0.0.0'), new PackageRuntimeCompatibility('8.0.0', ['sqlite' => '3.0'], ['json']), [new PackageInventoryEntry('app/final.txt', 5, hash('sha256', 'final'), PackageOwnership::PACKAGE_OWNED)], new PackageMigrationDeclaration(false));
    $target = TrustedWebcorePackageTarget::fromManifest(new PackageManifest($contract, '.copot/package.json', $payload), new PackageInventoryVerifier());
    mkdir($live . DIRECTORY_SEPARATOR . 'app', 0700, true); file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'final.txt', 'final');
    $plan = new LegacyReconciliationPlan($target, LegacyClassificationResult::canonicalBaseline('source-schema', '0.13.0'), [new FilesystemReconciliationAction(FilesystemReconciliationAction::UNCHANGED, 'app/final.txt', 5, hash('sha256', 'final'), hash('sha256', 'final'))], CoreMigrationPlan::allow('0.13.0', '0.13.0', 'source-schema', 'schema-final', [], true), 'pre', 'post', CoreMigrationStateIdentity::fromRecords([]));
    $identity = new RecoveryIdentity($prefix . '-' . bin2hex(random_bytes(4)));
    $manifest = new RecoveryManifest($identity, $plan->operationIdentity(), $target->packageIdentity(), $contract->releaseIdentity(), $target->archiveIdentity(), $target->payloadIdentity(), [new RecoveryDomainIdentity('database', 'database.webcore', 'db', hash('sha256', 'db')), new RecoveryDomainIdentity('filesystem.package-owned', 'filesystem.package-owned', $plan->identity(), hash('sha256', 'files')), new RecoveryDomainIdentity('lifecycle.committed', 'filesystem.lifecycle.committed', 'lifecycle', hash('sha256', 'lifecycle')), new RecoveryDomainIdentity('lifecycle.installed-lock', 'filesystem.lifecycle.installed-lock', 'installed-lock', hash('sha256', 'lock'))], $plan->preStateIdentity(), CoreMigrationStateIdentity::fromRecords([]));
    $store = new RecoveryLifecycleStore(new RecoveryStorageRoot($live, $recovery, str_repeat('c', 64)));
    $confirmation = ReconciliationConfirmation::forPlan($plan, $identity);
    $store->create(new RecoveryLifecycleRecord($identity, $manifest->identity(), $plan->operationIdentity(), RecoveryLifecycleState::READY, false, true, $identity->value(), $manifest->identity(), $confirmation->bindingIdentity()));
    $eligibility = new ReconciliationMutationEligibility($identity, $plan->operationIdentity(), $plan->identity(), $manifest->identity(), $target->packageIdentity(), $confirmation->bindingIdentity(), new IU2WU5Lease(), static function (): void {});
    $installation = new InstallationState($live . DIRECTORY_SEPARATOR . 'storage'); $installation->createMarker('0.8.0');
    $db = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    foreach (['users','roles','permissions','user_roles','role_permissions','settings','modules','module_permissions','themes','content','taxonomy_types','taxonomy_terms','taxonomy_assignments'] as $table) $db->exec('CREATE TABLE ' . $table . ' (id INTEGER)');
    $db->exec('CREATE TABLE core_migration_history (migration_id TEXT, sequence_number INTEGER, target_webcore_version TEXT, target_schema_identity TEXT, migration_checksum TEXT, applied_at TEXT)');
    $committedStore = new \Copot\Core\CommittedLifecycleStateStore($live . DIRECTORY_SEPARATOR . 'storage');
    $databaseResult = new LegacyReconciliationDatabaseResult(LegacyReconciliationDatabaseResult::COMPLETED, 'schema-final', CoreMigrationStateIdentity::fromRecords([]));
    return [$root, $live, $payload, $plan, $manifest, $eligibility, $store, $installation, $db, $committedStore, $databaseResult];
};

$finalize = static function (array $scenario, ?callable $beforeLifecycleWrite = null): LegacyReconciliationFinalizationResult {
    [$root, $live, $payload, $plan, $manifest, $eligibility, $store, $installation, $db, $committedStore, $databaseResult] = $scenario;
    $finalizer = new LegacyReconciliationFinalizer($store, new TargetPackageIntegrityVerifier(), new \Copot\Core\DatabaseHealthVerifier(), new CoreMigrationHealthVerifier(), new \Copot\Core\RuntimeHealthVerifier());
    return $finalizer->finalize($plan, $manifest, $eligibility, new WebcoreApplyResult(WebcoreApplyResult::COMPLETED, ['app/final.txt']), $databaseResult, $installation, $committedStore, new \Copot\Core\LiveTreePathGuard($live), $db, new CoreMigrationRegistry('empty', []), ['runtime' => static fn (): bool => true], $beforeLifecycleWrite);
};

$scenario = $makeScenario('copot-iu2-wu5-success');
try {
    $observedOrder = [];
    $result = $finalize($scenario, static function () use (&$observedOrder, $scenario): void { $observedOrder[] = $scenario[7]->readMarker()['version']; $observedOrder[] = $scenario[9]->read() === null ? 'lifecycle-absent' : 'lifecycle-present'; });
    $assert($result->status() === LegacyReconciliationFinalizationResult::COMPLETED, 'Exact WU5 finalization did not complete.');
    $assert($observedOrder === ['0.13.0', 'lifecycle-absent'], 'Installed-state was not written before lifecycle state.');
    $assert($scenario[7]->readMarker()['version'] === '0.13.0' && $scenario[9]->read()?->releaseIdentity() === 'trusted-release', 'Final target identities were not committed.');
    $assert((new \Copot\Core\InstalledStateInspector($scenario[9]))->inspect($scenario[7], new \Copot\Core\ExistingInstallEvidence(true, true))->status() === \Copot\Core\InstalledStateStatus::COMMITTED, 'Final state was not recognized as committed.');
    $repeat = $finalize($scenario);
    $assert($repeat->status() === LegacyReconciliationFinalizationResult::ALREADY_FINALIZED, 'Exact repeated finalization was not idempotent.');
} finally { $remove($scenario[0]); }

$scenario = $makeScenario('copot-iu2-wu5-integrity');
try { file_put_contents($scenario[1] . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'final.txt', 'drift'); $result = $finalize($scenario); $assert($result->status() === LegacyReconciliationFinalizationResult::FAILED, 'Integrity failure was accepted.'); $assert($scenario[7]->readMarker()['version'] === '0.8.0' && $scenario[9]->read() === null, 'Integrity failure advanced final state.'); } finally { $remove($scenario[0]); }

$scenario = $makeScenario('copot-iu2-wu5-health');
try { $result = (new LegacyReconciliationFinalizer($scenario[6], new TargetPackageIntegrityVerifier(), new \Copot\Core\DatabaseHealthVerifier(), new CoreMigrationHealthVerifier(), new \Copot\Core\RuntimeHealthVerifier()))->finalize($scenario[3], $scenario[4], $scenario[5], new WebcoreApplyResult(WebcoreApplyResult::COMPLETED), $scenario[10], $scenario[7], $scenario[9], new \Copot\Core\LiveTreePathGuard($scenario[1]), $scenario[8], new CoreMigrationRegistry('empty', []), ['runtime' => static fn (): bool => false]); $assert($result->status() === LegacyReconciliationFinalizationResult::FAILED, 'Health failure was accepted.'); $assert($scenario[7]->readMarker()['version'] === '0.8.0' && $scenario[9]->read() === null, 'Health failure advanced final state.'); } finally { $remove($scenario[0]); }

$scenario = $makeScenario('copot-iu2-wu5-schema');
try { $bad = new LegacyReconciliationDatabaseResult(LegacyReconciliationDatabaseResult::COMPLETED, 'wrong-schema', CoreMigrationStateIdentity::fromRecords([])); $result = (new LegacyReconciliationFinalizer($scenario[6], new TargetPackageIntegrityVerifier(), new \Copot\Core\DatabaseHealthVerifier(), new CoreMigrationHealthVerifier(), new \Copot\Core\RuntimeHealthVerifier()))->finalize($scenario[3], $scenario[4], $scenario[5], new WebcoreApplyResult(WebcoreApplyResult::COMPLETED), $bad, $scenario[7], $scenario[9], new \Copot\Core\LiveTreePathGuard($scenario[1]), $scenario[8], new CoreMigrationRegistry('empty', []), ['runtime' => static fn (): bool => true]); $assert($result->status() === LegacyReconciliationFinalizationResult::FAILED, 'Schema identity mismatch was accepted.'); $assert($scenario[7]->readMarker()['version'] === '0.8.0', 'Schema mismatch advanced installed state.'); } finally { $remove($scenario[0]); }

$scenario = $makeScenario('copot-iu2-wu5-post');
try { $result = $finalize($scenario, static function (): void { throw new RuntimeException('forced lifecycle write failure'); }); $assert($result->status() === LegacyReconciliationFinalizationResult::FAILED, 'Post-marker failure was reported as success.'); $assert($scenario[6]->read($scenario[4]->recoveryIdentity())->state() === RecoveryLifecycleState::RESTORE_REQUIRED, 'Post-marker failure did not preserve recovery-required state.'); $assert($scenario[7]->readMarker()['version'] === '0.13.0' && $scenario[9]->read() === null, 'Post-marker failure ordering was incorrect.'); } finally { $remove($scenario[0]); }

$scenario = $makeScenario('copot-iu2-wu5-released');
try { $scenario[5]->release(); $result = $finalize($scenario); $assert($result->status() === LegacyReconciliationFinalizationResult::FAILED, 'Released eligibility was accepted.'); $assert($scenario[7]->readMarker()['version'] === '0.8.0' && $scenario[9]->read() === null, 'Released eligibility advanced final state.'); } finally { $remove($scenario[0]); }

$scenario = $makeScenario('copot-iu2-wu5-mismatch');
try { $scenario[9]->write(new \Copot\Core\CommittedLifecycleState('0.13.0', 'different-release', 'trusted-tree', 1, 'schema-final', CoreMigrationStateIdentity::fromRecords([]), new DateTimeImmutable('2026-01-01T00:00:00+00:00'), $scenario[3]->target()->inventoryIdentity())); $result = $finalize($scenario); $assert($result->status() === LegacyReconciliationFinalizationResult::FAILED, 'Different committed identity was overwritten.'); $assert($scenario[7]->readMarker()['version'] === '0.8.0', 'Committed identity mismatch advanced installed state.'); } finally { $remove($scenario[0]); }

echo "IU2 WU5 installed-state and lifecycle finalization: {$assertions} assertions passed\n";
