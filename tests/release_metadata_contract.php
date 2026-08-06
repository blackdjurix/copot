<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
require $basePath . '/bootstrap/autoload.php';

use Copot\Core\ModuleDiscovery;
use Copot\Core\ReleaseMetadataValidator;
use Copot\Core\Version;

$assertions = 0;
$failures = [];
$assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$condition) {
        $failures[] = $message;
    }
};

$decode = static function (string $path): array {
    $data = json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new RuntimeException('Release metadata must be an object: ' . $path);
    }
    ReleaseMetadataValidator::validate($data);
    return $data;
};

$webcore = $decode($basePath . '/release.json');
$assert(Version::CURRENT === '0.13.0', 'Webcore source version was not reconciled to 0.13.0.');
$assert($webcore['transition'] === 'UPDATE', 'Webcore release transition is incorrect.');
$assert($webcore['whats_new'] === [
    'Package Lifecycle & Migration Foundation',
    'Module Package Lifecycle Foundation',
    'Backup & Recovery Foundation',
], 'Webcore release contents are incorrect.');

$modules = [
    'users-access', 'settings-manager', 'module-manager', 'content', 'taxonomy',
    'navigation', 'theme-manager', 'media', 'redirects', 'form-manager',
];

$discovery = new ModuleDiscovery($basePath . '/modules');
$definitions = [];
foreach ($discovery->discover() as $definition) {
    $definitions[$definition->name()] = $definition;
}

foreach ($modules as $module) {
    $assert(isset($definitions[$module]), 'Product module was not discovered: ' . $module);
    $assert(($definitions[$module]?->version()) === '0.1.0', 'Product module version changed: ' . $module);
    $release = $decode($basePath . '/modules/' . $module . '/release.json');
    $assert($release['transition'] === 'INSTALL', 'Module release transition is incorrect: ' . $module);
    $assert(count($release['whats_new']) > 0, "Module release What's New is empty: {$module}");
}

$assert(!is_file($basePath . '/modules/example/release.json'), 'Sample module received product release metadata.');

$invalid = [
    ['transition' => 'REPAIR', 'whats_new' => ['invalid']],
    ['transition' => 'INSTALL', 'whats_new' => []],
    ['transition' => 'INSTALL', 'whats_new' => ['']],
    ['transition' => 'INSTALL', 'whats_new' => [1]],
];
foreach ($invalid as $metadata) {
    try {
        ReleaseMetadataValidator::validate($metadata);
        $assert(false, 'Malformed release metadata was accepted.');
    } catch (InvalidArgumentException) {
        $assert(true, 'Malformed release metadata was rejected.');
    }
}

$manifest = require $basePath . '/build/package_manifest.php';
$assert(in_array('release.json', $manifest['include'] ?? [], true), 'Webcore release metadata is not package-owned.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'Release metadata contract: ' . $assertions . ' assertions passed.' . PHP_EOL;
