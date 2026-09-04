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
$assert(str_contains($view, 'data-site-settings-hero-picker') && str_contains($view, 'data-site-settings-hero-open') && str_contains($view, '>Choose</button>') && str_contains($view, 'data-site-settings-hero-clear') && !str_contains($view, '<select id="hero_media"'), 'Homepage Hero does not use the bounded Core Media picker.');
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
$assetsBoundary = strpos($view, 'site-settings-identity__hero');
$saveBoundary = strpos($view, 'Save Site Settings');
$assert($assetsBoundary !== false && substr_count($view, '<fieldset') === 5 && substr_count($view, '</fieldset>') === 5 && $saveBoundary !== false && strrpos($view, '</fieldset>') < $saveBoundary, 'Site Assets/Homepage or Save action structure is not properly separated.');
$assert(str_contains($view, '<div class="admin-actions admin-form__actions"><button class="admin-button admin-button--primary" type="submit" form="<?= $settingsFormId ?>">Save Site Settings</button>') && !str_contains($css, '.site-settings-identity__save'), 'Save action is not outside the column stack flow.');
$assert(str_contains($siteSettings, "'surface' => 'transparent'") && str_contains($siteSettings, "'spacing' => 'default'"), 'Site Settings does not use the transparent Page Frame intent.');
$assert(!str_contains($view, 'admin-panel__description') && str_contains($siteSettings, "'description' => 'Configure the site identity and baseline appearance.'"), 'Site Settings description is not owned by the shared Page Frame.');
$assert(str_contains($css, '.site-settings-asset-preview-row { display: grid; grid-template-columns: minmax(0, 1fr) minmax(10rem, 14rem); gap: var(--admin-space-4); align-items: stretch; }') && str_contains($css, '.site-settings-asset-preview { min-height: 120px; height: 100%; }'), 'Site Asset preview/action rows do not use stretch alignment.');
$assert(str_contains($view, 'PNG, JPG, or WebP up to 16 MB.') && !str_contains($view, 'high-resolution supported image'), 'Hero helper does not reflect Core Media capability.');
$assert(!str_contains($view, 'admin-media-picker__selected site-settings-hero-preview'), 'Static Hero preview still depends on picker-specific presentation classes.');
$assert(str_contains($css, '.site-settings-identity__settings { display: grid; gap: var(--admin-space-6); align-content: start; grid-column: 1;') && str_contains($css, '.site-settings-identity__media { display: grid; gap: var(--admin-space-6); align-content: start; grid-column: 2;'), 'Site Identity desktop columns do not use the accepted settings/media ownership.');
$assert(str_contains($view, 'site-settings-asset-preview') && str_contains($view, 'site-settings-asset-preview__empty') && str_contains($view, 'Choose <?= $escape($label) ?>'), 'Site Asset static preview states are missing.');
$assert(str_contains($view, 'PNG, JPG, or WebP. Maximum 2 MB; maximum 4096') && str_contains($view, 'PNG or ICO. Maximum 512 KB; maximum 512'), 'Site Asset capability helpers are missing authoritative limits.');
$assert(str_contains($view, 'site-settings-hero-preview') && str_contains($view, 'No Hero Image selected.') && !str_contains($view, 'Selected Homepage Hero Image.</p>'), 'Homepage Hero static preview composition is missing.');
$assert(str_contains($css, '.site-settings-hero-preview__empty[hidden] { display: none; }'), 'Hero preview empty state does not preserve the populated preview state.');
$assert(str_contains($picker, "empty.textContent = 'No Hero Image selected.'") && !str_contains($picker, 'text.textContent = `${item.title'), 'Hero preview still exposes media title or filename text.');
$assert(str_contains($view, 'data-site-settings-hero-open<?= !empty($values[\'hero_media\']) ? \' hidden\' : \'\' ?>') && str_contains($view, 'data-site-settings-hero-clear<?= empty($values[\'hero_media\']) ? \' hidden\' : \'\' ?>') && str_contains($picker, 'openButton.hidden = !!item'), 'Hero actions are not state-exclusive.');
$assert(str_contains($css, '.site-settings-asset-actions .admin-button,') && str_contains($css, '.site-settings-hero-actions .admin-button { width: 7rem; }') && str_contains($css, 'grid-template-columns: minmax(0, 1fr) minmax(10rem, 14rem);'), 'Media actions do not use bounded stable action slots.');
$assert(str_contains($css, '.site-settings-identity__hero .site-settings-hero-actions .admin-button { width: 7rem; }') && str_contains($view, 'data-site-settings-hero-open<?= !empty($values[\'hero_media\']) ? \' hidden\' : \'\' ?>') && str_contains($view, 'data-site-settings-hero-clear<?= empty($values[\'hero_media\']) ? \' hidden\' : \'\' ?>'), 'Hero action sizing or state visibility is not stable across viewport/state changes.');
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
