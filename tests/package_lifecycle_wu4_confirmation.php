<?php

declare(strict_types=1);

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$assert = static function (bool $condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } };
$throws = static function (callable $callback, string $message) use ($assert): void { try { $callback(); } catch (Throwable) { return; } $assert(false, $message); };
$hash = static fn (string $bytes): string => hash('sha256', $bytes);
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu4-confirmation-' . bin2hex(random_bytes(5)); mkdir($root, 0700, true);
$remove = static function (string $path) use (&$remove): void { if (is_file($path) || is_link($path)) { @unlink($path); return; } if (!is_dir($path)) { return; } foreach (scandir($path) ?: [] as $entry) { if ($entry !== '.' && $entry !== '..') { $remove($path . DIRECTORY_SEPARATOR . $entry); } } @rmdir($path); };

try {
    $old = new Copot\Core\CanonicalTargetInventoryEntry('APP_ROOT', 'package-owned', 'app/old.txt', 3, $hash('old'));
    $new = new Copot\Core\CanonicalTargetInventoryEntry('APP_ROOT', 'package-owned', 'app/new.txt', 3, $hash('new'));
    $sourceStore = new Copot\Core\SourceInventoryArtifactStore($root);
    $source = $sourceStore->publish(new Copot\Core\CanonicalTargetInventory([$old]), 'release-1', 3, 'tree-1');
    $removal = ['root' => 'APP_ROOT', 'path' => 'app/old.txt', 'ownership' => 'package-owned', 'expected_source_byte_size' => 3, 'expected_source_sha256' => $hash('old'), 'reason' => 'obsolete'];
    $package = new Copot\Core\PackageContract(
        'copot-webcore', 3, '0.14.0', 'release-2', 'tree-1', new Copot\Core\PackageCompatibility('0.13.0'),
        new Copot\Core\PackageRuntimeCompatibility('8.1.0', ['mysql' => '8.0.0'], ['json']),
        [new Copot\Core\PackageInventoryEntry('app/new.txt', 3, $hash('new'))], new Copot\Core\PackageMigrationDeclaration(), new Copot\Core\PackageTargetRequirements(), new Copot\Core\CanonicalTargetInventory([$new]), 'partial', [$source->reference()->fingerprint()], [$removal], []
    );
    $plan = (new Copot\Core\PackageV3SourceRelativePlanner())->plan($package, $source);
    $operationId = $plan->operationIdentity(); $now = gmdate(DATE_ATOM);
    $operations = new Copot\Core\LifecycleOperationStore($root);
    $operations->create(new Copot\Core\LifecycleOperationRecord($operationId, 'package-v3', '0.14.0', 'release-2', $hash('archive'), $root, $hash('payload'), $hash('apply-plan'), Copot\Core\LifecycleOperationRecord::PREPARING, 0, null, null, null, $now, $now));
    $evidence = new Copot\Core\DestructiveActorAuthorizationEvidence('actor-1', 'capability-1', 'session-1');
    $confirmation = new Copot\Core\DestructivePlanConfirmation($operationId, $plan->identity(), $plan->removalSetDigest(), $evidence);
    $cli = Copot\Core\DestructivePlanConfirmationCliAdapter::fromTypedInput($confirmation->toArray());
    $http = Copot\Core\DestructivePlanConfirmationSystemManagerAdapter::fromTypedRequest(['destructive_plan_confirmation' => $confirmation->toArray()]);
    $assert($cli->toArray() === $http->toArray() && $cli->bindingIdentity() === $confirmation->bindingIdentity(), 'CLI and System Manager adapters did not produce equivalent typed proof.');

    $authority = new Copot\Core\DestructivePlanConfirmationAuthority($operations, static function (Copot\Core\DestructiveActorAuthorizationEvidence $actor): bool { return $actor->actorIdentity() === 'actor-1' && $actor->capabilityReference() === 'capability-1'; }, static fn (Copot\Core\V3SourceRelativePlan $value): bool => $value->sourceFingerprint() !== 'stale');
    $receipt = $authority->accept($confirmation, $plan);
    $assert($receipt->bindingIdentity() === $confirmation->bindingIdentity() && $authority->verify($confirmation, $plan), 'Valid destructive confirmation was not accepted and verified.');
    $stored = $operations->read();
    $assert($stored instanceof Copot\Core\LifecycleOperationRecord && $stored->destructiveConfirmationActorIdentity() === 'actor-1' && $stored->destructiveConfirmationRemovalSetDigest() === $plan->removalSetDigest(), 'Accepted confirmation evidence was not durably linked to the operation.');
    $throws(static fn (): Copot\Core\DestructivePlanConfirmationReceipt => $authority->accept($confirmation, $plan), 'Confirmation replay was accepted.');
    $wrongDigest = new Copot\Core\DestructivePlanConfirmation($operationId, $plan->identity(), str_repeat('a', 64), $evidence);
    $throws(static fn (): Copot\Core\DestructivePlanConfirmationReceipt => $authority->accept($wrongDigest, $plan), 'Mismatched removal-set confirmation was accepted.');
    $deniedRoot = $root . DIRECTORY_SEPARATOR . 'denied'; mkdir($deniedRoot, 0700, true);
    $deniedStore = new Copot\Core\LifecycleOperationStore($deniedRoot);
    $deniedStore->create(new Copot\Core\LifecycleOperationRecord($operationId, 'package-v3', '0.14.0', 'release-2', $hash('archive'), $deniedRoot, $hash('payload'), $hash('apply-plan'), Copot\Core\LifecycleOperationRecord::PREPARING, 0, null, null, null, $now, $now));
    $unauthorized = new Copot\Core\DestructivePlanConfirmation($operationId, $plan->identity(), $plan->removalSetDigest(), new Copot\Core\DestructiveActorAuthorizationEvidence('actor-2', 'capability-2'));
    $deniedAuthority = new Copot\Core\DestructivePlanConfirmationAuthority($deniedStore, static fn (): bool => false);
    $throws(static fn (): Copot\Core\DestructivePlanConfirmationReceipt => $deniedAuthority->accept($unauthorized, $plan), 'Unauthorized actor confirmation was accepted.');
    $staleAuthority = new Copot\Core\DestructivePlanConfirmationAuthority($deniedStore, static fn (): bool => true, static fn (): bool => false);
    $throws(static fn (): Copot\Core\DestructivePlanConfirmationReceipt => $staleAuthority->accept($confirmation, $plan), 'Stale source/plan state was accepted.');
    $throws(static fn (): Copot\Core\DestructivePlanConfirmation => Copot\Core\DestructivePlanConfirmation::fromArray(['operation_identity' => $operationId, 'plan_identity' => $plan->identity(), 'removal_set_digest' => $plan->removalSetDigest(), 'confirmation_version' => 1]), 'Incomplete confirmation input was accepted.');
    $throws(static fn (): Copot\Core\DestructivePlanConfirmation => Copot\Core\DestructivePlanConfirmation::fromArray(['confirmed' => true]), 'Standalone boolean confirmation was accepted.');
    $throws(static fn (): Copot\Core\DestructivePlanConfirmation => Copot\Core\DestructivePlanConfirmation::fromArray(['operation_identity' => $operationId, 'plan_identity' => $plan->identity(), 'removal_set_digest' => $plan->removalSetDigest(), 'actor_authorization_evidence' => $evidence->toArray(), 'confirmation_version' => '1']), 'String confirmation version was accepted.');
    $assert($authority->consume($confirmation, $plan) && !$authority->verify($confirmation, $plan), 'Consumed confirmation was reusable.');
    echo "WU4 destructive confirmation assertions passed.\n";
} finally { $remove($root); }
