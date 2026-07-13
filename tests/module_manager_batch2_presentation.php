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

final class Batch2PresentationDiscovery extends ModuleDiscovery
{
    public function __construct(private array $definitions, private array $fixtureErrors = [])
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

final class Batch2PresentationRepository extends ModuleRepository
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
$temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-m3-3-batch2-presentation-' . bin2hex(random_bytes(5));
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

mkdir($temporaryRoot, 0777, true);

try {
    $alphaPath = $temporaryRoot . DIRECTORY_SEPARATOR . 'alpha';
    $betaPath = $temporaryRoot . DIRECTORY_SEPARATOR . 'beta';
    mkdir($alphaPath, 0777, true);
    mkdir($betaPath, 0777, true);
    $alpha = new ModuleDefinition(
        'alpha',
        '<script>alert(1)</script>',
        '1.0.0',
        $alphaPath,
        'Description <unsafe>',
        'Author',
        'routes.php',
        null,
        [],
        [['slug' => 'alpha.read', 'name' => 'Read alpha']]
    );
    $beta = new ModuleDefinition('beta', 'Beta', '1.0.0', $betaPath);
    $builder = new ModuleInventoryBuilder(
        new Batch2PresentationDiscovery([$beta, $alpha]),
        new Batch2PresentationRepository(
            [[
                'name' => 'alpha',
                'title' => 'Stored <unsafe>',
                'version' => '0.9.0',
                'path' => $temporaryRoot . DIRECTORY_SEPARATOR . 'old-alpha',
                'status' => 'disabled',
            ]],
            ['alpha' => [['permission_slug' => 'alpha.read', 'permission_name' => 'Read alpha']]]
        )
    );
    $inventory = $builder->build();
    $names = array_column($inventory, 'name');
    $assert($names === ['alpha', 'beta'], 'Inventory ordering is not deterministic.');

    $requiredFields = [
        'name',
        'title',
        'discovered_title',
        'stored_title',
        'version',
        'discovered_version',
        'stored_version',
        'lifecycle_state',
        'discovery_state',
        'dependencies',
        'permission_metadata_summary',
        'discovered_permission_metadata_summary',
        'stored_path_available',
        'discovered_path_available',
        'contribution_files',
        'diagnostics',
        'available_actions',
        'denial_reasons',
    ];
    foreach ($requiredFields as $field) {
        $assert(array_key_exists($field, $inventory[0]), "Normalized inventory field is missing [{$field}].");
    }

    $alphaItem = $inventory[0];
    $assert($alphaItem['discovered_title'] === '<script>alert(1)</script>', 'Discovered title was not preserved as data.');
    $assert($alphaItem['stored_title'] === 'Stored <unsafe>', 'Stored title was not kept separate from discovery.');
    $assert($alphaItem['discovered_version'] === '1.0.0' && $alphaItem['stored_version'] === '0.9.0',
        'Stored/discovered version separation is incorrect.');
    $assert($alphaItem['stored_path_available'] === false && $alphaItem['discovered_path_available'] === true,
        'Stored/discovered path availability was not normalized safely.');
    $assert($alphaItem['contribution_files']['routes']['available'] === false, 'Missing route availability was not normalized.');
    $codes = array_column($alphaItem['diagnostics'], 'code');
    $assert($codes === [
        'metadata_drift',
        'metadata_drift',
        'metadata_drift',
        'stored_path_unavailable',
        'route_file_missing',
    ],
        'Diagnostics are not ordered by the approved taxonomy.');
    $assert($alphaItem['available_actions']['enable']['enabled'] === false, 'Missing contribution file did not disable enablement.');
    $assert(in_array('route_file_missing', $alphaItem['denial_reasons']['enable'], true), 'Denial reason was not normalized.');

    $serialized = serialize($inventory);
    $assert(!str_contains($serialized, $temporaryRoot), 'Absolute temporary path leaked into the inventory.');
    $assert(!str_contains($serialized, 'PDOException') && !str_contains($serialized, 'SELECT '),
        'Raw exception or SQL details leaked into the inventory.');
    $assert(!str_contains($serialized, '<div') && !str_contains($serialized, '<form'),
        'Rendered HTML was introduced into the normalized inventory.');
    $assert($alphaItem['permission_metadata_summary'] === [[
        'slug' => 'alpha.read',
        'name' => 'Read alpha',
    ]], 'Permission metadata summary is not normalized safely.');

    echo "M3.3 Batch 2 presentation contract passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $removeDirectory($temporaryRoot);
}
