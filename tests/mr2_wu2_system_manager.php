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

$route = (string) file_get_contents($basePath . '/routes/system_manager.php');
$schema = (string) file_get_contents($basePath . '/database/schema.sql');
$assert(str_contains($route, "add('System Manager'"), 'System Manager navigation registration was removed.');
$assert(str_contains($schema, "'system.webcore.manage'"), 'Fresh-install schema does not seed the System Manager permission.');
$assert(str_contains($route, 'SystemManagerBrandingService'), 'Branding authority is not wired to System Manager.');
$assert(str_contains($route, "SystemManagerRecoveryGate.php"), 'System Manager recovery gate authority is not loadable from the route.');
$assert(str_contains($route, 'SystemManagerModuleFallback'), 'Conditional Modules fallback is not wired.');
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
