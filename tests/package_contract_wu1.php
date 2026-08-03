<?php

declare(strict_types=1);

use Copot\Core\InstallationState;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageContract;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageOwnership;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\PackageVersion;
use Copot\Core\Version;

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
$throws = static function (callable $callback, string $message) use (&$assertions): void {
    $assertions++;

    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    } catch (Throwable $exception) {
        throw new RuntimeException($message . ' threw an unexpected exception: ' . $exception::class, 0, $exception);
    }

    throw new RuntimeException($message . ' did not throw.');
};

$hash = str_repeat('A', 64);
$normalized = new PackageInventoryEntry(
    './app\\Core//Version.php',
    139,
    $hash
);
$assert($normalized->path() === 'app/Core/Version.php', 'Package inventory path was not normalized.');
$assert($normalized->sha256() === strtolower($hash), 'Package inventory SHA-256 was not canonicalized.');
$assert($normalized->byteSize() === 139, 'Package inventory byte size was not retained.');
$assert($normalized->ownership() === PackageOwnership::PACKAGE_OWNED, 'Package inventory ownership was not retained.');

$assert(PackageVersion::isValid('0.12.0'), 'Valid semantic version was rejected.');
$assert(PackageVersion::isValid('1.0.0-rc.1'), 'Valid prerelease version was rejected.');
$assert(!PackageVersion::isValid('0.12'), 'Incomplete semantic version was accepted.');
$assert(!PackageVersion::isValid('v0.12.0'), 'Prefixed semantic version was accepted.');
$throws(static fn (): bool => PackageVersion::compare('0.12', '1.0.0') === 0, 'Invalid semantic version comparison');

$minimumOnly = new PackageCompatibility('0.12.0');
$assert($minimumOnly->supports('0.12.0'), 'Minimum source version should be inclusive.');
$assert($minimumOnly->supports('1.0.0'), 'Minimum-only source compatibility rejected a newer source.');
$bounded = new PackageCompatibility('0.12.0', '1.0.0');
$assert($bounded->supports('0.12.0'), 'Bounded source compatibility rejected its minimum.');
$assert($bounded->supports('0.99.0'), 'Bounded source compatibility rejected an interior version.');
$assert(!$bounded->supports('1.0.0'), 'Maximum source version should be exclusive.');
$assert(!$bounded->supports('0.11.9'), 'Bounded source compatibility accepted an older source.');
$throws(static fn (): PackageCompatibility => new PackageCompatibility('1.0.0', '1.0.0'), 'Equal compatibility range');
$throws(static fn (): PackageCompatibility => new PackageCompatibility('1.0.0', '0.12.0'), 'Reversed compatibility range');

$runtime = new PackageRuntimeCompatibility(
    '8.2',
    ['mysql' => '8.0.0', 'mariadb' => '10.4.32'],
    ['PDO', 'pdo_mysql', 'session', 'json', 'filter']
);
$assert($runtime->minimumPhpVersion() === '8.2', 'Runtime PHP requirement was changed.');
$assert(count($runtime->requiredExtensions()) === 5, 'Runtime extension requirements were not retained.');
$throws(static fn (): PackageRuntimeCompatibility => new PackageRuntimeCompatibility('8.2', [], ['pdo']), 'Empty database runtime requirements');
$throws(static fn (): PackageRuntimeCompatibility => new PackageRuntimeCompatibility('8.2', ['mysql' => '8.0'], []), 'Empty extension runtime requirements');

$migrationNone = new PackageMigrationDeclaration();
$assert(!$migrationNone->declaresCoreMigrations(), 'Default migration declaration unexpectedly requires migrations.');
$migrationDeclared = new PackageMigrationDeclaration(true, 'core-migrations-1');
$assert($migrationDeclared->declarationIdentity() === 'core-migrations-1', 'Migration declaration identity was not retained.');
$throws(static fn (): PackageMigrationDeclaration => new PackageMigrationDeclaration(true), 'Migration declaration without identity');
$throws(static fn (): PackageMigrationDeclaration => new PackageMigrationDeclaration(false, 'unexpected'), 'Migration identity without declaration');

$contract = new PackageContract(
    PackageContract::WEBCORE_PACKAGE_TYPE,
    PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION,
    '1.0.0',
    'release-opaque-1',
    'tree-opaque-1',
    $minimumOnly,
    $runtime,
    [
        $normalized,
        new PackageInventoryEntry('storage/cache/.gitkeep', 0, str_repeat('b', 64)),
    ],
    $migrationNone
);
$assert($contract->releaseIdentity() !== $contract->sourceTreeIdentity(), 'Release and source-tree identities were conflated.');
$assert($contract->versionRelation('0.12.0') === PackageContract::FORWARD, 'Forward version relation was not classified.');
$assert($contract->versionRelation('1.0.0') === PackageContract::REPAIR, 'Equal version relation was not reserved for repair.');
$assert($contract->versionRelation('1.1.0') === PackageContract::UNSUPPORTED_DOWNGRADE, 'Lower target version was not rejected.');
$throws(static fn (): PackageContract => new PackageContract(
    PackageContract::WEBCORE_PACKAGE_TYPE,
    0,
    '1.0.0',
    'release-opaque-1',
    null,
    $minimumOnly,
    $runtime,
    [$normalized],
    $migrationNone
), 'Invalid manifest contract version');
$throws(static fn (): PackageContract => new PackageContract(
    PackageContract::WEBCORE_PACKAGE_TYPE,
    1,
    '1.0.0',
    'release-opaque-1',
    null,
    $minimumOnly,
    $runtime,
    [$normalized, $normalized],
    $migrationNone
), 'Duplicate package inventory path');

$throws(static fn (): PackageInventoryEntry => new PackageInventoryEntry('../outside.php', 1, $hash), 'Traversal inventory path');
$throws(static fn (): PackageInventoryEntry => new PackageInventoryEntry('/absolute.php', 1, $hash), 'Absolute inventory path');
$throws(static fn (): PackageInventoryEntry => new PackageInventoryEntry('C:/absolute.php', 1, $hash), 'Drive-qualified inventory path');
$throws(static fn (): PackageInventoryEntry => new PackageInventoryEntry('app/file.php', -1, $hash), 'Negative inventory size');
$throws(static fn (): PackageInventoryEntry => new PackageInventoryEntry('app/file.php', 1, 'invalid'), 'Invalid inventory SHA-256');
$throws(static fn (): PackageInventoryEntry => new PackageInventoryEntry('.env', 1, $hash), 'Operator-owned path represented as package-owned');
$throws(static fn (): PackageInventoryEntry => new PackageInventoryEntry('storage/installed.lock', 1, $hash), 'Runtime-generated path represented as package-owned');
$operatorEntry = new PackageInventoryEntry('.env', 1, $hash, PackageOwnership::OPERATOR_OWNED);
$assert($operatorEntry->ownership() === PackageOwnership::OPERATOR_OWNED, 'Explicit operator ownership was rejected.');
$throws(static fn (): PackageContract => new PackageContract(
    PackageContract::WEBCORE_PACKAGE_TYPE,
    1,
    '1.0.0',
    'release-opaque-2',
    null,
    $minimumOnly,
    $runtime,
    [$operatorEntry],
    $migrationNone
), 'Operator-owned inventory entry');
$assert(PackageOwnership::classify('.env') === PackageOwnership::OPERATOR_OWNED, 'Environment ownership was misclassified.');
$assert(PackageOwnership::classify('storage/logs/copot.log') === PackageOwnership::RUNTIME_GENERATED, 'Log ownership was misclassified.');
$assert(PackageOwnership::classify('modules/example/module.json') === PackageOwnership::CONDITIONALLY_MANAGED, 'Conditional module ownership was misclassified.');
$assert(count(PackageOwnership::values()) === 4, 'Ownership classifications are incomplete.');

$temporaryStorage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu1-marker-' . bin2hex(random_bytes(6));
mkdir($temporaryStorage, 0777, true);
$removeDirectory = static function (string $path) use (&$removeDirectory): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $child = $path . DIRECTORY_SEPARATOR . $entry;
        is_dir($child) && !is_link($child) ? $removeDirectory($child) : unlink($child);
    }

    rmdir($path);
};

try {
    $state = new InstallationState($temporaryStorage);
    $assert(!$state->isInstalled(), 'Fresh installation state was not initially absent.');
    $state->createMarker(Version::CURRENT);
    $marker = $state->readMarker();
    $assert(($marker['version'] ?? null) === Version::CURRENT, 'Installation marker version changed.');
    $assert(count($marker ?? []) === 2, 'Installation marker schema changed.');
} finally {
    $removeDirectory($temporaryStorage);
}

$assert(Version::CURRENT === '0.12.0', 'Version::CURRENT changed during WU1.');

echo "WU1 package contract focused tests passed ({$assertions} assertions)." . PHP_EOL;
