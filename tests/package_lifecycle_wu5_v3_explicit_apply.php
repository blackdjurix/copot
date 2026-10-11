<?php

declare(strict_types=1);

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

use Copot\Core\CanonicalTargetInventory;
use Copot\Core\CanonicalTargetInventoryEntry;
use Copot\Core\DestructiveActorAuthorizationEvidence;
use Copot\Core\DestructivePlanConfirmation;
use Copot\Core\DestructivePlanConfirmationAuthority;
use Copot\Core\InstallationMutex;
use Copot\Core\LifecycleOperationRecord;
use Copot\Core\LifecycleOperationStore;
use Copot\Core\LiveFileActivationCapability;
use Copot\Core\LiveTreePathGuard;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageContract;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageOwnership;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\PackageV3SourceRelativePlanner;
use Copot\Core\PackageRemovalSet;
use Copot\Core\PackageOwnedFileApplier;
use Copot\Core\SourceInventoryArtifactStore;
use Copot\Core\StagedFile;
use Copot\Core\StagedPayload;
use Copot\Core\StagingSession;
use Copot\Core\V3ExplicitApplyExecutor;
use Copot\Core\V3SourceRelativeAction;
use Copot\Core\WebcoreApplyResult;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void { $assertions++; if (!$condition) { throw new RuntimeException($message); } };
$hash = static fn (string $value): string => hash('sha256', $value);
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu5-v3-' . bin2hex(random_bytes(6));
$remove = static function (string $path) use (&$remove): void { if (is_file($path) || is_link($path)) { @unlink($path); return; } if (!is_dir($path)) { return; } foreach (scandir($path) ?: [] as $entry) { if ($entry !== '.' && $entry !== '..') { $remove($path . DIRECTORY_SEPARATOR . $entry); } } @rmdir($path); };

mkdir($root . DIRECTORY_SEPARATOR . 'live' . DIRECTORY_SEPARATOR . 'app', 0700, true);
mkdir($root . DIRECTORY_SEPARATOR . 'storage', 0700, true);
mkdir($root . DIRECTORY_SEPARATOR . 'staging', 0700, true);
mkdir($root . DIRECTORY_SEPARATOR . 'apply', 0700, true);

try {
    $oldBytes = 'old';
    $newBytes = 'new';
    file_put_contents($root . DIRECTORY_SEPARATOR . 'live' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'old.txt', $oldBytes);
    $old = new CanonicalTargetInventoryEntry('APP_ROOT', CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/old.txt', strlen($oldBytes), $hash($oldBytes));
    $new = new CanonicalTargetInventoryEntry('APP_ROOT', CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/new.txt', strlen($newBytes), $hash($newBytes));
    $sourceStore = new SourceInventoryArtifactStore($root . DIRECTORY_SEPARATOR . 'storage');
    $source = $sourceStore->publish(new CanonicalTargetInventory([$old]), 'release-1', 3, 'tree-1');
    $removal = ['root' => 'APP_ROOT', 'path' => 'app/old.txt', 'ownership' => CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'expected_source_byte_size' => 3, 'expected_source_sha256' => $hash($oldBytes), 'reason' => 'renamed'];
    $rename = ['source_root' => 'APP_ROOT', 'source_path' => 'app/old.txt', 'target_root' => 'APP_ROOT', 'target_path' => 'app/new.txt'];
    $package = new PackageContract('copot-webcore', 3, '0.14.0', 'release-2', 'tree-1', new PackageCompatibility('0.13.0'), new PackageRuntimeCompatibility('8.1.0', ['mysql' => '8.0.0'], ['json']), [new \Copot\Core\PackageInventoryEntry('app/new.txt', 3, $hash($newBytes))], new PackageMigrationDeclaration(), new \Copot\Core\PackageTargetRequirements(), new CanonicalTargetInventory([$new]), 'partial', [$source->reference()->fingerprint()], [$removal], [$rename]);
    $plan = (new PackageV3SourceRelativePlanner())->plan($package, $source);
    $session = StagingSession::create($root . DIRECTORY_SEPARATOR . 'live', $root . DIRECTORY_SEPARATOR . 'staging');
    mkdir($session->payloadPath() . DIRECTORY_SEPARATOR . 'app', 0700, true);
    file_put_contents($session->payloadPath() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'new.txt', $newBytes);
    $payload = new StagedPayload($session, str_repeat('a', 64), [new StagedFile('app/new.txt', 3, $hash($newBytes))]);
    $now = gmdate(DATE_ATOM);
    $operation = new LifecycleOperationRecord($plan->operationIdentity(), 'patch', '0.14.0', 'release-2', str_repeat('a', 64), $session->path(), str_repeat('b', 64), str_repeat('c', 64), LifecycleOperationRecord::PREPARING, 0, null, null, null, $now, $now);
    $operations = new LifecycleOperationStore($root . DIRECTORY_SEPARATOR . 'storage');
    $operations->create($operation);
    $actor = new DestructiveActorAuthorizationEvidence('operator-1', 'capability-1');
    $confirmation = new DestructivePlanConfirmation($plan->operationIdentity(), $plan->identity(), $plan->removalSetDigest(), $actor);
    $authority = new DestructivePlanConfirmationAuthority($operations, static fn (): bool => true);
    $authority->accept($confirmation, $plan);
    $captured = false;
    $guard = new LiveTreePathGuard($root . DIRECTORY_SEPARATOR . 'live');
    $executor = new V3ExplicitApplyExecutor();
    $result = $executor->execute($plan, $payload, $source, $operation, $operations, new PackageOwnedFileApplier($guard, LiveFileActivationCapability::current(), $root . DIRECTORY_SEPARATOR . 'apply'), $guard, static function () use (&$captured): bool { $captured = true; return true; }, $authority, $confirmation);
    $assert($result->status() === WebcoreApplyResult::AWAITING_WU6, 'V3 explicit rename did not reach the existing WU6 handoff.');
    $assert($captured, 'Recovery capture callback was not required before mutation.');
    $assert(!file_exists($root . DIRECTORY_SEPARATOR . 'live' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'old.txt'), 'Rename source was not explicitly removed.');
    $assert(file_get_contents($root . DIRECTORY_SEPARATOR . 'live' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'new.txt') === $newBytes, 'Rename target was not delivered.');
    $assert($operations->read()?->destructiveConfirmationConsumedAt() !== null, 'Confirmation consumption was not durably recorded.');
    echo "WU5 v3 explicit apply assertions passed ({$assertions} assertions).\n";
} finally {
    $remove($root);
}
