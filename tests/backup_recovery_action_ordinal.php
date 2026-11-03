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
use Copot\Core\BackupRecovery\RecoveryArtifactRecord;
use Copot\Core\BackupRecovery\FilesystemRecoveryBundleCodec;
use Copot\Core\BackupRecovery\FilesystemRecoveryDomain;
use Copot\Core\BackupRecovery\FilesystemRecoveryPathGuard;
use Copot\Core\LiveTreePathGuard;

$base = dirname(__DIR__);
require $base . '/bootstrap/autoload.php';
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void { $assertions++; if (!$condition) throw new RuntimeException($message); };
$hash = static fn (string $bytes): string => hash('sha256', $bytes);

$target = new CanonicalTargetInventory([new CanonicalTargetInventoryEntry(CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/new.php', 3, $hash('new'))]);
$package = new PackageContract(PackageContract::WEBCORE_PACKAGE_TYPE, PackageContract::V3_MANIFEST_CONTRACT_VERSION, '0.14.0', 'ordinal-release', 'ordinal-tree', new PackageCompatibility('0.13.0'), new PackageRuntimeCompatibility('8.1.0', ['mysql' => '8.0.0'], ['json']), [new PackageInventoryEntry('app/new.php', 3, $hash('new'), PackageOwnership::PACKAGE_OWNED)], new PackageMigrationDeclaration(), null, $target, 'full');
$actions = [
    new V3SourceRelativeAction(V3SourceRelativeAction::REPLACE, CanonicalTargetInventoryEntry::APP_ROOT, 'app/new.php', 3, $hash('new'), CanonicalTargetInventoryEntry::APP_ROOT, 'app/new.php', 'replaced'),
    new V3SourceRelativeAction(V3SourceRelativeAction::DELIVER, CanonicalTargetInventoryEntry::PUBLIC_ROOT, 'assets/new.css', 3, $hash('css')),
    new V3SourceRelativeAction(V3SourceRelativeAction::REMOVE, CanonicalTargetInventoryEntry::PUBLIC_ROOT, 'assets/old.css', 3, $hash('old-css'), CanonicalTargetInventoryEntry::PUBLIC_ROOT, 'assets/old.css', 'obsolete'),
    new V3SourceRelativeAction(V3SourceRelativeAction::DELIVER, CanonicalTargetInventoryEntry::APP_ROOT, 'app/renamed.php', 4, $hash('next')),
    new V3SourceRelativeAction(V3SourceRelativeAction::RENAME, CanonicalTargetInventoryEntry::APP_ROOT, 'app/renamed.php', 4, $hash('next'), CanonicalTargetInventoryEntry::APP_ROOT, 'app/legacy.php', 'renamed'),
];
$plan = new V3SourceRelativePlan($package, 'package-evidence', str_repeat('a', 64), null, $actions, $target->fingerprint(), 'full', []);
$recovery = new RecoveryIdentity('ordinal-recovery');
$entries = [
    FilesystemRecoveryEntry::existing('app/new.php', 3, $hash('new'), 'old', null),
    FilesystemRecoveryEntry::absent('public/assets/new.css', 3, $hash('css')),
    FilesystemRecoveryEntry::existing('public/assets/old.css', 3, $hash('old-css'), 'old', null),
    FilesystemRecoveryEntry::absent('app/renamed.php', 4, $hash('next')),
    FilesystemRecoveryEntry::existing('app/legacy.php', 4, $hash('next'), 'old!', null),
];
$applyPlan = FilesystemRecoveryPlan::fromV3Plan($plan);
$artifactIdentity = str_repeat('b', 64);
$linkage = FilesystemRecoveryActionLinkageBuilder::build($plan, $entries, 'ordinal-operation', $recovery, $artifactIdentity);
$assert(count($linkage->links()) === 5, 'All mutation actions were not linked.');
$assert(array_map(static fn ($link): int => $link->ordinal(), $linkage->links()) === [0, 1, 2, 3, 4], 'Canonical action ordinals were renumbered or sorted independently.');
$assert($linkage->links()[0]->path() === 'app/new.php' && $linkage->links()[1]->path() === 'assets/new.css' && $linkage->links()[2]->root() === CanonicalTargetInventoryEntry::PUBLIC_ROOT && $linkage->links()[3]->path() === 'app/renamed.php' && $linkage->links()[4]->path() === 'app/legacy.php', 'Mutation path mapping changed.');
$assert($linkage->links()[1]->action() === V3SourceRelativeAction::DELIVER && $linkage->links()[1]->originalState() === FilesystemRecoveryEntry::ABSENT_BEFORE_OPERATION && $linkage->links()[1]->originalArtifactIdentity() === null, 'ADD did not record an absent original without a backup artifact.');
$assert($linkage->links()[0]->originalState() === FilesystemRecoveryEntry::EXISTING_FILE && $linkage->links()[0]->originalArtifactIdentity() === $artifactIdentity, 'REPLACE did not retain original-byte artifact binding.');
$assert($linkage->links()[3]->originalState() === FilesystemRecoveryEntry::ABSENT_BEFORE_OPERATION && $linkage->links()[4]->originalState() === FilesystemRecoveryEntry::EXISTING_FILE, 'RENAME did not bind target absence and source original bytes.');
$assert(count($applyPlan->entries()) === 5 && $applyPlan->entries()[0]->path() === 'app/legacy.php' && $applyPlan->entries()[1]->path() === 'app/new.php' && $applyPlan->entries()[2]->path() === 'app/renamed.php', 'RENAME recovery scope does not cover both paths.');
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
$duplicate[] = new V3SourceRelativeAction(V3SourceRelativeAction::REMOVE, CanonicalTargetInventoryEntry::APP_ROOT, 'app/new.php', 3, $hash('new'), CanonicalTargetInventoryEntry::APP_ROOT, 'app/new.php', 'obsolete');
$duplicatePlan = new V3SourceRelativePlan($package, 'package-evidence', str_repeat('a', 64), null, $duplicate, $target->fingerprint(), 'full', []);
try { FilesystemRecoveryActionLinkageBuilder::build($duplicatePlan, $entries, 'ordinal-operation', $recovery, $artifactIdentity); $assert(false, 'Repeated destructive path was accepted.'); } catch (Throwable) { $assert(true, 'Repeated destructive path failed closed.'); }
try { FilesystemRecoveryActionLinkageBuilder::build($plan, array_slice($entries, 1), 'ordinal-operation', $recovery, $artifactIdentity); $assert(false, 'Missing original-byte evidence was accepted.'); } catch (Throwable) { $assert(true, 'Missing original-byte evidence failed closed.'); }
$addOnlyActions = [new V3SourceRelativeAction(V3SourceRelativeAction::DELIVER, CanonicalTargetInventoryEntry::APP_ROOT, 'app/created.php', 7, $hash('created'))];
$addOnlyTarget = new CanonicalTargetInventory([new CanonicalTargetInventoryEntry(CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/created.php', 7, $hash('created'))]);
$addOnlyPackage = new PackageContract(PackageContract::WEBCORE_PACKAGE_TYPE, PackageContract::V3_MANIFEST_CONTRACT_VERSION, '0.14.0', 'add-only-release', 'add-only-tree', new PackageCompatibility('0.13.0'), new PackageRuntimeCompatibility('8.1.0', ['mysql' => '8.0.0'], ['json']), [new PackageInventoryEntry('app/created.php', 7, $hash('created'), PackageOwnership::PACKAGE_OWNED)], new PackageMigrationDeclaration(), null, $addOnlyTarget, 'full');
$addOnlyPlan = new V3SourceRelativePlan($addOnlyPackage, 'package-evidence', str_repeat('c', 64), null, $addOnlyActions, $addOnlyTarget->fingerprint(), 'full', []);
$captureRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-add-only-capture-' . bin2hex(random_bytes(4));
mkdir($captureRoot, 0700, true);
$capturedAddOnly = (new FilesystemRecoveryDomain(new FilesystemRecoveryPathGuard(new LiveTreePathGuard($captureRoot)), $artifactStore))->capture(FilesystemRecoveryPlan::fromV3Plan($addOnlyPlan));
$addOnlyLinkage = FilesystemRecoveryActionLinkageBuilder::build($addOnlyPlan, $capturedAddOnly->entries(), 'add-only-operation', new RecoveryIdentity('add-only-recovery'), $capturedAddOnly->artifact()->artifactIdentity());
$assert($addOnlyLinkage !== null && count($addOnlyLinkage->links()) === 1, 'ADD-only recovery capture was rejected.');
try { FilesystemRecoveryActionLinkageBuilder::build($addOnlyPlan, [FilesystemRecoveryEntry::existing('app/created.php', 7, $hash('created'), 'created', null)], 'add-only-operation', new RecoveryIdentity('add-only-recovery'), $artifactIdentity); $assert(false, 'ADD with existing original state was accepted.'); } catch (Throwable) { $assert(true, 'ADD original-state mismatch failed closed.'); }
$remove($captureRoot);
$renameRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-rename-recovery-' . bin2hex(random_bytes(4));
mkdir($renameRoot . DIRECTORY_SEPARATOR . 'live' . DIRECTORY_SEPARATOR . 'app', 0700, true);
file_put_contents($renameRoot . DIRECTORY_SEPARATOR . 'live' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'z-old.php', 'old-source');
file_put_contents($renameRoot . DIRECTORY_SEPARATOR . 'live' . DIRECTORY_SEPARATOR . 'unrelated.txt', 'operator-data');
$renameEntry = new CanonicalTargetInventoryEntry(CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/a-new.php', 10, $hash('new-target'));
$renameTarget = new CanonicalTargetInventory([$renameEntry]);
$renamePackage = new PackageContract(PackageContract::WEBCORE_PACKAGE_TYPE, PackageContract::V3_MANIFEST_CONTRACT_VERSION, '0.14.0', 'rename-release', 'rename-tree', new PackageCompatibility('0.13.0'), new PackageRuntimeCompatibility('8.1.0', ['mysql' => '8.0.0'], ['json']), [new PackageInventoryEntry('app/a-new.php', 10, $hash('new-target'), PackageOwnership::PACKAGE_OWNED)], new PackageMigrationDeclaration(), null, $renameTarget, 'full');
$renameRemoval = ['kind' => 2, 'reason' => 3, 'source_root' => CanonicalTargetInventoryEntry::APP_ROOT, 'source_path' => 'app/z-old.php', 'target_root' => CanonicalTargetInventoryEntry::APP_ROOT, 'target_path' => 'app/a-new.php', 'expected_source_byte_size' => 10, 'expected_source_sha256' => $hash('old-source')];
$renamePlan = new V3SourceRelativePlan($renamePackage, 'rename-evidence', str_repeat('d', 64), null, [new V3SourceRelativeAction(V3SourceRelativeAction::DELIVER, CanonicalTargetInventoryEntry::APP_ROOT, 'app/a-new.php', 10, $hash('new-target')), new V3SourceRelativeAction(V3SourceRelativeAction::RENAME, CanonicalTargetInventoryEntry::APP_ROOT, 'app/a-new.php', 10, $hash('old-source'), CanonicalTargetInventoryEntry::APP_ROOT, 'app/z-old.php', 'renamed')], $renameTarget->fingerprint(), 'full', [$renameRemoval]);
$renameStorage = $renameRoot . DIRECTORY_SEPARATOR . 'storage'; mkdir($renameStorage, 0700, true);
$renameStore = new RecoveryArtifactStore(new RecoveryStorageRoot($renameStorage, $renameStorage . DIRECTORY_SEPARATOR . 'recovery', 'rename-test'));
$renameDomain = new FilesystemRecoveryDomain(new FilesystemRecoveryPathGuard(new LiveTreePathGuard($renameRoot . DIRECTORY_SEPARATOR . 'live')), $renameStore);
$renameRecoveryPlan = FilesystemRecoveryPlan::fromV3Plan($renamePlan);
$renameCapture = $renameDomain->capture($renameRecoveryPlan);
$renameRecovery = new RecoveryIdentity('rename-recovery');
$renameManifest = new RecoveryManifest($renameRecovery, 'rename-operation', 'rename-package', '0.14.0', $hash('rename-archive'), $renameRecoveryPlan->applyPlanIdentity(), [$renameCapture->domainIdentity()], 'rename-pre', 'rename-ledger');
$renameStore->publish($renameManifest, [['record' => $renameCapture->artifact(), 'bytes' => $renameCapture->artifactBytes()]]);
$beforeRename = $renameDomain->restore($renameRecovery, $renameCapture->artifact(), $renameRecoveryPlan);
$assert($beforeRename->status() === 'completed' && file_get_contents($renameRoot . DIRECTORY_SEPARATOR . 'live' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'z-old.php') === 'old-source' && !file_exists($renameRoot . DIRECTORY_SEPARATOR . 'live' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'a-new.php'), 'Pre-mutation RENAME recovery was not a safe no-op.');
file_put_contents($renameRoot . DIRECTORY_SEPARATOR . 'live' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'a-new.php', 'new-target');
@unlink($renameRoot . DIRECTORY_SEPARATOR . 'live' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'z-old.php');
$interruptedRename = $renameDomain->restore($renameRecovery, $renameCapture->artifact(), $renameRecoveryPlan, static function (int $count): void { if ($count === 1) throw new RuntimeException('rename restore interruption'); });
$assert($interruptedRename->status() === 'failed' && !file_exists($renameRoot . DIRECTORY_SEPARATOR . 'live' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'a-new.php') && !file_exists($renameRoot . DIRECTORY_SEPARATOR . 'live' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'z-old.php'), 'Interrupted RENAME rollback did not preserve its resumable boundary.');
$retriedRename = $renameDomain->restore($renameRecovery, $renameCapture->artifact(), $renameRecoveryPlan);
$assert($retriedRename->status() === 'completed' && file_get_contents($renameRoot . DIRECTORY_SEPARATOR . 'live' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'z-old.php') === 'old-source' && !file_exists($renameRoot . DIRECTORY_SEPARATOR . 'live' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'a-new.php') && file_get_contents($renameRoot . DIRECTORY_SEPARATOR . 'live' . DIRECTORY_SEPARATOR . 'unrelated.txt') === 'operator-data', 'RENAME retry did not converge without touching unrelated files.');
$nonRenameRecovery = new RecoveryIdentity('nonrename-recovery');
$nonRenamePlan = FilesystemRecoveryPlan::fromCapturedEntries(str_repeat('e', 64), [FilesystemRecoveryEntry::existing('app/z-old.php', 10, $hash('new-target'), 'old-source', null)]);
$nonRenameCodec = new FilesystemRecoveryBundleCodec();
$nonRenameBytes = $nonRenameCodec->encode($nonRenamePlan, $nonRenamePlan->entries());
$nonRenameArtifact = new RecoveryArtifactRecord('filesystem.package-owned', $hash($nonRenameBytes), strlen($nonRenameBytes));
$nonRenameManifest = new RecoveryManifest($nonRenameRecovery, 'nonrename-operation', 'rename-package', '0.14.0', $hash('nonrename-archive'), $nonRenamePlan->applyPlanIdentity(), [new RecoveryDomainIdentity('filesystem.package-owned', 'filesystem.package-owned', $nonRenamePlan->applyPlanIdentity(), $nonRenameArtifact->artifactIdentity())], 'rename-pre', 'rename-ledger');
$renameStore->publish($nonRenameManifest, [['record' => $nonRenameArtifact, 'bytes' => $nonRenameBytes]]);
@unlink($renameRoot . DIRECTORY_SEPARATOR . 'live' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'z-old.php');
$missingNonRename = $renameDomain->restore($nonRenameRecovery, $nonRenameArtifact, $nonRenamePlan);
$assert($missingNonRename->status() === 'failed' && !file_exists($renameRoot . DIRECTORY_SEPARATOR . 'live' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'z-old.php'), 'Missing source for non-RENAME action was not fail-closed.');
$remove($renameRoot);
echo "Action ordinal assertions: {$assertions}\n";
