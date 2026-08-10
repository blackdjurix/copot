<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$bootstrap = file_get_contents($base . '/bootstrap/installer.php');
$view = file_get_contents($base . '/resources/views/installer/index.php');

if (!is_string($bootstrap) || !is_string($view)) {
    throw new RuntimeException('WU1 sources could not be read.');
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(str_contains($bootstrap, 'InstallerRequirements($basePath)'), 'Requirements service is not used.');
$assert(str_contains($bootstrap, '$requirementsService->check($sessionReady)'), 'Mandatory requirements are not rechecked per request.');
$assert(str_contains($bootstrap, '$requirementsSessionKey = \'installer_requirements_acknowledged\''), 'Requirements acknowledgement is not session-scoped.');
$assert(str_contains($bootstrap, '$session->remove($requirementsSessionKey)'), 'A newly failing requirement does not clear acknowledgement.');
$assert(str_contains($bootstrap, '$currentStep = \'requirements\''), 'A blocking requirement does not return to the Requirements step.');
$assert(str_contains($bootstrap, 'return Response::redirect($deploymentContext->url(\'/install?step=database\'))'), 'Successful Requirements progression does not target Database.');
$assert(str_contains($bootstrap, '\'state\' => !$requirementsPassed || !$requirementsAcknowledged ? \'current\' : \'completed\''), 'Requirements progress state is not gated by acknowledgement.');
$assert(str_contains($bootstrap, '\'statusKind\' => $statusKind'), 'Status semantics are not exposed to the view.');

$requirementsGate = strpos($view, 'if (($currentStep ?? \'\') === \'requirements\')');
$databaseStep = strpos($view, 'if (($currentStep ?? \'database\') === \'database\')');
$assert(is_int($requirementsGate) && is_int($databaseStep) && $requirementsGate < $databaseStep, 'Requirements is not the first conditional installer step.');
$assert(str_contains($view, "Continue to Database"), 'Requirements has no visible progression action.');
$assert(str_contains($view, 'status--<?= htmlspecialchars($statusKind'), 'Status presentation is not semantic.');
$assert(str_contains($view, '.status--warning'), 'Warning status styling is missing.');
$assert(str_contains($view, '.status--error'), 'Blocking error status styling is missing.');
$assert(str_contains($view, 'width: min(calc(100% - 32px), 720px)'), 'Responsive installer-card width foundation is missing.');
$assert(str_contains($view, 'body { align-items: flex-start; }'), 'Small-screen overflow behavior is not defined.');

fwrite(STDOUT, "WU1 installer shell/progression assertions: {$assertions}\n");
