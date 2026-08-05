<?php

declare(strict_types=1);

use Copot\Core\ModuleDiscovery;
use Copot\Core\ModuleIdentity;
use Copot\Core\ModuleMigrationDeclaration;
use Copot\Core\ModuleMigrationDescriptor;
use Copot\Core\ModulePackageConflictDeclaration;
use Copot\Core\ModulePackageContract;
use Copot\Core\ModulePackageDependencyDeclaration;
use Copot\Core\ModulePackageOwnership;
use Copot\Core\ModulePackageTarget;
use Copot\Core\ModulePermissionDeclaration;
use Copot\Core\ModuleProvisioningDeclaration;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageIdentity;
use Copot\Core\PackageRuntimeCompatibility;

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

$module = new ModuleIdentity('content');
$package = new PackageIdentity('copot-content-package');
$webcore = new PackageCompatibility('0.12.0', '1.0.0');
$runtime = new PackageRuntimeCompatibility('8.2.0', ['mysql' => '8.0.0'], ['json', 'pdo_mysql']);
$ownership = new ModulePackageOwnership($module, 'modules/content');
$dependency = new ModulePackageDependencyDeclaration(
    new ModulePackageTarget(ModulePackageTarget::MODULE, 'taxonomy'),
    new PackageCompatibility('0.1.0', '1.0.0')
);
$conflict = new ModulePackageConflictDeclaration(
    new ModulePackageTarget(ModulePackageTarget::PACKAGE, 'legacy-content'),
    new PackageCompatibility('1.0.0', '2.0.0')
);
$migration = new ModuleMigrationDeclaration(
    $module,
    true,
    'content-migrations-v1',
    [new ModuleMigrationDescriptor('content-001', 1, new PackageCompatibility('0.1.0'), '1.0.0', 'content-schema-1')]
);
$provisioning = new ModuleProvisioningDeclaration(
    'content-schema-1',
    [new ModulePermissionDeclaration('content.read', 'Read content')]
);

$contract = new ModulePackageContract(
    ModulePackageContract::MODULE_PACKAGE_TYPE,
    ModulePackageContract::CURRENT_CONTRACT_VERSION,
    $package,
    $module,
    'Content Module Package',
    '1.0.0',
    'copot-content-release-1',
    $webcore,
    $runtime,
    $ownership,
    [$dependency],
    [$conflict],
    $migration,
    $provisioning
);

$assert($contract->packageType() === 'copot-module', 'Module package type was not retained.');
$assert($contract->packageIdentity()->value() === 'copot-content-package', 'Package identity was not retained separately.');
$assert($contract->moduleIdentity()->value() === 'content', 'Technical Module identity was not retained.');
$assert($contract->title() === 'Content Module Package', 'Human-facing title was not retained.');
$assert($contract->packageVersion() === '1.0.0', 'Package SemVer was not retained.');
$assert($contract->releaseIdentity() === 'copot-content-release-1', 'Release identity was not retained.');
$assert($contract->supportsCommittedWebcoreVersion('0.12.0'), 'Committed Webcore minimum was rejected.');
$assert(!$contract->supportsCommittedWebcoreVersion('1.0.0'), 'Exclusive Webcore maximum was accepted.');
$assert($contract->ownership()->rootPath() === 'modules/content', 'Module ownership root was incorrect.');
$assert(count($contract->dependencies()) === 1, 'Dependency declaration was not retained.');
$assert(count($contract->conflicts()) === 1, 'Conflict declaration was not retained.');
$assert($contract->migrationDeclaration()->owner()->value() === 'content', 'Migration ownership was not retained.');
$assert($contract->provisioningDeclaration()->permissions()[0]->slug() === 'content.read', 'Permission declaration was not retained.');
$assert($contract->toArray()['package_identity'] !== $contract->toArray()['technical_module_identity'], 'Serialized identities were conflated.');

$assert((new ModulePackageTarget(ModulePackageTarget::MODULE, 'taxonomy'))->kind() === ModulePackageTarget::MODULE, 'Module dependency target kind was not retained.');
$assert((new ModulePackageTarget(ModulePackageTarget::PACKAGE, 'legacy-content'))->kind() === ModulePackageTarget::PACKAGE, 'Package conflict target kind was not retained.');
$assert($runtime->requiredExtensions() === ['json', 'pdo_mysql'], 'Shared runtime requirement primitive was not reused.');

$throws(static fn (): PackageIdentity => new PackageIdentity('Content Package'), 'Invalid package identity');
$throws(static fn (): ModuleIdentity => new ModuleIdentity('Content'), 'Invalid technical Module identity');
$throws(static fn (): ModulePackageTarget => new ModulePackageTarget('unsupported', 'content'), 'Invalid target kind');
$throws(static fn (): ModulePackageOwnership => new ModulePackageOwnership($module, 'modules/taxonomy'), 'Ownership/module mismatch');
$throws(static fn (): ModulePackageContract => new ModulePackageContract(
    'copot-webcore', 1, $package, $module, 'Content Module Package', '1.0.0', 'copot-content-release-1',
    $webcore, null, $ownership, [], [], new ModuleMigrationDeclaration($module), new ModuleProvisioningDeclaration()
), 'Wrong Module package type');
$throws(static fn (): ModulePackageContract => new ModulePackageContract(
    'copot-module', 1, new PackageIdentity('content'), $module, 'Content Module Package', '1.0.0', 'copot-content-release-1',
    $webcore, null, $ownership, [], [], new ModuleMigrationDeclaration($module), new ModuleProvisioningDeclaration()
), 'Package/technical identity confusion');
$throws(static fn (): ModulePackageContract => new ModulePackageContract(
    'copot-module', 1, $package, $module, 'Content Module Package', '1.02.0', 'copot-content-release-1',
    $webcore, null, $ownership, [], [], new ModuleMigrationDeclaration($module), new ModuleProvisioningDeclaration()
), 'Invalid package SemVer');
$throws(static fn (): PackageCompatibility => new PackageCompatibility('1.0.0', '0.12.0'), 'Unsupported Webcore range');
$throws(static fn (): ModulePackageDependencyDeclaration => new ModulePackageDependencyDeclaration(
    new ModulePackageTarget(ModulePackageTarget::MODULE, 'taxonomy'), new PackageCompatibility('1.0.0', '0.12.0')
), 'Invalid dependency version constraint');
$throws(static fn (): ModulePackageContract => new ModulePackageContract(
    'copot-module', 1, $package, $module, 'Content Module Package', '1.0.0', 'copot-content-release-1',
    $webcore, null, $ownership,
    [new ModulePackageDependencyDeclaration(new ModulePackageTarget(ModulePackageTarget::MODULE, 'content'), $webcore)],
    [], new ModuleMigrationDeclaration($module), new ModuleProvisioningDeclaration()
), 'Self-dependency declaration');
$throws(static fn (): ModulePackageContract => new ModulePackageContract(
    'copot-module', 1, $package, $module, 'Content Module Package', '1.0.0', 'copot-content-release-1',
    $webcore, null, $ownership, [$dependency, $dependency], [], new ModuleMigrationDeclaration($module), new ModuleProvisioningDeclaration()
), 'Duplicate dependency declaration');
$throws(static fn (): ModulePackageContract => new ModulePackageContract(
    'copot-module', 1, $package, $module, 'Content Module Package', '1.0.0', 'copot-content-release-1',
    $webcore, null, $ownership, [], [$conflict, $conflict], new ModuleMigrationDeclaration($module), new ModuleProvisioningDeclaration()
), 'Duplicate conflict declaration');
$throws(static fn (): ModuleMigrationDeclaration => new ModuleMigrationDeclaration($module, true), 'Malformed migration declaration');
$throws(static fn (): ModuleMigrationDeclaration => new ModuleMigrationDeclaration(
    $module, true, 'content-migrations-v1', [
        new ModuleMigrationDescriptor('content-002', 2, new PackageCompatibility('0.1.0'), '1.0.0', 'content-schema-1'),
        new ModuleMigrationDescriptor('content-001', 1, new PackageCompatibility('0.1.0'), '1.0.0', 'content-schema-1'),
    ]
), 'Unordered migration declaration');
$throws(static fn (): ModulePackageContract => new ModulePackageContract(
    'copot-module', 1, $package, $module, 'Content Module Package', '1.0.0', 'copot-content-release-1',
    $webcore, null, $ownership, [], [], new ModuleMigrationDeclaration(new ModuleIdentity('taxonomy')), new ModuleProvisioningDeclaration()
), 'Migration ownership mismatch');
$throws(static fn (): ModuleProvisioningDeclaration => new ModuleProvisioningDeclaration(
    'content-schema-1', [new ModulePermissionDeclaration('content.read', 'Read content'), new ModulePermissionDeclaration('content.read', 'Duplicate')]
), 'Duplicate permission declaration');
$throws(static fn (): ModulePermissionDeclaration => new ModulePermissionDeclaration('', 'Read content'), 'Malformed permission declaration');
$throws(static fn (): PackageRuntimeCompatibility => new PackageRuntimeCompatibility('8.2.0', [], ['json']), 'Invalid runtime requirement');

$discovered = (new ModuleDiscovery($basePath . '/modules'))->discover();
$content = array_values(array_filter($discovered, static fn ($definition): bool => $definition->name() === 'content'))[0] ?? null;
$assert($content !== null && $content->name() === basename($content->path()), 'Existing module.json folder/name invariant changed.');
$assert($content !== null && $content->version() === '0.1.0', 'Existing first-party module manifest changed unexpectedly.');

echo "Module Package Lifecycle WU1 focused tests passed ({$assertions} assertions)." . PHP_EOL;
