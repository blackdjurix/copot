<?php

declare(strict_types=1);

use Copot\Core\CommittedLifecycleState;
use Copot\Core\CommittedLifecycleStateStore;
use Copot\Core\ExistingInstallEvidence;
use Copot\Core\HealthGateMatrix;
use Copot\Core\InstalledStateInspection;
use Copot\Core\InstalledStateInspector;
use Copot\Core\InstalledStateStatus;
use Copot\Core\InstallationState;
use Copot\Core\LiveTreePathGuard;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageContract;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageOwnership;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\RuntimeCompatibilityContext;
use Copot\Core\RuntimeHealthVerifier;
use Copot\Core\TargetPackageIntegrityVerifier;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) { throw new RuntimeException($message); }
};

$remove = static function (string $path) use (&$remove): void {
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) { return; }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') { $remove($path . DIRECTORY_SEPARATOR . $entry); }
    }
    @rmdir($path);
};

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu6-' . bin2hex(random_bytes(6));
mkdir($root, 0700, true);

try {
    $storage = $root . DIRECTORY_SEPARATOR . 'storage';
    $live = $root . DIRECTORY_SEPARATOR . 'live';
    mkdir($storage, 0700, true);
    mkdir($live . DIRECTORY_SEPARATOR . 'app', 0700, true);
    file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'target.txt', 'target');
    file_put_contents($live . DIRECTORY_SEPARATOR . 'extra.txt', 'unclassified extra');

    $state = new InstallationState($storage);
    $state->createMarker('0.12.0');
    $marker = $state->readMarker();
    $store = new CommittedLifecycleStateStore($storage);
    $inspector = new InstalledStateInspector($store);

    $assert($inspector->inspect($state, new ExistingInstallEvidence())->status() === InstalledStateStatus::LEGACY, 'Marker without rich state was not LEGACY.');

    $committed = new CommittedLifecycleState('0.12.0', 'release-12', 'tree-12', 1, 'schema-12', 'migration-12', new DateTimeImmutable($marker['installed_at']));
    $store->write($committed);
    $inspection = $inspector->inspect($state, new ExistingInstallEvidence());
    $assert($inspection->status() === InstalledStateStatus::COMMITTED, 'Compatible rich state was not COMMITTED.');
    $assert($inspection->snapshot()?->migrationStateIdentity() === 'migration-12', 'Rich migration identity was not exposed through WU3 inspection.');

    $state->replaceMarker('0.12.1', $marker['installed_at']);
    $assert($inspector->inspect($state, new ExistingInstallEvidence())->status() === InstalledStateStatus::INCONSISTENT, 'Marker contradiction was not INCONSISTENT.');
    $state->replaceMarker('0.12.0', $marker['installed_at']);
    file_put_contents($storage . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'committed-state.json', '{bad');
    $assert($inspector->inspect($state, new ExistingInstallEvidence())->status() === InstalledStateStatus::INVALID, 'Malformed rich state was not INVALID.');
    $store->write($committed);

    $hash = hash('sha256', 'target');
    $package = new PackageContract(
        PackageContract::WEBCORE_PACKAGE_TYPE,
        PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION,
        '0.12.0',
        'release-12',
        'tree-12',
        new PackageCompatibility('0.0.0'),
        new PackageRuntimeCompatibility('8.0', ['mysql' => '8.0'], ['json']),
        [new PackageInventoryEntry('app/target.txt', 6, $hash, PackageOwnership::PACKAGE_OWNED)],
        new PackageMigrationDeclaration(false)
    );
    $integrity = (new TargetPackageIntegrityVerifier())->verify($package, new LiveTreePathGuard($live));
    $assert($integrity instanceof HealthGateMatrix && $integrity->passed(), 'Valid target package integrity did not pass.');
    $assert(is_file($live . DIRECTORY_SEPARATOR . 'extra.txt'), 'Integrity verification mutated an unclassified live-tree file.');

    file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'target.txt', 'changed');
    $assert(!(new TargetPackageIntegrityVerifier())->verify($package, new LiveTreePathGuard($live))->passed(), 'Changed target file passed integrity.');

    $runtime = (new RuntimeHealthVerifier())->verify([
        'bootstrap' => static fn (): bool => true,
        'public' => static fn (): bool => true,
        'admin' => static fn (): bool => true,
    ]);
    $assert($runtime->passed(), 'Deterministic runtime health checks did not pass.');
    $failedRuntime = (new RuntimeHealthVerifier())->verify(['theme' => static fn (): bool => false]);
    $assert(!$failedRuntime->passed(), 'Failed runtime health check passed.');
} finally {
    $remove($root);
}

echo "WU6 health, integrity, and commit-state focused tests passed ({$assertions} assertions)." . PHP_EOL;
