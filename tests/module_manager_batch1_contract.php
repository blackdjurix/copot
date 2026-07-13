<?php

declare(strict_types=1);

use Copot\Core\Application;
use Copot\Core\Env;
use Copot\Core\ModuleDiscovery;
use Copot\Core\ModuleManager;
use Copot\Core\ModuleRepository;

$basePath = dirname(__DIR__);

chdir($basePath);
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$removeDirectory = static function (string $path) use (&$removeDirectory): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $child = $path . DIRECTORY_SEPARATOR . $entry;

        if (is_dir($child)) {
            $removeDirectory($child);
        } else {
            unlink($child);
        }
    }

    rmdir($path);
};

$temporaryDirectory = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'copot-m3-3-batch1-'
    . bin2hex(random_bytes(6));
$moduleName = 'batch1_contract_' . bin2hex(random_bytes(4));
$moduleDirectory = $temporaryDirectory . DIRECTORY_SEPARATOR . $moduleName;
$invalidDirectory = $temporaryDirectory . DIRECTORY_SEPARATOR . 'invalid-manifest';

if (!mkdir($moduleDirectory, 0777, true) || !mkdir($invalidDirectory, 0777, true)) {
    throw new RuntimeException('Unable to create temporary Module Manager fixtures.');
}

file_put_contents(
    $moduleDirectory . DIRECTORY_SEPARATOR . 'module.json',
    json_encode([
        'name' => $moduleName,
        'title' => 'Batch 1 Contract Fixture',
        'version' => '1.0.0',
        'permissions' => [
            [
                'slug' => $moduleName . '.read',
                'name' => 'Read Batch 1 fixture',
            ],
        ],
    ], JSON_THROW_ON_ERROR)
);
file_put_contents($invalidDirectory . DIRECTORY_SEPARATOR . 'module.json', '{');

$app = new Application($basePath);
$connection = $app->database()->connection();
$discovery = new ModuleDiscovery($temporaryDirectory);
$repository = new ModuleRepository($app->database());
$manager = new ModuleManager($discovery, $repository);

try {
    $connection->beginTransaction();

    $discovered = $manager->discover();
    $definition = null;

    foreach ($discovered as $candidate) {
        if ($candidate->name() === $moduleName) {
            $definition = $candidate;
            break;
        }
    }

    $assert($definition !== null, 'A valid module manifest was not discovered.');
    $assert($definition->title() === 'Batch 1 Contract Fixture', 'Discovered module title is incorrect.');
    $assert($definition->version() === '1.0.0', 'Discovered module version is incorrect.');
    $assert($definition->path() === $moduleDirectory, 'Discovered module path is incorrect.');

    $discoveryErrors = $manager->discoveryErrors();
    $assert(count($discoveryErrors) === 1, 'Malformed manifest did not produce one controlled discovery error.');
    $assert(
        ($discoveryErrors[0]['module'] ?? null) === 'invalid-manifest',
        'Discovery error did not identify the malformed fixture.'
    );
    $assert(
        !str_contains((string) ($discoveryErrors[0]['error'] ?? ''), $temporaryDirectory),
        'Discovery error exposed the temporary filesystem path.'
    );

    $assert(is_array($manager->installed()), 'Installed-module listing was not returned as an array.');
    $assert($repository->findByName($moduleName) === null, 'Fixture unexpectedly existed before installation.');

    $manager->install($moduleName);
    $installed = $repository->findByName($moduleName);
    $assert(is_array($installed), 'Installation did not create an installed module row.');
    $assert(($installed['status'] ?? null) === 'disabled', 'Installation did not produce a disabled module.');

    $listed = array_filter(
        $manager->installed(),
        static fn (array $module): bool => ($module['name'] ?? null) === $moduleName
    );
    $assert(count($listed) === 1, 'Installed-module listing did not include the fixture exactly once.');

    $permissions = $repository->permissionsFor($moduleName);
    $assert(count($permissions) === 1, 'Permission metadata was not stored exactly once.');
    $assert(
        ($permissions[0]['permission_slug'] ?? null) === $moduleName . '.read'
        && ($permissions[0]['permission_name'] ?? null) === 'Read Batch 1 fixture',
        'Stored permission metadata does not match the discovered manifest.'
    );

    $rowsBeforeDuplicate = (int) $connection->query(
        "SELECT COUNT(*) FROM modules WHERE name = " . $connection->quote($moduleName)
    )->fetchColumn();
    $permissionsBeforeDuplicate = count($repository->permissionsFor($moduleName));
    $duplicateError = null;

    try {
        $manager->install($moduleName);
    } catch (Throwable $exception) {
        $duplicateError = $exception;
    }

    $assert($duplicateError instanceof RuntimeException, 'Duplicate installation was not rejected.');
    $assert(
        !str_contains($duplicateError->getMessage(), $temporaryDirectory),
        'Duplicate-install error exposed the temporary filesystem path.'
    );
    $rowsAfterDuplicate = (int) $connection->query(
        "SELECT COUNT(*) FROM modules WHERE name = " . $connection->quote($moduleName)
    )->fetchColumn();
    $assert($rowsAfterDuplicate === $rowsBeforeDuplicate, 'Duplicate installation mutated module rows.');
    $assert(
        count($repository->permissionsFor($moduleName)) === $permissionsBeforeDuplicate,
        'Duplicate installation mutated permission metadata.'
    );

    $manager->enable($moduleName);
    $enabled = $repository->findByName($moduleName);
    $assert(($enabled['status'] ?? null) === 'enabled', 'Eligible installed module was not enabled.');

    $manager->disable($moduleName);
    $disabled = $repository->findByName($moduleName);
    $assert(($disabled['status'] ?? null) === 'disabled', 'Enabled module was not disabled.');

    $manager->uninstall($moduleName);
    $assert($repository->findByName($moduleName) === null, 'Disabled module was not uninstalled.');
    $assert($repository->permissionsFor($moduleName) === [], 'Permission metadata remained after uninstall.');

    echo "M3.3 Batch 1 Module Manager baseline passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    $removeDirectory($temporaryDirectory);
}
