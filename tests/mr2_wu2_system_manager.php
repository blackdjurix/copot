<?php

declare(strict_types=1);

use Copot\Core\SiteBranding;
use Copot\Core\WebcoreBranding;

$basePath = dirname(__DIR__);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$defaults = WebcoreBranding::defaults();
$resolved = WebcoreBranding::resolvePalette($defaults);
$assert($resolved['foreground-main'] === 'neutral-light', 'Main foreground did not resolve from contrast evidence.');
$assert($resolved['foreground-accent'] === 'neutral-light', 'Accent foreground did not resolve from contrast evidence.');
$assert(WebcoreBranding::contrastRatio($defaults['main'], $resolved[$resolved['foreground-main']]) >= 4.5, 'Main contrast is below the required threshold.');
$assert(WebcoreBranding::contrastRatio($defaults['accent'], $resolved[$resolved['foreground-accent']]) >= 4.5, 'Accent contrast is below the required threshold.');

try {
    WebcoreBranding::validatePalette([
        'main' => '#000000', 'accent' => '#010101', 'neutral-dark' => '#000000', 'neutral-light' => '#010101',
    ]);
    throw new RuntimeException('Unusable palette was accepted.');
} catch (\Copot\Core\SettingsException) {
    $assert(true, 'Unusable palette rejection passed.');
}

$branding = new SiteBranding(
    'Example Site', 'Tagline', null, null,
    $resolved, 'text', 'neutral-light'
);
$assert($branding->identityMode() === 'text', 'Admin identity mode was not retained.');
$assert($branding->identityColorValue() === $defaults['neutral-light'], 'Admin identity color did not resolve from the palette.');
$assert(str_contains($branding->cssVariables(), '--site-color-main:' . $defaults['main']), 'Public branding variables omitted Main.');
$assert(str_contains($branding->cssVariables(), '--site-color-neutral-light:' . $defaults['neutral-light']), 'Public branding variables omitted Neutral Light.');

$render = static function (string $view, array $data) use ($basePath): string {
    $view = $basePath . '/' . $view;
    extract($data, EXTR_SKIP);
    ob_start();
    try {
        require $view;
        return (string) ob_get_clean();
    } catch (Throwable $exception) {
        ob_end_clean();
        throw $exception;
    }
};

$page = $render('resources/views/admin/system-manager.php', [
    'section' => 'health',
    'status' => ['installed_state' => 'committed', 'installed_version' => '0.13.0'],
    'branding' => ['palette' => $resolved, 'identity_mode' => 'text', 'identity_color' => 'neutral-light'],
    'localization' => ['locale' => 'en_US', 'timezone' => 'UTC', 'date_format' => 'Y-m-d', 'time_format' => 'H:i'],
    'health' => ['status' => 'operational', 'status_label' => 'Operational', 'message' => 'No material health findings were reported.', 'findings' => [], 'producers' => [['source' => 'webcore.lifecycle', 'availability' => 'ready']]],
    'modules' => [], 'release' => ['whats_new' => ['Validated release metadata']], 'systemManagerPath' => '/dapur/settings/system-manager',
    'preflightPath' => '/dapur/settings/system-manager/preflight', 'applyPath' => '/dapur/settings/system-manager/apply',
    'retryPath' => '/dapur/settings/system-manager/retry', 'reconcilePath' => '/dapur/settings/system-manager/reconcile',
    'brandingPath' => '/dapur/settings/system-manager/branding', 'localizationPath' => '/dapur/settings/system-manager/localization',
    'moduleActionPath' => '/dapur/settings/system-manager/modules/action', 'csrfToken' => 'token',
]);
$assert(str_contains($page, 'System Health'), 'System Health area did not render.');
$assert(str_contains($page, 'Operational'), 'Authorized health status did not render.');
$assert(!str_contains($page, 'SystemHealthAggregator'), 'Health presentation contains engine internals.');

$systemPage = $render('resources/views/admin/system-manager.php', [
    'section' => 'system', 'status' => ['installed_state' => 'committed', 'installed_version' => '0.13.0', 'schema_state_identity' => 'schema-1', 'migration_state_identity' => 'migration-1', 'maintenance' => 'not_required', 'next_action' => 'Update'],
    'branding' => ['palette' => $resolved], 'localization' => [], 'health' => [], 'modules' => [], 'release' => ['whats_new' => ['Release note']],
    'systemManagerPath' => '/dapur/settings/system-manager', 'preflightPath' => '/dapur/settings/system-manager/preflight', 'applyPath' => '/dapur/settings/system-manager/apply',
    'retryPath' => '/dapur/settings/system-manager/retry', 'reconcilePath' => '/dapur/settings/system-manager/reconcile', 'brandingPath' => '/dapur/settings/system-manager/branding',
    'localizationPath' => '/dapur/settings/system-manager/localization', 'moduleActionPath' => '/dapur/settings/system-manager/modules/action', 'csrfToken' => 'token',
]);
$assert(strpos($systemPage, 'system-manager-package-title') < strpos($systemPage, 'system-manager-release-title'), 'System Update bar is not before the information cards.');
$assert(strpos($systemPage, 'system-manager-release-title') < strpos($systemPage, 'system-manager-status-title'), 'System desktop overview order is not What’s New before lifecycle.');
$assert(str_contains($systemPage, 'system-manager-system-overview'), 'System overview composition wrapper is missing.');
$assert(str_contains($systemPage, 'system-manager-update-bar'), 'Compact Update operation bar is missing.');
$assert(!str_contains($systemPage, 'system-manager-update-bar"><header'), 'Update bar retained the large panel header hierarchy.');
$assert(!str_contains($systemPage, 'system-manager-update-bar admin-panel'), 'Update bar is still structurally presented as an Admin panel.');
$assert(!str_contains($systemPage, 'Package ZIP') && str_contains($systemPage, 'Webcore or Module'), 'Update copy retains redundant ZIP terminology.');
$assert(str_contains($systemPage, 'accept=".zip"') && !str_contains($systemPage, 'application/zip'), 'Package picker still declares duplicate ZIP filters.');
$assert(str_contains($systemPage, 'data-system-manager-details'), 'Lifecycle content-fit detail list hook is missing.');

$brandingPage = $render('resources/views/admin/system-manager.php', [
    'section' => 'branding', 'status' => [], 'branding' => ['palette' => $resolved, 'identity_mode' => 'text', 'identity_color' => 'neutral-light'],
    'localization' => ['locale' => 'en_US', 'timezone' => 'UTC', 'date_format' => 'Y-m-d', 'time_format' => 'H:i'], 'health' => [], 'modules' => [], 'release' => [],
    'systemManagerPath' => '/dapur/settings/system-manager', 'preflightPath' => '/dapur/settings/system-manager/preflight', 'applyPath' => '/dapur/settings/system-manager/apply',
    'retryPath' => '/dapur/settings/system-manager/retry', 'reconcilePath' => '/dapur/settings/system-manager/reconcile', 'brandingPath' => '/dapur/settings/system-manager/branding',
    'localizationPath' => '/dapur/settings/system-manager/localization', 'moduleActionPath' => '/dapur/settings/system-manager/modules/action', 'csrfToken' => 'token',
]);
$assert(str_contains($brandingPage, 'system-manager-detail-form'), 'Branding detail form presentation is missing.');
$assert(substr_count($brandingPage, 'data-color-control') === 4, 'Branding does not expose four bounded color controls.');
$assert(!str_contains($brandingPage, 'data-color-format') && !str_contains($brandingPage, 'value="rgb"') && !str_contains($brandingPage, 'value="hsl"'), 'External color representation selectors remain in the normal Branding form.');
$assert(substr_count($brandingPage, 'data-color-canonical') === 4, 'Branding colors do not retain one canonical submitted value each.');
$assert(str_contains($systemPage, 'Save Localization') && str_contains($systemPage, 'data-admin-capability="localization"'), 'Localization was not moved into the System capability surface.');
$assert(!str_contains($brandingPage, 'Localization'), 'Localization remains presented in Branding.');
$assert(substr_count($systemPage, 'data-admin-fit-group') === 2 && substr_count($brandingPage, 'data-admin-fit-group') === 2, 'Localization and Branding do not use two-field-set fit groups.');

$adminCss = (string) file_get_contents($basePath . '/public/admin-assets/css/admin.css');
$assert(str_contains($adminCss, 'grid-template-columns: minmax(10rem, 11rem) minmax(0, 1fr)'), 'Lifecycle fixed label column is missing.');
$assert(str_contains($adminCss, ".system-manager-status-grid {\n    display: grid;\n    grid-template-columns: 1fr;"), 'Lifecycle detail list is not explicitly single-column.');
$assert(str_contains($adminCss, '.system-manager-status-grid > div {') && str_contains($adminCss, 'grid-template-columns: 1fr;'), 'Lifecycle mobile stacked treatment is missing.');
$assert(!str_contains($adminCss, ".system-manager-status-grid > div,\n.admin-status-card"), 'Lifecycle detail rows must not share card styling.');
$assert(str_contains($systemPage, 'system-manager-status-grid--inline'), 'Lifecycle detail-list layout marker is missing.');
$detailRule = [];
preg_match('/\\.system-manager-status-grid > div \\{(.*?)\\}/s', $adminCss, $detailRule);
$assert(isset($detailRule[1]) && !str_contains($detailRule[1], 'border'), 'Lifecycle rows must not have per-row borders.');
$assert(str_contains($adminCss, '.system-manager-result[hidden]') && str_contains($adminCss, 'display: none;'), 'Idle Update result region is not hidden.');
$systemJs = (string) file_get_contents($basePath . '/public/admin-assets/js/system-manager.js');
$assert(str_contains($systemJs, 'ResizeObserver') && str_contains($systemJs, 'is-stacked'), 'Lifecycle content-fit switching is missing.');
$assert(str_contains($systemJs, 'MutationObserver'), 'Lifecycle content changes do not trigger re-evaluation.');
$assert(str_contains($systemJs, 'payload.guidance'), 'Module completion guidance is not rendered by package feedback.');
$assert(str_contains($systemJs, 'validHex') && str_contains($systemJs, 'data-color-native') && !str_contains($systemJs, 'data-color-format'), 'Canonical native color control synchronization is missing.');
$capabilityJs = (string) file_get_contents($basePath . '/public/admin-assets/js/admin-form-capabilities.js');
$assert(str_contains($capabilityJs, 'is-level-1') && str_contains($capabilityJs, 'is-level-2') && str_contains($capabilityJs, 'is-level-3'), 'Three-level fit layout capability is incomplete.');
$assert(str_contains($capabilityJs, 'ResizeObserver') && str_contains($capabilityJs, 'MutationObserver'), 'Fit layout capability does not re-evaluate content/container changes.');
$assert(str_contains($capabilityJs, 'sessionStorage') && str_contains($capabilityJs, 'beforeunload'), 'Capability-local draft guard is missing.');

$route = (string) file_get_contents($basePath . '/routes/system_manager.php');
$modulePackageFallback = (string) file_get_contents($basePath . '/app/Core/SystemManagerModulePackageFallback.php');
$schema = (string) file_get_contents($basePath . '/database/schema.sql');
$assert(str_contains($route, "add('System Manager'"), 'System Manager navigation registration was removed.');
$assert(str_contains($schema, "'system.webcore.manage'"), 'Fresh-install schema does not seed the System Manager permission.');
$assert(str_contains($route, 'SystemManagerBrandingService'), 'Branding authority is not wired to System Manager.');
$assert(str_contains($route, 'admin-settings-tabs-wrap') && str_contains($route, 'admin-settings-tab'), 'System Manager navigation does not reuse the Settings tab pattern.');
$assert(str_contains($route, 'admin-form-capabilities.js') && str_contains($route, 'clearCapability'), 'Shared capability presentation/dirty-state asset is not wired.');
$assert(str_contains($route, 'system-manager-tabs-wrap') && str_contains($route, '$content = $tabsMarkup'), 'System Manager tabs are not placed in the content area.');
$assert(!str_contains($route, 'class="admin-tabs"'), 'System Manager still uses the button-style tab pattern.');
$assert(str_contains($route, "SystemManagerRecoveryGate.php"), 'System Manager recovery gate authority is not loadable from the route.');
$assert(str_contains($route, 'SystemManagerModuleFallback'), 'Conditional Modules fallback is not wired.');
$assert(str_contains($route, 'SystemManagerModulePackageFallback'), 'System Manager Module package fallback is not wired.');
$assert(str_contains($modulePackageFallback, 'Module packages must be handled through Module Manager.'), 'Module Manager routing guidance is missing.');
$assert(str_contains($modulePackageFallback, 'Fresh Module install completed') && str_contains($modulePackageFallback, 'ModuleTransitionPlan::INSTALL'), 'Fresh Module install guidance is missing or not classification-gated.');
$assert(!str_contains($route, 'Plugin'), 'Plugin package handling was introduced unexpectedly.');
$assert(str_contains($route, 'systemHealthReport'), 'System Health report authority is not consumed.');
$assert(str_contains($route, 'preflightUpload') && str_contains($route, 'executeUpload'), 'Lifecycle intake and execution endpoints are not preserved.');
$assert(!str_contains($route, 'modules/module-manager'), 'System Manager reaches into Module Manager private files.');

$pageFrame = (string) file_get_contents($basePath . '/resources/views/admin/page-frame.php');
$assert(str_contains($pageFrame, 'admin-page-frame'), 'System Manager does not use the WU1 Page Frame boundary.');
$themeLayout = (string) file_get_contents($basePath . '/themes/default/layouts/app.php');
$themeCss = (string) file_get_contents($basePath . '/themes/default/assets/css/app.css');
$assert(str_contains($themeLayout, 'cssVariables'), 'Public layout does not consume the bounded branding palette.');
$assert(str_contains($themeCss, '--site-color-main') && str_contains($themeCss, '--site-color-neutral-light'), 'Default public theme does not consume resolved palette variables.');

echo "MR.2 WU2 System Manager focused test passed ({$assertions} assertions)." . PHP_EOL;
