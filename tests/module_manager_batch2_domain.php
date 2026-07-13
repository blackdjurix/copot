<?php

declare(strict_types=1);

use Copot\Core\ModuleDefinition;
use Copot\Core\ModuleDiscovery;
use Copot\Core\ModuleRepository;

$basePath = dirname(__DIR__);

chdir($basePath);
require $basePath . '/bootstrap/autoload.php';
require $basePath . '/modules/module-manager/Services/ModuleActionPolicy.php';
require $basePath . '/modules/module-manager/Services/ModuleInventoryBuilder.php';

final class Batch2FixtureDiscovery extends ModuleDiscovery
{
    public function __construct(private array $definitions, private array $fixtureErrors)
    {
    }

    public function discover(): array
    {
        return $this->definitions;
    }

    public function errors(): array
    {
        return $this->fixtureErrors;
    }
}

final class Batch2FixtureRepository extends ModuleRepository
{
    public function __construct(private array $rows, private array $permissions = [])
    {
    }

    public function all(): array
    {
        return $this->rows;
    }

    public function permissionsFor(string $moduleName): array
    {
        return $this->permissions[$moduleName] ?? [];
    }
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-m3-3-batch2-domain-' . bin2hex(random_bytes(5));
$removeDirectory = static function (string $path) use (&$removeDirectory): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $child = $path . DIRECTORY_SEPARATOR . $entry;
        is_dir($child) ? $removeDirectory($child) : unlink($child);
    }

    rmdir($path);
};
$makeDefinition = static function (string $name, string $title, string $version, array $requires = [], ?string $routes = null, ?string $listeners = null) use ($temporaryRoot): ModuleDefinition {
    $path = $temporaryRoot . DIRECTORY_SEPARATOR . $name;
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create a Batch 2 fixture directory.');
    }

    return new ModuleDefinition(
        $name,
        $title,
        $version,
        $path,
        'Batch 2 fixture',
        'Copot',
        $routes,
        $listeners,
        ['modules' => $requires],
        [['slug' => $name . '.read', 'name' => 'Read ' . $name]]
    );
};

mkdir($temporaryRoot, 0777, true);

try {
    $definitions = [
        $makeDefinition('alpha', '<Unsafe> Alpha', '1.0.0'),
        $makeDefinition('beta', 'Beta', '1.0.0', ['gamma']),
        $makeDefinition('gamma', 'Gamma', '1.0.0'),
        $makeDefinition('route-missing', 'Route Missing', '1.0.0', [], 'routes.php'),
        $makeDefinition('listener-missing', 'Listener Missing', '1.0.0', [], null, 'listeners.php'),
        $makeDefinition('dependency-missing', 'Dependency Missing', '1.0.0', ['not-installed']),
        $makeDefinition('dependency-disabled', 'Dependency Disabled', '1.0.0', ['disabled-base']),
        $makeDefinition('version-constrained', 'Version Constrained', '1.0.0', [['name' => 'not-installed', 'version' => '>=1.0']], 'routes.php'),
        $makeDefinition('self-dependency', 'Self Dependency', '1.0.0', ['self-dependency']),
        $makeDefinition('duplicate-dependency', 'Duplicate Dependency', '1.0.0', ['gamma', 'gamma']),
        $makeDefinition('cycle-a', 'Cycle A', '1.0.0', ['cycle-b']),
        $makeDefinition('cycle-b', 'Cycle B', '1.0.0', ['cycle-a']),
        $makeDefinition('target', 'Target', '1.0.0'),
        $makeDefinition('dependent', 'Dependent', '1.0.0', ['target']),
        $makeDefinition('drift', 'Discovered Drift', '2.0.0'),
    ];
    $definitionsByName = [];
    foreach ($definitions as $definition) {
        $definitionsByName[$definition->name()] = $definition;
    }

    $rows = [
        ['name' => 'beta', 'title' => 'Beta', 'version' => '1.0.0', 'path' => $definitionsByName['beta']->path(), 'status' => 'disabled'],
        ['name' => 'gamma', 'title' => 'Gamma', 'version' => '1.0.0', 'path' => $definitionsByName['gamma']->path(), 'status' => 'enabled'],
        ['name' => 'missing-installed', 'title' => 'Missing', 'version' => '1.0.0', 'path' => $temporaryRoot . DIRECTORY_SEPARATOR . 'gone', 'status' => 'disabled'],
        ['name' => 'malformed', 'title' => 'Malformed', 'version' => '1.0.0', 'path' => $temporaryRoot . DIRECTORY_SEPARATOR . 'malformed', 'status' => 'disabled'],
        ['name' => 'invalid', 'title' => 'Invalid', 'version' => '1.0.0', 'path' => $temporaryRoot . DIRECTORY_SEPARATOR . 'invalid', 'status' => 'disabled'],
        ['name' => 'route-missing', 'title' => 'Route Missing', 'version' => '1.0.0', 'path' => $definitionsByName['route-missing']->path(), 'status' => 'disabled'],
        ['name' => 'listener-missing', 'title' => 'Listener Missing', 'version' => '1.0.0', 'path' => $definitionsByName['listener-missing']->path(), 'status' => 'disabled'],
        ['name' => 'dependency-missing', 'title' => 'Dependency Missing', 'version' => '1.0.0', 'path' => $definitionsByName['dependency-missing']->path(), 'status' => 'disabled'],
        ['name' => 'dependency-disabled', 'title' => 'Dependency Disabled', 'version' => '1.0.0', 'path' => $definitionsByName['dependency-disabled']->path(), 'status' => 'disabled'],
        ['name' => 'version-constrained', 'title' => 'Version Constrained', 'version' => '1.0.0', 'path' => $definitionsByName['version-constrained']->path(), 'status' => 'disabled'],
        ['name' => 'disabled-base', 'title' => 'Disabled Base', 'version' => '1.0.0', 'path' => $temporaryRoot . DIRECTORY_SEPARATOR . 'disabled-base', 'status' => 'disabled'],
        ['name' => 'self-dependency', 'title' => 'Self Dependency', 'version' => '1.0.0', 'path' => $definitionsByName['self-dependency']->path(), 'status' => 'disabled'],
        ['name' => 'duplicate-dependency', 'title' => 'Duplicate Dependency', 'version' => '1.0.0', 'path' => $definitionsByName['duplicate-dependency']->path(), 'status' => 'disabled'],
        ['name' => 'cycle-a', 'title' => 'Cycle A', 'version' => '1.0.0', 'path' => $definitionsByName['cycle-a']->path(), 'status' => 'disabled'],
        ['name' => 'cycle-b', 'title' => 'Cycle B', 'version' => '1.0.0', 'path' => $definitionsByName['cycle-b']->path(), 'status' => 'disabled'],
        ['name' => 'target', 'title' => 'Target', 'version' => '1.0.0', 'path' => $definitionsByName['target']->path(), 'status' => 'enabled'],
        ['name' => 'dependent', 'title' => 'Dependent', 'version' => '1.0.0', 'path' => $definitionsByName['dependent']->path(), 'status' => 'enabled'],
        ['name' => 'invalid-status', 'title' => 'Invalid Status', 'version' => '1.0.0', 'path' => $temporaryRoot . DIRECTORY_SEPARATOR . 'invalid-status', 'status' => 'pending'],
        ['name' => 'drift', 'title' => 'Stored Drift', 'version' => '1.0.0', 'path' => $temporaryRoot . DIRECTORY_SEPARATOR . 'old-drift', 'status' => 'disabled'],
    ];
    $errors = [
        ['module' => 'malformed', 'error' => 'module.json must contain valid JSON object metadata.'],
        ['module' => 'invalid', 'error' => 'Missing required field [version].'],
        ['module' => 'ghost-dependent', 'error' => 'Missing required field [title].'],
    ];
    $permissions = [
        'beta' => [['permission_slug' => 'beta.read', 'permission_name' => 'Read old beta']],
    ];
    $builder = new ModuleInventoryBuilder(
        new Batch2FixtureDiscovery($definitions, $errors),
        new Batch2FixtureRepository($rows, $permissions)
    );
    $inventory = $builder->build();
    $byName = [];
    foreach ($inventory as $item) {
        $byName[$item['name']] = $item;
    }

    $orderedNames = array_keys($byName);
    $sortedNames = $orderedNames;
    sort($sortedNames, SORT_STRING);
    $assert($orderedNames === $sortedNames, 'Inventory keys were not deterministic.');
    $assert($byName['alpha']['lifecycle_state'] === 'not_installed', 'Not-installed lifecycle state is incorrect.');
    $assert($byName['beta']['lifecycle_state'] === 'installed_disabled', 'Disabled lifecycle state is incorrect.');
    $assert($byName['gamma']['lifecycle_state'] === 'installed_enabled', 'Enabled lifecycle state is incorrect.');
    $assert($byName['missing-installed']['discovery_state'] === 'missing', 'Missing discovery state is incorrect.');
    $assert($byName['malformed']['discovery_state'] === 'malformed', 'Malformed discovery state is incorrect.');
    $assert($byName['invalid']['discovery_state'] === 'invalid_metadata', 'Invalid metadata state is incorrect.');
    $assert($byName['alpha']['available_actions']['install']['enabled'] === true, 'Valid uninstalled module cannot be installed.');
    $assert($byName['beta']['available_actions']['enable']['enabled'] === true, 'Eligible disabled module cannot be enabled.');
    $assert($byName['gamma']['available_actions']['disable']['enabled'] === true, 'Safe enabled module cannot be disabled.');
    $assert(in_array('enabled_module', $byName['gamma']['denial_reasons']['uninstall'], true), 'Enabled module uninstall denial is missing.');
    $assert(in_array('route_file_missing', $byName['route-missing']['denial_reasons']['enable'], true), 'Missing route file did not block enablement.');
    $assert(in_array('listener_file_missing', $byName['listener-missing']['denial_reasons']['enable'], true), 'Missing listener file did not block enablement.');
    $assert(in_array('dependency_missing', $byName['dependency-missing']['denial_reasons']['enable'], true), 'Missing dependency did not block enablement.');
    $assert(in_array('dependency_disabled', $byName['dependency-disabled']['denial_reasons']['enable'], true), 'Disabled dependency did not block enablement.');
    $assert(in_array('unsupported_version_constraint', $byName['version-constrained']['denial_reasons']['enable'], true), 'Version constraint did not block enablement.');
    $assert($byName['version-constrained']['denial_reasons']['enable'][0] === 'route_file_missing', 'Primary denial ordering is not deterministic.');
    $assert(in_array('self_dependency', $byName['self-dependency']['denial_reasons']['enable'], true), 'Self-dependency did not block enablement.');
    $assert(in_array('duplicate_dependency', $byName['duplicate-dependency']['denial_reasons']['enable'], true), 'Duplicate dependency did not block enablement.');
    $assert(in_array('dependency_cycle', $byName['cycle-a']['denial_reasons']['enable'], true), 'Dependency cycle did not block enablement.');
    $assert(in_array('enabled_dependent', $byName['target']['denial_reasons']['disable'], true), 'Enabled dependent did not block disablement.');
    $unknownRows = array_merge($rows, [
        ['name' => 'unknown-target', 'title' => 'Unknown Target', 'version' => '1.0.0', 'path' => $temporaryRoot . DIRECTORY_SEPARATOR . 'unknown-target', 'status' => 'enabled'],
        ['name' => 'ghost-dependent', 'title' => 'Ghost', 'version' => '1.0.0', 'path' => $temporaryRoot . DIRECTORY_SEPARATOR . 'ghost', 'status' => 'enabled', 'requires' => ['unknown-target']],
    ]);
    $unknownInventory = (new ModuleInventoryBuilder(
        new Batch2FixtureDiscovery($definitions, $errors),
        new Batch2FixtureRepository($unknownRows, $permissions)
    ))->build();
    $unknownByName = [];
    foreach ($unknownInventory as $item) {
        $unknownByName[$item['name']] = $item;
    }
    $assert(in_array('dependent_safety_unknown', $unknownByName['unknown-target']['denial_reasons']['disable'], true), 'Unknown dependent safety did not fail closed.');
    $assert($unknownByName['gamma']['available_actions']['disable']['enabled'] === true, 'Unrelated target was globally blocked by unknown dependent safety.');
    $assert(in_array('dependent_safety_unknown', array_column($unknownByName['ghost-dependent']['diagnostics'], 'code'), true), 'Unresolved dependent safety limitation was not represented.');
    $assert($byName['invalid-status']['stored_status'] === 'invalid', 'Invalid stored status was not normalized safely.');
    $assert($byName['invalid-status']['lifecycle_state'] === 'installed_disabled', 'Invalid stored status changed the approved lifecycle taxonomy.');
    foreach (['enable', 'disable', 'uninstall'] as $action) {
        $assert($byName['invalid-status']['available_actions'][$action]['enabled'] === false, "Invalid stored status did not quarantine {$action}.");
        $assert($byName['invalid-status']['denial_reasons'][$action] === ['invalid_stored_status'], "Invalid stored status denial for {$action} is not primary.");
    }
    $assert(in_array('metadata_drift', array_column($byName['drift']['diagnostics'], 'code'), true), 'Metadata drift was not detected.');
    $assert($byName['drift']['available_actions']['enable']['enabled'] === true, 'Metadata drift alone blocked an otherwise valid action.');
    $assert(count($byName['beta']['permission_metadata_summary']) === 1, 'Permission metadata summary was not normalized.');
    $assert(
        count(array_filter($byName['beta']['diagnostics'], static fn (array $diagnostic): bool => $diagnostic['code'] === 'metadata_drift')) === 1,
        'Changed permission metadata was not detected as drift.'
    );
    $assert($byName['target']['lifecycle_state'] === 'installed_enabled' && $byName['target']['diagnostics'] !== [], 'Lifecycle state and diagnostics did not coexist.');

    echo "M3.3 Batch 2 domain contract passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $removeDirectory($temporaryRoot);
}
