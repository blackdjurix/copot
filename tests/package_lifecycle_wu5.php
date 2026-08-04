<?php

declare(strict_types=1);

use Copot\Core\CoreMigrationPlan;
use Copot\Core\InstallationMutex;
use Copot\Core\InstalledStateInspection;
use Copot\Core\InstalledStateSnapshot;
use Copot\Core\LifecycleOperationRecord;
use Copot\Core\LifecycleOperationStore;
use Copot\Core\LiveFileActivationCapability;
use Copot\Core\LiveTreePathGuard;
use Copot\Core\MaintenanceCoordinator;
use Copot\Core\MigrationRunResult;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageContract;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageOwnedFileApplier;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\PackageOwnership;
use Copot\Core\RuntimeCompatibilityContext;
use Copot\Core\StagedFile;
use Copot\Core\StagedPayload;
use Copot\Core\StagingSession;
use Copot\Core\TransitionPlan;
use Copot\Core\WebcoreApplyCoordinator;
use Copot\Core\WebcoreApplyPlan;
use Copot\Core\WebcoreApplyResult;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$remove = static function (string $path) use (&$remove): void {
    if (is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            $remove($path . DIRECTORY_SEPARATOR . $entry);
        }
    }
    @rmdir($path);
};

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu5-' . bin2hex(random_bytes(6));
$live = $root . DIRECTORY_SEPARATOR . 'live';
$storage = $live . DIRECTORY_SEPARATOR . 'storage';
$stagingRoot = $root . DIRECTORY_SEPARATOR . 'staging';
$applyRoot = $root . DIRECTORY_SEPARATOR . 'apply';
mkdir($storage, 0700, true);
mkdir($stagingRoot, 0700, true);

try {
    $store = new LifecycleOperationStore($storage);
    $hash = str_repeat('a', 64);
    $record = new LifecycleOperationRecord(
        'operation-1', 'patch', '0.12.1', 'release-1', $hash, $stagingRoot,
        $hash, $hash, LifecycleOperationRecord::PREPARING, 0, null, $hash,
        null, gmdate(DATE_ATOM), gmdate(DATE_ATOM)
    );
    $store->create($record);
    try {
        $store->create($record);
        $assert(false, 'A second active lifecycle operation was accepted.');
    } catch (Throwable) {
        $assert(true, 'A second active lifecycle operation was rejected.');
    }
    $assert($store->classify(false) === 'interrupted', 'Non-terminal record was not classified as interrupted without the mutex.');
    $assert($store->classify(true) === 'active', 'Non-terminal record was not classified as active for its executor.');
    $updated = $record->advance(LifecycleOperationRecord::APPLYING, 1, 'app/a.txt');
    $store->save($updated);
    $assert($store->read()?->lastVerifiedPath() === 'app/a.txt', 'Operation progress was not durably updated.');
    $store->clear($updated->advance(LifecycleOperationRecord::COMPLETED, 1, 'app/a.txt'));
    $assert($store->read() === null, 'Completed operation record was not cleared.');

    $session = StagingSession::create($live, $stagingRoot);
    mkdir($session->payloadPath() . DIRECTORY_SEPARATOR . 'app', 0700, true);
    file_put_contents($session->payloadPath() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'a.txt', 'new-content');
    $file = new StagedFile('app/a.txt', 11, hash('sha256', 'new-content'));
    $payload = new StagedPayload($session, $hash, [$file]);
    $plan = WebcoreApplyPlan::fromPayload($payload);
    $assert($plan->identity() !== '', 'Apply plan identity was not produced.');

    $guard = new LiveTreePathGuard($live);
    try {
        new PackageOwnedFileApplier($guard, new LiveFileActivationCapability(true, true), $live);
        $assert(false, 'Apply temporary root overlapping the live root was accepted.');
    } catch (Throwable) {
        $assert(true, 'Apply temporary root overlapping the live root was rejected.');
    }
    $applier = new PackageOwnedFileApplier($guard, new LiveFileActivationCapability(true, true), $applyRoot);
    $result = $applier->apply($plan);
    $assert($result->status() === WebcoreApplyResult::COMPLETED, 'Nested package-owned file creation failed.');
    $assert(file_get_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'a.txt') === 'new-content', 'Applied file contents were incorrect.');
    $assert(hash_file('sha256', $live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'a.txt') === $file->sha256(), 'Applied file hash was not preserved.');

    file_put_contents($live . DIRECTORY_SEPARATOR . 'keep.txt', 'operator-data');
    $assert($applier->apply($plan)->status() === WebcoreApplyResult::COMPLETED, 'Repeat apply did not complete.');
    $assert(file_get_contents($live . DIRECTORY_SEPARATOR . 'keep.txt') === 'operator-data', 'Apply removed an unrelated live-tree file.');
    file_put_contents($live . DIRECTORY_SEPARATOR . 'blocked', 'not-a-directory');
    try {
        $guard->ensureParent('blocked/child.txt');
        $assert(false, 'A non-directory live-tree ancestor was followed.');
    } catch (Throwable) {
        $assert(true, 'A non-directory live-tree ancestor was rejected.');
    }

    file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'a.txt', 'old-content');
    $replacementBlocked = new PackageOwnedFileApplier($guard, new LiveFileActivationCapability(true, false), $applyRoot);
    $blocked = $replacementBlocked->apply($plan);
    $assert($blocked->status() === WebcoreApplyResult::FAILED, 'Unsupported replacement capability was not rejected before mutation.');
    $assert(file_get_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'a.txt') === 'old-content', 'Unsupported replacement changed the live file.');

    try {
        $badSession = StagingSession::create($live, $stagingRoot);
        $badPayload = new StagedPayload($badSession, $hash, [new StagedFile('.env', 1, $hash)]);
        WebcoreApplyPlan::fromPayload($badPayload);
        $assert(false, 'Non-package-owned apply path was accepted.');
    } catch (Throwable) {
        $assert(true, 'Non-package-owned apply path was rejected.');
    }

    $runtime = new RuntimeCompatibilityContext(PHP_VERSION, [], []);
    $package = new PackageContract(
        PackageContract::WEBCORE_PACKAGE_TYPE,
        PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION,
        '0.12.1',
        'release-1',
        null,
        new PackageCompatibility('0.0.0'),
        new PackageRuntimeCompatibility('8.0', ['sqlite' => '3.0'], ['json']),
        [new PackageInventoryEntry('app/a.txt', 11, $file->sha256(), PackageOwnership::PACKAGE_OWNED)],
        new PackageMigrationDeclaration(false)
    );
    $transition = TransitionPlan::allow(TransitionPlan::PATCH, $package, new InstalledStateSnapshot('0.12.0', new DateTimeImmutable(), 'old-release', null, 1, 'schema', 'migration'));
    $mutex = new InstallationMutex($storage);
    $coordinatorStore = new LifecycleOperationStore($storage);
    $coordinatorMaintenance = new MaintenanceCoordinator($coordinatorStore);
    $coordinatorApplier = new PackageOwnedFileApplier($guard, new LiveFileActivationCapability(true, true), $applyRoot);
    $coordinator = new WebcoreApplyCoordinator($mutex, $coordinatorMaintenance, $coordinatorApplier, static fn (CoreMigrationPlan $plan): MigrationRunResult => new MigrationRunResult(MigrationRunResult::NOOP));
    file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'a.txt', 'old-content');
    $operationResult = $coordinator->execute($plan, $transition, CoreMigrationPlan::allow('0.12.0', '0.12.0', 'schema', 'schema', []));
    $assert($operationResult->status() === WebcoreApplyResult::AWAITING_WU6, 'Successful apply did not stop at the WU6 handoff.');
    $assert($coordinatorMaintenance->isActive(), 'Maintenance was cleared before WU6 commit.');
    $coordinator->acknowledgeW6((string) $operationResult->operationId());
    $assert(!$coordinatorMaintenance->isActive(), 'Maintenance did not clear after WU6 acknowledgement.');
    $assert(file_get_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'a.txt') === 'new-content', 'Coordinator did not apply the staged file.');

    $payload->cleanup();
    echo "WU5 apply and interruption focused tests passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $remove($root);
}
