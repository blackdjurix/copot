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
$layout = $read('resources/views/admin/layout.php');

$assert(str_contains($view, 'site-settings-modules-table'), 'Canonical Modules inventory view is missing.');
$assert(str_contains($view, '<th scope="col">Module</th><th scope="col">Version</th><th scope="col">Issue</th><th scope="col">Status</th>'), 'Inventory columns are not exactly Module, Version, Issue, Status.');
$tableHead = preg_match('/<thead>.*?<\/thead>/s', $view, $headMatch) === 1 ? strip_tags($headMatch[0]) : '';
$assert(!str_contains($tableHead, 'Actions') && !str_contains($tableHead, 'Discovery') && !str_contains($tableHead, 'Notes'), 'Inventory contains a forbidden primary column.');
$assert(str_contains($view, 'Scalable client-side search') === false, 'Implementation detail leaked into the rendered inventory view.');
$assert(substr_count($view, 'data-site-settings-module-filter=') === 4 && str_contains($view, 'data-site-settings-module-filter="name"') && str_contains($view, 'data-site-settings-module-filter="version"') && str_contains($view, 'data-site-settings-module-filter="issue"') && str_contains($view, 'data-site-settings-module-filter="status"'), 'The coordinated Name, Version, Issue, and Status filters are incomplete.');
$assert(!str_contains($view, 'data-site-settings-module-search') && str_contains($script, 'toLowerCase()') && str_contains($script, 'row.dataset.filterName'), 'Generic search was not replaced by case-insensitive title/identity filtering.');
$assert(str_contains($script, 'Object.entries(values).every') && str_contains($script, 'filter();') && !str_contains($script, 'site-settings-module-no-match'), 'Combined filter semantics or filtered-empty composition is incorrect.');
$assert(str_contains($script, "matching Module\${visible === 1 ? '' : 's'}") && str_contains($view, 'site-settings-modules__result-count') && !str_contains($view, 'No matching Modules'), 'Module result count does not use the accepted below-filter placement and wording.');
$assert(str_contains($script, 'data-site-settings-module-row') && str_contains($script, 'event.key === \'Enter\''), 'Whole-row keyboard/open behavior is missing.');
$assert(str_contains($routes, "'modules.manage'") && str_contains($routes, '$requireSettingsUser'), 'Modules and ordinary settings permission composition is missing.');
$assert(str_contains($routes, "adminNavigation()->add('Site Settings', \$path, [\$permission, 'modules.manage', 'system.webcore.manage']"), 'Site Settings navigation does not expose the parent for any implemented capability.' );
$navigationPermissions = new class extends \Copot\Core\PermissionChecker {
    public function __construct() {}
    public function userHasRole(int $userId, string $role): bool { return false; }
    public function userCan(int $userId, string $permission): bool { return $permission === 'admin.access' || $permission === 'modules.manage'; }
};
$navigationUser = new \Copot\Core\User(['id' => 1, 'name' => 'Modules operator', 'email' => 'modules@example.test', 'password_hash' => 'unused', 'status' => 'active'], $navigationPermissions);
$navigation = new \Copot\Core\AdminNavigation();
$navigation->add('Site Settings', '/admin/settings', ['settings.update', 'modules.manage', 'system.webcore.manage'], 'settings', 70);
$assert(array_column($navigation->itemsFor($navigationUser), 'label') === ['Site Settings'], 'AdminNavigation any-permission behavior did not expose Site Settings to a Modules operator.');
$assert(str_contains($routes, '$modulesProjection->detail') && str_contains($routes, 'modules/{name}'), 'Canonical subordinate Module Detail route is missing.');
$assert(str_contains($routes, "'/modules/' . " . '$moduleAction') && !str_contains($routes, '$systemPath . ' . "'/modules"), 'Module actions are not subordinate to Site Settings Modules.');
$assert(str_contains($adapter, 'new ModulePackageOperator($this->app)') && str_contains($adapter, '$this->app->modules()->'), 'Existing package and lifecycle authorities are not reused.');
$assert(str_contains($adapter, 'validateOrReject') && str_contains($adapter, "available_actions'][\$action]") && str_contains($adapter, "denial_reasons'][\$action]"), 'Module action CSRF and authority-derived eligibility boundaries are missing.');
$assert(str_contains($adapter, "settingsPath() . '/modules/'") && !str_contains($adapter, "settingsPath() . '/system/modules'"), 'Module transport is not subordinate to the canonical Modules composition.');
$assert(str_contains($healthProducer, 'implements SystemHealthProducer') && str_contains($healthProducer, 'SystemHealthProducerResult'), 'Module diagnostics are not adapted to the existing System Health producer path.');
$assert(str_contains($healthProducer, 'return true;') && substr_count($healthProducer, 'SystemHealthProducerAvailability::UNAVAILABLE,') === 1, 'Module Health evidence is incorrectly optional or does not preserve unavailable evidence.' );
$assert(str_contains($healthProducer, 'SystemHealthFindingSeverity::CRITICAL') && str_contains($healthProducer, 'dedupeKey'), 'Module Health does not preserve severity or bound duplicate findings.');
$assert(str_contains($healthProducer, "can('modules.manage')") && !str_contains($healthProducer, 'file_get_contents'), 'Module Health visibility/state handling is outside the Module permission boundary.');
$assert(str_contains($bootstrap, 'SystemHealthAggregator') && str_contains($bootstrap, 'new SiteSettingsModuleHealthProducer($app)') && str_contains($bootstrap, "can('modules.manage')") && str_contains($bootstrap, 'findings() === []') && str_contains($bootstrap, '?SystemHealthReport'), 'Module Health production composition does not gate visibility or preserve unavailable/empty evidence safely.');
$assert(str_contains($settingsView, "array_key_exists('system', \$areas)") && str_contains($routes, "system.webcore.manage"), 'System capability remains independently composed from Modules.');
$headerOrder = [strpos($view, '<h3>Modules</h3>'), strpos($view, 'Review discovered Modules and open a Module for lifecycle actions and operational evidence.'), strpos($view, 'Add Module package (ZIP)'), strpos($view, 'site-settings-module-package-controls')];
$assert($headerOrder[0] !== false && $headerOrder[0] < $headerOrder[1] && $headerOrder[1] < $headerOrder[2] && $headerOrder[2] < $headerOrder[3], 'Modules header and package intake hierarchy is not vertical.');
$assert(str_contains($view, 'site-settings-module-package-controls') && str_contains($css, '.site-settings-module-package-controls input[type="file"]') && str_contains($css, '.site-settings-module-package-controls .admin-button'), 'Package chooser and Add Module action do not share the bounded desktop control row.');

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
$assert($producer->required() === true, 'Module Health producer must treat its authorized evidence as required for aggregation.');
$assert($producerResult->source() === 'webcore.modules' && count($producerResult->findings()) === 2, 'Module Health did not deduplicate and preserve authoritative diagnostics.');
$assert($producerResult->findings()[0]->severity() === 'error' && $producerResult->findings()[0]->summary() === 'A Module dependency condition requires review.', 'Module Health severity or sanitized category presentation changed.');
$assert(!str_contains(json_encode($producerResult->toArray()), 'dependency_missing'), 'Module Health exposed an internal diagnostic code.');
$unavailableProducer = new SiteSettingsModuleHealthProducer(new stdClass(), static function (): array { throw new RuntimeException('diagnostic evidence unavailable'); });
$unavailableResult = $unavailableProducer->report(new \Copot\Core\SystemHealthContext(
    new \Copot\Core\InstallationIdentity('inst_' . str_repeat('b', 32)),
    new stdClass()
));
$assert($unavailableResult->availability() === \Copot\Core\SystemHealthProducerAvailability::UNAVAILABLE && $unavailableResult->required() === true, 'Unavailable Module evidence was not preserved as required evidence.');
$moduleViewer = new class extends \Copot\Core\User {
    public function __construct() {}
    public function can(string $permission): bool { return $permission === 'modules.manage'; }
};
$ordinaryAdminViewer = new class extends \Copot\Core\User {
    public function __construct() {}
    public function can(string $permission): bool { return $permission === 'admin.access'; }
};
$healthResolver = static function (\Copot\Core\SystemHealthContext $context) use ($unavailableProducer): ?\Copot\Core\SystemHealthReport {
    if (!$context->viewer() instanceof \Copot\Core\User || !$context->viewer()->can('modules.manage')) return null;
    return (new \Copot\Core\SystemHealthAggregator())->aggregate($context, [$unavailableProducer]);
};
$healthProvider = new \Copot\Core\SystemHealthReportProvider($healthResolver);
$hiddenHealth = $healthProvider->report(new \Copot\Core\SystemHealthContext(new \Copot\Core\InstallationIdentity('inst_' . str_repeat('c', 32)), $ordinaryAdminViewer));
$assert($hiddenHealth === null, 'A viewer without Modules authority received an authoritative Module-only health report.');
$unavailableHealth = $healthProvider->report(new \Copot\Core\SystemHealthContext(new \Copot\Core\InstallationIdentity('inst_' . str_repeat('d', 32)), $moduleViewer));
$assert($unavailableHealth?->status() === \Copot\Core\SystemHealthOverallStatus::ATTENTION_REQUIRED, 'Unavailable authorized Module evidence was presented as Operational.');
$emptyProducer = new SiteSettingsModuleHealthProducer(new stdClass(), static fn (): array => []);
$emptyHealthProvider = new \Copot\Core\SystemHealthReportProvider(static function (\Copot\Core\SystemHealthContext $context) use ($emptyProducer): ?\Copot\Core\SystemHealthReport {
    if (!$context->viewer() instanceof \Copot\Core\User || !$context->viewer()->can('modules.manage')) return null;
    $result = $emptyProducer->report($context);
    if ($result->findings() === [] && \Copot\Core\SystemHealthProducerAvailability::isEvidenceSufficient($result->availability())) return null;
    return (new \Copot\Core\SystemHealthAggregator())->aggregate($context, [$emptyProducer]);
});
$emptyHealth = $emptyHealthProvider->report(new \Copot\Core\SystemHealthContext(new \Copot\Core\InstallationIdentity('inst_' . str_repeat('e', 32)), $moduleViewer));
$assert($emptyHealth === null, 'An authorized zero-finding Module-only producer manufactured an Operational report.');
$findingProducer = new SiteSettingsModuleHealthProducer(new stdClass(), static fn (): array => [['name' => 'alpha', 'diagnostics' => [['code' => 'dependency_missing', 'severity' => 'error']]]]);
$findingHealthProvider = new \Copot\Core\SystemHealthReportProvider(static function (\Copot\Core\SystemHealthContext $context) use ($findingProducer): ?\Copot\Core\SystemHealthReport {
    if (!$context->viewer() instanceof \Copot\Core\User || !$context->viewer()->can('modules.manage')) return null;
    $result = $findingProducer->report($context);
    if ($result->findings() === [] && \Copot\Core\SystemHealthProducerAvailability::isEvidenceSufficient($result->availability())) return null;
    return (new \Copot\Core\SystemHealthAggregator())->aggregate($context, [$findingProducer]);
});
$findingHealth = $findingHealthProvider->report(new \Copot\Core\SystemHealthContext(new \Copot\Core\InstallationIdentity('inst_' . str_repeat('f', 32)), $moduleViewer));
$assert($findingHealth?->status() === \Copot\Core\SystemHealthOverallStatus::DEGRADED && count($findingHealth?->findings() ?? []) === 1, 'Meaningful Module findings did not reach the existing System Health aggregator.');
$assert(str_contains($authority, 'public function projectionInventory') && str_contains($authority, 'public function projectionDetail'), 'Shared normalized Module projection access is missing.');
$assert(str_contains($settingsView, '$canUpdateSettings') && str_contains($settingsView, '$canManageModules') && str_contains($settingsView, 'moduleItems'), 'Site Settings read-versus-action composition is missing.');
$assert(str_contains($css, '.site-settings-modules-table th:nth-child(1)') && str_contains($css, 'table-layout: fixed'), 'Unequal available-width inventory layout is missing.');
$assert(str_contains($css, '.site-settings-modules-table thead') && str_contains($css, 'grid-template-columns: minmax(5.5rem, .35fr)'), 'Responsive stacked Module presentation is missing.');
$assert(str_contains($view, 'site-settings-module-identity') && str_contains($css, '.site-settings-module-identity { min-width: 0; overflow-wrap: anywhere; }'), 'Module title and technical identity are not grouped into one mobile value area.');
$assert(str_contains($css, '.site-settings-modules-table th:first-child { margin: calc(-1 * var(--admin-space-4)) calc(-1 * var(--admin-space-4)) 0; padding: var(--admin-space-4) var(--admin-space-4) var(--admin-space-2); }'), 'Mobile Module identity section does not extend across the full card width.');
$assert(str_contains($layout, 'admin.css?v=m311-wu3-acceptance-modules-3'), 'Modules presentation stylesheet cache-bust is missing.');
$assert(str_contains($view, 'site-settings-modules.js?v=wu4-modules-4'), 'Modules filter count script cache-bust is missing.');

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
$assert(str_contains($html, 'data-filter-name="&lt;alpha&gt; alpha"') && str_contains($html, 'data-filter-version="1.2.0"') && str_contains($html, 'data-filter-issue="dependency error"') && str_contains($html, 'data-filter-status="enabled"'), 'Rendered authoritative filter values are missing from Module rows.');
$emptyHtml = $render([
    'items' => [],
    'csrfToken' => 'token',
    'detailPath' => static fn (string $name): string => '/admin/settings/modules/' . rawurlencode($name),
    'packagePath' => '/admin/settings/modules/package',
    'lifecyclePath' => '/admin/settings/modules/package/lifecycle',
    'moduleError' => null,
    'moduleNotice' => null,
    'url' => static fn (string $path): string => $path,
]);
$assert(str_contains($emptyHtml, 'No Modules found') && !str_contains($emptyHtml, 'No matching Modules'), 'True-empty inventory state is not distinct from the filtered no-match state.');

echo "WU4 Batch 2 Site Settings Modules tests passed ({$assertions} assertions)." . PHP_EOL;
