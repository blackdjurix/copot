<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$render = static function (array $data) use ($basePath): string {
    extract($data, EXTR_SKIP);
    ob_start();
    try { require $basePath . '/resources/views/admin/system-manager.php'; return (string) ob_get_clean(); }
    catch (Throwable $exception) { ob_end_clean(); throw $exception; }
};

$module = [
    'name' => 'content', 'title' => 'Content Module', 'version' => '0.1.0',
    'stored_version' => '0.1.0', 'discovered_version' => '0.1.0',
    'lifecycle_state' => 'installed_enabled', 'discovery_state' => 'valid',
    'description' => 'Content administration.', 'author' => 'COPOT',
    'dependencies' => [], 'permissions' => [], 'permission_metadata' => [],
    'diagnostics' => [], 'available_actions' => ['disable' => ['visible' => true, 'enabled' => true]],
    'denial_reasons' => [], 'lifecycle_action' => 'REPAIR',
    'available_package_version' => '0.1.0', 'available_package_release' => 'content-release-1',
    'lifecycle_evidence' => [
        'release_identity' => 'content-release-1', 'package_integrity_identity' => str_repeat('a', 64),
        'last_committed_lifecycle_target' => 'canonical-current', 'committed_at' => '2026-08-25T00:00:00+00:00',
    ],
];
$page = $render([
    'section' => 'modules', 'modules' => [$module], 'moduleDetail' => $module,
    'status' => [], 'branding' => [], 'localization' => [], 'health' => [], 'release' => [],
    'systemManagerPath' => '/admin/settings/system-manager', 'moduleDetailPath' => '/admin/settings/system-manager/modules',
    'moduleActionPath' => '/admin/settings/system-manager/modules/action', 'csrfToken' => 'token',
]);
$assert(str_contains($page, 'Content Module'), 'Module detail title is missing.');
$assert(str_contains($page, 'content-release-1'), 'Existing release evidence is missing.');
$assert(str_contains($page, 'Package integrity identity'), 'Existing integrity evidence is missing.');
$assert(str_contains($page, 'Disable'), 'Detail action eligibility is missing.');
$assert(str_contains($page, 'Durable Module operation history'), 'Unsupported evidence boundary is explicit.');

$inventoryPage = $render([
    'section' => 'modules', 'modules' => [$module], 'moduleDetail' => null,
    'status' => [], 'branding' => [], 'localization' => [], 'health' => [], 'release' => [],
    'systemManagerPath' => '/admin/settings/system-manager', 'moduleDetailPath' => '/admin/settings/system-manager/modules',
    'moduleActionPath' => '/admin/settings/system-manager/modules/action', 'csrfToken' => 'token',
]);
$assert(str_contains($inventoryPage, 'Search Modules'), 'Module search control is missing.');
$assert(str_contains($inventoryPage, 'data-module-search="content module content"'), 'Search index does not include title and technical identity.');
$assert(str_contains($inventoryPage, 'View details'), 'Inventory detail affordance is missing.');
$assert(str_contains($inventoryPage, 'No matching Modules'), 'No-match state is missing.');

$fallback = file_get_contents($basePath . '/app/Core/SystemManagerModuleFallback.php');
$operator = file_get_contents($basePath . '/app/Core/ModulePackageOperator.php');
$javascript = file_get_contents($basePath . '/public/admin-assets/js/system-manager.js');
$assert(is_string($fallback) && str_contains($fallback, 'public function detail'), 'Detail projection is not Core evidence-backed.');
$assert(is_string($operator) && str_contains($operator, 'available_package_dependencies'), 'Package dependency evidence is not reused.');
$assert(is_string($javascript) && str_contains($javascript, 'data-system-manager-module-search'), 'Client-side inventory filtering is missing.');
$assert(is_string($javascript) && str_contains($javascript, 'data-system-manager-module-no-match'), 'Client-side no-match behavior is missing.');

echo "system_manager_wu3_module_detail: {$assertions} assertions passed\n";
