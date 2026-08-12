<?php

declare(strict_types=1);

use Copot\Core\InstallerModuleSelection;
use Copot\Core\InstallerValidationException;
use Copot\Core\ModuleDiscovery;

$base = dirname(__DIR__);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$actual = new InstallerModuleSelection(new ModuleDiscovery($base . '/modules'));
$catalog = $actual->catalog();
$mandatoryNames = array_column($catalog['mandatory'], 'name');
$assert(in_array('content', $mandatoryNames, true), 'Baseline Content Module is not mandatory.');
$assert(!in_array('example', array_column($catalog['optional'], 'name'), true), 'Sample Example Module was exposed as an installer option.');
$assert(count(array_intersect(InstallerModuleSelection::MANDATORY_MODULES, $mandatoryNames)) === count(InstallerModuleSelection::MANDATORY_MODULES), 'Mandatory platform Module classification is incomplete.');

$fixture = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu4-' . bin2hex(random_bytes(4));
mkdir($fixture . DIRECTORY_SEPARATOR . 'alpha', 0700, true);
mkdir($fixture . DIRECTORY_SEPARATOR . 'beta', 0700, true);
file_put_contents($fixture . DIRECTORY_SEPARATOR . 'alpha' . DIRECTORY_SEPARATOR . 'module.json', json_encode([
    'name' => 'alpha', 'title' => 'Alpha', 'version' => '1.0.0', 'requires' => ['modules' => []],
], JSON_THROW_ON_ERROR));
file_put_contents($fixture . DIRECTORY_SEPARATOR . 'beta' . DIRECTORY_SEPARATOR . 'module.json', json_encode([
    'name' => 'beta', 'title' => 'Beta', 'version' => '1.0.0', 'requires' => ['modules' => ['alpha']],
], JSON_THROW_ON_ERROR));

try {
    $selection = new InstallerModuleSelection(new ModuleDiscovery($fixture));
    $normalized = $selection->normalize([
        'install' => ['beta' => '1'],
        'active' => ['beta' => '1'],
    ]);
    $assert($normalized['install'] === ['beta'] && $normalized['active'] === ['beta'], 'Staged Install and Active choices were not normalized.');

    try {
        $selection->validate($normalized);
        throw new RuntimeException('Dependency validation unexpectedly accepted a missing prerequisite.');
    } catch (InstallerValidationException $exception) {
        $assert(str_contains((string) ($exception->errors()['modules'] ?? ''), 'alpha'), 'Dependency failure did not identify the required Module.');
    }

    $valid = $selection->validate($selection->normalize([
        'install' => ['alpha' => '1', 'beta' => '1'],
        'active' => ['alpha' => '1', 'beta' => '1'],
    ]));
    $assert($valid['install'] === ['alpha', 'beta'] && $valid['active'] === ['alpha', 'beta'], 'Satisfied Module dependencies did not validate.');

    $cleared = $selection->normalize(['install' => [], 'active' => ['beta' => '1']]);
    $assert($cleared['install'] === [] && $cleared['active'] === [], 'Clearing Install did not clear Active.');
} finally {
    foreach ([$fixture . DIRECTORY_SEPARATOR . 'alpha' . DIRECTORY_SEPARATOR . 'module.json', $fixture . DIRECTORY_SEPARATOR . 'beta' . DIRECTORY_SEPARATOR . 'module.json'] as $file) {
        @unlink($file);
    }
    @rmdir($fixture . DIRECTORY_SEPARATOR . 'alpha');
    @rmdir($fixture . DIRECTORY_SEPARATOR . 'beta');
    @rmdir($fixture);
}

$bootstrap = (string) file_get_contents($base . '/bootstrap/installer.php');
$view = (string) file_get_contents($base . '/resources/views/installer/index.php');
$css = (string) file_get_contents($base . '/public/installer-assets/css/installer.css');
$assert(str_contains($bootstrap, "installer_modules_staged") && str_contains($bootstrap, "session->set(\$moduleSelectionSessionKey"), 'Module selections are not session-staged for revisit persistence.');
$assert(str_contains($bootstrap, "'stage_modules'") && str_contains($bootstrap, 'No Modules were installed or activated.'), 'WU4 staging boundary is missing or does not state the no-mutation invariant.');
$assert(str_contains($view, 'module_install[') && str_contains($view, 'module_active['), 'Modules UI does not expose staged Install and Active choices.');
$assert(str_contains($view, 'class="phase-form"') && str_contains($view, 'class="installer-actions installer-navigation"'), 'Modules UI does not consume shared installer form/navigation primitives.');
$assert(str_contains($css, '.module-selection') && str_contains($css, '.module-choice'), 'Module-specific presentation primitives are missing.');
$assert(!str_contains($bootstrap, 'new ModuleManager(\n                    new ModuleDiscovery($basePath . \'/modules\')'), 'WU4 staging path constructs a mutating ModuleManager.');

fwrite(STDOUT, "WU4 optional Module assertions: {$assertions}" . PHP_EOL);
