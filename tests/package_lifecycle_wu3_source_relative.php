<?php

declare(strict_types=1);

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void { $assertions++; if (!$condition) { throw new RuntimeException($message); } };
$throws = static function (callable $callback, string $message) use ($assert, &$assertions): void { try { $callback(); } catch (Throwable) { $assertions++; return; } $assert(false, $message); };
$hash = static fn (string $bytes): string => hash('sha256', $bytes);
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu3-source-' . bin2hex(random_bytes(5));
mkdir($root, 0700, true);
$remove = static function (string $path) use (&$remove): void { if (is_file($path) || is_link($path)) { @unlink($path); return; } if (!is_dir($path)) { return; } foreach (scandir($path) ?: [] as $entry) { if ($entry !== '.' && $entry !== '..') { $remove($path . DIRECTORY_SEPARATOR . $entry); } } @rmdir($path); };

$contract = static function (array $delivered, Copot\Core\CanonicalTargetInventory $target, string $coverage, array $allow = [], array $removals = [], array $renames = [], ?string $tree = 'tree-1') use ($hash): Copot\Core\PackageContract {
    $entries = array_map(static fn (array $entry): Copot\Core\PackageInventoryEntry => new Copot\Core\PackageInventoryEntry($entry[0], $entry[1], $entry[2]), $delivered);
    return new Copot\Core\PackageContract(
        'copot-webcore', 3, '0.14.0', 'release-2', $tree,
        new Copot\Core\PackageCompatibility('0.13.0'),
        new Copot\Core\PackageRuntimeCompatibility('8.1.0', ['mysql' => '8.0.0'], ['json']),
        $entries, new Copot\Core\PackageMigrationDeclaration(), new Copot\Core\PackageTargetRequirements(), $target, $coverage, $allow, $removals, $renames
    );
};

try {
    $old = new Copot\Core\CanonicalTargetInventoryEntry('APP_ROOT', 'package-owned', 'app/old.txt', 3, $hash('old'));
    $new = new Copot\Core\CanonicalTargetInventoryEntry('APP_ROOT', 'package-owned', 'app/new.txt', 3, $hash('new'));
    $target = new Copot\Core\CanonicalTargetInventory([$old, $new]);
    $sourceStore = new Copot\Core\SourceInventoryArtifactStore($root);
    $source = $sourceStore->publish(new Copot\Core\CanonicalTargetInventory([$old]), 'release-1', 3, 'tree-1');

    $partial = $contract([['app/new.txt', 3, $hash('new')]], $target, 'partial', [$source->reference()->fingerprint()]);
    $plan = (new Copot\Core\PackageV3SourceRelativePlanner())->plan($partial, $source);
    $kinds = array_map(static fn (Copot\Core\V3SourceRelativeAction $action): string => $action->action(), $plan->actions());
    $assert(in_array(Copot\Core\V3SourceRelativeAction::RETAIN, $kinds, true) && in_array(Copot\Core\V3SourceRelativeAction::DELIVER, $kinds, true), 'Partial source-relative plan did not retain and deliver the expected files.');
    $assert($plan->targetFingerprint() === $target->fingerprint() && str_starts_with($plan->identity(), 'v3-plan-sha256:'), 'Plan identity or target fingerprint is not canonical.');
    $assert($plan->preparedEvidence()['mutation_executed'] === false && preg_match('/^[a-f0-9]{64}$/D', $plan->removalSetDigest()) === 1, 'Prepared-plan evidence is incomplete.');

    $fullTarget = new Copot\Core\CanonicalTargetInventory([$new]);
    $full = $contract([['app/new.txt', 3, $hash('new')]], $fullTarget, 'full');
    $fullPlan = (new Copot\Core\PackageV3SourceRelativePlanner())->plan($full);
    $assert(count($fullPlan->actions()) === 1 && $fullPlan->actions()[0]->action() === Copot\Core\V3SourceRelativeAction::DELIVER, 'Full v3 plan did not require physical delivery.');

    $throws(static fn (): Copot\Core\V3SourceRelativePlan => (new Copot\Core\PackageV3SourceRelativePlanner())->plan($partial), 'Partial v3 plan accepted without committed source evidence.');
    $badAllow = $contract([['app/new.txt', 3, $hash('new')]], $target, 'partial', [str_repeat('a', 64)]);
    $throws(static fn (): Copot\Core\V3SourceRelativePlan => (new Copot\Core\PackageV3SourceRelativePlanner())->plan($badAllow, $source), 'Source fingerprint allowlist mismatch was accepted.');
    $badTarget = new Copot\Core\CanonicalTargetInventory([new Copot\Core\CanonicalTargetInventoryEntry('APP_ROOT', 'package-owned', 'app/old.txt', 4, $hash('drift')), $new]);
    $badPartial = $contract([['app/new.txt', 3, $hash('new')]], $badTarget, 'partial', [$source->reference()->fingerprint()]);
    $throws(static fn (): Copot\Core\V3SourceRelativePlan => (new Copot\Core\PackageV3SourceRelativePlanner())->plan($badPartial, $source), 'Retained-source byte drift was accepted.');

    $renameTarget = new Copot\Core\CanonicalTargetInventory([$new]);
    $removal = ['root' => 'APP_ROOT', 'path' => 'app/old.txt', 'ownership' => 'package-owned', 'expected_source_byte_size' => 3, 'expected_source_sha256' => $hash('old'), 'reason' => 'renamed'];
    $rename = ['source_root' => 'APP_ROOT', 'source_path' => 'app/old.txt', 'target_root' => 'APP_ROOT', 'target_path' => 'app/new.txt'];
    $renamePackage = $contract([['app/new.txt', 3, $hash('new')]], $renameTarget, 'partial', [$source->reference()->fingerprint()], [$removal], [$rename]);
    $renamePlan = (new Copot\Core\PackageV3SourceRelativePlanner())->plan($renamePackage, $source);
    $assert(count($renamePlan->removalActions()) === 1 && $renamePlan->removalActions()[0]['kind'] === Copot\Core\PackageRemovalSet::RENAME_SOURCE_REMOVAL, 'Rename source action was not prepared as a typed removal action.');
    $collisionSource = $sourceStore->publish(new Copot\Core\CanonicalTargetInventory([$old, $new]), 'release-1', 3, 'tree-1');
    $throws(static fn (): Copot\Core\V3SourceRelativePlan => (new Copot\Core\PackageV3SourceRelativePlanner())->plan($renamePackage, $collisionSource), 'Rename collision with an unrelated existing target was accepted.');

    $oldA = new Copot\Core\CanonicalTargetInventoryEntry('APP_ROOT', 'package-owned', 'app/old-a.txt', 5, $hash('old-a'));
    $oldB = new Copot\Core\CanonicalTargetInventoryEntry('APP_ROOT', 'package-owned', 'app/old-b.txt', 5, $hash('old-b'));
    $newA = new Copot\Core\CanonicalTargetInventoryEntry('APP_ROOT', 'package-owned', 'app/new-a.txt', 5, $hash('new-a'));
    $newB = new Copot\Core\CanonicalTargetInventoryEntry('APP_ROOT', 'package-owned', 'app/new-b.txt', 5, $hash('new-b'));
    $multiSource = $sourceStore->publish(new Copot\Core\CanonicalTargetInventory([$oldA, $oldB]), 'release-1', 3, 'tree-1');
    $multiTarget = new Copot\Core\CanonicalTargetInventory([$newA, $newB]);
    $multiRemovals = [
        ['root' => 'APP_ROOT', 'path' => 'app/old-a.txt', 'ownership' => 'package-owned', 'expected_source_byte_size' => 5, 'expected_source_sha256' => $hash('old-a'), 'reason' => 'renamed'],
        ['root' => 'APP_ROOT', 'path' => 'app/old-b.txt', 'ownership' => 'package-owned', 'expected_source_byte_size' => 5, 'expected_source_sha256' => $hash('old-b'), 'reason' => 'renamed'],
    ];
    $distinctRenames = [
        ['source_root' => 'APP_ROOT', 'source_path' => 'app/old-a.txt', 'target_root' => 'APP_ROOT', 'target_path' => 'app/new-a.txt'],
        ['source_root' => 'APP_ROOT', 'source_path' => 'app/old-b.txt', 'target_root' => 'APP_ROOT', 'target_path' => 'app/new-b.txt'],
    ];
    $distinctPackage = $contract([['app/new-a.txt', 5, $hash('new-a')], ['app/new-b.txt', 5, $hash('new-b')]], $multiTarget, 'partial', [$multiSource->reference()->fingerprint()], $multiRemovals, $distinctRenames);
    $distinctPlan = (new Copot\Core\PackageV3SourceRelativePlanner())->plan($distinctPackage, $multiSource);
    $assert(count($distinctPlan->removalActions()) === 2, 'Distinct rename destinations were not both accepted.');

    $duplicateTargetRenames = [
        ['source_root' => 'APP_ROOT', 'source_path' => 'app/old-a.txt', 'target_root' => 'APP_ROOT', 'target_path' => 'app/new.txt'],
        ['source_root' => 'APP_ROOT', 'source_path' => 'app/old-b.txt', 'target_root' => 'APP_ROOT', 'target_path' => 'app/new.txt'],
    ];
    $duplicateTarget = new Copot\Core\CanonicalTargetInventory([new Copot\Core\CanonicalTargetInventoryEntry('APP_ROOT', 'package-owned', 'app/new.txt', 3, $hash('new'))]);
    $duplicateTargetPackage = $contract([['app/new.txt', 3, $hash('new')]], $duplicateTarget, 'partial', [$multiSource->reference()->fingerprint()], $multiRemovals, $duplicateTargetRenames);
    $throws(static fn (): Copot\Core\V3SourceRelativePlan => (new Copot\Core\PackageV3SourceRelativePlanner())->plan($duplicateTargetPackage, $multiSource), 'Duplicate rename destination was accepted.');

    $duplicateSourceRenames = [
        ['source_root' => 'APP_ROOT', 'source_path' => 'app/old-a.txt', 'target_root' => 'APP_ROOT', 'target_path' => 'app/new-a.txt'],
        ['source_root' => 'APP_ROOT', 'source_path' => 'app/old-a.txt', 'target_root' => 'APP_ROOT', 'target_path' => 'app/new-b.txt'],
    ];
    $duplicateSourcePackage = $contract([['app/new-a.txt', 5, $hash('new-a')], ['app/new-b.txt', 5, $hash('new-b')]], $multiTarget, 'partial', [$multiSource->reference()->fingerprint()], $multiRemovals, $duplicateSourceRenames);
    $throws(static fn (): Copot\Core\V3SourceRelativePlan => (new Copot\Core\PackageV3SourceRelativePlanner())->plan($duplicateSourcePackage, $multiSource), 'Duplicate rename source was accepted.');

    $appOld = new Copot\Core\CanonicalTargetInventoryEntry('APP_ROOT', 'package-owned', 'same/old.txt', 7, $hash('app-old'));
    $publicOld = new Copot\Core\CanonicalTargetInventoryEntry('PUBLIC_ROOT', 'package-owned', 'same/old.txt', 10, $hash('public-old'));
    $appNew = new Copot\Core\CanonicalTargetInventoryEntry('APP_ROOT', 'package-owned', 'same/new.txt', 7, $hash('app-new'));
    $publicNew = new Copot\Core\CanonicalTargetInventoryEntry('PUBLIC_ROOT', 'package-owned', 'same/new.txt', 10, $hash('public-new'));
    $rootSource = $sourceStore->publish(new Copot\Core\CanonicalTargetInventory([$appOld, $publicOld]), 'release-1', 3, 'tree-1');
    $rootRemovals = [
        ['root' => 'APP_ROOT', 'path' => 'same/old.txt', 'ownership' => 'package-owned', 'expected_source_byte_size' => 7, 'expected_source_sha256' => $hash('app-old'), 'reason' => 'renamed'],
        ['root' => 'PUBLIC_ROOT', 'path' => 'same/old.txt', 'ownership' => 'package-owned', 'expected_source_byte_size' => 10, 'expected_source_sha256' => $hash('public-old'), 'reason' => 'renamed'],
    ];
    $rootRenames = [
        ['source_root' => 'APP_ROOT', 'source_path' => 'same/old.txt', 'target_root' => 'APP_ROOT', 'target_path' => 'same/new.txt'],
        ['source_root' => 'PUBLIC_ROOT', 'source_path' => 'same/old.txt', 'target_root' => 'PUBLIC_ROOT', 'target_path' => 'same/new.txt'],
    ];
    $rootPackage = $contract([['same/new.txt', 7, $hash('app-new')], ['public/same/new.txt', 10, $hash('public-new')]], new Copot\Core\CanonicalTargetInventory([$appNew, $publicNew]), 'partial', [$rootSource->reference()->fingerprint()], $rootRemovals, $rootRenames);
    $rootPlan = (new Copot\Core\PackageV3SourceRelativePlanner())->plan($rootPackage, $rootSource);
    $assert(count($rootPlan->removalActions()) === 2, 'APP_ROOT and PUBLIC_ROOT rename identities falsely collided.');

    echo "WU3 source-relative assertions passed: {$assertions}.\n";
} finally { $remove($root); }
