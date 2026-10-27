<?php

declare(strict_types=1);

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

use Copot\Core\BackupRecovery\FilesystemRecoveryPlan;
use Copot\Core\CanonicalTargetInventoryEntry;
use Copot\Core\LiveTreePathGuard;
use Copot\Core\LifecycleOperationRecord;
use Copot\Core\LiveFileActivationCapability;
use Copot\Core\PackageRemovalSet;
use Copot\Core\PackageOwnedFileApplier;
use Copot\Core\PackageContract;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\CanonicalTargetInventory;
use Copot\Core\V3ExplicitApplyExecutor;
use Copot\Core\V3SourceRelativeAction;
use Copot\Core\V3SourceRelativePlan;
use Copot\Core\ProtectedResourcePolicy;
use Copot\Core\StagedFile;
use Copot\Core\StagedPayload;
use Copot\Core\StagingSession;
use Copot\Core\WebcoreApplyPlan;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) { throw new RuntimeException($message); }
};
$throws = static function (callable $callback, string $message) use ($assert): void {
    try { $callback(); } catch (Throwable) { return; }
    $assert(false, $message);
};
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-hta01-' . bin2hex(random_bytes(6));
$remove = static function (string $path) use (&$remove): void {
    if (is_file($path) || is_link($path)) { @unlink($path); return; }
    if (!is_dir($path)) { return; }
    foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry);
    @rmdir($path);
};

mkdir($root . DIRECTORY_SEPARATOR . 'live', 0700, true);
try {
    $assert(ProtectedResourcePolicy::isProtected(CanonicalTargetInventoryEntry::PUBLIC_ROOT, '.htaccess'), 'Protected target was not recognized.');
    $assert(!ProtectedResourcePolicy::isProtected(CanonicalTargetInventoryEntry::APP_ROOT, '.htaccess'), 'Protection leaked to APP_ROOT.');
    $throws(static function (): void { ProtectedResourcePolicy::assertMutable(CanonicalTargetInventoryEntry::PUBLIC_ROOT, '.htaccess'); }, 'Policy allowed protected mutation.');

    $guard = new LiveTreePathGuard($root . DIRECTORY_SEPARATOR . 'live');
    $assert($guard->destination('public/.htaccess') !== '', 'Read-only protected destination inspection failed.');

    $hash = hash('sha256', 'routing');
    $action = ['kind' => PackageRemovalSet::EXPLICIT_REMOVAL, 'reason' => PackageRemovalSet::OBSOLETE, 'source_root' => CanonicalTargetInventoryEntry::PUBLIC_ROOT, 'source_path' => '.htaccess', 'target_root' => '', 'target_path' => '', 'expected_source_byte_size' => 7, 'expected_source_sha256' => $hash];
    $throws(static fn (): string => PackageRemovalSet::serialize(str_repeat('a', 64), [$action]), 'Removal serialization allowed protected path.');

    $session = StagingSession::create($root . DIRECTORY_SEPARATOR . 'live', $root . DIRECTORY_SEPARATOR . 'staging');
    $payload = new StagedPayload($session, str_repeat('b', 64), [new StagedFile('public/.htaccess', 7, $hash)]);
    $throws(static fn (): FilesystemRecoveryPlan => FilesystemRecoveryPlan::fromApplyPlan(WebcoreApplyPlan::fromPayload($payload)), 'Recovery plan allowed protected path.');

    mkdir($session->payloadPath(), 0700, true);
    mkdir($root . DIRECTORY_SEPARATOR . 'storage', 0700, true);
    file_put_contents($root . DIRECTORY_SEPARATOR . 'live' . DIRECTORY_SEPARATOR . 'normal.txt', 'old');
    mkdir($root . DIRECTORY_SEPARATOR . 'live' . DIRECTORY_SEPARATOR . 'public', 0700, true);
    file_put_contents($root . DIRECTORY_SEPARATOR . 'live' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . '.htaccess', 'protected');
    $normalHash = hash('sha256', 'new');
    file_put_contents($session->payloadPath() . DIRECTORY_SEPARATOR . 'normal.txt', 'new');
    $normalPayload = new StagedPayload($session, str_repeat('c', 64), [new StagedFile('normal.txt', 3, $normalHash)]);
    $package = new PackageContract(PackageContract::WEBCORE_PACKAGE_TYPE, 3, '0.13.0', 'atomicity-test', 'tree-test', new PackageCompatibility('0.13.0'), new PackageRuntimeCompatibility('8.0.0', ['sqlite' => '3.0'], ['json']), [new PackageInventoryEntry('normal.txt', 3, $normalHash)], new PackageMigrationDeclaration(false), null, new CanonicalTargetInventory([new CanonicalTargetInventoryEntry('APP_ROOT', CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'normal.txt', 3, $normalHash)]), 'full');
    $normalAction = new V3SourceRelativeAction(V3SourceRelativeAction::DELIVER, CanonicalTargetInventoryEntry::APP_ROOT, 'normal.txt', 3, $normalHash);
    $plan = new V3SourceRelativePlan($package, str_repeat('d', 64), '', null, [$normalAction], hash('sha256', 'target'), 'full', []);
    $badAction = new V3SourceRelativeAction(V3SourceRelativeAction::REMOVE, CanonicalTargetInventoryEntry::PUBLIC_ROOT, '.htaccess', 9, hash('sha256', 'protected'), CanonicalTargetInventoryEntry::PUBLIC_ROOT, '.htaccess', 'obsolete');
    $actions = new ReflectionProperty(V3SourceRelativePlan::class, 'actions'); $actions->setAccessible(true); $actions->setValue($plan, [$normalAction, $badAction]);
    $removals = new ReflectionProperty(V3SourceRelativePlan::class, 'removalActions'); $removals->setAccessible(true); $removals->setValue($plan, [['kind' => PackageRemovalSet::EXPLICIT_REMOVAL, 'reason' => PackageRemovalSet::OBSOLETE, 'source_root' => CanonicalTargetInventoryEntry::PUBLIC_ROOT, 'source_path' => '.htaccess', 'target_root' => '', 'target_path' => '', 'expected_source_byte_size' => 9, 'expected_source_sha256' => hash('sha256', 'protected')]]);
    $operation = new LifecycleOperationRecord($plan->operationIdentity(), 'patch', '0.13.0', 'atomicity-test', str_repeat('e', 64), $session->path(), str_repeat('f', 64), hash('sha256', 'apply'), LifecycleOperationRecord::PREPARING, 0, null, null, null, gmdate(DATE_ATOM), gmdate(DATE_ATOM));
    $operations = new Copot\Core\LifecycleOperationStore($root . DIRECTORY_SEPARATOR . 'storage'); $operations->create($operation);
    $captured = false;
    $result = (new V3ExplicitApplyExecutor())->execute($plan, $normalPayload, null, $operation, $operations, new PackageOwnedFileApplier($guard, LiveFileActivationCapability::current(), $root . DIRECTORY_SEPARATOR . 'apply'), $guard, static function () use (&$captured): bool { $captured = true; return true; });
    $assert($result->status() === Copot\Core\WebcoreApplyResult::BLOCKED && !$captured, 'Protected plan was not rejected before capture.');
    $assert(file_get_contents($root . DIRECTORY_SEPARATOR . 'live' . DIRECTORY_SEPARATOR . 'normal.txt') === 'old', 'Normal file was partially mutated.');
    $assert(hash_file('sha256', $root . DIRECTORY_SEPARATOR . 'live' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . '.htaccess') === hash('sha256', 'protected'), 'Protected file changed during atomicity test.');
    echo "HTACCESS protection focused assertions passed ({$assertions} assertions).\n";
} finally {
    $remove($root);
}
