<?php

declare(strict_types=1);

use Copot\Core\BackupRecovery\RecoveryDomainIdentity;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryLifecycleRecord;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;
use Copot\Core\BackupRecovery\RecoveryManifest;
use Copot\Core\BackupRecovery\RecoveryStorageRoot;
use Copot\Core\CoreMigrationPlan;
use Copot\Core\FilesystemReconciliationAction;
use Copot\Core\LegacyClassificationResult;
use Copot\Core\LegacyReconciliationFilesystemConverger;
use Copot\Core\LegacyReconciliationPlan;
use Copot\Core\LiveFileActivationCapability;
use Copot\Core\LiveTreePathGuard;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageContract;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageInventoryVerifier;
use Copot\Core\PackageManifest;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageOwnedFileApplier;
use Copot\Core\PackageOwnership;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\ReconciliationMutationEligibility;
use Copot\Core\StagedFile;
use Copot\Core\StagedPayload;
use Copot\Core\StagingSession;
use Copot\Core\TrustedWebcorePackageTarget;
use Copot\Core\WebcoreApplyResult;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void { $assertions++; if (!$condition) throw new RuntimeException($message); };
$throws = static function (callable $operation, string $message) use ($assert): void { try { $operation(); $assert(false, $message); } catch (Throwable) { $assert(true, $message . ' (rejected)'); } };
$remove = static function (string $path) use (&$remove): void { if (is_link($path) || is_file($path)) { @unlink($path); return; } if (is_dir($path)) { foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry); @rmdir($path); } };

final class IU2WU3Lease implements \Copot\Core\BackupRecovery\DatabaseQuiescenceLease
{
    private bool $active = true;
    public function isActive(): bool { return $this->active; }
    public function release(): void { $this->active = false; }
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-iu2-wu3-' . bin2hex(random_bytes(6));
$live = $root . DIRECTORY_SEPARATOR . 'live';
$stagingRoot = $root . DIRECTORY_SEPARATOR . 'staging';
$recoveryRoot = $root . DIRECTORY_SEPARATOR . 'recovery';
mkdir($live, 0700, true); mkdir($stagingRoot, 0700, true); mkdir($recoveryRoot, 0700, true);

try {
    mkdir($live . DIRECTORY_SEPARATOR . 'app', 0700, true);
    file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'replace.txt', 'old');
    file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'unchanged.txt', 'same');
    $session = StagingSession::create($live, $stagingRoot);
    mkdir($session->payloadPath() . DIRECTORY_SEPARATOR . 'app', 0700, true);
    file_put_contents($session->payloadPath() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'replace.txt', 'new');
    file_put_contents($session->payloadPath() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'create.txt', 'made');
    file_put_contents($session->payloadPath() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'unchanged.txt', 'same');
    $hash = static fn (string $value): string => hash('sha256', $value);
    $payload = new StagedPayload($session, str_repeat('a', 64), [
        new StagedFile('app/replace.txt', 3, $hash('new')),
        new StagedFile('app/create.txt', 4, $hash('made')),
        new StagedFile('app/unchanged.txt', 4, $hash('same')),
    ]);
    $package = new PackageContract(
        PackageContract::WEBCORE_PACKAGE_TYPE,
        PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION,
        '0.13.0', 'trusted-release', null,
        new PackageCompatibility('0.0.0'),
        new PackageRuntimeCompatibility('8.0.0', ['sqlite' => '3.0.0'], ['json']),
        [
            new PackageInventoryEntry('app/replace.txt', 3, $hash('new'), PackageOwnership::PACKAGE_OWNED),
            new PackageInventoryEntry('app/create.txt', 4, $hash('made'), PackageOwnership::PACKAGE_OWNED),
            new PackageInventoryEntry('app/unchanged.txt', 4, $hash('same'), PackageOwnership::PACKAGE_OWNED),
        ],
        new PackageMigrationDeclaration(false)
    );
    $target = TrustedWebcorePackageTarget::fromManifest(new PackageManifest($package, '.copot/package.json', $payload), new PackageInventoryVerifier());
    $plan = new LegacyReconciliationPlan(
        $target,
        LegacyClassificationResult::canonicalBaseline('canonical-schema', '0.8.0'),
        [
            new FilesystemReconciliationAction(FilesystemReconciliationAction::CREATE, 'app/create.txt', 4, $hash('made')),
            new FilesystemReconciliationAction(FilesystemReconciliationAction::REPLACE, 'app/replace.txt', 3, $hash('new'), $hash('old')),
            new FilesystemReconciliationAction(FilesystemReconciliationAction::UNCHANGED, 'app/unchanged.txt', 4, $hash('same'), $hash('same')),
        ],
        CoreMigrationPlan::allow('0.8.0', '0.13.0', 'canonical-schema', 'target-schema', [], true),
        'pre-state', 'post-state', hash('sha256', 'migration-state')
    );
    $store = new RecoveryLifecycleStore(new RecoveryStorageRoot($live, $recoveryRoot, str_repeat('c', 64)));
    $guard = new LiveTreePathGuard($live);
    $applier = new PackageOwnedFileApplier($guard, new LiveFileActivationCapability(true, true), $root . DIRECTORY_SEPARATOR . 'apply');
    $converger = new LegacyReconciliationFilesystemConverger($store, $applier, $guard);

    $manifestFor = static function (LegacyReconciliationPlan $value, RecoveryIdentity $identity, TrustedWebcorePackageTarget $trusted): RecoveryManifest {
        return new RecoveryManifest($identity, $value->operationIdentity(), $trusted->packageIdentity(), $trusted->contract()->releaseIdentity(), $trusted->archiveIdentity(), $trusted->payloadIdentity(), [
            new RecoveryDomainIdentity('database', 'database.webcore', 'db', hash('sha256', 'db')),
            new RecoveryDomainIdentity('filesystem.package-owned', 'filesystem.package-owned', $value->identity(), hash('sha256', 'files')),
            new RecoveryDomainIdentity('lifecycle.committed', 'filesystem.lifecycle.committed', 'lifecycle', hash('sha256', 'lifecycle')),
            new RecoveryDomainIdentity('lifecycle.installed-lock', 'filesystem.lifecycle.installed-lock', 'installed-lock', hash('sha256', 'lock')),
        ], $value->preStateIdentity(), \Copot\Core\CoreMigrationStateIdentity::fromRecords([]));
    };
    $eligibilityFor = static function (LegacyReconciliationPlan $value, RecoveryManifest $manifest, RecoveryLifecycleStore $lifecycleStore) use ($assert): ReconciliationMutationEligibility {
        $identity = $manifest->recoveryIdentity();
        $confirmation = \Copot\Core\ReconciliationConfirmation::forPlan($value, $identity);
        $lifecycleStore->create(new RecoveryLifecycleRecord($identity, $manifest->identity(), $value->operationIdentity(), RecoveryLifecycleState::READY, false, true, $identity->value(), $manifest->identity(), $confirmation->bindingIdentity()));
        $lease = new IU2WU3Lease();
        return new ReconciliationMutationEligibility($identity, $value->operationIdentity(), $value->identity(), $manifest->identity(), $value->target()->packageIdentity(), $confirmation->bindingIdentity(), $lease, static function (): void {});
    };

    $identity = new RecoveryIdentity('wu3-success-' . bin2hex(random_bytes(4)));
    $manifest = $manifestFor($plan, $identity, $target);
    $eligibility = $eligibilityFor($plan, $manifest, $store);
    $unchangedBefore = filemtime($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'unchanged.txt');
    $result = $converger->converge($plan, $manifest, $eligibility, $payload);
    clearstatcache();
    $assert($result->status() === WebcoreApplyResult::COMPLETED, 'CREATE/REPLACE convergence did not complete.');
    $assert(file_get_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'create.txt') === 'made', 'CREATE did not write the package-owned file.');
    $assert(file_get_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'replace.txt') === 'new', 'REPLACE did not update the package-owned file.');
    $assert(file_get_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'unchanged.txt') === 'same', 'UNCHANGED content changed.');
    $assert(filemtime($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'unchanged.txt') === $unchangedBefore, 'UNCHANGED file was rewritten.');
    $assert($store->read($identity)->mutationStarted() && $store->read($identity)->state() === RecoveryLifecycleState::READY, 'Mutation-start was not recorded at the accepted boundary.');
    $eligibility->release();

    $throws(static fn (): WebcoreApplyResult => $converger->converge($plan, $manifest, $eligibility, $payload), 'Released eligibility was accepted.');
    $throws(static fn (): FilesystemReconciliationAction => new FilesystemReconciliationAction('delete', 'app/old.txt', 1, $hash('x')), 'Stale deletion action was accepted.');
    $throws(static fn (): FilesystemReconciliationAction => new FilesystemReconciliationAction(FilesystemReconciliationAction::CREATE, '../escape.txt', 1, $hash('x')), 'Traversal action was accepted.');
    $throws(static fn (): PackageInventoryEntry => new PackageInventoryEntry('.env', 1, $hash('x'), PackageOwnership::PACKAGE_OWNED), 'Operator-owned inventory path was accepted.');

    $invalidIdentity = new RecoveryIdentity('wu3-invalid-' . bin2hex(random_bytes(4)));
    $invalidManifest = $manifestFor($plan, $invalidIdentity, $target);
    $invalidEligibility = $eligibilityFor($plan, $invalidManifest, $store);
    file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'create.txt', 'already');
    $before = file_get_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'replace.txt');
    $throws(static fn (): WebcoreApplyResult => $converger->converge($plan, $invalidManifest, $invalidEligibility, $payload), 'Unexpected CREATE drift was accepted.');
    $assert(file_get_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'replace.txt') === $before, 'Pre-mutation drift rejection changed another file.');
    $assert(!$store->read($invalidIdentity)->mutationStarted(), 'Pre-mutation failure crossed mutation-start.');

    @unlink($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'create.txt');
    file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'replace.txt', 'drift');
    $driftIdentity = new RecoveryIdentity('wu3-drift-' . bin2hex(random_bytes(4)));
    $driftManifest = $manifestFor($plan, $driftIdentity, $target);
    $driftEligibility = $eligibilityFor($plan, $driftManifest, $store);
    $throws(static fn (): WebcoreApplyResult => $converger->converge($plan, $driftManifest, $driftEligibility, $payload), 'Unexpected REPLACE live drift was accepted.');
    $assert(!$store->read($driftIdentity)->mutationStarted(), 'REPLACE drift rejection crossed mutation-start.');
    file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'replace.txt', 'old');

    $postIdentity = new RecoveryIdentity('wu3-post-' . bin2hex(random_bytes(4)));
    $postManifest = $manifestFor($plan, $postIdentity, $target);
    $postEligibility = $eligibilityFor($plan, $postManifest, $store);
    $postResult = $converger->converge($plan, $postManifest, $postEligibility, $payload, static function (int $cursor): void { if ($cursor === 1) throw new RuntimeException('forced post-start failure'); });
    $assert($postResult->status() === WebcoreApplyResult::BLOCKED, 'Post-mutation failure was not blocked.');
    $assert($store->read($postIdentity)->state() === RecoveryLifecycleState::RESTORE_REQUIRED, 'Post-mutation failure did not enter RESTORE_REQUIRED.');
    $assert($store->read($postIdentity)->mutationStarted(), 'Post-mutation recovery state lost mutation-start.');
    $assert($store->read($postIdentity)->recoveryIdentity()->value() === $postIdentity->value(), 'Recovery identity changed after post-mutation failure.');

    if (function_exists('symlink')) {
        @unlink($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'create.txt');
        $outside = $root . DIRECTORY_SEPARATOR . 'outside.txt'; file_put_contents($outside, 'outside');
        if (@symlink($outside, $live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'create.txt')) {
            $linkIdentity = new RecoveryIdentity('wu3-link-' . bin2hex(random_bytes(4)));
            $linkManifest = $manifestFor($plan, $linkIdentity, $target);
            $linkEligibility = $eligibilityFor($plan, $linkManifest, $store);
            $throws(static fn (): WebcoreApplyResult => $converger->converge($plan, $linkManifest, $linkEligibility, $payload), 'Symlink destination escape was accepted.');
            $assert(!$store->read($linkIdentity)->mutationStarted(), 'Symlink rejection crossed mutation-start.');
        }
    }
    $payload->cleanup();
} finally { $remove($root); }

echo "IU2 WU3 guarded filesystem convergence: {$assertions} assertions passed\n";
