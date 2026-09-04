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
$colorJs = $read('public/admin-assets/js/site-settings-color.js');
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
$assert(str_contains($view, 'Main Color') && !str_contains($view, 'Branding Accent'), 'Legacy four-color branding remains user-facing.');
$assert(str_contains($view, 'data-site-settings-hero-picker') && str_contains($view, 'data-site-settings-hero-open>Choose</button>') && str_contains($view, 'data-site-settings-hero-clear') && !str_contains($view, '<select id="hero_media"'), 'Homepage Hero does not use the bounded Core Media picker.');
$assert(str_contains($siteSettings, "childUrl('media/select')") && str_contains($picker, 'aria-pressed') && str_contains($picker, 'FormData'), 'Homepage Hero picker does not preserve bounded selection and upload behavior.');
$assert(str_contains($view, 'data-site-settings-hero-upload-button>Upload</button>') && str_contains($view, 'class="admin-field" method="post"'), 'Site Settings upload actions do not use shared field/action composition.');
$assert(str_contains($view, 'data-site-settings-hero-upload></div><div class="admin-actions"><button class="admin-button admin-button--primary" type="button" data-site-settings-hero-upload-button>Upload</button><button class="admin-button admin-button--secondary" type="button" data-site-settings-hero-close>Cancel</button>'), 'Hero picker upload and cancel actions are not grouped in the shared action row.');
$assert(str_contains($view, 'data-site-settings-hero-clear') && str_contains($view, 'admin-button--secondary" type="button" data-site-settings-hero-clear'), 'Hero Clear action does not use the shared button treatment.');
$assert(str_contains($view, '>Upload</button>') && str_contains($view, 'class="admin-actions" method="post"') && str_contains($view, '>Remove</button>'), 'Site Asset actions do not use concise shared button labels.');
$assert(str_contains($view, '<legend class="admin-fieldset__legend">Site Assets</legend><div class="site-settings-asset-stack">') && str_contains($view, "['logo','Logo'") && str_contains($view, "['favicon','Favicon'"), 'Site Assets stack layout is missing.');
$assert(str_contains($view, 'admin-settings-color-input') && str_contains($css, '.admin-settings-color-input'), 'Webcore Main Color does not use a compact shared control.');
$assert(str_contains($view, 'site-settings-identity-layout') && str_contains($view, 'site-settings-identity__assets') && str_contains($view, 'site-settings-identity__hero'), 'Site Identity desktop composition is missing.');
$assert(str_contains($view, 'site-settings-identity__general') && str_contains($view, 'site-settings-identity__localization') && str_contains($view, 'site-settings-identity__appearance'), 'Site Identity settings column composition is missing.');
$assert(str_contains($view, 'site-settings-identity__settings') && str_contains($view, 'site-settings-identity__media') && str_contains($css, '.site-settings-identity__settings { display: grid; gap: var(--admin-space-6); align-content: start;'), 'Site Identity columns do not use independent top-aligned stacks.');
$assert(str_contains($view, 'admin-settings-field-grid--single') && str_contains($css, '.admin-settings-field-grid--single { grid-template-columns: minmax(0, 1fr); }'), 'General fields are not stacked in one column.');
$assert(str_contains($view, '<select id="<?= $key ?>" name="<?= $key ?>"') && str_contains($view, "['locale','Locale',\$locales ?? []]") && str_contains($view, 'foreach ($options as $option)'), 'Localization controls are not controlled selects.');
$assert(str_contains($siteSettings, "'locales' =>") && str_contains($siteSettings, "'timezones' => timezone_identifiers_list()") && str_contains($siteSettings, "'dateFormats' =>") && str_contains($siteSettings, "'timeFormats' =>"), 'Localization controls are missing canonical option sources.');
$assert(str_contains($css, '.site-settings-page > .admin-panel__body > .admin-alert { margin-inline: var(--admin-space-6); }') && str_contains($css, '.admin-settings-color-control .admin-settings-color-hex { width: 11ch; max-width: 100%; }'), 'Site Identity alert or HEX control refinement is missing.');
$assert(str_contains($css, '.site-settings-page #site-settings-identity { width: 100%; }') && !str_contains($view, '<h2 class="admin-panel__title" id="site-settings-title">Site Settings</h2>'), 'Site Identity width or redundant inner heading was not reconciled.');
$assert(str_contains($view, 'id="main_color_hex"') && str_contains($view, 'Main Color</label>') && !str_contains($view, 'Webcore Color Scheme — Main Color'), 'Main Color presentation was not simplified to the synchronized HEX control.');
$assert(str_contains($colorJs, 'data-main-color-picker') && str_contains($colorJs, 'setCustomValidity') && str_contains($colorJs, 'picker.value = value.toLowerCase()'), 'Main Color picker and HEX synchronization behavior is missing.');
$assert(str_starts_with(ltrim($systemManager), "<?php\n\n//") && str_contains($systemManager, 'return;'), 'Legacy System Manager route is still active.');
$assert(str_starts_with(ltrim($settingsManager), "<?php\n\n//") && str_contains($settingsManager, 'return;'), 'Retired Settings Manager projection is still active.');

require_once $base . '/app/Core/WebcoreColorScheme.php';
$scheme = Copot\Core\WebcoreColorScheme::resolve('#336699');
$assert($scheme['main'] === '#336699', 'Main Color resolution changed the selected value.');
$assert($scheme['neutral-black'] === '#000000' && $scheme['neutral-white'] === '#ffffff', 'Webcore neutral ownership is not canonical.');
$assert($scheme === Copot\Core\WebcoreColorScheme::resolve('#336699'), 'Color variants are not deterministic.');

echo "WU4 Batch 1 Site Settings tests passed ({$assertions} assertions)." . PHP_EOL;
