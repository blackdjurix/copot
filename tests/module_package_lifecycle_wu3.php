<?php

declare(strict_types=1);

use Copot\Core\CommittedLifecycleState;
use Copot\Core\CommittedLifecycleStateStore;
use Copot\Core\InstalledStateStatus;
use Copot\Core\ModuleIdentity;
use Copot\Core\ModuleLifecycleState;
use Copot\Core\ModuleLifecycleStateInspector;
use Copot\Core\ModuleLifecycleStateStore;
use Copot\Core\ModuleLifecycleTarget;
use Copot\Core\ModulePackageContract;
use Copot\Core\ModulePackageOwnership;
use Copot\Core\ModuleProvisioningDeclaration;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageIdentity;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\RuntimeCompatibilityContext;
use Copot\Core\ModuleTransitionPlan;
use Copot\Core\ModuleTransitionPlanner;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-module-wu3-' . bin2hex(random_bytes(6));
mkdir($root, 0700, true);
$remove = static function (string $path) use (&$remove): void {
    if (is_file($path) || is_link($path)) { @unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry);
    @rmdir($path);
};

$module = new ModuleIdentity('content');
$runtimeRows = [];
$runtimeReader = static function (string $name) use (&$runtimeRows): ?array { return $runtimeRows[$name] ?? null; };
$store = new ModuleLifecycleStateStore($root);
$inspector = new ModuleLifecycleStateInspector($store, $runtimeReader);
$webcoreStore = new CommittedLifecycleStateStore($root);
$webcoreStore->write(new CommittedLifecycleState('0.12.0', 'webcore-release', null, 1, 'schema-1', 'migration-1', new DateTimeImmutable('2026-01-01T00:00:00+00:00')));
$runtimeContext = new RuntimeCompatibilityContext(PHP_VERSION, ['mysql' => '8.0.0'], ['json']);
$planner = new ModuleTransitionPlanner($webcoreStore, $runtimeContext);

$contract = static function (string $version = '1.0.0', string $package = 'copot-content-package', ?PackageRuntimeCompatibility $runtime = null, ?ModuleIdentity $targetModule = null) use ($module): ModulePackageContract {
    $targetModule ??= $module;
    return new ModulePackageContract(ModulePackageContract::MODULE_PACKAGE_TYPE, 1, new PackageIdentity($package), $targetModule, 'Content Package', $version, 'release-' . str_replace('.', '-', $version), new PackageCompatibility('0.1.0', '1.0.0'), $runtime ?? new PackageRuntimeCompatibility('8.0.0', ['mysql' => '8.0.0'], ['json']), new ModulePackageOwnership($targetModule, $targetModule->ownershipRoot()), [], [], new \Copot\Core\ModuleMigrationDeclaration($targetModule), new ModuleProvisioningDeclaration());
};
$target = static fn (ModulePackageContract $contract): ModuleLifecycleTarget => new ModuleLifecycleTarget($contract, str_repeat('a', 64));

$fresh = $inspector->inspect($module);
$assert($fresh->status() === InstalledStateStatus::FRESH, 'Fresh Module state was not identified.');
$freshPlan = $planner->plan($fresh, $target($contract()));
$assert($freshPlan->accepted() && $freshPlan->classification() === ModuleTransitionPlan::INSTALL && !$freshPlan->finalEnabled(), 'Fresh Module did not plan disabled INSTALL.');

$runtimeRows['content'] = ['name' => 'content', 'version' => '1.0.0', 'status' => 'enabled'];
$legacy = $inspector->inspect($module);
$assert($legacy->status() === InstalledStateStatus::LEGACY, 'Legacy runtime Module was not identified.');
$legacyPlan = $planner->plan($legacy, $target($contract()));
$assert(!$legacyPlan->accepted() && $legacyPlan->classification() === ModuleTransitionPlan::LEGACY_BLOCKED, 'Legacy Module was not fail-closed.');

$state = new ModuleLifecycleState(new PackageIdentity('copot-content-package'), $module, '1.0.0', 'release-1', 1, 'migration-1', str_repeat('b', 64), true, 'repair', new DateTimeImmutable('2026-01-02T00:00:00+00:00'));
$store->write($state);
$committed = $inspector->inspect($module);
$assert($committed->status() === InstalledStateStatus::COMMITTED, 'Committed Module state was not identified.');
$assert($store->read($module)?->toArray() === $state->toArray(), 'Module lifecycle state did not round-trip deterministically.');
$assert($planner->plan($committed, $target($contract()))->classification() === ModuleTransitionPlan::REPAIR, 'Same target was not classified as REPAIR.');
$assert($planner->plan($committed, $target($contract()))->finalEnabled(), 'REPAIR did not preserve enabled state.');
$repairEvidence = $planner->plan($committed, $target($contract()));
$assert($repairEvidence->versionRelation() === 'same' && $repairEvidence->releaseIdentityChanged() && $repairEvidence->packageIntegrityChanged(), 'REPAIR did not expose release/integrity comparison evidence.');
$disabledState = new ModuleLifecycleState(new PackageIdentity('copot-content-package'), $module, '1.0.0', 'release-1', 1, 'migration-1', str_repeat('c', 64), false, 'repair', new DateTimeImmutable('2026-01-03T00:00:00+00:00'));
$store->write($disabledState);
$runtimeRows['content'] = ['name' => 'content', 'version' => '1.0.0', 'status' => 'disabled'];
$disabledCommitted = $inspector->inspect($module);
$assert($planner->plan($disabledCommitted, $target($contract()))->classification() === ModuleTransitionPlan::REPAIR && !$planner->plan($disabledCommitted, $target($contract()))->finalEnabled(), 'REPAIR did not preserve disabled state.');
$store->write($state);
$runtimeRows['content'] = ['name' => 'content', 'version' => '1.0.0', 'status' => 'enabled'];

foreach ([['1.0.1', ModuleTransitionPlan::PATCH], ['1.1.0', ModuleTransitionPlan::UPDATE], ['2.0.0', ModuleTransitionPlan::UPGRADE]] as [$version, $classification]) {
    $plan = $planner->plan($committed, $target($contract($version)));
    $assert($plan->accepted() && $plan->classification() === $classification && $plan->finalEnabled(), $classification . ' classification or enabled-state preservation failed.');
}
$downgrade = $planner->plan($committed, $target($contract('0.9.0')));
$assert(!$downgrade->accepted() && str_contains($downgrade->reason(), 'Downgrade'), 'Downgrade was not rejected.');
$rebinding = $planner->plan($committed, $target($contract('1.0.0', 'copot-other-package')));
$assert(!$rebinding->accepted() && str_contains($rebinding->reason(), 'rebinding'), 'Package identity rebinding was not rejected.');
$technicalRebinding = $planner->plan($committed, $target($contract('1.0.0', 'copot-content-package', null, new ModuleIdentity('other'))));
$assert(!$technicalRebinding->accepted() && str_contains($technicalRebinding->reason(), 'Technical Module'), 'Technical Module identity rebinding was not rejected.');

$runtimeRows = [];
$assert($inspector->inspect($module)->status() === InstalledStateStatus::INCONSISTENT, 'Missing runtime registration was not inconsistent.');
$runtimeRows['content'] = ['name' => 'content', 'version' => '0.9.0', 'status' => 'disabled'];
$assert($inspector->inspect($module)->status() === InstalledStateStatus::INCONSISTENT, 'Contradictory runtime state was not inconsistent.');

$missingRoot = $root . DIRECTORY_SEPARATOR . 'missing';
mkdir($missingRoot, 0700, true);
$missingWebcore = new ModuleTransitionPlanner(new CommittedLifecycleStateStore($missingRoot), new RuntimeCompatibilityContext(PHP_VERSION, ['mysql' => '8.0.0'], ['json']));
$assert(!$missingWebcore->plan($committed, $target($contract()))->accepted(), 'Missing committed Webcore state was not rejected.');
$runtimeRows['content'] = ['name' => 'content', 'version' => '1.0.0', 'status' => 'disabled'];
$unsupportedRuntime = $planner->plan($fresh, $target($contract('1.0.0', 'copot-content-package', new PackageRuntimeCompatibility('99.0.0', ['mysql' => '8.0.0'], ['json']))));
$assert(!$unsupportedRuntime->accepted() && str_contains($unsupportedRuntime->reason(), 'runtime'), 'Unsatisfied runtime requirements were not rejected.');
$statePath = $root . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'content.json';
file_put_contents($statePath, '{"invalid":true}');
$assert($inspector->inspect($module)->status() === InstalledStateStatus::INVALID, 'Malformed Module lifecycle state was not invalid.');

$remove($root);
echo 'Module Package Lifecycle WU3 focused tests passed (' . $assertions . " assertions).\n";
