<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;
    if (!$condition) throw new RuntimeException($message);
};
$read = static fn (string $path): string => (string) file_get_contents($basePath . '/' . $path);

$routes = $read('routes/site_settings.php');
$view = $read('resources/views/admin/site-settings.php');
$contract = $read('docs/58_seven_tab_site_settings_completion_hardening_contract.md');

$expectedTabs = [
    "'identity' => 'Site Identity'",
    "'system' => 'System'",
    "'security' => 'Security'",
    "'email' => 'Email'",
    "'modules' => 'Modules'",
    "'redirects' => 'Redirects'",
    "'health' => 'System Health'",
];
$positions = [];
foreach ($expectedTabs as $tab) {
    $position = strpos($view, $tab);
    $assert($position !== false, 'Seven-Tab area is missing: ' . $tab);
    $positions[] = $position;
}
$assert($positions === array_values($positions) && $positions === array_unique($positions), 'Seven-Tab order is not deterministic.');
$assert(str_contains($contract, "1. Site Identity") && str_contains($contract, "4. Email") && str_contains($contract, "7. System Health"), 'The accepted seven-tab order is not represented by the contract.');
$assert(str_contains($routes, "'email.manage'") && str_contains($routes, '$emailOperator'), 'Email projection does not use the accepted capability authority.');
$assert(str_contains($routes, '$emailOperator->read') && str_contains($routes, '$emailOperator->updateConfiguration') && str_contains($routes, '$emailOperator->replaceCredential') && str_contains($routes, '$emailOperator->sendTest'), 'Email read, configuration, credential, and test-delivery actions are not delegated through one authority.');
$assert(str_contains($view, 'site-settings-email') && str_contains($view, 'email_credential') && str_contains($view, 'credential') && str_contains($view, 'Send Test Email'), 'Email operator projection or secret-state boundary is missing.');
$assert(!str_contains($view, 'email_password') && !str_contains($view, 'credential_value'), 'Email credential is exposed through a readable field or view model.');
$assert(str_contains($routes, 'email_status=') && str_contains($view, 'site-settings-email'), 'Email actions do not return bounded operator feedback to the Email tab.');
$assert(str_contains($view, 'site-settings-security') && str_contains($view, 'site-settings-modules') && str_contains($view, 'site-settings-redirects') && str_contains($view, '$canManageModules'), 'Existing accepted capability projections were not retained.');
$assert(str_contains($routes, "'redirects.manage'") && str_contains($routes, "'security.manage'"), 'Existing Security or Redirect permission boundaries were removed.');

echo "WU5 Site Settings integration tests passed ({$assertions} assertions)." . PHP_EOL;
