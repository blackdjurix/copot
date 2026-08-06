<?php

declare(strict_types=1);

use Copot\Core\BackupRecovery\DatabaseQuiescenceCapability;
use Copot\Core\BackupRecovery\DatabaseQuiescenceLease;
use Copot\Core\BackupRecovery\RecoveryDomainIdentity;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryLifecycleCoordinator;
use Copot\Core\BackupRecovery\RecoveryLifecycleRecord;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;
use Copot\Core\BackupRecovery\RecoveryMaintenanceBoundary;
use Copot\Core\BackupRecovery\RecoveryManifest;
use Copot\Core\BackupRecovery\RecoveryOrchestrator;
use Copot\Core\BackupRecovery\RecoveryStorageRoot;
use Copot\Core\CoreMigrationPlan;
use Copot\Core\CoreMigrationStateIdentity;
use Copot\Core\FilesystemReconciliationAction;
use Copot\Core\InstallationMutex;
use Copot\Core\LegacyClassificationResult;
use Copot\Core\LegacyReconciliationDatabaseResult;
use Copot\Core\LegacyReconciliationFinalizationResult;
use Copot\Core\LegacyReconciliationIntegratedLifecycle;
use Copot\Core\LegacyReconciliationIntegratedResult;
use Copot\Core\LegacyReconciliationPlan;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageContract;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageInventoryVerifier;
use Copot\Core\PackageManifest;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageOwnership;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\ReconciliationMutationEligibility;
use Copot\Core\StagedPayload;
use Copot\Core\StagedFile;
use Copot\Core\StagingSession;
use Copot\Core\TrustedWebcorePackageTarget;
use Copot\Core\WebcoreApplyResult;

$base = dirname(__DIR__); chdir($base); require $base . '/bootstrap/autoload.php';
$assertions = 0;
$assert = static function (bool $ok, string $message) use (&$assertions): void { $assertions++; if (!$ok) throw new RuntimeException($message); };
$remove = static function (string $path) use (&$remove): void { if (is_link($path) || is_file($path)) { @unlink($path); return; } if (is_dir($path)) { foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry); @rmdir($path); } };

final class IU2WU6Lease implements DatabaseQuiescenceLease { private bool $active = true; public function isActive(): bool { return $this->active; } public function release(): void { $this->active = false; } }
final class IU2WU6Quiescence implements DatabaseQuiescenceCapability { public function isAvailable(): bool { return true; } public function acquire(): ?DatabaseQuiescenceLease { return new IU2WU6Lease(); } }
final class IU2WU6Maintenance implements RecoveryMaintenanceBoundary { public bool $active = false; public function enter(RecoveryIdentity $identity): bool { $this->active = true; return true; } public function isActive(RecoveryIdentity $identity): bool { return $this->active; } public function leave(RecoveryIdentity $identity): bool { $this->active = false; return true; } }

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-iu2-wu6-' . bin2hex(random_bytes(6));
mkdir($root . DIRECTORY_SEPARATOR . 'live' . DIRECTORY_SEPARATOR . 'storage', 0700, true); mkdir($root . DIRECTORY_SEPARATOR . 'staging', 0700, true); mkdir($root . DIRECTORY_SEPARATOR . 'recovery', 0700, true);
$live = $root . DIRECTORY_SEPARATOR . 'live';
try {
    $session = StagingSession::create($live, $root . DIRECTORY_SEPARATOR . 'staging');
    mkdir($session->payloadPath() . DIRECTORY_SEPARATOR . 'app', 0700, true); file_put_contents($session->payloadPath() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'x.txt', 'x');
    $payload = new StagedPayload($session, str_repeat('a', 64), [new StagedFile('app/x.txt', 1, hash('sha256', 'x'))]);
    $contract = new PackageContract(PackageContract::WEBCORE_PACKAGE_TYPE, PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION, '0.13.0', 'wu6-release', 'wu6-tree', new PackageCompatibility('0.0.0'), new PackageRuntimeCompatibility('8.0.0', ['sqlite' => '3.0'], ['json']), [new PackageInventoryEntry('app/x.txt', 1, hash('sha256', 'x'), PackageOwnership::PACKAGE_OWNED)], new PackageMigrationDeclaration(false));
    $target = TrustedWebcorePackageTarget::fromManifest(new PackageManifest($contract, '.copot/package.json', $payload), new PackageInventoryVerifier());
    $plan = new LegacyReconciliationPlan($target, LegacyClassificationResult::canonicalBaseline('source-schema', '0.12.0'), [new FilesystemReconciliationAction(FilesystemReconciliationAction::CREATE, 'app/x.txt', 1, hash('sha256', 'x'))], CoreMigrationPlan::allow('0.12.0', '0.13.0', 'source-schema', 'schema-final', [], true), 'pre-state', 'post-state', CoreMigrationStateIdentity::fromRecords([]));
    $identity = new RecoveryIdentity('wu6-original-' . bin2hex(random_bytes(3)));
    $manifest = new RecoveryManifest($identity, $plan->operationIdentity(), $target->packageIdentity(), $contract->releaseIdentity(), $target->archiveIdentity(), $target->payloadIdentity(), [new RecoveryDomainIdentity('database', 'database.webcore', 'db', str_repeat('1', 64)), new RecoveryDomainIdentity('filesystem.package-owned', 'filesystem.package-owned', $plan->identity(), str_repeat('2', 64)), new RecoveryDomainIdentity('lifecycle.committed', 'filesystem.lifecycle.committed', 'lifecycle', str_repeat('3', 64)), new RecoveryDomainIdentity('lifecycle.installed-lock', 'filesystem.lifecycle.installed-lock', 'lock', str_repeat('4', 64))], $plan->preStateIdentity(), CoreMigrationStateIdentity::fromRecords([]));
    $store = new RecoveryLifecycleStore(new RecoveryStorageRoot($live, $root . DIRECTORY_SEPARATOR . 'recovery', str_repeat('5', 64)));
    $confirmation = new \Copot\Core\ReconciliationConfirmation($plan->operationIdentity(), $plan->identity(), $identity, $target->packageIdentity());
    $store->create(new RecoveryLifecycleRecord($identity, $manifest->identity(), $plan->operationIdentity(), RecoveryLifecycleState::READY, false, true, $identity->value(), $manifest->identity(), $confirmation->bindingIdentity()));
    $eligibility = new ReconciliationMutationEligibility($identity, $plan->operationIdentity(), $plan->identity(), $manifest->identity(), $target->packageIdentity(), $confirmation->bindingIdentity(), new IU2WU6Lease(), static function (): void {});
    mkdir($root . DIRECTORY_SEPARATOR . 'mutex', 0700, true); mkdir($root . DIRECTORY_SEPARATOR . 'restore-mutex', 0700, true);
    $maintenance = new IU2WU6Maintenance(); $coordinator = new RecoveryLifecycleCoordinator($store, new InstallationMutex($root . DIRECTORY_SEPARATOR . 'mutex'), new IU2WU6Quiescence(), $maintenance);
    $integrated = new LegacyReconciliationIntegratedLifecycle($store, $coordinator, new RecoveryOrchestrator($store, new InstallationMutex($root . DIRECTORY_SEPARATOR . 'restore-mutex'), new IU2WU6Quiescence(), $maintenance));

    $order = [];
    $clean = $integrated->reconcile($plan, $manifest, $eligibility, static function () use (&$order, $store, $identity): WebcoreApplyResult { $order[] = 'filesystem'; $store->markMutationStarting($identity); return new WebcoreApplyResult(WebcoreApplyResult::COMPLETED, ['app/x.txt']); }, static function () use (&$order): LegacyReconciliationDatabaseResult { $order[] = 'database'; return new LegacyReconciliationDatabaseResult(LegacyReconciliationDatabaseResult::COMPLETED, 'schema-final', CoreMigrationStateIdentity::fromRecords([])); }, static function () use (&$order): LegacyReconciliationFinalizationResult { $order[] = 'finalization'; return new LegacyReconciliationFinalizationResult(LegacyReconciliationFinalizationResult::COMPLETED, new \Copot\Core\HealthGateMatrix([])); });
    $assert($clean->status() === LegacyReconciliationIntegratedResult::COMPLETED && $order === ['filesystem', 'database', 'finalization'], 'Clean WU1-WU5 integrated path did not complete in order.');
    $assert($store->read($identity)->state() === RecoveryLifecycleState::CLEANED, 'Successful integrated reconciliation did not clean the accepted recovery lifecycle.');

    $identity2 = new RecoveryIdentity('wu6-failure-' . bin2hex(random_bytes(3))); $manifest2 = new RecoveryManifest($identity2, $plan->operationIdentity(), $target->packageIdentity(), $contract->releaseIdentity(), $target->archiveIdentity(), $target->payloadIdentity(), $manifest->domainIdentities(), $plan->preStateIdentity(), CoreMigrationStateIdentity::fromRecords([])); $confirmation2 = \Copot\Core\ReconciliationConfirmation::forPlan($plan, $identity2);
    $store->create(new RecoveryLifecycleRecord($identity2, $manifest2->identity(), $plan->operationIdentity(), RecoveryLifecycleState::READY, false, true, $identity2->value(), $manifest2->identity(), $confirmation2->bindingIdentity()));
    $eligibility2 = new ReconciliationMutationEligibility($identity2, $plan->operationIdentity(), $plan->identity(), $manifest2->identity(), $target->packageIdentity(), $confirmation2->bindingIdentity(), new IU2WU6Lease(), static function (): void {});
    $failed = $integrated->reconcile($plan, $manifest2, $eligibility2, static function () use ($store, $identity2): WebcoreApplyResult { $store->markMutationStarting($identity2); throw new RuntimeException('filesystem interruption'); }, static fn (): LegacyReconciliationDatabaseResult => throw new RuntimeException('must not run'), static fn (): LegacyReconciliationFinalizationResult => throw new RuntimeException('must not run'));
    $assert($failed->status() === LegacyReconciliationIntegratedResult::RESTORE_REQUIRED && $store->read($identity2)->state() === RecoveryLifecycleState::RESTORE_REQUIRED, 'Post-mutation interruption did not become RESTORE_REQUIRED.');
    $restored = $integrated->restore($plan, $manifest2, $eligibility2, static function (): void {}, static function (): void {}, static function (): void {}, static function (): void {}, static function () use ($store, $identity2): void { if ($store->read($identity2)->state() !== RecoveryLifecycleState::VERIFYING) throw new RuntimeException('restore verification was not sequenced'); });
    $assert($restored->recoveryIdentity()->equals($identity2) && $restored->state() === RecoveryLifecycleState::RESTORED, 'Restore did not preserve the exact recovery identity.');

    $identity3 = new RecoveryIdentity('wu6-retry-' . bin2hex(random_bytes(3))); $manifest3 = new RecoveryManifest($identity3, $plan->operationIdentity(), $target->packageIdentity(), $contract->releaseIdentity(), $target->archiveIdentity(), $target->payloadIdentity(), $manifest->domainIdentities(), $plan->preStateIdentity(), CoreMigrationStateIdentity::fromRecords([])); $confirmation3 = \Copot\Core\ReconciliationConfirmation::forPlan($plan, $identity3);
    $store->create(new RecoveryLifecycleRecord($identity3, $manifest3->identity(), $plan->operationIdentity(), RecoveryLifecycleState::READY, false, true, $identity3->value(), $manifest3->identity(), $confirmation3->bindingIdentity()));
    $retryEligibility = new ReconciliationMutationEligibility($identity3, $plan->operationIdentity(), $plan->identity(), $manifest3->identity(), $target->packageIdentity(), $confirmation3->bindingIdentity(), new IU2WU6Lease(), static function (): void {});
    $retry = $integrated->retryAfterRestore($plan, $manifest2, $restored, $manifest3, $retryEligibility, static fn (): LegacyReconciliationIntegratedResult => new LegacyReconciliationIntegratedResult(LegacyReconciliationIntegratedResult::COMPLETED, RecoveryLifecycleState::CLEANED));
    $assert($retry->status() === LegacyReconciliationIntegratedResult::COMPLETED, 'Verified restored retry did not complete.');
    $stale = new ReconciliationMutationEligibility($identity2, $plan->operationIdentity(), $plan->identity(), $manifest2->identity(), $target->packageIdentity(), $confirmation->bindingIdentity(), new IU2WU6Lease(), static function (): void {});
    try { $integrated->retryAfterRestore($plan, $manifest2, $restored, $manifest3, $stale, static fn (): LegacyReconciliationIntegratedResult => $retry); $assert(false, 'Stale recovery eligibility was accepted for retry.'); } catch (Throwable) { $assert(true, 'Stale recovery eligibility rejected for retry.'); }
} finally { $remove($root); }

echo "IU2 WU6 integrated lifecycle: {$assertions} assertions passed\n";
