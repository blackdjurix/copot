<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
require_once $basePath . '/bootstrap/autoload.php';
require_once $basePath . '/modules/module-manager/Services/SiteSettingsModuleHealthProducer.php';
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$read = static fn (string $path): string => (string) file_get_contents($basePath . '/' . $path);
$render = static function (array $data) use ($basePath): string {
    extract($data, EXTR_SKIP);
    ob_start();
    try {
        require $basePath . '/resources/views/admin/site-settings-modules.php';
        return (string) ob_get_clean();
    } catch (Throwable $exception) {
        ob_end_clean();
        throw $exception;
    }
};

$view = $read('resources/views/admin/site-settings-modules.php');
$script = $read('public/admin-assets/js/site-settings-modules.js');
$routes = $read('routes/site_settings.php');
$adapter = $read('modules/module-manager/Services/SiteSettingsModulesAdmin.php');
$authority = $read('modules/module-manager/Services/ModuleManagerAdmin.php');
$healthProducer = $read('modules/module-manager/Services/SiteSettingsModuleHealthProducer.php');
$bootstrap = $read('bootstrap/app.php');
$css = $read('public/admin-assets/css/admin.css');
$settingsView = $read('resources/views/admin/site-settings.php');

$assert(str_contains($view, 'site-settings-modules-table'), 'Canonical Modules inventory view is missing.');
$assert(str_contains($view, '<th scope="col">Module</th><th scope="col">Version</th><th scope="col">Issue</th><th scope="col">Status</th>'), 'Inventory columns are not exactly Module, Version, Issue, Status.');
$tableHead = preg_match('/<thead>.*?<\/thead>/s', $view, $headMatch) === 1 ? strip_tags($headMatch[0]) : '';
$assert(!str_contains($tableHead, 'Actions') && !str_contains($tableHead, 'Discovery') && !str_contains($tableHead, 'Notes'), 'Inventory contains a forbidden primary column.');
$assert(str_contains($view, 'Scalable client-side search') === false, 'Implementation detail leaked into the rendered inventory view.');
$assert(str_contains($view, 'data-site-settings-module-search'), 'Accepted Module search control is missing.');
$assert(str_contains($script, 'toLowerCase()') && str_contains($script, 'row.dataset.searchIndex'), 'Case-insensitive title/identity filtering is missing.');
$assert(str_contains($script, 'data-site-settings-module-row') && str_contains($script, 'event.key === \'Enter\''), 'Whole-row keyboard/open behavior is missing.');
$assert(str_contains($routes, "'modules.manage'") && str_contains($routes, '$requireSettingsUser'), 'Modules and ordinary settings permission composition is missing.');
$assert(str_contains($routes, '$modulesProjection->detail') && str_contains($routes, 'modules/{name}'), 'Canonical subordinate Module Detail route is missing.');
$assert(str_contains($routes, "'/modules/' . " . '$moduleAction') && !str_contains($routes, '$systemPath . ' . "'/modules"), 'Module actions are not subordinate to Site Settings Modules.');
$assert(str_contains($adapter, 'new ModulePackageOperator($this->app)') && str_contains($adapter, '$this->app->modules()->'), 'Existing package and lifecycle authorities are not reused.');
$assert(str_contains($adapter, 'validateOrReject') && str_contains($adapter, "available_actions'][\$action]") && str_contains($adapter, "denial_reasons'][\$action]"), 'Module action CSRF and authority-derived eligibility boundaries are missing.');
$assert(str_contains($adapter, "settingsPath() . '/modules/'") && !str_contains($adapter, "settingsPath() . '/system/modules'"), 'Module transport is not subordinate to the canonical Modules composition.');
$assert(str_contains($healthProducer, 'implements SystemHealthProducer') && str_contains($healthProducer, 'SystemHealthProducerResult'), 'Module diagnostics are not adapted to the existing System Health producer path.');
$assert(str_contains($healthProducer, 'SystemHealthFindingSeverity::CRITICAL') && str_contains($healthProducer, 'dedupeKey'), 'Module Health does not preserve severity or bound duplicate findings.');
$assert(str_contains($healthProducer, "can('modules.manage')") && !str_contains($healthProducer, 'file_get_contents'), 'Module Health visibility/state handling is outside the Module permission boundary.');
$assert(str_contains($bootstrap, 'SystemHealthAggregator') && str_contains($bootstrap, 'new SiteSettingsModuleHealthProducer($app)'), 'Module Health producer is not connected to the existing application report resolver.');
$assert(str_contains($settingsView, "array_key_exists('system', \$areas)") && str_contains($routes, "system.webcore.manage"), 'System capability remains independently composed from Modules.');

$producer = new SiteSettingsModuleHealthProducer(new stdClass(), static fn (): array => [
    ['name' => 'alpha', 'diagnostics' => [
        ['code' => 'dependency_missing', 'severity' => 'error'],
        ['code' => 'dependency_missing', 'severity' => 'error'],
        ['code' => 'metadata_drift', 'severity' => 'warning'],
    ]],
]);
$producerResult = $producer->report(new \Copot\Core\SystemHealthContext(
    new \Copot\Core\InstallationIdentity('inst_' . str_repeat('a', 32)),
    new stdClass()
));
$assert($producerResult->source() === 'webcore.modules' && count($producerResult->findings()) === 2, 'Module Health did not deduplicate and preserve authoritative diagnostics.');
$assert($producerResult->findings()[0]->severity() === 'error' && $producerResult->findings()[0]->summary() === 'A Module dependency condition requires review.', 'Module Health severity or sanitized category presentation changed.');
$assert(!str_contains(json_encode($producerResult->toArray()), 'dependency_missing'), 'Module Health exposed an internal diagnostic code.');
$assert(str_contains($authority, 'public function projectionInventory') && str_contains($authority, 'public function projectionDetail'), 'Shared normalized Module projection access is missing.');
$assert(str_contains($settingsView, '$canUpdateSettings') && str_contains($settingsView, '$canManageModules') && str_contains($settingsView, 'moduleItems'), 'Site Settings read-versus-action composition is missing.');
$assert(str_contains($css, '.site-settings-modules-table th:nth-child(1)') && str_contains($css, 'table-layout: fixed'), 'Unequal available-width inventory layout is missing.');
$assert(str_contains($css, '.site-settings-modules-table thead') && str_contains($css, 'grid-template-columns: minmax(5.5rem, .35fr)'), 'Responsive stacked Module presentation is missing.');

$html = $render([
    'items' => [
        [
            'name' => 'alpha',
            'title' => '<Alpha>',
            'version' => '1.2.0',
            'lifecycle_state' => 'installed_enabled',
            'diagnostics' => [
                ['code' => 'dependency_missing', 'severity' => 'error'],
                ['code' => 'metadata_drift', 'severity' => 'warning'],
            ],
            'available_package_version' => '1.3.0',
        ],
        [
            'name' => 'plain',
            'title' => 'Plain Module',
            'version' => '1.0.0',
            'lifecycle_state' => 'installed_disabled',
            'diagnostics' => [],
        ],
    ],
    'csrfToken' => 'token',
    'detailPath' => static fn (string $name): string => '/admin/settings/modules/' . rawurlencode($name),
    'packagePath' => '/admin/settings/modules/package',
    'lifecyclePath' => '/admin/settings/modules/package/lifecycle',
    'moduleError' => null,
    'moduleNotice' => null,
    'url' => static fn (string $path): string => $path,
]);
$assert(substr_count($html, '<tr class="site-settings-module-row"') === 2, 'Rendered inventory row count is incorrect.');
$assert(str_contains($html, 'Dependency error') && str_contains($html, '+1 more'), 'Issue category/severity-bounded additional count is missing.');
$assert(str_contains($html, '>—</span>'), 'Neutral no-issue representation is missing.');
$assert(str_contains($html, 'Enabled') && str_contains($html, 'Disabled'), 'Lifecycle Status presentation is missing.');
$assert(str_contains($html, '&lt;Alpha&gt;') && str_contains($html, 'alpha'), 'Module title or technical identity was not safely rendered.');
$assert(!str_contains($html, 'Actions</th>') && !str_contains($html, 'Discovery</th>') && !str_contains($html, 'Notes</th>'), 'Forbidden inventory columns rendered.');
$assert(!str_contains($html, 'dependency_missing') && !str_contains($html, 'metadata_drift'), 'Raw diagnostic codes leaked into inventory.');
$assert(!str_contains($html, '<form method="post" action="/admin/settings/modules/disable"'), 'Lifecycle actions were placed in the inventory.');
$assert(str_contains($html, 'data-site-settings-module-row') && str_contains($html, 'tabindex="0"'), 'Whole-row open target is not keyboard reachable.');

echo "WU4 Batch 2 Site Settings Modules tests passed ({$assertions} assertions)." . PHP_EOL;
