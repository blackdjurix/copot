<?php

declare(strict_types=1);

use Copot\Core\CanonicalTargetInventory;
use Copot\Core\CanonicalTargetInventoryEntry;
use Copot\Core\LiveTreePathGuard;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageContract;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageOwnership;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\StagedFile;
use Copot\Core\StagedPayload;
use Copot\Core\StagingSession;
use Copot\Core\V3SourceRelativeAction;
use Copot\Core\V3SourceRelativePlan;
use Copot\Core\WebcoreApplyPlan;
use Copot\Core\BackupRecovery\FilesystemRecoveryActionLinkageBuilder;
use Copot\Core\BackupRecovery\FilesystemRecoveryActionLinkageCodec;
use Copot\Core\BackupRecovery\FilesystemRecoveryDomain;
use Copot\Core\BackupRecovery\FilesystemRecoveryEntry;
use Copot\Core\BackupRecovery\FilesystemRecoveryPathGuard;
use Copot\Core\BackupRecovery\FilesystemRecoveryPlan;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\RecoveryDomainIdentity;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryManifest;
use Copot\Core\BackupRecovery\RecoveryStorageRoot;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$throws = static function (callable $callback, string $message) use ($assert): void {
    try { $callback(); } catch (Throwable) { return; }
    $assert(false, $message);
};
$hash = static fn (string $bytes): string => hash('sha256', $bytes);
$remove = static function (string $path) use (&$remove): void {
    if (is_file($path) || is_link($path)) { @unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry);
    @rmdir($path);
};

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-action-linkage-' . bin2hex(random_bytes(5));
$live = $root . DIRECTORY_SEPARATOR . 'live';
$storage = $root . DIRECTORY_SEPARATOR . 'storage';
$stage = null;
mkdir($live . DIRECTORY_SEPARATOR . 'app', 0700, true);
mkdir($live . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets', 0700, true);
mkdir($storage, 0700, true);
file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'new.php', 'old');
file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'legacy.php', 'old!');
file_put_contents($live . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'old.css', 'old');

try {
    $target = new CanonicalTargetInventory([
        new CanonicalTargetInventoryEntry('APP_ROOT', CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/new.php', 3, $hash('new')),
        new CanonicalTargetInventoryEntry('APP_ROOT', CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/renamed.php', 4, $hash('next')),
        new CanonicalTargetInventoryEntry('PUBLIC_ROOT', CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'assets/new.css', 3, $hash('css')),
    ]);
    $removal = ['root' => 'APP_ROOT', 'path' => 'app/legacy.php', 'ownership' => PackageOwnership::PACKAGE_OWNED, 'expected_source_byte_size' => 4, 'expected_source_sha256' => $hash('old!'), 'reason' => 'renamed'];
    $rename = ['source_root' => 'APP_ROOT', 'source_path' => 'app/legacy.php', 'target_root' => 'APP_ROOT', 'target_path' => 'app/renamed.php'];
    $package = new PackageContract(
        PackageContract::WEBCORE_PACKAGE_TYPE,
        PackageContract::V3_MANIFEST_CONTRACT_VERSION,
        '0.14.0',
        'action-linkage-release',
        'action-linkage-tree',
        new PackageCompatibility('0.13.0'),
        new PackageRuntimeCompatibility('8.1.0', ['mysql' => '8.0.0'], ['json']),
        [
            new PackageInventoryEntry('app/new.php', 3, $hash('new'), PackageOwnership::PACKAGE_OWNED),
            new PackageInventoryEntry('app/renamed.php', 4, $hash('next'), PackageOwnership::PACKAGE_OWNED),
            new PackageInventoryEntry('public/assets/new.css', 3, $hash('css'), PackageOwnership::PACKAGE_OWNED),
        ],
        new PackageMigrationDeclaration(),
        null,
        $target,
        'full',
        [],
        [$removal],
        [$rename]
    );
    $actions = [
        new V3SourceRelativeAction(V3SourceRelativeAction::REPLACE, 'APP_ROOT', 'app/new.php', 3, $hash('new'), 'APP_ROOT', 'app/new.php', 'replaced'),
        new V3SourceRelativeAction(V3SourceRelativeAction::DELIVER, 'PUBLIC_ROOT', 'assets/new.css', 3, $hash('css')),
        new V3SourceRelativeAction(V3SourceRelativeAction::REMOVE, 'PUBLIC_ROOT', 'assets/old.css', 3, $hash('old'), 'PUBLIC_ROOT', 'assets/old.css', 'obsolete'),
        new V3SourceRelativeAction(V3SourceRelativeAction::DELIVER, 'APP_ROOT', 'app/renamed.php', 4, $hash('next')),
        new V3SourceRelativeAction(V3SourceRelativeAction::RENAME, 'APP_ROOT', 'app/legacy.php', 4, $hash('old!'), 'APP_ROOT', 'app/legacy.php', 'renamed'),
    ];
    $plan = new V3SourceRelativePlan($package, 'action-linkage-package', str_repeat('a', 64), null, $actions, $target->fingerprint(), 'full', []);
    $recoveryPlan = FilesystemRecoveryPlan::fromV3Plan($plan);
    $assert(count($recoveryPlan->entries()) === 5, 'Recovery scope did not include the RENAME source and destination.');
    $assert($recoveryPlan->renameDestination('app/legacy.php') === 'app/renamed.php', 'RENAME destination identity was not retained.');

    $artifactStore = new RecoveryArtifactStore(new RecoveryStorageRoot($storage, $storage . DIRECTORY_SEPARATOR . 'recovery', 'action-linkage-test'));
    $domain = new FilesystemRecoveryDomain(new FilesystemRecoveryPathGuard(new LiveTreePathGuard($live)), $artifactStore);
    $capture = $domain->capture($recoveryPlan);
    $recovery = new RecoveryIdentity('action-linkage-recovery');
    $actualApplyPlanIdentity = hash('sha256', 'actual-webcore-apply-plan');
    $linkage = FilesystemRecoveryActionLinkageBuilder::build($plan, $capture->entries(), 'action-linkage-operation', $recovery, $capture->artifact()->artifactIdentity(), $actualApplyPlanIdentity);
    $assert($linkage !== null && count($linkage->links()) === 5, 'ADD/REPLACE/REMOVE/RENAME actions were not fully linked.');
    $assert($linkage->applyPlanIdentity() === $actualApplyPlanIdentity, 'Action linkage was not bound to the actual WebcoreApplyPlan identity.');
    $assert($linkage->links()[0]->action() === 'replace' && $linkage->links()[0]->originalState() === FilesystemRecoveryEntry::EXISTING_FILE, 'REPLACE original-state evidence was not bound.');
    $assert($linkage->links()[1]->action() === 'deliver' && $linkage->links()[1]->originalState() === FilesystemRecoveryEntry::ABSENT_BEFORE_OPERATION && $linkage->links()[1]->originalArtifactIdentity() === null, 'ADD original-state evidence was not bound.');
    $renameLink = $linkage->links()[4];
    $assert($renameLink->action() === 'rename' && $renameLink->path() === 'app/legacy.php' && $renameLink->originalState() === FilesystemRecoveryEntry::EXISTING_FILE, 'RENAME source linkage was not bound to its pre-image.');
    $decoded = (new FilesystemRecoveryActionLinkageCodec())->decode($linkage->artifactBytes());
    $assert($decoded->identity() === $linkage->identity() && $decoded->operationIdentity() === 'action-linkage-operation', 'Linkage codec changed identity or operation binding.');

    $manifest = new RecoveryManifest(
        $recovery,
        'action-linkage-operation',
        'action-linkage-package',
        '0.14.0',
        $hash('archive'),
        $recoveryPlan->applyPlanIdentity(),
        [$capture->domainIdentity(), $linkage->artifact()->domainIdentifier() === 'filesystem.action-linkage'
            ? new RecoveryDomainIdentity('filesystem.action-linkage', 'filesystem.action-linkage', $linkage->applyPlanIdentity(), $linkage->artifact()->artifactIdentity())
            : throw new RuntimeException('Linkage domain identifier is incorrect.')],
        'pre-lifecycle',
        'pre-ledger'
    );
    $artifactStore->publish($manifest, [
        ['record' => $capture->artifact(), 'bytes' => $capture->artifactBytes()],
        ['record' => $linkage->artifact(), 'bytes' => $linkage->artifactBytes()],
    ]);
    $read = $artifactStore->readManifest($recovery);
    $assert($read['manifest']->identity() === $manifest->identity() && $read['complete'], 'Recovery manifest/domain linkage did not reread complete.');
    $assert((new FilesystemRecoveryActionLinkageCodec())->decode($artifactStore->readArtifact($recovery, $linkage->artifact()))->identity() === $linkage->identity(), 'Published linkage artifact failed independent verification.');

    $badEntries = $capture->entries();
    $badEntries[2] = FilesystemRecoveryEntry::existing('app/renamed.php', 4, $hash('next'), 'next', null);
    $throws(static fn () => FilesystemRecoveryActionLinkageBuilder::build($plan, $badEntries, 'action-linkage-operation', $recovery, $capture->artifact()->artifactIdentity()), 'RENAME destination pre-image mismatch was accepted.');
    $throws(static fn () => new FilesystemRecoveryEntry('public/.htaccess', 3, $hash('hta')), '.htaccess recovery entry was accepted.');
    $stage = StagingSession::create($live, $root . DIRECTORY_SEPARATOR . 'stage');
    $protectedPayload = new StagedPayload($stage, $hash('hta-package'), [new StagedFile('public/.htaccess', 3, $hash('hta'))]);
    $throws(static fn () => FilesystemRecoveryPlan::fromApplyPlan(WebcoreApplyPlan::fromPayload($protectedPayload)), '.htaccess recovery apply scope was accepted.');
    $throws(static fn () => (new FilesystemRecoveryPathGuard(new LiveTreePathGuard($live)))->resolve('public/.htaccess'), '.htaccess recovery restore path was accepted.');

    echo "Action linkage assertions: {$assertions}\n";
} finally {
    if ($stage instanceof StagingSession) $stage->cleanup();
    $remove($root);
}
