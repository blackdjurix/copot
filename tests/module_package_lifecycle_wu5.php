<?php

declare(strict_types=1);

use Copot\Core\ModuleIdentity;
use Copot\Core\ModuleMigrationDeclaration;
use Copot\Core\ModuleMigrationDescriptor;
use Copot\Core\ModuleMigrationLedger;
use Copot\Core\ModuleMigrationReconciler;
use Copot\Core\ModuleMigrationReconciliationResult;
use Copot\Core\ModuleMigrationRegistry;
use Copot\Core\ModulePermissionDeclaration;
use Copot\Core\ModulePermissionReconciler;
use Copot\Core\ModulePermissionReconciliationResult;
use Copot\Core\ModuleProvisioningDeclaration;
use Copot\Core\ModuleProvisioningReconciler;
use Copot\Core\ModuleProvisioningReconciliationResult;
use Copot\Core\PackageCompatibility;

$basePath = dirname(__DIR__); chdir($basePath); require $basePath . '/bootstrap/autoload.php';
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void { $assertions++; if (!$condition) throw new RuntimeException($message); };
$throws = static function (callable $callback, string $message) use (&$assertions): void { $assertions++; try { $callback(); } catch (InvalidArgumentException) { return; } throw new RuntimeException($message . ' did not throw.'); };
$storage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu5-' . bin2hex(random_bytes(6)); mkdir($storage, 0700, true);
$removeTree = static function (string $path) use (&$removeTree): void { if (!is_dir($path)) return; foreach (scandir($path) ?: [] as $entry) { if ($entry === '.' || $entry === '..') continue; $child = $path . DIRECTORY_SEPARATOR . $entry; is_dir($child) && !is_link($child) ? $removeTree($child) : @unlink($child); } @rmdir($path); };
try {
    $module = new ModuleIdentity('sample');
    $baselineCalls = 0;
    $ledger = new ModuleMigrationLedger($storage);
    $reconciler = new ModuleMigrationReconciler($ledger);
    $fresh = $reconciler->freshBaseline($module, 'schema-1', static function (ModuleIdentity $owner) use (&$baselineCalls): void { $baselineCalls++; if ($owner->value() !== 'sample') throw new RuntimeException('Wrong baseline owner.'); });
    $assert($fresh->status() === ModuleMigrationReconciliationResult::COMPLETED && $baselineCalls === 1, 'Fresh Module baseline did not complete.');
    $assert($ledger->read($module)['records'] === [], 'Fresh baseline replayed historical migrations.');

    $executed = [];
    $migrationA = new ModuleMigrationDescriptor('sample.a', 10, new PackageCompatibility('1.0.0', '1.2.0'), '1.1.0', 'schema-2', ModuleMigrationDescriptor::TRANSACTIONAL, 'module-source:a', static function (PDO $connection) use (&$executed): void { $executed[] = 'a'; $connection->exec('CREATE TABLE wu5_a (id INTEGER)'); });
    $migrationB = new ModuleMigrationDescriptor('sample.b', 20, new PackageCompatibility('1.1.0'), '1.2.0', 'schema-3', ModuleMigrationDescriptor::NON_TRANSACTIONAL, 'module-source:b', static function (PDO $connection) use (&$executed): void { $executed[] = 'b'; });
    $registry = new ModuleMigrationRegistry($module, new ModuleMigrationDeclaration($module, true, 'sample-migrations', [$migrationA, $migrationB]));
    $connection = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stateBefore = $ledger->stateIdentity($module);
    $run = $reconciler->reconcile($connection, $module, '1.2.0', $registry, $stateBefore);
    $assert($run->status() === ModuleMigrationReconciliationResult::COMPLETED && $run->appliedMigrationIds() === ['sample.a', 'sample.b'], 'Ordered Module migrations did not complete: ' . $run->status() . ' / ' . json_encode($run->appliedMigrationIds()) . ' / ' . $run->reason());
    $assert($executed === ['a', 'b'] && count($ledger->read($module)['records']) === 2, 'Module migration ledger did not record the applied suffix.');
    $assert($run->stateIdentity() === $ledger->stateIdentity($module), 'Module migration state identity was not deterministic.');
    $noop = $reconciler->reconcile($connection, $module, '1.2.0', $registry, $run->stateIdentity());
    $assert($noop->status() === ModuleMigrationReconciliationResult::NOOP && $noop->appliedMigrationIds() === [], 'Completed Module history was not a no-op.');

    $failedStorage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu5-fail-' . bin2hex(random_bytes(6)); mkdir($failedStorage, 0700, true);
    $failedLedger = new ModuleMigrationLedger($failedStorage); $failedReconciler = new ModuleMigrationReconciler($failedLedger); $failedReconciler->freshBaseline($module, 'schema-1', static function (ModuleIdentity $owner): void {});
    $failedMigration = new ModuleMigrationDescriptor('sample.fail', 10, new PackageCompatibility('1.0.0'), '1.1.0', 'schema-fail', ModuleMigrationDescriptor::TRANSACTIONAL, 'module-source:fail', static function (PDO $connection): void { $connection->exec('CREATE TABLE wu5_rolled_back (id INTEGER)'); }, null, static function (PDO $connection): bool { return false; });
    $failedRegistry = new ModuleMigrationRegistry($module, new ModuleMigrationDeclaration($module, true, 'sample-fail', [$failedMigration]));
    $failedConnection = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $failed = $failedReconciler->reconcile($failedConnection, $module, '1.1.0', $failedRegistry, $failedLedger->stateIdentity($module));
    $assert($failed->status() === ModuleMigrationReconciliationResult::FAILED, 'Failed Module migration did not fail closed.');
    $assert((int) $failedConnection->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='wu5_rolled_back'")->fetchColumn() === 0, 'Failed transactional Module migration was not rolled back.');
    $assert($failedLedger->read($module)['records'] === [], 'Failed Module migration was recorded.');
    $removeTree($failedStorage);

    $throws(static fn (): ModuleMigrationRegistry => new ModuleMigrationRegistry(new ModuleIdentity('other'), new ModuleMigrationDeclaration($module, true, 'sample-migrations', [$migrationA])), 'Cross-owner migration registry');
    $provisionCalls = []; $provisioning = new ModuleProvisioningReconciler(static function (ModuleIdentity $owner, ModuleProvisioningDeclaration $declaration, bool $baseline) use (&$provisionCalls): void { $provisionCalls[] = [$owner->value(), $declaration->schemaIdentity(), $baseline]; }, static function (ModuleIdentity $owner, ModuleProvisioningDeclaration $declaration): bool { return $owner->value() === 'sample' && $declaration->schemaIdentity() === 'schema-3'; });
    $provision = new ModuleProvisioningDeclaration('schema-3');
    $provisionResult = $provisioning->reconcile($module, $provision);
    $assert($provisionResult->status() === ModuleProvisioningReconciliationResult::COMPLETED && $provisionCalls === [['sample', 'schema-3', false]], 'Module provisioning reconciliation was not bounded to the target.');
    $baselineResult = $provisioning->establishBaseline($module, $provision);
    $assert($baselineResult->status() === ModuleProvisioningReconciliationResult::COMPLETED && $provisionCalls[1][2] === true, 'Module provisioning baseline was not explicit.');

    $permissionRows = [['permission_slug' => 'old', 'permission_name' => 'Old']]; $upserts = [];
    $permissionReconciler = new ModulePermissionReconciler(static function (ModuleIdentity $owner) use (&$permissionRows): array { return $permissionRows; }, static function (ModuleIdentity $owner, ModulePermissionDeclaration $permission) use (&$upserts): void { $upserts[] = [$owner->value(), $permission->slug(), $permission->name()]; });
    $permissions = new ModuleProvisioningDeclaration(null, [new ModulePermissionDeclaration('new', 'New'), new ModulePermissionDeclaration('old', 'Renamed')]);
    $permissionResult = $permissionReconciler->reconcile($module, $permissions);
    $assert($permissionResult->status() === ModulePermissionReconciliationResult::COMPLETED && $permissionResult->added() === ['new'] && $permissionResult->changed() === ['old'] && $permissionResult->preserved() === [], 'Permission metadata add/change reconciliation was incorrect.');
    $assert($upserts === [['sample', 'new', 'New'], ['sample', 'old', 'Renamed']], 'Permission reconciliation touched the wrong owner.');
    $conflictReconciler = new ModulePermissionReconciler(static fn (ModuleIdentity $owner): array => [], static function (): void {}, static fn (string $slug): ?string => 'other');
    $permissionConflict = $conflictReconciler->reconcile($module, new ModuleProvisioningDeclaration(null, [new ModulePermissionDeclaration('same', 'Same')]));
    $assert($permissionConflict->status() === ModulePermissionReconciliationResult::FAILED, 'Cross-owner permission conflict was not blocked.');
} finally { $removeTree($storage); }
echo "WU5 Module migration/provisioning focused tests passed ({$assertions} assertions)." . PHP_EOL;
