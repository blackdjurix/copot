<?php

declare(strict_types=1);

use Copot\Core\BackupRecovery\FilesystemRecoveryPlan;
use Copot\Core\CanonicalTargetInventory;
use Copot\Core\CanonicalTargetInventoryEntry;
use Copot\Core\InstallationMutex;
use Copot\Core\LifecycleOperationRecord;
use Copot\Core\LifecycleOperationStore;
use Copot\Core\LiveFileActivationCapability;
use Copot\Core\LiveTreePathGuard;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageContract;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageManifestReader;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageOwnedFileApplier;
use Copot\Core\PackageOwnership;
use Copot\Core\PackageRemovalSet;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\PackageV3SourceRelativePlanner;
use Copot\Core\ProtectedResourcePolicy;
use Copot\Core\SourceInventoryArtifact;
use Copot\Core\SourceInventoryReference;
use Copot\Core\StagedFile;
use Copot\Core\StagedPayload;
use Copot\Core\StagingSession;
use Copot\Core\V3ExplicitApplyExecutor;
use Copot\Core\V3SourceRelativeAction;
use Copot\Core\V3SourceRelativePlan;
use Copot\Core\WebcoreApplyPlan;

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

$assert(ProtectedResourcePolicy::isProtected(CanonicalTargetInventoryEntry::PUBLIC_ROOT, '.htaccess'), 'Protected resource was not recognized.');
$assert(!ProtectedResourcePolicy::isProtected(CanonicalTargetInventoryEntry::APP_ROOT, '.htaccess'), 'Protection leaked to APP_ROOT.');
$throws(static function (): void { ProtectedResourcePolicy::assertMutable(CanonicalTargetInventoryEntry::PUBLIC_ROOT, '.htaccess'); }, 'Policy allowed direct protected mutation.');

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-hta01-' . bin2hex(random_bytes(5));
$live = $root . DIRECTORY_SEPARATOR . 'live';
$staging = $root . DIRECTORY_SEPARATOR . 'staging';
$storage = $root . DIRECTORY_SEPARATOR . 'storage';
mkdir($live, 0700, true); mkdir($staging, 0700, true); mkdir($storage, 0700, true);

try {
    $normalEntry = new CanonicalTargetInventoryEntry(CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/normal.php', 5, $hash('normal'));
    $normalTarget = new CanonicalTargetInventory([$normalEntry]);
    $baseManifest = [
        'package_type' => PackageContract::WEBCORE_PACKAGE_TYPE, 'manifest_contract_version' => 3,
        'target_webcore_version' => '0.14.0', 'release_identity' => 'htaccess-test', 'source_tree_identity' => null,
        'source_compatibility' => ['minimum_source_version' => '0.13.0', 'maximum_source_version' => null],
        'runtime_compatibility' => ['minimum_php_version' => '8.1.0', 'minimum_database_versions' => ['mysql' => '8.0.0'], 'required_extensions' => ['json']],
        'inventory' => [['path' => 'app/normal.php', 'byte_size' => 5, 'sha256' => $hash('normal'), 'ownership' => PackageOwnership::PACKAGE_OWNED]],
        'migration_declaration' => ['declares_core_migrations' => false, 'declaration_identity' => null], 'target_requirements' => [], 'coverage' => 'full',
        'target_inventory' => ['serialization_version' => 1, 'fingerprint' => $normalTarget->fingerprint(), 'entries' => [$normalEntry->toArray()]],
        'source_state_requirements' => ['allowed_inventory_fingerprints' => []], 'removals' => [], 'renames' => [],
    ];
    $manifestRoot = $root . DIRECTORY_SEPARATOR . 'manifest-live'; mkdir($manifestRoot, 0700, true);
    $manifestStage = StagingSession::create($manifestRoot, $staging . DIRECTORY_SEPARATOR . 'manifest');
    mkdir($manifestStage->payloadPath() . DIRECTORY_SEPARATOR . '.copot', 0700, true);
    $readManifest = static function (array $data) use ($manifestStage): void {
        $raw = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        file_put_contents($manifestStage->payloadPath() . DIRECTORY_SEPARATOR . '.copot' . DIRECTORY_SEPARATOR . 'package.json', $raw);
        $files = [new StagedFile('.copot/package.json', strlen($raw), hash('sha256', $raw)), new StagedFile('app/normal.php', 5, hash('sha256', 'normal'))];
        (new PackageManifestReader())->read(new StagedPayload($manifestStage, str_repeat('a', 64), $files));
    };
    $inventoryCase = $baseManifest;
    $inventoryCase['inventory'] = [['path' => 'public/.htaccess', 'byte_size' => 3, 'sha256' => $hash('hta'), 'ownership' => PackageOwnership::PACKAGE_OWNED]];
    $throws(static fn () => $readManifest($inventoryCase), 'Manifest ADD/REPLACE admission allowed .htaccess.');
    $removeCase = $baseManifest;
    $removeCase['removals'] = [['root' => CanonicalTargetInventoryEntry::PUBLIC_ROOT, 'path' => '.htaccess', 'ownership' => PackageOwnership::PACKAGE_OWNED, 'expected_source_byte_size' => 3, 'expected_source_sha256' => $hash('hta'), 'reason' => 'obsolete']];
    $throws(static fn () => $readManifest($removeCase), 'Manifest REMOVE admission allowed .htaccess.');
    foreach ([
        ['source_root' => CanonicalTargetInventoryEntry::PUBLIC_ROOT, 'source_path' => '.htaccess', 'target_root' => CanonicalTargetInventoryEntry::APP_ROOT, 'target_path' => 'app/normal.php'],
        ['source_root' => CanonicalTargetInventoryEntry::APP_ROOT, 'source_path' => 'app/normal.php', 'target_root' => CanonicalTargetInventoryEntry::PUBLIC_ROOT, 'target_path' => '.htaccess'],
    ] as $rename) {
        $renameCase = $baseManifest; $renameCase['renames'] = [$rename];
        $throws(static fn () => $readManifest($renameCase), 'Manifest RENAME admission allowed .htaccess.');
    }
    $manifestStage->cleanup();

    $protectedEntry = new CanonicalTargetInventoryEntry(CanonicalTargetInventoryEntry::PUBLIC_ROOT, CanonicalTargetInventoryEntry::PACKAGE_OWNED, '.htaccess', 3, $hash('hta'));
    $sourceInventory = new CanonicalTargetInventory([$protectedEntry, $normalEntry]);
    $sourceReference = new SourceInventoryReference(str_repeat('b', 64), 1, $sourceInventory->fingerprint(), 'htaccess-source', 3, 'tree');
    $source = new SourceInventoryArtifact($sourceInventory, $sourceReference);
    $makePackage = static function (array $removals = [], array $renames = [], ?CanonicalTargetInventory $target = null, array $inventory = []) use ($normalTarget, $normalEntry, $sourceReference): PackageContract {
        return new PackageContract(PackageContract::WEBCORE_PACKAGE_TYPE, 3, '0.14.0', 'htaccess-plan', 'tree', new PackageCompatibility('0.13.0'), new PackageRuntimeCompatibility('8.1.0', ['mysql' => '8.0.0'], ['json']), $inventory ?: [new PackageInventoryEntry('app/normal.php', 5, hash('sha256', 'normal'), PackageOwnership::PACKAGE_OWNED)], new PackageMigrationDeclaration(), null, $target ?? $normalTarget, 'partial', [$sourceReference->fingerprint()], $removals, $renames);
    };
    $planner = new PackageV3SourceRelativePlanner();
    $protectedRemoval = [['root' => CanonicalTargetInventoryEntry::PUBLIC_ROOT, 'path' => '.htaccess', 'ownership' => PackageOwnership::PACKAGE_OWNED, 'expected_source_byte_size' => 3, 'expected_source_sha256' => $hash('hta'), 'reason' => 'obsolete']];
    $throws(static fn () => $planner->plan($makePackage($protectedRemoval), $source), 'Planner REMOVE allowed .htaccess.');
    $protectedRenameSource = [['source_root' => CanonicalTargetInventoryEntry::PUBLIC_ROOT, 'source_path' => '.htaccess', 'target_root' => CanonicalTargetInventoryEntry::APP_ROOT, 'target_path' => 'app/normal.php']];
    $throws(static fn () => $planner->plan($makePackage([], $protectedRenameSource), $source), 'Planner RENAME source allowed .htaccess.');
    $protectedTarget = new CanonicalTargetInventory([$protectedEntry]);
    $throws(static fn () => $planner->plan($makePackage([], [], $protectedTarget, [new PackageInventoryEntry('public/.htaccess', 3, $hash('hta'), PackageOwnership::PACKAGE_OWNED)]), $source), 'Planner ADD/REPLACE target allowed .htaccess.');

    $makePlan = static function (V3SourceRelativeAction $action) use ($makePackage, $normalTarget): V3SourceRelativePlan {
        return new V3SourceRelativePlan($makePackage(), 'package-evidence', str_repeat('c', 64), null, [$action], $normalTarget->fingerprint(), 'full', []);
    };
    $actions = [
        V3SourceRelativeAction::DELIVER => new V3SourceRelativeAction(V3SourceRelativeAction::DELIVER, CanonicalTargetInventoryEntry::PUBLIC_ROOT, '.htaccess', 3, $hash('hta')),
        V3SourceRelativeAction::REPLACE => new V3SourceRelativeAction(V3SourceRelativeAction::REPLACE, CanonicalTargetInventoryEntry::PUBLIC_ROOT, '.htaccess', 3, $hash('hta'), CanonicalTargetInventoryEntry::PUBLIC_ROOT, '.htaccess', 'replaced'),
        V3SourceRelativeAction::REMOVE => new V3SourceRelativeAction(V3SourceRelativeAction::REMOVE, CanonicalTargetInventoryEntry::PUBLIC_ROOT, '.htaccess', 3, $hash('hta'), CanonicalTargetInventoryEntry::PUBLIC_ROOT, '.htaccess', 'obsolete'),
        'RENAME_SOURCE' => new V3SourceRelativeAction(V3SourceRelativeAction::RENAME, CanonicalTargetInventoryEntry::APP_ROOT, 'app/normal.php', 5, $hash('normal'), CanonicalTargetInventoryEntry::PUBLIC_ROOT, '.htaccess', 'renamed'),
        'RENAME_DESTINATION' => new V3SourceRelativeAction(V3SourceRelativeAction::RENAME, CanonicalTargetInventoryEntry::PUBLIC_ROOT, '.htaccess', 3, $hash('hta'), CanonicalTargetInventoryEntry::APP_ROOT, 'app/normal.php', 'renamed'),
    ];
    foreach ($actions as $name => $action) {
        $plan = $makePlan($action);
        $throws(static fn () => FilesystemRecoveryPlan::fromV3Plan($plan), "Recovery plan {$name} allowed .htaccess.");
        mkdir($storage . DIRECTORY_SEPARATOR . $name, 0700, true);
        $operations = new LifecycleOperationStore($storage . DIRECTORY_SEPARATOR . $name);
        $operation = new LifecycleOperationRecord($plan->operationIdentity(), 'patch', '0.14.0', 'htaccess-test', str_repeat('d', 64), $staging, str_repeat('e', 64), str_repeat('f', 64), LifecycleOperationRecord::PREPARING, 0, null, null, null, gmdate(DATE_ATOM), gmdate(DATE_ATOM));
        $operations->create($operation);
        $session = StagingSession::create($live, $staging . DIRECTORY_SEPARATOR . $name);
        $payload = new StagedPayload($session, str_repeat('1', 64), []);
        $guard = new LiveTreePathGuard($live);
        $captured = false;
        $result = (new V3ExplicitApplyExecutor())->execute($plan, $payload, null, $operation, $operations, new PackageOwnedFileApplier($guard, LiveFileActivationCapability::current(), $root . DIRECTORY_SEPARATOR . 'apply'), $guard, static function () use (&$captured): bool { $captured = true; return true; });
        $assert($result->status() === 'blocked' && !$captured, "Executor {$name} did not fail before side effect.");
        $session->cleanup();
    }
    $normalSession = StagingSession::create($live, $staging . DIRECTORY_SEPARATOR . 'applier');
    mkdir($normalSession->payloadPath() . DIRECTORY_SEPARATOR . 'public', 0700, true);
    file_put_contents($normalSession->payloadPath() . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . '.htaccess', 'hta');
    $normalPayload = new StagedPayload($normalSession, str_repeat('2', 64), [new StagedFile('public/.htaccess', 3, $hash('hta'))]);
    $throws(static fn () => (new PackageOwnedFileApplier(new LiveTreePathGuard($live), LiveFileActivationCapability::current(), $root . DIRECTORY_SEPARATOR . 'apply'))->apply(WebcoreApplyPlan::fromPayload($normalPayload)), 'Applier allowed protected path mutation.');
    $assert(!file_exists($live . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . '.htaccess'), 'Applier created a protected file.');
    $normalSession->cleanup();
    echo "HTACCESS protection assertions: {$assertions}\n";
} finally {
    $remove($root);
}
