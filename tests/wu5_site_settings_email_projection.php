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
$emailView = $read('resources/views/admin/site-settings-email.php');
$adminCss = $read('public/admin-assets/css/admin.css');
$operator = $read('app/Core/EmailOperatorService.php');
$expected = ["'identity' => 'Site Identity'", "'system' => 'System'", "'security' => 'Security'", "'email' => 'Email'", "'modules' => 'Modules'", "'redirects' => 'Redirects'", "'health' => 'System Health'"];
$previous = -1;
foreach ($expected as $entry) { $position = strpos($view, $entry); $assert($position !== false && $position > $previous, 'Site Settings tabs are not in the locked seven-tab order.'); $previous = $position; }
$assert(str_contains($routes, "'email.manage'"), 'Site Settings navigation does not recognize email.manage.');
$assert(str_contains($routes, 'EmailOperatorService') && str_contains($routes, 'EmailCredentialBoundary') && str_contains($routes, 'EmailTransport'), 'Production Email operator stack is not canonical.');
$assert(str_contains($routes, '$emailOperator->updateConfiguration') && str_contains($routes, '$emailOperator->replaceCredential') && str_contains($routes, '$emailOperator->sendTest'), 'Email routes do not use EmailOperatorService.');
$assert(str_contains($view, "'email' => \$canManageEmail") && str_contains($view, 'site-settings-email.php'), 'Email tab is not capability-gated and projected.');
$assert(str_contains($emailView, 'SMTP Server') && str_contains($emailView, 'SMTP Credential') && str_contains($emailView, 'Sender Identity') && str_contains($emailView, 'Test Email'), 'Email operator groups are incomplete.');
$assert(str_contains($emailView, 'Not configured') && !str_contains($emailView, 'email_credential" value='), 'Credential state is not redacted in the projection.');
$assert(str_contains($emailView, 'configuration_unavailable') && str_contains($emailView, 'reauthentication_required') && str_contains($emailView, 'credential_unavailable') && str_contains($emailView, 'test_delivery_failed'), 'Email operator feedback coverage is incomplete.');
$assert(str_contains($emailView, 'aria-describedby="email-credential-help"') && str_contains($emailView, 'aria-describedby="email-test-help"'), 'Email credential and test controls lack associated guidance.');
$assert(str_contains($emailView, 'site-settings-email__section') && str_contains($adminCss, '.site-settings-email__section') && str_contains($adminCss, '@media (max-width: 760px)'), 'Email responsive section treatment is missing.');
$assert(str_contains($adminCss, '#site-settings-security') && str_contains($adminCss, 'admin-table-wrap'), 'Security projection hardening does not preserve bounded session-table presentation.');
$assert(str_contains($operator, '&& $operator->can(\'email.manage\')') && !str_contains($operator, 'settings.update'), 'Canonical Email authorization has an unintended settings.update dependency.');

echo "WU5 Site Settings Email projection passed ({$assertions} assertions)." . PHP_EOL;
