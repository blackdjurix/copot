<?php

use Copot\Core\CanonicalTargetInventory;
use Copot\Core\CanonicalTargetInventoryEntry;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageContract;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageOwnership;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\V3SourceRelativeAction;
use Copot\Core\V3SourceRelativePlan;
use Copot\Core\BackupRecovery\FilesystemRecoveryActionLinkageBuilder;
use Copot\Core\BackupRecovery\FilesystemRecoveryActionLinkageCodec;
use Copot\Core\BackupRecovery\FilesystemRecoveryPlan;
use Copot\Core\BackupRecovery\FilesystemRecoveryEntry;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\RecoveryStorageRoot;
use Copot\Core\BackupRecovery\RecoveryManifest;
use Copot\Core\BackupRecovery\RecoveryDomainIdentity;
use Copot\Core\BackupRecovery\RecoveryStoragePathPolicy;

$base = dirname(__DIR__);
require $base . '/bootstrap/autoload.php';
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void { $assertions++; if (!$condition) throw new RuntimeException($message); };
$hash = static fn (string $bytes): string => hash('sha256', $bytes);

$target = new CanonicalTargetInventory([new CanonicalTargetInventoryEntry(CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/new.php', 3, $hash('new'))]);
$package = new PackageContract(PackageContract::WEBCORE_PACKAGE_TYPE, PackageContract::V3_MANIFEST_CONTRACT_VERSION, '0.14.0', 'ordinal-release', 'ordinal-tree', new PackageCompatibility('0.13.0'), new PackageRuntimeCompatibility('8.1.0', ['mysql' => '8.0.0'], ['json']), [new PackageInventoryEntry('app/new.php', 3, $hash('new'), PackageOwnership::PACKAGE_OWNED)], new PackageMigrationDeclaration(), null, $target, 'full');
$actions = [
    new V3SourceRelativeAction(V3SourceRelativeAction::REPLACE, CanonicalTargetInventoryEntry::APP_ROOT, 'app/new.php', 3, $hash('new'), CanonicalTargetInventoryEntry::APP_ROOT, 'app/old.php', 'replaced'),
    new V3SourceRelativeAction(V3SourceRelativeAction::DELIVER, CanonicalTargetInventoryEntry::PUBLIC_ROOT, 'assets/new.css', 3, $hash('css')),
    new V3SourceRelativeAction(V3SourceRelativeAction::REMOVE, CanonicalTargetInventoryEntry::PUBLIC_ROOT, 'assets/old.css', 3, $hash('old-css'), CanonicalTargetInventoryEntry::PUBLIC_ROOT, 'assets/old.css', 'obsolete'),
    new V3SourceRelativeAction(V3SourceRelativeAction::RENAME, CanonicalTargetInventoryEntry::APP_ROOT, 'app/renamed.php', 4, $hash('next'), CanonicalTargetInventoryEntry::APP_ROOT, 'app/legacy.php', 'renamed'),
];
$plan = new V3SourceRelativePlan($package, 'package-evidence', str_repeat('a', 64), null, $actions, $target->fingerprint(), 'full', []);
$recovery = new RecoveryIdentity('ordinal-recovery');
$entries = [
    FilesystemRecoveryEntry::existing('app/old.php', 3, $hash('new'), 'old', null),
    FilesystemRecoveryEntry::existing('public/assets/old.css', 3, $hash('old-css'), 'old', null),
    FilesystemRecoveryEntry::existing('app/legacy.php', 4, $hash('next'), 'old!', null),
];
$applyPlan = FilesystemRecoveryPlan::fromV3Plan($plan);
$artifactIdentity = str_repeat('b', 64);
$linkage = FilesystemRecoveryActionLinkageBuilder::build($plan, $entries, 'ordinal-operation', $recovery, $artifactIdentity);
$assert(count($linkage->links()) === 3, 'All destructive actions were not linked.');
$assert(array_map(static fn ($link): int => $link->ordinal(), $linkage->links()) === [0, 2, 3], 'Canonical action ordinals were renumbered or sorted independently.');
$assert($linkage->links()[0]->path() === 'app/old.php' && $linkage->links()[1]->root() === CanonicalTargetInventoryEntry::PUBLIC_ROOT && $linkage->links()[2]->path() === 'app/legacy.php', 'APP_ROOT/PUBLIC_ROOT source mapping changed.');
$codec = new FilesystemRecoveryActionLinkageCodec();
$encoded = $codec->encode($linkage);
$decoded = $codec->decode($encoded);
$assert($decoded->identity() === $linkage->identity(), 'Immutable linkage reread changed identity.');
$assert($decoded->links()[0]->recoveryIdentity()->equals($recovery), 'Recovery identity was lost during linkage reread.');
$storage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-action-ordinal-' . bin2hex(random_bytes(4));
mkdir($storage, 0700, true);
$artifactStore = new RecoveryArtifactStore(new RecoveryStorageRoot($storage, $storage . DIRECTORY_SEPARATOR . 'recovery', 'action-ordinal-test'));
$manifest = new RecoveryManifest($recovery, 'ordinal-operation', 'ordinal-package', '0.14.0', $hash('archive'), $linkage->applyPlanIdentity(), [new RecoveryDomainIdentity('filesystem.action-linkage', 'filesystem.action-linkage', $linkage->applyPlanIdentity(), $linkage->artifact()->artifactIdentity())], 'lifecycle', 'ledger');
$artifactStore->publish($manifest, [['record' => $linkage->artifact(), 'bytes' => $linkage->artifactBytes()]]);
$readManifest = $artifactStore->readManifest($recovery);
$assert($readManifest['manifest']->identity() === $manifest->identity() && $readManifest['complete'], 'Published action linkage manifest did not reread as complete.');
$assert($codec->decode($artifactStore->readArtifact($recovery, $linkage->artifact()))->identity() === $linkage->identity(), 'Published action linkage artifact failed independent reread.');
$artifactStore->publish($manifest, [['record' => $linkage->artifact(), 'bytes' => $linkage->artifactBytes()]]);
$artifactPath = (new RecoveryStoragePathPolicy(new RecoveryStorageRoot($storage, $storage . DIRECTORY_SEPARATOR . 'recovery', 'action-ordinal-test')))->artifactPath($recovery, $linkage->artifact());
file_put_contents($artifactPath, 'corrupt');
try { $artifactStore->readManifest($recovery); $assert(false, 'Corrupted published action linkage was accepted.'); } catch (Throwable) { $assert(true, 'Corrupted published action linkage failed closed.'); }
$remove = static function (string $path) use (&$remove): void { if (is_file($path) || is_link($path)) { @unlink($path); return; } if (!is_dir($path)) return; foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry); @rmdir($path); };
$remove($storage);
$corrupted = substr($encoded, 0, -2) . 'xx';
try { $codec->decode($corrupted); $assert(false, 'Corrupted linkage was accepted.'); } catch (Throwable) { $assert(true, 'Corrupted linkage failed closed.'); }
$duplicate = $actions;
$duplicate[] = new V3SourceRelativeAction(V3SourceRelativeAction::REMOVE, CanonicalTargetInventoryEntry::APP_ROOT, 'app/old.php', 3, $hash('new'), CanonicalTargetInventoryEntry::APP_ROOT, 'app/old.php', 'obsolete');
$duplicatePlan = new V3SourceRelativePlan($package, 'package-evidence', str_repeat('a', 64), null, $duplicate, $target->fingerprint(), 'full', []);
try { FilesystemRecoveryActionLinkageBuilder::build($duplicatePlan, $entries, 'ordinal-operation', $recovery, $artifactIdentity); $assert(false, 'Repeated destructive path was accepted.'); } catch (Throwable) { $assert(true, 'Repeated destructive path failed closed.'); }
try { FilesystemRecoveryActionLinkageBuilder::build($plan, array_slice($entries, 1), 'ordinal-operation', $recovery, $artifactIdentity); $assert(false, 'Missing original-byte evidence was accepted.'); } catch (Throwable) { $assert(true, 'Missing original-byte evidence failed closed.'); }
echo "Action ordinal assertions: {$assertions}\n";
