<?php

declare(strict_types=1);

use Copot\Core\ModuleIdentity;
use Copot\Core\ModulePackageContract;
use Copot\Core\ModulePackageIntakeInspector;
use Copot\Core\ModulePackageOwnership;
use Copot\Core\ModulePackageTarget;
use Copot\Core\ModulePackageDependencyDeclaration;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageIdentity;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\ModuleMigrationDeclaration;
use Copot\Core\ModuleProvisioningDeclaration;
use Copot\Core\ZipIntakeService;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$throws = static function (callable $callback, string $message) use (&$assertions): void {
    $assertions++;
    try { $callback(); } catch (Throwable) { return; }
    throw new RuntimeException($message . ' did not throw.');
};
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-module-wu2-' . bin2hex(random_bytes(6));
$live = $root . DIRECTORY_SEPARATOR . 'live';
$stage = $root . DIRECTORY_SEPARATOR . 'stage';
mkdir($live . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'existing', 0700, true);
file_put_contents($live . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'existing' . DIRECTORY_SEPARATOR . 'module.json', '{}');

$remove = static function (string $path) use (&$remove): void {
    if (is_file($path) || is_link($path)) { @unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry);
    @rmdir($path);
};
$archive = static function (array $files, array $manifest) use ($root): string {
    $path = $root . DIRECTORY_SEPARATOR . 'package-' . bin2hex(random_bytes(4)) . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Unable to create fixture archive.');
    foreach ($files as $name => $contents) $zip->addFromString($name, $contents);
    $zip->addFromString('.copot/package.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    $zip->close();
    return $path;
};
$makeManifest = static function (array $files, string $type = 'copot-module'): array {
    $module = new ModuleIdentity('content');
    $contract = new ModulePackageContract(
        ModulePackageContract::MODULE_PACKAGE_TYPE,
        ModulePackageContract::CURRENT_CONTRACT_VERSION,
        new PackageIdentity('copot-content-package'),
        $module,
        'Content Module Package',
        '1.0.0',
        'copot-content-release-1',
        new PackageCompatibility('0.12.0', '1.0.0'),
        new PackageRuntimeCompatibility('8.2.0', ['mysql' => '8.0.0'], ['json']),
        new ModulePackageOwnership($module, 'modules/content'),
        [new ModulePackageDependencyDeclaration(new ModulePackageTarget(ModulePackageTarget::MODULE, 'taxonomy'), new PackageCompatibility('1.0.0'))],
        [], new ModuleMigrationDeclaration($module), new ModuleProvisioningDeclaration()
    );
    $data = $contract->toArray();
    $data['inventory'] = [];
    foreach ($files as $path => $contents) $data['inventory'][] = ['path' => $path, 'byte_size' => strlen($contents), 'sha256' => hash('sha256', $contents), 'ownership' => 'package_owned'];
    $data['package_type'] = $type;
    return $data;
};
$moduleJson = json_encode(['name' => 'content', 'title' => 'Content', 'version' => '1.0.0'], JSON_THROW_ON_ERROR);
$files = ['modules/content/module.json' => $moduleJson, 'modules/content/routes.php' => '<?php return [];'];
$inspector = new ModulePackageIntakeInspector(new ZipIntakeService($live, $stage));
$valid = $archive($files, $makeManifest($files));
$inspection = $inspector->inspect($valid);
$assert($inspection->accepted(), 'Valid Module package was not accepted.');
$assert($inspection->contract()->packageType() === 'copot-module', 'Module package type was not inspected.');
$assert(count($inspection->livePayload()->files()) === 2, 'Package metadata was not excluded from live inventory.');
$assert($inspection->runtimeManifest()['name'] === 'content', 'Runtime module manifest was not validated.');
$assert(is_file($live . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'existing' . DIRECTORY_SEPARATOR . 'module.json'), 'Live Module fixture was modified.');
$inspection->livePayload()->cleanup();

$wrongType = $archive($files, $makeManifest($files, 'copot-webcore'));
$throws(static fn () => $inspector->inspect($wrongType), 'Wrong package type');
$outside = ['modules/content/module.json' => $moduleJson, 'modules/other/file.php' => 'x'];
$outsideManifest = $makeManifest($outside);
$throws(static fn () => $inspector->inspect($archive($outside, $outsideManifest)), 'Sibling Module ownership');
$badInventory = $makeManifest($files);
$badInventory['inventory'][1]['sha256'] = str_repeat('0', 64);
$throws(static fn () => $inspector->inspect($archive($files, $badInventory)), 'Inventory mismatch');
$badRuntime = ['modules/content/module.json' => json_encode(['name' => 'other', 'title' => 'Content', 'version' => '1.0.0'], JSON_THROW_ON_ERROR)];
$badRuntimeManifest = $makeManifest($badRuntime);
$throws(static fn () => $inspector->inspect($archive($badRuntime, $badRuntimeManifest)), 'Runtime identity mismatch');

$remove($root);
echo 'Module Package Lifecycle WU2 focused tests passed (' . $assertions . " assertions).\n";
