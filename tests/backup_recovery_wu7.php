<?php

declare(strict_types=1);

use Copot\Core\BackupRecovery\DatabaseCaptureContext;
use Copot\Core\BackupRecovery\DatabaseRestoreAttemptContext;
use Copot\Core\BackupRecovery\DatabaseRestoreContext;
use Copot\Core\BackupRecovery\DatabaseQuiescenceCapability;
use Copot\Core\BackupRecovery\DatabaseQuiescenceLease;
use Copot\Core\BackupRecovery\FilesystemRecoveryDomain;
use Copot\Core\BackupRecovery\FilesystemRecoveryPathGuard;
use Copot\Core\BackupRecovery\FilesystemRecoveryPlan;
use Copot\Core\BackupRecovery\InstalledLockRecoveryDomain;
use Copot\Core\BackupRecovery\LifecycleRecoveryDomain;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\RecoveryDomainIdentity;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryLifecycleCoordinator;
use Copot\Core\BackupRecovery\RecoveryLifecycleException;
use Copot\Core\BackupRecovery\RecoveryLifecycleRecord;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryMaintenanceBoundary;
use Copot\Core\BackupRecovery\RecoveryManifest;
use Copot\Core\BackupRecovery\RecoveryMutationPermit;
use Copot\Core\BackupRecovery\RecoveryOrchestrator;
use Copot\Core\BackupRecovery\RecoveryRootResolver;
use Copot\Core\BackupRecovery\RecoveryStateVerification;
use Copot\Core\BackupRecovery\RecoveryStorageRoot;
use Copot\Core\BackupRecovery\RecoveryStorageException;
use Copot\Core\BackupRecovery\MySqlRecoveryProvider;
use Copot\Core\CommittedLifecycleState;
use Copot\Core\CommittedLifecycleStateStore;
use Copot\Core\Env;
use Copot\Core\InstallationMutex;
use Copot\Core\InstallationState;
use Copot\Core\LiveTreePathGuard;
use Copot\Core\PackageOwnership;
use Copot\Core\StagedFile;
use Copot\Core\StagedPayload;
use Copot\Core\StagingSession;
use Copot\Core\WebcoreApplyPlan;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';
Env::load($base . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void { $assertions++; if (!$condition) throw new RuntimeException($message); };
$throws = static function (callable $callback, string $message) use ($assert): void { try { $callback(); $assert(false, $message); } catch (Throwable) { $assert(true, $message); } };
$remove = static function (string $path) use (&$remove): void { if (is_link($path) || is_file($path)) { @unlink($path); return; } if (is_dir($path)) { foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry); @rmdir($path); } };
$mkdir = static function (string $path): void { if (!mkdir($path, 0700, true) && !is_dir($path)) throw new RuntimeException('Fixture directory could not be created.'); };
$hash = static fn (string $bytes): string => hash('sha256', $bytes);

final class WU7Lease implements DatabaseQuiescenceLease { private bool $active = true; public function isActive(): bool { return $this->active; } public function release(): void { $this->active = false; } }
final class WU7Quiescence implements DatabaseQuiescenceCapability { public bool $available = true; public int $acquires = 0; public ?WU7Lease $lease = null; public function isAvailable(): bool { return $this->available; } public function acquire(): ?DatabaseQuiescenceLease { $this->acquires++; return $this->available ? ($this->lease = new WU7Lease()) : null; } }
final class WU7Maintenance implements RecoveryMaintenanceBoundary { public bool $active = false; public function enter(RecoveryIdentity $id): bool { $this->active = true; return true; } public function isActive(RecoveryIdentity $id): bool { return $this->active; } public function leave(RecoveryIdentity $id): bool { $this->active = false; return true; } }

$fixture = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu7-' . bin2hex(random_bytes(6));
$live = $fixture . DIRECTORY_SEPARATOR . 'live';
$storage = $live . DIRECTORY_SEPARATOR . 'storage';
$configuredRecovery = $fixture . DIRECTORY_SEPARATOR . 'private-recovery';
$stagingRoot = $fixture . DIRECTORY_SEPARATOR . 'staging';
$applyRoot = $fixture . DIRECTORY_SEPARATOR . 'apply-temp';
$documentRoot = $fixture . DIRECTORY_SEPARATOR . 'document-root';
$dbName = 'copot_wu7_' . bin2hex(random_bytes(5));
$server = null;

try {
    $mkdir($live . DIRECTORY_SEPARATOR . 'app'); $mkdir($storage); $mkdir($configuredRecovery); $mkdir($stagingRoot); $mkdir($applyRoot); $mkdir($documentRoot);
    file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'existing.txt', 'pre-operation-bytes');
    file_put_contents($live . DIRECTORY_SEPARATOR . 'operator.txt', 'operator-owned');
    $throws(static fn () => (new RecoveryRootResolver($live, null))->resolve(), 'Missing configured recovery root was rejected.');
    $throws(static fn () => (new RecoveryRootResolver($live, $documentRoot, [], [$documentRoot]))->resolve(), 'Document-root overlap was rejected.');
    $root = (new RecoveryRootResolver($live, $configuredRecovery, [$stagingRoot, $applyRoot]))->resolve();
    $rootAgain = (new RecoveryRootResolver($live, $configuredRecovery, [$stagingRoot, $applyRoot]))->resolve();
    $assert($root->projectIdentity() === $rootAgain->projectIdentity() && $root->path() === $rootAgain->path(), 'Project recovery isolation was not deterministic.');
    $store = new RecoveryArtifactStore($root);
    $guard = new FilesystemRecoveryPathGuard(new LiveTreePathGuard($live));

    $session = StagingSession::create($live, $stagingRoot);
    $payloadRoot = $session->payloadPath() . DIRECTORY_SEPARATOR . 'app'; $mkdir($payloadRoot);
    file_put_contents($payloadRoot . DIRECTORY_SEPARATOR . 'existing.txt', 'mutated-target-bytes');
    file_put_contents($payloadRoot . DIRECTORY_SEPARATOR . 'created.txt', 'created-by-operation');
    $payload = new StagedPayload($session, $hash('wu7-archive'), [new StagedFile('app/existing.txt', 20, $hash('mutated-target-bytes')), new StagedFile('app/created.txt', 20, $hash('created-by-operation'))]);
    $applyPlan = WebcoreApplyPlan::fromPayload($payload);
    $filesystemPlan = FilesystemRecoveryPlan::fromApplyPlan($applyPlan);
    $filesystemDomain = new FilesystemRecoveryDomain($guard, $store);
    $filesystemCapture = $filesystemDomain->capture($filesystemPlan);
    $assert($filesystemCapture->domainIdentity()->identifier() === 'filesystem.package-owned', 'Package filesystem domain was not captured.');

    $server = new PDO('mysql:host=' . Env::get('DB_HOST', '127.0.0.1') . ';port=' . Env::get('DB_PORT', 3306) . ';charset=utf8mb4', (string)Env::get('DB_USERNAME', 'root'), (string)Env::get('DB_PASSWORD', ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $quoted = '`' . $dbName . '`'; $server->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $makeDb = static fn (): PDO => new PDO('mysql:host=' . Env::get('DB_HOST', '127.0.0.1') . ';port=' . Env::get('DB_PORT', 3306) . ';dbname=' . $dbName . ';charset=utf8mb4', (string)Env::get('DB_USERNAME', 'root'), (string)Env::get('DB_PASSWORD', ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $db = $makeDb();
    $db->exec('CREATE TABLE parent (id INT UNSIGNED NOT NULL AUTO_INCREMENT, label VARCHAR(64) NOT NULL, payload BLOB NULL, PRIMARY KEY(id), UNIQUE KEY uq_label(label), KEY ix_label(label)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $db->exec('CREATE TABLE core_migration_history (migration_id VARCHAR(191) NOT NULL PRIMARY KEY, sequence_number INT UNSIGNED NOT NULL UNIQUE, target_webcore_version VARCHAR(64) NOT NULL, target_schema_identity VARCHAR(191) NOT NULL, migration_checksum CHAR(64) NOT NULL, applied_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $db->exec("INSERT INTO parent(label,payload) VALUES ('α', X'0001FF')");
    $db->exec("INSERT INTO core_migration_history VALUES ('wu7-ledger',1,'0.10.0','schema-wu7',REPEAT('a',64),'2026-08-06 12:00:00')");
    $provider = new MySqlRecoveryProvider();
    $databaseCapture = $provider->capture(new DatabaseCaptureContext($makeDb(), $makeDb(), $dbName));
    $assert($databaseCapture->record()->domainIdentifier() === 'database.webcore' && $provider->verifyCaptured($databaseCapture)->isValid(), 'Database domain capture or verification failed.');

    $installation = new InstallationState($storage); $installation->createMarker('0.10.0'); $markerBefore = $installation->readMarker();
    $lifecycleStore = new CommittedLifecycleStateStore($storage);
    $lifecycleState = new CommittedLifecycleState('0.10.0', 'release-before', 'tree-before', 1, 'schema-wu7', $databaseCapture->migrationLedgerIdentity(), new DateTimeImmutable($markerBefore['installed_at']), 'package-before');
    $lifecycleStore->write($lifecycleState);
    $lifecycleDomain = new LifecycleRecoveryDomain($lifecycleStore, $guard); $markerDomain = new InstalledLockRecoveryDomain($installation, $guard);
    $lifecycleCapture = $lifecycleDomain->capture(); $markerCapture = $markerDomain->capture();
    $assert($lifecycleCapture->stateKind() === 'PRESENT_COMMITTED_STATE', 'Present lifecycle state was not captured.');

    $recoveryIdentity = new RecoveryIdentity('wu7-' . bin2hex(random_bytes(4)));
    $domainIdentities = [
        new RecoveryDomainIdentity('database.webcore', 'database.webcore', hash('sha256', $dbName), $databaseCapture->record()->artifactIdentity()),
        $filesystemCapture->domainIdentity(),
        new RecoveryDomainIdentity('filesystem.lifecycle.committed', 'filesystem.lifecycle.committed', 'committed-state', $lifecycleCapture->identity()),
        new RecoveryDomainIdentity('filesystem.lifecycle.installed-lock', 'filesystem.lifecycle.installed-lock', 'installed-lock', $markerCapture->identity()),
    ];
    $manifest = new RecoveryManifest($recoveryIdentity, 'wu7-operation', 'copot-webcore', 'release-target', $hash('wu7-archive'), $applyPlan->identity(), $domainIdentities, $lifecycleCapture->identity(), $databaseCapture->migrationLedgerIdentity());
    $store->publish($manifest, [
        ['record' => $databaseCapture->record(), 'bytes' => $databaseCapture->bytes()],
        ['record' => $filesystemCapture->artifact(), 'bytes' => $filesystemCapture->artifactBytes()],
        ['record' => $lifecycleCapture->record(), 'bytes' => $lifecycleCapture->bytes()],
        ['record' => $markerCapture->record(), 'bytes' => $markerCapture->bytes()],
    ]);
    $assert($store->readManifest($recoveryIdentity)['complete'] === true, 'Four-domain immutable manifest was not verified.');

    $lifecycleRoot = $root;
    $lifecycleStoreW6 = new Copot\Core\BackupRecovery\RecoveryLifecycleStore($lifecycleRoot);
    $maintenance = new WU7Maintenance(); $quiescence = new WU7Quiescence();
    $coordinator = new RecoveryLifecycleCoordinator($lifecycleStoreW6, new InstallationMutex($storage), $quiescence, $maintenance);
    $orchestrationIdentity = new RecoveryIdentity('wu7-orchestration-' . bin2hex(random_bytes(3)));
    $record = new RecoveryLifecycleRecord($orchestrationIdentity, $store->readManifest($recoveryIdentity)['identity'], 'wu7-operation', RecoveryLifecycleState::CREATED, false, false, $orchestrationIdentity->value(), $store->readManifest($recoveryIdentity)['identity'], 'release-target');
    $orchestrator = new RecoveryOrchestrator($lifecycleStoreW6, new InstallationMutex($storage), $quiescence, $maintenance);
    $mutated = false; $verified = false;
    $final = $orchestrator->captureAndAuthorize($record, static function () use (&$filesystemCapture, &$databaseCapture, &$lifecycleCapture, &$markerCapture): void { if (!$filesystemCapture || !$databaseCapture || !$lifecycleCapture || !$markerCapture) throw new RuntimeException('A recovery domain was missing.'); }, static function (): void {}, static function (): void {}, 'release-target', static function (RecoveryMutationPermit $permit) use (&$mutated): void { if (!$permit->isValid()) throw new RuntimeException('Mutation permit was invalid.'); $mutated = true; }, static function () use (&$verified): void { $verified = true; });
    $assert($mutated && $verified && $final->state() === RecoveryLifecycleState::CLEANED, 'Complete capture-to-confirmation-to-mutation lifecycle did not complete.');
    $restoreRecord = new RecoveryLifecycleRecord($recoveryIdentity, $store->readManifest($recoveryIdentity)['identity'], 'wu7-operation', RecoveryLifecycleState::READY, true, true, $recoveryIdentity->value(), $store->readManifest($recoveryIdentity)['identity'], 'release-target');
    $lifecycleStoreW6->create($restoreRecord);
    $assert($quiescence->lease !== null && !$quiescence->lease->isActive(), 'Quiescence lease was not released after verification.');

    file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'existing.txt', 'mutated-target-bytes'); file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'created.txt', 'created-by-operation'); file_put_contents($live . DIRECTORY_SEPARATOR . 'operator.txt', 'operator-data-preserved');
    $mutatedState = new CommittedLifecycleState('0.11.0', 'release-target', 'tree-target', 1, 'schema-wu7', $databaseCapture->migrationLedgerIdentity(), new DateTimeImmutable($markerBefore['installed_at'])); $lifecycleStore->write($mutatedState); $installation->replaceMarker('0.11.0', $markerBefore['installed_at']);
    $db->exec("UPDATE parent SET label='mutated'"); $db->exec('DELETE FROM core_migration_history');
    $targetDb = $provider->stateIdentity($db, $dbName); $targetTables = $provider->tableSetIdentity($db, $dbName);
    $attempt = new DatabaseRestoreAttemptContext($recoveryIdentity, 'wu7-attempt', $targetDb, $targetTables, DatabaseRestoreAttemptContext::PREPARED, ['core_migration_history', 'parent'], static function (string $stage) use ($coordinator, $recoveryIdentity): void { $coordinator->recordRestoreStage($recoveryIdentity, 'wu7-attempt', $stage); });
    $coordinator->transition($recoveryIdentity, RecoveryLifecycleState::RESTORE_REQUIRED);
    $coordinator->beginRestore($recoveryIdentity);
    $provider->restoreFromStore($recoveryIdentity, $databaseCapture->record(), $store, new DatabaseRestoreContext($makeDb(), $makeDb(), $dbName, $attempt));
    $assert($provider->verifyRestored($databaseCapture, new DatabaseRestoreContext($makeDb(), $makeDb(), $dbName))->isValid(), 'Database restore did not verify exact state.');
    $fsResult = $filesystemDomain->restore($recoveryIdentity, $filesystemCapture->artifact(), $filesystemPlan); $assert($fsResult->status() === 'completed', 'Filesystem restore did not complete: ' . $fsResult->reason());
    $lifecycleDomain->restore($lifecycleCapture, (new Copot\Core\BackupRecovery\LifecycleRecoveryArtifactCodec())->artifactFromState($mutatedState)->identity());
    $markerDomain->restore($markerCapture, $markerDomain->capture()->identity());
    $assert(file_get_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'existing.txt') === 'pre-operation-bytes' && !file_exists($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'created.txt') && file_get_contents($live . DIRECTORY_SEPARATOR . 'operator.txt') === 'operator-data-preserved', 'Filesystem restore did not preserve exact/unrelated state.');
    $databaseVerification = $provider->verifyRestored($databaseCapture, new DatabaseRestoreContext($makeDb(), $makeDb(), $dbName));
    $crossDomain = (new RecoveryStateVerification())->verify($manifest, $lifecycleCapture, $markerCapture, $databaseVerification);
    $assert($crossDomain->passed(), 'Cross-domain lifecycle, marker, and migration verification failed.');
    $coordinator->beginVerification($recoveryIdentity); $coordinator->markRestored($recoveryIdentity); $coordinator->markCleanupPending($recoveryIdentity, 'release-target'); $coordinator->markCleaned($recoveryIdentity);
    $assert($installation->readMarker() === $markerBefore && $lifecycleStore->read()?->toArray() === $lifecycleState->toArray(), 'Lifecycle and installed.lock did not restore independently.');

    $absentStore = new CommittedLifecycleStateStore($storage); $absentStore->remove(); $absent = (new LifecycleRecoveryDomain($absentStore, $guard))->capture(); $absentState = new CommittedLifecycleState('0.11.0', 'created', 'tree', 1, 'schema', $databaseCapture->migrationLedgerIdentity(), new DateTimeImmutable($markerBefore['installed_at'])); $absentStore->write($absentState); (new LifecycleRecoveryDomain($absentStore, $guard))->restore($absent, (new Copot\Core\BackupRecovery\LifecycleRecoveryArtifactCodec())->artifactFromState($absentState)->identity()); $assert($absentStore->read() === null, 'Explicit lifecycle absence was not restored.');

    $failureIdentity = new RecoveryIdentity('wu7-failure-' . bin2hex(random_bytes(3))); $failureRecord = new RecoveryLifecycleRecord($failureIdentity, str_repeat('f', 64), 'failure-operation', RecoveryLifecycleState::CREATED); $failureOrchestrator = new RecoveryOrchestrator($lifecycleStoreW6, new InstallationMutex($storage), new WU7Quiescence(), new WU7Maintenance()); try { $failureOrchestrator->captureAndAuthorize($failureRecord, static function (): void { throw new RuntimeException('capture failure'); }, static function (): void {}, static function (): void {}, 'target', static function (): void {}, static function (): void {}); } catch (Throwable) {} $assert($lifecycleStoreW6->read($failureIdentity)->state() === RecoveryLifecycleState::FAILED_BEFORE_MUTATION, 'Capture failure was not classified before mutation.');
    $throws(static fn () => $coordinator->markReconciliationCleanupPending($recoveryIdentity, $manifest->identity(), 'release-target'), 'Forward cleanup was rejected without READY verification state.');
    $assert($store->readManifest($recoveryIdentity)['complete'] === true, 'Immutable recovery evidence was deleted during cleanup.');
    echo "Backup & Recovery WU7 acceptance tests passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    try { if ($server instanceof PDO) $server->exec('DROP DATABASE IF EXISTS `' . $dbName . '`'); } catch (Throwable) {}
    $remove($fixture);
}
