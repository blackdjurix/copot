<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;
    if (!$condition) throw new RuntimeException($message);
};
$read = static fn (string $path): string => (string) file_get_contents($base . '/' . $path);

$bootstrap = $read('bootstrap/app.php');
$siteSettings = $read('routes/site_settings.php');
$mediaAdmin = $read('routes/media_admin.php');
$systemManager = $read('routes/system_manager.php');
$settingsManager = $read('modules/settings-manager/routes.php');
$registry = $read('app/Core/SettingsRegistry.php');
$heroService = $read('app/Core/HomepageHeroImageService.php');
$web = $read('routes/web.php');
$layout = $read('resources/views/layout.php');
$view = $read('resources/views/admin/site-settings.php');
$picker = $read('public/admin-assets/js/site-settings-hero-picker.js');
$css = $read('public/admin-assets/css/admin.css');

$assert(str_contains($bootstrap, "routes/site_settings.php"), 'Bootstrap does not load canonical Site Settings routes.');
$assert(!str_contains($bootstrap, "routes/system_manager.php"), 'Bootstrap still loads the retired System Manager route.');
$assert(str_contains($siteSettings, "childUrl('settings')"), 'Canonical Site Settings path is missing.');
foreach (['Site Identity', 'System', 'Security', 'Email', 'Modules', 'System Health'] as $area) {
    $assert(str_contains($view, $area), "Site Settings area is missing: {$area}.");
}
$assert(str_contains($siteSettings, "settings.update"), 'Site Settings write permission is missing.');
$assert(str_contains($siteSettings, "media.use"), 'Hero selection does not preserve Core Media permission separation.');
$assert(str_contains($mediaAdmin, "media/select/upload") && str_contains($mediaAdmin, "media.upload"), 'Core Media selection upload boundary is missing.');
$assert(str_contains($siteSettings, 'HomepageHeroImageService'), 'Homepage Hero service is not wired.');
$assert(str_contains($siteSettings, 'site-assets/logo') && str_contains($siteSettings, 'site-assets/favicon'), 'Site Asset controls are missing.');
$assert(str_contains($registry, "'homepage_hero_media'") && str_contains($registry, "'main_color'"), 'Batch 1 setting definitions are missing.');
$assert(str_contains($heroService, 'registerUsage') && str_contains($heroService, 'removeUsage'), 'Hero usage reconciliation is missing.');
$assert(str_contains($web, "'homepageHero'"), 'Built-in Public View does not receive the Hero projection.');
$assert(str_contains($layout, 'builtin-site-hero') && str_contains($layout, 'builtin-main'), 'Built-in Public View integration is missing.');
$assert(str_contains($view, 'Webcore Color Scheme — Main Color') && !str_contains($view, 'Branding Accent'), 'Legacy four-color branding remains user-facing.');
$assert(str_contains($view, 'data-site-settings-hero-picker') && str_contains($view, 'Choose Image') && str_contains($view, 'Upload New') && !str_contains($view, '<select id="hero_media"'), 'Homepage Hero does not use the bounded Core Media picker.');
$assert(str_contains($siteSettings, "childUrl('media/select')") && str_contains($picker, 'aria-pressed') && str_contains($picker, 'FormData'), 'Homepage Hero picker does not preserve bounded selection and upload behavior.');
$assert(str_contains($view, '<legend class="admin-fieldset__legend">Site Assets</legend><div class="admin-settings-field-grid">') && str_contains($view, "['logo','Logo'") && str_contains($view, "['favicon','Favicon'"), 'Site Assets sibling layout is missing.');
$assert(str_contains($view, 'admin-settings-color-input') && str_contains($css, '.admin-settings-color-input'), 'Webcore Main Color does not use a compact shared control.');
$assert(str_starts_with(ltrim($systemManager), "<?php\n\n//") && str_contains($systemManager, 'return;'), 'Legacy System Manager route is still active.');
$assert(str_starts_with(ltrim($settingsManager), "<?php\n\n//") && str_contains($settingsManager, 'return;'), 'Retired Settings Manager projection is still active.');

require_once $base . '/app/Core/WebcoreColorScheme.php';
$scheme = Copot\Core\WebcoreColorScheme::resolve('#336699');
$assert($scheme['main'] === '#336699', 'Main Color resolution changed the selected value.');
$assert($scheme['neutral-black'] === '#000000' && $scheme['neutral-white'] === '#ffffff', 'Webcore neutral ownership is not canonical.');
$assert($scheme === Copot\Core\WebcoreColorScheme::resolve('#336699'), 'Color variants are not deterministic.');

echo "WU4 Batch 1 Site Settings tests passed ({$assertions} assertions)." . PHP_EOL;
