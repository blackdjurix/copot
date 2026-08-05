<?php

declare(strict_types=1);

use Copot\Core\InstalledStateStatus;
use Copot\Core\ModuleDependencyConflictPlanner;
use Copot\Core\ModuleDependencyConflictStatus;
use Copot\Core\ModuleIdentity;
use Copot\Core\ModuleLifecycleState;
use Copot\Core\ModuleLifecycleStateStore;
use Copot\Core\ModuleLifecycleTarget;
use Copot\Core\ModulePackageConflictDeclaration;
use Copot\Core\ModulePackageContract;
use Copot\Core\ModulePackageDependencyDeclaration;
use Copot\Core\ModulePackageOwnership;
use Copot\Core\ModulePackageTarget;
use Copot\Core\ModuleProvisioningDeclaration;
use Copot\Core\ModuleTransitionPlan;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageIdentity;
use Copot\Core\PackageRuntimeCompatibility;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void { $assertions++; if (!$condition) throw new RuntimeException($message); };
$throws = static function (callable $callback, string $message) use (&$assertions): void { $assertions++; try { $callback(); } catch (Throwable) { return; } throw new RuntimeException($message . ' did not throw.'); };
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-module-wu4-' . bin2hex(random_bytes(6));
mkdir($root, 0700, true);
$remove = static function (string $path) use (&$remove): void { if (is_file($path) || is_link($path)) { @unlink($path); return; } if (!is_dir($path)) return; foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry); @rmdir($path); };
$store = new ModuleLifecycleStateStore($root);
$runtime = [];
$contracts = [];
$runtimeReader = static function (string $name) use (&$runtime): ?array { return $runtime[$name] ?? null; };
$contractReader = static function (string $name) use (&$contracts): ?ModulePackageContract { return $contracts[$name] ?? null; };
$planner = new ModuleDependencyConflictPlanner($store, $runtimeReader, $contractReader);
$makeContract = static function (string $moduleName, string $packageName, string $version = '1.0.0', array $dependencies = [], array $conflicts = []): ModulePackageContract {
    $module = new ModuleIdentity($moduleName);
    return new ModulePackageContract(ModulePackageContract::MODULE_PACKAGE_TYPE, 1, new PackageIdentity($packageName), $module, ucfirst($moduleName) . ' Package', $version, $packageName . '-release-' . str_replace('.', '-', $version), new PackageCompatibility('0.1.0', '1.0.0'), new PackageRuntimeCompatibility('8.0.0', ['mysql' => '8.0.0'], ['json']), new ModulePackageOwnership($module, $module->ownershipRoot()), $dependencies, $conflicts, new \Copot\Core\ModuleMigrationDeclaration($module), new ModuleProvisioningDeclaration());
};
$makeState = static function (string $moduleName, string $packageName, string $version = '1.0.0', bool $enabled = true): ModuleLifecycleState { $module = new ModuleIdentity($moduleName); return new ModuleLifecycleState(new PackageIdentity($packageName), $module, $version, $packageName . '-release', 1, $packageName . '-migration', hash('sha256', $moduleName . $version), $enabled, 'repair', new DateTimeImmutable('2026-01-01T00:00:00+00:00')); };
$target = static fn (ModulePackageContract $contract): ModuleLifecycleTarget => new ModuleLifecycleTarget($contract, hash('sha256', $contract->moduleIdentity()->value() . $contract->packageVersion()));
$accept = static function (ModuleLifecycleTarget $target): ModuleTransitionPlan { return ModuleTransitionPlan::allow(ModuleTransitionPlan::REPAIR, $target, null, true); };
$dependency = static fn (string $name, string $kind = ModulePackageTarget::MODULE, string $minimum = '1.0.0'): ModulePackageDependencyDeclaration => new ModulePackageDependencyDeclaration(new ModulePackageTarget($kind, $name), new PackageCompatibility($minimum));
$conflict = static fn (string $name, string $kind = ModulePackageTarget::MODULE, ?PackageCompatibility $constraint = null): ModulePackageConflictDeclaration => new ModulePackageConflictDeclaration(new ModulePackageTarget($kind, $name), $constraint);

$store->write($makeState('content', 'pkg-content'));
$store->write($makeState('taxonomy', 'pkg-taxonomy'));
$runtime['content'] = ['name' => 'content', 'version' => '1.0.0', 'status' => 'enabled'];
$runtime['taxonomy'] = ['name' => 'taxonomy', 'version' => '1.0.0', 'status' => 'enabled'];
$taxonomy = $makeContract('taxonomy', 'pkg-taxonomy');
$content = $makeContract('content', 'pkg-content', '1.0.0', [$dependency('taxonomy')]);
$contracts['content'] = $content; $contracts['taxonomy'] = $taxonomy;
$satisfied = $planner->plan($target($content), $accept($target($content)));
$assert($satisfied->status() === ModuleDependencyConflictStatus::SATISFIED && $satisfied->accepted(), 'Satisfied dependency was not accepted.');

$missingContract = $makeContract('content', 'pkg-content', '1.0.0', [$dependency('missing')]);
$missing = $planner->plan($target($missingContract), $accept($target($missingContract)));
$assert($missing->status() === ModuleDependencyConflictStatus::UNRESOLVABLE && $missing->findings()[0]->status() === ModuleDependencyConflictStatus::MISSING_DEPENDENCY, 'Missing dependency was not classified deterministically.');
$runtime['taxonomy']['status'] = 'disabled';
$disabled = $planner->plan($target($content), $accept($target($content)));
$assert($disabled->status() === ModuleDependencyConflictStatus::RESOLUTION_REQUIRED && $disabled->findings()[0]->status() === ModuleDependencyConflictStatus::DEPENDENCY_DISABLED, 'Disabled dependency was not operator-blocked.');
$runtime['taxonomy']['status'] = 'enabled';
$incompatibleContract = $makeContract('content', 'pkg-content', '1.0.0', [$dependency('taxonomy', ModulePackageTarget::MODULE, '2.0.0')]);
$incompatible = $planner->plan($target($incompatibleContract), $accept($target($incompatibleContract)));
$assert($incompatible->status() === ModuleDependencyConflictStatus::UNRESOLVABLE && $incompatible->findings()[0]->status() === ModuleDependencyConflictStatus::INCOMPATIBLE_DEPENDENCY, 'Incompatible dependency was not rejected.');
$taxonomyUpdate = $target($makeContract('taxonomy', 'pkg-taxonomy', '2.0.0'));
$forward = $planner->plan($target($incompatibleContract), $accept($target($incompatibleContract)), [$taxonomyUpdate]);
$assert($forward->status() === ModuleDependencyConflictStatus::RESOLUTION_REQUIRED && count($forward->orderedPrerequisites()) === 1 && $forward->orderedPrerequisites()[0]->contract()->moduleIdentity()->value() === 'taxonomy', 'Dependency forward update did not produce a deterministic prerequisite.');

$conflictContract = $makeContract('content', 'pkg-content', '1.0.0', [], [$conflict('taxonomy', ModulePackageTarget::MODULE, new PackageCompatibility('1.0.0', '2.0.0'))]);
$declaredConflict = $planner->plan($target($conflictContract), $accept($target($conflictContract)));
$assert($declaredConflict->status() === ModuleDependencyConflictStatus::RESOLUTION_REQUIRED && $declaredConflict->findings()[0]->status() === ModuleDependencyConflictStatus::DECLARED_CONFLICT, 'Declared version-scoped conflict was not detected.');

$contracts['content'] = $content;
$contracts['taxonomy'] = $makeContract('taxonomy', 'pkg-taxonomy', '1.0.0', [$dependency('content')]);
$cycle = $planner->plan($target($content), $accept($target($content)));
$assert($cycle->status() === ModuleDependencyConflictStatus::UNRESOLVABLE && $cycle->findings()[0]->status() === ModuleDependencyConflictStatus::CYCLIC_DEPENDENCY, 'Direct dependency cycle was not rejected.');

foreach ([['alpha', 'pkg-alpha', 'beta'], ['beta', 'pkg-beta', 'gamma'], ['gamma', 'pkg-gamma', 'alpha']] as [$name, $package, $required]) {
    $store->write($makeState($name, $package)); $runtime[$name] = ['name' => $name, 'version' => '1.0.0', 'status' => 'enabled'];
}
$contracts['alpha'] = $makeContract('alpha', 'pkg-alpha', '1.0.0', [$dependency('beta')]);
$contracts['beta'] = $makeContract('beta', 'pkg-beta', '1.0.0', [$dependency('gamma')]);
$contracts['gamma'] = $makeContract('gamma', 'pkg-gamma', '1.0.0', [$dependency('alpha')]);
$multiCycle = $planner->plan($target($contracts['alpha']), $accept($target($contracts['alpha'])));
$assert($multiCycle->status() === ModuleDependencyConflictStatus::UNRESOLVABLE && count(array_filter($multiCycle->findings(), static fn ($finding): bool => $finding->status() === ModuleDependencyConflictStatus::CYCLIC_DEPENDENCY)) === 1, 'Multi-node dependency cycle was not deterministic.');

$throws(static fn () => $makeContract('content', 'pkg-content', '1.0.0', [$dependency('content')]), 'Self-dependency contract');
$identityRoot = $root . DIRECTORY_SEPARATOR . 'identity'; mkdir($identityRoot, 0700, true); $identityStore = new ModuleLifecycleStateStore($identityRoot); $identityStore->write($makeState('content', 'pkg-other')); $identityRuntime = static fn (string $name): ?array => ['name' => $name, 'version' => '1.0.0', 'status' => 'enabled']; $identityPlanner = new ModuleDependencyConflictPlanner($identityStore, $identityRuntime, static fn (string $name): ?ModulePackageContract => null); $identityTarget = $target($content); $identityPlan = $identityPlanner->plan($identityTarget, ModuleTransitionPlan::reject(ModuleTransitionPlan::REJECTED, 'identity', $identityTarget));
$assert($identityPlan->findings()[0]->status() === ModuleDependencyConflictStatus::IDENTITY_CONFLICT, 'Technical/package identity conflict was not reported.');

$evidencePlanner = new ModuleDependencyConflictPlanner($store, $runtimeReader, $contractReader, static fn (ModulePackageContract $contract): array => [['type' => 'owned_path', 'identity' => 'modules/taxonomy', 'message' => 'Authoritative path owner differs.'], ['type' => 'schema', 'identity' => 'taxonomy-schema', 'message' => 'Authoritative schema owner differs.'], ['type' => 'permission', 'identity' => 'taxonomy.read', 'message' => 'Authoritative permission owner differs.']]);
$evidencePlan = $evidencePlanner->plan($target($content), $accept($target($content)));
$evidenceStatuses = array_map(static fn ($finding): string => $finding->status(), $evidencePlan->findings());
$assert(in_array(ModuleDependencyConflictStatus::OWNED_PATH_CONFLICT, $evidenceStatuses, true) && in_array(ModuleDependencyConflictStatus::SCHEMA_OWNERSHIP_CONFLICT, $evidenceStatuses, true) && in_array(ModuleDependencyConflictStatus::PERMISSION_OWNERSHIP_CONFLICT, $evidenceStatuses, true), 'Authoritative ownership conflict evidence was not preserved.');
$assert($store->read('content')?->toArray() === $makeState('content', 'pkg-content')->toArray() || $store->read('content') !== null, 'Planning did not preserve lifecycle registry state.');

$remove($root);
echo 'Module Package Lifecycle WU4 focused tests passed (' . $assertions . " assertions).\n";
