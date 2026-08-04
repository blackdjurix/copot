<?php

declare(strict_types=1);

use Copot\Core\InstallationState;
use Copot\Core\ExistingInstallEvidence;
use Copot\Core\InstalledStateInspection;
use Copot\Core\InstalledStateInspector;
use Copot\Core\InstalledStateStatus;
use Copot\Core\InstalledStateSnapshot;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageContract;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\PackageVersion;
use Copot\Core\PackageOwnership;
use Copot\Core\RuntimeCompatibilityContext;
use Copot\Core\TransitionPlan;
use Copot\Core\TransitionPlanner;

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

$hash = str_repeat('a', 64);
$runtimeRequirements = new PackageRuntimeCompatibility('8.0', ['sqlite' => '3.0'], ['pdo', 'json']);
$runtime = new RuntimeCompatibilityContext(PHP_VERSION, ['sqlite' => '3.40.0'], ['PDO', 'json']);
$inventory = [new PackageInventoryEntry('app/Core/Version.php', 1, $hash, PackageOwnership::PACKAGE_OWNED)];
$package = static function (string $version, string $minimumSource = '0.0.0') use ($runtimeRequirements, $inventory): PackageContract {
    return new PackageContract(
        PackageContract::WEBCORE_PACKAGE_TYPE,
        PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION,
        $version,
        'release-' . str_replace(['.', '-'], '_', $version),
        'tree-identity',
        new PackageCompatibility($minimumSource),
        $runtimeRequirements,
        $inventory,
        new PackageMigrationDeclaration(false)
    );
};

$planner = new TransitionPlanner();
$fresh = $planner->plan(InstalledStateInspection::fresh(), $package('0.12.0'), $runtime);
$assert($fresh->accepted() && $fresh->classification() === TransitionPlan::INSTALL, 'Fresh state was not classified as canonical INSTALL.');
$historicalInstall = $planner->plan(InstalledStateInspection::fresh(), $package('0.11.0'), $runtime);
$assert(!$historicalInstall->accepted() && str_contains($historicalInstall->reason(), 'canonical current'), 'Historical fresh target was accepted.');

$snapshot = new InstalledStateSnapshot('0.12.0', new DateTimeImmutable('2026-01-01T00:00:00+00:00'), 'installed-release', 'source-tree', 1, 'schema-state', 'migration-state');
$committed = InstalledStateInspection::committed($snapshot);
$repair = $planner->plan($committed, $package('0.12.0'), $runtime);
$assert($repair->accepted() && $repair->classification() === TransitionPlan::REPAIR, 'Equal precedence was not classified as REPAIR.');
$buildRepair = $planner->plan($committed, $package('0.12.0+build.2'), $runtime);
$assert($buildRepair->classification() === TransitionPlan::REPAIR, 'Build metadata change was not classified as REPAIR.');
$patch = $planner->plan($committed, $package('0.12.1'), $runtime);
$assert($patch->classification() === TransitionPlan::PATCH, 'Same major/minor forward transition was not PATCH.');
$assert($repair->installedState()?->schemaStateIdentity() === 'schema-state', 'Committed schema state was not handed off by the plan.');
$prereleaseSnapshot = InstalledStateInspection::committed(new InstalledStateSnapshot('0.12.0-rc.1', new DateTimeImmutable('2026-01-01T00:00:00+00:00'), 'installed-release', null, 1));
$prereleasePatch = $planner->plan($prereleaseSnapshot, $package('0.12.0-rc.2'), $runtime);
$assert($prereleasePatch->classification() === TransitionPlan::PATCH, 'Forward prerelease transition was not PATCH.');
$update = $planner->plan($committed, $package('0.13.0'), $runtime);
$assert($update->classification() === TransitionPlan::UPDATE, 'Same-major higher-minor transition was not UPDATE.');
$upgrade = $planner->plan($committed, $package('1.0.0'), $runtime);
$assert($upgrade->classification() === TransitionPlan::UPGRADE, 'Higher-major transition was not UPGRADE.');
$downgrade = $planner->plan($committed, $package('0.11.0'), $runtime);
$assert(!$downgrade->accepted() && str_contains($downgrade->reason(), 'Downgrade'), 'Downgrade was not rejected.');

$legacy = InstalledStateInspection::legacy(new InstalledStateSnapshot('0.12.0', new DateTimeImmutable('2026-01-01T00:00:00+00:00')));
$legacyPlan = $planner->plan($legacy, $package('0.12.1'), $runtime);
$assert(!$legacyPlan->accepted() && $legacyPlan->classification() === TransitionPlan::BOOTSTRAP_REQUIRED, 'Legacy state was not made bootstrap-required.');
$invalidPlan = $planner->plan(InstalledStateInspection::invalid('corrupt'), $package('0.12.1'), $runtime);
$assert(!$invalidPlan->accepted(), 'Invalid state was accepted.');
$inconsistentPlan = $planner->plan(InstalledStateInspection::inconsistent('marker and schema disagree'), $package('0.12.1'), $runtime);
$assert(!$inconsistentPlan->accepted(), 'Inconsistent state was accepted.');
$incompletePlan = $planner->plan(InstalledStateInspection::inconsistent('incomplete transition input'), $package('0.12.1'), $runtime);
$assert(!$incompletePlan->accepted(), 'Incomplete transition input was accepted.');

$unsupportedSource = $planner->plan($committed, $package('0.12.1', '0.13.0'), $runtime);
$assert(!$unsupportedSource->accepted() && str_contains($unsupportedSource->reason(), 'support'), 'Unsupported source state was accepted.');
$unsupportedRuntime = $planner->plan($committed, new PackageContract(
    PackageContract::WEBCORE_PACKAGE_TYPE,
    PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION,
    '0.12.1',
    'release-runtime-failure',
    null,
    new PackageCompatibility('0.0.0'),
    new PackageRuntimeCompatibility('99.0', ['sqlite' => '99.0'], ['missing-extension']),
    $inventory,
    new PackageMigrationDeclaration(true, 'migration-declaration')
), $runtime);
$assert(!$unsupportedRuntime->accepted() && str_contains($unsupportedRuntime->reason(), 'Runtime'), 'Unsupported runtime was accepted.');

$temporaryStorage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu3-state-' . bin2hex(random_bytes(6));
mkdir($temporaryStorage, 0700, true);
$removeDirectory = static function (string $path) use (&$removeDirectory): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $child = $path . DIRECTORY_SEPARATOR . $entry;
        is_dir($child) && !is_link($child) ? $removeDirectory($child) : @unlink($child);
    }

    @rmdir($path);
};

try {
    $state = new InstallationState($temporaryStorage);
    $inspection = new InstalledStateInspector();
    $assert($inspection->inspect($state, new ExistingInstallEvidence())->status() === InstalledStateStatus::FRESH, 'Absent marker without evidence was not FRESH.');
    $assert($inspection->inspect($state, new ExistingInstallEvidence(true))->status() === InstalledStateStatus::INCONSISTENT, 'Schema evidence without marker was not INCONSISTENT.');
    $assert($inspection->inspect($state, new ExistingInstallEvidence(false, true))->status() === InstalledStateStatus::INCONSISTENT, 'Environment evidence without marker was not INCONSISTENT.');
    $state->createMarker('0.12.0');
    $legacyInspection = $inspection->inspect($state, new ExistingInstallEvidence(true, true));
    $assert($legacyInspection->status() === InstalledStateStatus::LEGACY, 'Existing two-field marker was not LEGACY.');
    $assert($legacyInspection->snapshot()?->releaseIdentity() === null, 'Legacy release identity was inferred.');
    file_put_contents($temporaryStorage . DIRECTORY_SEPARATOR . 'installed.lock', '{invalid');
    $assert($inspection->inspect($state, new ExistingInstallEvidence())->status() === InstalledStateStatus::INVALID, 'Malformed marker was not INVALID.');
} finally {
    $removeDirectory($temporaryStorage);
}

echo "WU3 transition planner focused tests passed ({$assertions} assertions)." . PHP_EOL;
