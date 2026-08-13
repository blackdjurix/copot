<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$bootstrap = file_get_contents($base . '/bootstrap/installer.php');
$view = file_get_contents($base . '/resources/views/installer/index.php');
$css = file_get_contents($base . '/public/installer-assets/css/installer.css');
$validator = file_get_contents($base . '/app/Core/InstallerAdministratorValidator.php');

if (!is_string($bootstrap) || !is_string($view) || !is_string($css) || !is_string($validator)) {
    throw new RuntimeException('WU3 sources could not be read.');
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(str_contains($bootstrap, "installer_administrator_staged"), 'Administrator staging does not have an authoritative session key.');
$assert(str_contains($bootstrap, "stage_administrator"), 'Administrator staging action is missing.');
$assert(str_contains($bootstrap, '$session->set($administratorSessionKey, $stagedAdministrator)'), 'Administrator staging is not persisted in the session.');
$assert(str_contains($bootstrap, "url('/install?step=finalize')"), 'Successful Administrator staging does not advance to Review & Install.');
$assert(str_contains($bootstrap, 'InstallerAdministratorValidator::validate($input)'), 'Administrator staging does not reuse the repository-native validation boundary.');
$assert(str_contains($bootstrap, "'password' => \$validated['password']"), 'Staged Administrator credentials are not retained server-side.');
$assert(!str_contains($bootstrap, '$administratorSetup->install($input, $requirementsPassed)'), 'Administrator staging still invokes the mutating setup service.');
$assert(str_contains($bootstrap, "'label' => 'Review & Install'"), 'Review & Install is not represented in installer progression.');
$assert(str_contains($view, 'name="action" value="stage_administrator"'), 'Administrator form does not use the staged action.');
$assert(str_contains($view, 'id="admin_password"'), 'Administrator password field is missing.');
$assert(!str_contains($view, 'Password is kept during this installation.'), 'Administrator password helper copy should be absent.');
$assert(!str_contains($view, 'name="action" value="create_administrator"'), 'Administrator view still exposes the pre-Review mutation action.');
$assert(str_contains($validator, 'SettingsRegistry::core()'), 'WU3 validation does not use repository-native settings definitions.');
$assert(str_contains($css, '--form-label-column: 150px;'), 'Administrator desktop form does not use the shared label column.');
$assert(str_contains($css, 'grid-template-columns: var(--form-label-column) minmax(0, 1fr) max-content minmax(0, 1fr);'), 'Administrator paired rows do not use the shared desktop grid.');
$assert(substr_count($view, 'class="form-inline-fields"') >= 3, 'Administrator paired desktop rows are incomplete.');
$assert(substr_count($view, 'class="form-row"') >= 2, 'Administrator single-field rows are incomplete.');
$assert(str_contains($view, 'administrator-navigation'), 'Administrator navigation container is missing.');
$assert(str_contains($view, 'class="form-help"'), 'Administrator password safety help is not attached to the shared feedback slot.');

fwrite(STDOUT, "WU3 staged Administrator assertions: {$assertions}\n");
