<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$bootstrap = file_get_contents($base . '/bootstrap/installer.php');
$view = file_get_contents($base . '/resources/views/installer/index.php');
$validator = file_get_contents($base . '/app/Core/InstallerAdministratorValidator.php');

if (!is_string($bootstrap) || !is_string($view) || !is_string($validator)) {
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
$assert(str_contains($bootstrap, "url('/install?step=modules')"), 'Successful Administrator staging does not advance to the WU4 boundary.');
$assert(str_contains($bootstrap, 'InstallerAdministratorValidator::validate($input)'), 'Administrator staging does not reuse the repository-native validation boundary.');
$assert(str_contains($bootstrap, "'password' => \$validated['password']"), 'Staged Administrator credentials are not retained server-side.');
$assert(!str_contains($bootstrap, '$administratorSetup->install($input, $requirementsPassed)'), 'Administrator staging still invokes the mutating setup service.');
$assert(str_contains($bootstrap, "'label' => 'Modules'"), 'WU4 handoff placeholder is not represented in installer progression.');
$assert(str_contains($view, 'name="action" value="stage_administrator"'), 'Administrator form does not use the staged action.');
$assert(str_contains($view, 'id="admin_password"'), 'Administrator password field is missing.');
$assert(str_contains($view, 'Password is retained securely'), 'Administrator password retention boundary is not explained.');
$assert(str_contains($view, 'Optional Module selection will be available in the next work unit.'), 'WU4 handoff placeholder is missing.');
$assert(!str_contains($view, 'name="action" value="create_administrator"'), 'Administrator view still exposes the pre-Review mutation action.');
$assert(str_contains($validator, 'SettingsRegistry::core()'), 'WU3 validation does not use repository-native settings definitions.');

fwrite(STDOUT, "WU3 staged Administrator assertions: {$assertions}\n");
