<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
require $basePath . '/modules/settings-manager/Services/SettingsField.php';
require $basePath . '/modules/settings-manager/Services/SettingsSection.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$malicious = '"><script>alert(1)</script>';
$sections = [new SettingsSection(
    'fixture',
    'Fixture <Section>',
    'Section <description>',
    [
        new SettingsField('fixture.text', 'fixture', 'text', 'string', 'text', 'Text <Label>', $malicious, true, 12, [], 'default'),
        new SettingsField('fixture.integer', 'fixture', 'integer', 'integer', 'number', 'Integer', null, true, null, [], 1),
        new SettingsField('fixture.float', 'fixture', 'float', 'float', 'number', 'Float', null, false, null, [], 1.5),
        new SettingsField('fixture.enabled', 'fixture', 'enabled', 'boolean', 'checkbox', 'Enabled', null, false, null, [], false),
        new SettingsField('fixture.choice', 'fixture', 'choice', 'string', 'select', 'Choice', null, true, null, ['safe', $malicious], 'safe'),
        new SettingsField('fixture.invalid_choice', 'fixture', 'invalid_choice', 'string', 'select', 'Invalid Choice', null, true, null, ['safe'], 'safe'),
        new SettingsField('fixture.integer_choice', 'fixture', 'integer_choice', 'integer', 'select', 'Integer Choice', null, true, null, [1, 2], 1),
        new SettingsField('fixture.float_choice', 'fixture', 'float_choice', 'float', 'select', 'Float Choice', null, true, null, [1.5, 2.5], 1.5),
        new SettingsField('fixture.foo_bar', 'fixture', 'foo_bar', 'string', 'text', 'Underscore', 'Underscore help.', false, null, [], ''),
        new SettingsField('fixture.foo-bar', 'fixture', 'foo-bar', 'string', 'text', 'Hyphen', 'Hyphen help.', false, null, [], ''),
    ]
)];
$formAction = '/dapur/settings?x=<bad>';
$csrfToken = $malicious;
$values = [
    'fixture.text' => $malicious,
    'fixture.integer' => 7,
    'fixture.float' => 2.5,
    'fixture.enabled' => true,
    'fixture.choice' => $malicious,
    'fixture.invalid_choice' => $malicious,
    'fixture.integer_choice' => '1',
    'fixture.float_choice' => '1.5',
    'fixture.foo_bar' => 'underscore',
    'fixture.foo-bar' => 'hyphen',
];
$fieldErrors = [
    'fixture.text' => ['Error <strong>unsafe</strong>', 'Second error.'],
    'fixture.choice' => ['Invalid choice.'],
    'fixture.invalid_choice' => ['Invalid choice.'],
    'fixture.foo_bar' => ['Underscore error.'],
    'fixture.foo-bar' => ['Hyphen error.'],
];
$formErrors = ['Form <error>.'];
$saved = false;
$assetErrors = [];
$assetNotice = null;
$logoUrl = null;
$faviconUrl = null;
$logoUploadAction = '/dapur/settings/site-assets/logo';
$logoRemoveAction = '/dapur/settings/site-assets/logo/remove';
$faviconUploadAction = '/dapur/settings/site-assets/favicon';
$faviconRemoveAction = '/dapur/settings/site-assets/favicon/remove';

ob_start();
require $basePath . '/modules/settings-manager/views/admin/settings.php';
$html = (string) ob_get_clean();

$assert(str_contains($html, 'Fixture &lt;Section&gt;'), 'Section label was not escaped.');
$assert(str_contains($html, 'Section &lt;description&gt;'), 'Section description was not escaped.');
$assert(str_contains($html, 'Text &lt;Label&gt;'), 'Field label was not escaped.');
$assert(!str_contains($html, '<script>'), 'Executable submitted or option HTML was rendered.');
$assert(str_contains($html, 'name="settings[fixture.text]"'), 'Nested identifier boundary was not rendered.');
$textId = 'setting-' . bin2hex('fixture.text');
$underscoreId = 'setting-' . bin2hex('fixture.foo_bar');
$hyphenId = 'setting-' . bin2hex('fixture.foo-bar');
$assert(str_contains($html, 'id="' . $textId . '"'), 'Identifier HTML ID was not deterministic.');
$assert(str_contains($html, 'maxlength="12"'), 'Maximum length was not rendered.');
$assert(str_contains($html, 'step="1"'), 'Integer step was not rendered.');
$assert(str_contains($html, 'step="any"'), 'Float step was not rendered.');
$assert(str_contains($html, 'type="checkbox"'), 'Checkbox presentation was not rendered.');
$assert(str_contains($html, 'value="1"'), 'Checkbox value boundary was not rendered.');
$assert(str_contains($html, 'checked'), 'Effective checkbox value was not rendered.');
$assert(str_contains($html, '<select'), 'Select presentation was not rendered.');
$assert(str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt; (invalid)'),
    'Invalid-current select option was not escaped or clearly labeled.');
$assert(preg_match('/<option value="1" selected>1<\/option>/', $html) === 1,
    'Integer select did not match a normalized submitted value to its typed option.');
$assert(preg_match('/<option value="1\.5" selected>1\.5<\/option>/', $html) === 1,
    'Float select did not match a normalized submitted value to its typed option.');
$assert(str_contains($html, 'aria-invalid="true"'), 'Invalid field accessibility state was absent.');
$assert(str_contains($html, $textId . '-help ' . $textId . '-error'), 'Help/error associations were incomplete.');
$assert($underscoreId !== $hyphenId, 'Underscore and hyphen identifiers produced the same control ID.');
$assert(str_contains($html, 'for="' . $underscoreId . '"') && str_contains($html, 'for="' . $hyphenId . '"'),
    'Collision fixture labels do not reference distinct controls.');
$assert(str_contains($html, 'id="' . $underscoreId . '-help"') && str_contains($html, 'id="' . $hyphenId . '-help"'),
    'Collision fixture help IDs are not distinct.');
$assert(str_contains($html, 'id="' . $underscoreId . '-error"') && str_contains($html, 'id="' . $hyphenId . '-error"'),
    'Collision fixture error IDs are not distinct.');
preg_match_all('/\sid="([^"]+)"/', $html, $idMatches);
$assert(count($idMatches[1]) === count(array_unique($idMatches[1])), 'Rendered Settings page contains duplicate IDs.');
$assert(str_contains($html, 'Form &lt;error&gt;.'), 'Form error was not escaped or rendered.');
$assert(str_contains($html, 'Upload Logo') && str_contains($html, 'Upload Favicon'), 'Site Asset controls were lost.');
$assert(str_contains($html, 'action="/dapur/settings/site-assets/logo"'), 'Configured Logo action was lost.');
$assert(str_contains($html, 'action="/dapur/settings/site-assets/favicon"'), 'Configured Favicon action was lost.');

$routes = (string) file_get_contents($basePath . '/modules/settings-manager/routes.php');
$assert(str_contains($routes, 'new SettingsManager('), 'Route did not wire SettingsManager.');
$assert(str_contains($routes, '$settingsManager->sections()'), 'Route did not use manager sections.');
$assert(str_contains($routes, '$request->post(\'settings\')'), 'Route did not enforce the nested request boundary.');
$assert(str_contains($routes, '$settingsManager->save($submitted)'), 'Route did not delegate persistence to SettingsManager.');
$assert(str_contains($routes, 'catch (SettingsValidationException $exception)'), 'Route did not handle manager validation errors.');
$assert(str_contains($routes, '$exception->fieldErrors()'), 'Route did not forward inline field errors.');
$assert(str_contains($routes, '$exception->formErrors()'), 'Route did not forward form errors.');
$assert(str_contains($routes, '$exception->submittedValues()'), 'Route did not preserve submitted values.');
$assert(str_contains($routes, "childUrl('settings')"), 'Configured Settings path ownership was lost.');
$assert(!str_contains($routes, "router()->get('/admin/settings'"), 'A duplicate default-path GET route was introduced.');
$assert(!str_contains($routes, 'SettingsService::set('), 'Route directly persisted scalar manager fields.');
$assert(!str_contains($routes, '$settingsFields'), 'Fixed scalar field wiring remains in the route.');

echo "M3.2 Batch 3 presentation passed ({$assertions} assertions)." . PHP_EOL;
