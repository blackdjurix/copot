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
use Copot\Core\BackupRecovery\RecoveryStorageRoot;
use Copot\Core\CoreMigrationPlan;
use Copot\Core\ExistingInstallEvidence;
use Copot\Core\InstallationMutex;
use Copot\Core\LegacyClassificationResult;
use Copot\Core\LegacyReconciliationPlan;
use Copot\Core\LegacyReconciliationRecoveryOrchestrator;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageContract;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageInventoryVerifier;
use Copot\Core\PackageManifest;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageOwnership;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\ReconciliationConfirmation;
use Copot\Core\StagedFile;
use Copot\Core\StagedPayload;
use Copot\Core\StagingSession;
use Copot\Core\TrustedWebcorePackageTarget;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$throws = static function (callable $operation, string $message) use ($assert): void {
    try { $operation(); $assert(false, $message); } catch (Throwable) { $assert(true, $message . ' (rejected)'); }
};
$remove = static function (string $path) use (&$remove): void {
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (is_dir($path)) { foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry); @rmdir($path); }
};

final class IU2WU2Lease implements DatabaseQuiescenceLease
{
    private bool $active = true;
    public function isActive(): bool { return $this->active; }
    public function release(): void { $this->active = false; }
}
final class IU2WU2Quiescence implements DatabaseQuiescenceCapability
{
    public bool $available = true;
    public int $acquires = 0;
    public ?IU2WU2Lease $lastLease = null;
    public function isAvailable(): bool { return $this->available; }
    public function acquire(): ?DatabaseQuiescenceLease { $this->acquires++; return $this->available ? ($this->lastLease = new IU2WU2Lease()) : null; }
}
final class IU2WU2Maintenance implements RecoveryMaintenanceBoundary
{
    public bool $active = false;
    public function enter(RecoveryIdentity $identity): bool { $this->active = true; return true; }
    public function isActive(RecoveryIdentity $identity): bool { return $this->active; }
    public function leave(RecoveryIdentity $identity): bool { $this->active = false; return true; }
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-iu2-wu2-' . bin2hex(random_bytes(6));
$project = $root . DIRECTORY_SEPARATOR . 'project';
$recoveryRoot = $root . DIRECTORY_SEPARATOR . 'recovery';
$lockRoot = $root . DIRECTORY_SEPARATOR . 'lock';
mkdir($project, 0700, true); mkdir($recoveryRoot, 0700, true); mkdir($lockRoot, 0700, true);

try {
    $session = StagingSession::create($project, $root . DIRECTORY_SEPARATOR . 'staging');
    mkdir($session->payloadPath() . DIRECTORY_SEPARATOR . 'app', 0700, true);
    file_put_contents($session->payloadPath() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'target.txt', 'target');
    $payload = new StagedPayload($session, str_repeat('b', 64), [new StagedFile('app/target.txt', 6, hash('sha256', 'target'))]);
    $contract = new PackageContract(
        PackageContract::WEBCORE_PACKAGE_TYPE,
        PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION,
        '0.13.0',
        'trusted-release',
        null,
        new PackageCompatibility('0.0.0'),
        new PackageRuntimeCompatibility('8.0.0', ['sqlite' => '3.0.0'], ['json']),
        [new PackageInventoryEntry('app/target.txt', 6, hash('sha256', 'target'), PackageOwnership::PACKAGE_OWNED)],
        new PackageMigrationDeclaration(false)
    );
    $target = TrustedWebcorePackageTarget::fromManifest(new PackageManifest($contract, '.copot/package.json', $payload), new PackageInventoryVerifier());
    $plan = new LegacyReconciliationPlan(
        $target,
        LegacyClassificationResult::canonicalBaseline('canonical-schema', '0.8.0'),
        [],
        CoreMigrationPlan::allow('0.8.0', '0.13.0', 'canonical-schema', 'target-schema', [], true),
        'pre-lifecycle',
        'post-lifecycle',
        hash('sha256', 'post-migrations')
    );
    $recoveryIdentity = LegacyReconciliationRecoveryOrchestrator::recoveryIdentity($plan);
    $domains = [
        new RecoveryDomainIdentity('database', 'database.webcore', 'configured-webcore-database', hash('sha256', 'database')),
        new RecoveryDomainIdentity('filesystem.package-owned', 'filesystem.package-owned', $plan->identity(), hash('sha256', 'filesystem')),
        new RecoveryDomainIdentity('lifecycle.committed', 'filesystem.lifecycle.committed', 'committed-state', hash('sha256', 'lifecycle')),
        new RecoveryDomainIdentity('lifecycle.installed-lock', 'filesystem.lifecycle.installed-lock', 'installed-lock', hash('sha256', 'marker')),
    ];
    $manifest = new RecoveryManifest(
        $recoveryIdentity,
        $plan->operationIdentity(),
        $target->packageIdentity(),
        $target->contract()->releaseIdentity(),
        $target->archiveIdentity(),
        $target->payloadIdentity(),
        $domains,
        $plan->preStateIdentity(),
        \Copot\Core\CoreMigrationStateIdentity::fromRecords([])
    );
    $quiescence = new IU2WU2Quiescence();
    $maintenance = new IU2WU2Maintenance();
    $store = new RecoveryLifecycleStore(new RecoveryStorageRoot($project, $recoveryRoot, str_repeat('c', 64)));
    $coordinator = new RecoveryLifecycleCoordinator($store, new InstallationMutex($lockRoot), $quiescence, $maintenance);
    $orchestrator = new LegacyReconciliationRecoveryOrchestrator($coordinator, $store, $quiescence);
    $confirmation = ReconciliationConfirmation::forPlan($plan, $recoveryIdentity);
    $captureCalls = 0; $verifyCalls = 0;
    $eligibility = $orchestrator->prepare($plan, $manifest, static function (RecoveryManifest $value) use (&$captureCalls, $manifest): void { $captureCalls++; if ($value->identity() !== $manifest->identity()) throw new RuntimeException('Manifest changed during capture.'); }, static function (RecoveryManifest $value) use (&$verifyCalls): void { $verifyCalls++; }, $confirmation);
    $assert($eligibility->isValid(), 'Valid WU2 eligibility was not returned.');
    $assert($captureCalls === 1 && $verifyCalls === 1, 'Recovery capture/readiness was not performed exactly once.');
    $assert($store->read($recoveryIdentity)->state() === RecoveryLifecycleState::READY, 'Recovery did not reach READY.');
    $assert(!$store->read($recoveryIdentity)->mutationStarted(), 'WU2 crossed the mutation boundary.');
    $assert($maintenance->active && $quiescence->acquires === 1, 'Maintenance/quiescence was not held for eligibility.');
    $assert($eligibility->planIdentity() === $plan->identity() && $eligibility->targetIdentity() === $target->packageIdentity(), 'Eligibility identities were incomplete.');
    $eligibility->release();
    $assert(!$eligibility->isValid() && !$maintenance->active, 'Eligibility release did not close the guarded interval.');

    $wrongConfirmation = new ReconciliationConfirmation($plan->operationIdentity(), $plan->identity(), $recoveryIdentity, 'operator-fabricated-target');
    $throws(static fn (): ReconciliationMutationEligibility => $orchestrator->prepare($plan, $manifest, static function (): void {}, static function (): void {}, $wrongConfirmation), 'Mismatched target confirmation was accepted.');

    $unavailable = new IU2WU2Quiescence(); $unavailable->available = false;
    $blockedStore = new RecoveryLifecycleStore(new RecoveryStorageRoot($project, $recoveryRoot . '-blocked', str_repeat('d', 64)));
    $blockedMaintenance = new IU2WU2Maintenance();
    $blocked = new LegacyReconciliationRecoveryOrchestrator(new RecoveryLifecycleCoordinator($blockedStore, new InstallationMutex($lockRoot), $unavailable, $blockedMaintenance), $blockedStore, $unavailable);
    $throws(static fn (): ReconciliationMutationEligibility => $blocked->prepare($plan, $manifest, static function (): void {}, static function (): void {}, $confirmation), 'Unavailable quiescence was accepted.');
    $assert(!$blockedMaintenance->active, 'Failed quiescence left maintenance active.');
    $assert(!$blockedStore->read($recoveryIdentity)->mutationStarted(), 'Failed quiescence fabricated post-mutation state.');
    $assert(!file_exists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'target.txt'), 'WU2 mutated the package filesystem.');
    $payload->cleanup();
} finally {
    $remove($root);
}

echo "IU2 WU2 recovery, confirmation, and quiescence: {$assertions} assertions passed\n";
