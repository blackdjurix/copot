<?php

declare(strict_types=1);

use Copot\Core\View;

$basePath = dirname(__DIR__);
require $basePath . '/bootstrap/autoload.php';
require $basePath . '/modules/settings-manager/Services/SettingsField.php';
require $basePath . '/modules/settings-manager/Services/SettingsSection.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
        exit(1);
    }
};

$source = (string) file_get_contents($basePath . '/modules/settings-manager/views/admin/settings.php');
$js = (string) file_get_contents($basePath . '/public/admin-assets/js/admin-settings.js');
$css = (string) file_get_contents($basePath . '/public/admin-assets/css/admin.css');

$sections = [
    new SettingsSection('site', 'General', 'General settings.', [
        new SettingsField('site.name', 'site', 'name', 'string', 'text', 'Site Name', 'Name.', true, 150, [], 'Copot'),
        new SettingsField('site.tagline', 'site', 'tagline', 'string', 'text', 'Tagline', 'Tagline.', false, 255, [], ''),
    ]),
    new SettingsSection('localization', 'Localization', 'Localization settings.', [
        new SettingsField('localization.timezone', 'localization', 'timezone', 'string', 'select', 'Timezone', 'Timezone.', true, null, ['UTC', 'Asia/Jakarta'], 'UTC'),
    ]),
];

$view = new View($basePath . '/modules/settings-manager/views');
$html = $view->render('admin/settings', [
    'formAction' => '/dapur/settings',
    'csrfToken' => 'csrf-token',
    'sections' => $sections,
    'values' => ['site.name' => 'Copot', 'site.tagline' => '', 'localization.timezone' => 'Asia/Jakarta'],
    'fieldErrors' => [],
    'formErrors' => [],
    'saved' => false,
    'assetErrors' => [],
    'assetNotice' => null,
    'logoUrl' => null,
    'faviconUrl' => null,
    'logoUploadAction' => '/dapur/settings/site-assets/logo',
    'logoRemoveAction' => '/dapur/settings/site-assets/logo/remove',
    'faviconUploadAction' => '/dapur/settings/site-assets/favicon',
    'faviconRemoveAction' => '/dapur/settings/site-assets/favicon/remove',
]);

foreach (['general', 'localization', 'security', 'email', 'maintenance', 'branding'] as $tab) {
    $assert(str_contains($html, 'data-settings-tab="' . $tab . '"'), "Missing {$tab} tab.");
    $assert(str_contains($html, 'data-settings-panel="' . $tab . '"'), "Missing {$tab} panel.");
    $assert(str_contains($html, 'aria-controls="settings-panel-' . $tab . '"'), "Missing {$tab} aria-controls.");
}
$assert(str_contains($html, 'role="tablist"'), 'Settings tablist role is missing.');
$assert(substr_count($html, 'role="tab"') === 6, 'Expected six tab roles.');
$assert(substr_count($html, 'role="tabpanel"') === 6, 'Expected six tabpanel roles.');
$assert(str_contains($html, 'Security settings are not configurable in this build.'), 'Security empty state is missing.');
$assert(str_contains($html, 'Email settings are not configurable in this build.'), 'Email empty state is missing.');
$assert(str_contains($html, 'Maintenance settings are not configurable in this build.'), 'Maintenance empty state is missing.');
$assert(substr_count($html, 'class="admin-form admin-settings-form"') === 1, 'Existing global Settings form workflow was not preserved.');
$assert(str_contains($html, 'method="post" action="/dapur/settings"'), 'Settings action changed.');
$assert(str_contains($html, 'name="_token" value="csrf-token"'), 'Settings CSRF token changed.');
$assert(str_contains($html, 'name="settings[site.name]"'), 'General field name changed.');
$assert(str_contains($html, 'name="settings[localization.timezone]"'), 'Localization field name changed.');
$assert(str_contains($html, 'admin-settings-brand-assets'), 'Vertical Branding asset wrapper is missing.');
$assert(str_contains($html, 'data-asset-preview="logo"'), 'Logo preview target is missing.');
$assert(str_contains($html, 'data-asset-preview="favicon"'), 'Favicon preview target is missing.');
$assert(substr_count($html, 'src=""') === 0, 'Empty Branding asset states must not render empty image sources.');
$assert(str_contains($source, "if (!empty(\$asset['url'])):"), 'Stored Branding asset preview branch is missing.');
$assert(str_contains($js, 'document.createElement(\'img\')'), 'Local asset selection must create a preview image when none is stored.');
$assert(str_contains($js, 'preview.dataset.assetPreviewAlt'), 'Local asset preview accessibility text is missing.');
$assert(str_contains($html, '/admin-assets/js/admin-settings.js'), 'Page-specific Settings JavaScript is missing.');

foreach (['ArrowRight', 'ArrowLeft', 'Home', 'End', 'Enter'] as $key) {
    $assert(str_contains($js, "event.key === '{$key}'"), "Keyboard support missing for {$key}.");
}
$assert(str_contains($js, "event.key === ' '"), 'Keyboard support missing for Space.');
$assert(str_contains($js, "activate(tabs[next].dataset.settingsTab, { focus: true })"), 'Arrow/Home/End navigation must automatically activate the destination tab.');
$assert(str_contains($js, 'next = (index + 1) % tabs.length'), 'ArrowRight wraparound navigation is missing.');
$assert(str_contains($js, 'next = (index - 1 + tabs.length) % tabs.length'), 'ArrowLeft wraparound navigation is missing.');
$assert(str_contains($js, 'next = tabs.length - 1'), 'End navigation is missing.');
$assert(str_contains($js, "history.replaceState"), 'Hash update behavior is missing.');
$assert(str_contains($js, "beforeunload"), 'Unsaved-change warning is missing.');
$assert(str_contains($js, "URL.createObjectURL"), 'Local asset preview is missing.');
$assert(str_contains($js, "button.disabled = true"), 'Double-submit prevention is missing.');
$assert(str_contains($css, 'Settings Category 1 remediation'), 'Settings remediation CSS marker is missing.');
$assert(str_contains($css, '.admin-settings-tabs-wrap'), 'Responsive tab wrapper is missing.');
$assert(str_contains($css, 'overflow-x: auto'), 'Horizontal tab scrolling is missing.');
$assert(str_contains($css, '.admin-settings-field-grid'), 'Settings field grid is missing.');
$assert(str_contains($css, '.admin-settings-brand-assets'), 'Vertical Branding styling is missing.');
$assert(!str_contains($source, '_settings_section'), 'Category 2 partial-save contract entered Category 1 scope.');
$assert(!str_contains($source, 'Security settings are configurable'), 'Fake Security capability entered scope.');

echo "Settings Category 1 remediation smoke tests passed ({$assertions} assertions)." . PHP_EOL;
