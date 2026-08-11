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
$assert(str_contains($bootstrap, "['database', 'requirements', 'administrator', 'finalize']"), 'Installer review re-entry steps are not explicitly bounded.');
$assert(str_contains($bootstrap, '$requirementsReview = true'), 'Completed Requirements review mode is not represented.');
$assert(str_contains($bootstrap, '$forwardStep ='), 'Forward lifecycle state is not separated from Requirements review mode.');
$assert(str_contains($bootstrap, '$requirementsForwardUrl ='), 'Requirements review has no return target for the active forward step.');
$assert(str_contains($bootstrap, "['database', 'requirements', 'administrator', 'finalize']"), 'Completed later installer phases are not bounded review targets.');
$assert(str_contains($bootstrap, '$requestedStep === \'administrator\' && $schemaReady'), 'Administrator review is not limited to an available completed schema phase.');
$assert(str_contains($bootstrap, '$requestedStep === \'finalize\' && $schemaReady && $administratorExists'), 'Finalize review is not limited to its legitimate lifecycle context.');
$assert(str_contains($bootstrap, "['reviewUrl'] ="), 'Completed installer phases do not expose bounded review URLs.');
$assert(str_contains($bootstrap, '$session->remove($requirementsSessionKey)'), 'A newly failing requirement does not clear acknowledgement.');
$assert(str_contains($bootstrap, '$currentStep = \'requirements\''), 'A blocking requirement does not return to the Requirements step.');
$assert(str_contains($bootstrap, 'return Response::redirect($deploymentContext->url(\'/install?step=database\'))'), 'Successful Requirements progression does not target Database.');
$assert(str_contains($bootstrap, '\'state\' => !$requirementsPassed || !$requirementsAcknowledged ? \'current\' : \'completed\''), 'Requirements progress state is not gated by acknowledgement.');
$assert(str_contains($bootstrap, '\'state\' => !$requirementsPassed || !$requirementsAcknowledged || !$schemaReady || !$administratorExists'), 'Finalize is not blocked until Requirements, schema, and administrator state are ready.');
$assert(str_contains($bootstrap, '\'statusKind\' => $statusKind'), 'Status semantics are not exposed to the view.');
$assert(str_contains($bootstrap, '$currentStep === \'requirements\' && $requirementsReview'), 'Fresh Requirements and completed Requirements review status are not distinguished.');

$requirementsGate = strpos($view, 'if (($currentStep ?? \'\') === \'requirements\')');
$databaseStep = strpos($view, 'if (($currentStep ?? \'database\') === \'database\')');
$assert(is_int($requirementsGate) && is_int($databaseStep) && $requirementsGate < $databaseStep, 'Requirements is not the first conditional installer step.');
$assert(str_contains($view, "Continue to Database"), 'Requirements has no visible progression action.');
$assert(str_contains($view, 'Return to '), 'Completed Requirements review has no forward return action.');
$assert(str_contains($view, 'stepIsCurrentReview'), 'Current completed-step review is not excluded from self-navigation.');
$assert(str_contains($view, 'href="<?= htmlspecialchars($requirementsReviewUrl'), 'Completed Requirements has no review URL.');
$assert(str_contains($view, 'Review completed Requirements'), 'Mobile Requirements review affordance is missing.');
$assert(str_contains($view, 'Previous: Requirements'), 'Database has no explicit Previous path to Requirements.');
$assert(str_contains($view, 'Previous: Database'), 'Administrator and Site has no explicit Previous path to Database.');
$assert(str_contains($view, 'Previous: Administrator &amp; Site'), 'Finalize has no explicit Previous path to Administrator and Site.');
$assert(str_contains($view, '$stepIsReviewable'), 'Completed-step navigation is not bounded by completed state and review URL.');
$assert(str_contains($view, '.installer-actions {'), 'Shared installer action-row layout is missing.');
$assert(str_contains($view, 'justify-content: space-between;'), 'Installer action row does not separate secondary and primary actions.');
$assert(str_contains($view, 'button-secondary'), 'Secondary action styling is missing from the shared action row.');
$assert(str_contains($view, '.installer-actions { flex-direction: column; align-items: stretch; }'), 'Mobile installer action-row layout is not defined.');
$assert(str_contains($view, '.mobile-requirements-review {'), 'Requirements review affordance has no shared style.');
$assert(str_contains($view, "display: none;\n            margin: -8px 0 12px;"), 'Requirements review affordance is not hidden from desktop duplication.');
$assert(str_contains($view, '.mobile-requirements-review { display: block; }'), 'Mobile Requirements review affordance is not visible.');
$assert(str_contains($view, 'elseif (($currentStep ?? \'\') === \'finalize\')'), 'Finalize is not bounded to the Finalize step.');
$assert(str_contains($view, '.steps .step { display: none; }'), 'Mobile progress does not hide non-current steps.');
$assert(str_contains($view, '.steps .step-current {'), 'Mobile progress does not preserve the current step.');
$assert(str_contains($view, 'grid-template-rows: auto auto;'), 'Mobile current phase is not presented as two stacked rows.');
$assert(str_contains($view, 'aria-current="step"'), 'Current installer phase is missing its current-step semantic.');
$assert(str_contains($view, 'class="step-state"'), 'Installer phase state is not retained for screen readers.');
$assert(str_contains($view, 'border-top-width: 5px;'), 'Current installer phase lacks a non-color visual cue.');
$assert(str_contains($view, 'status--<?= htmlspecialchars($statusKind'), 'Status presentation is not semantic.');
$assert(str_contains($view, 'class="status-message"'), 'Status message does not have an explicit layout target.');
$assert(str_contains($view, '.status--message-only'), 'Label-less status messages do not have a full-width layout mode.');
$assert(str_contains($view, 'showMessageOnlyStatus'), 'Dynamic status updates do not preserve message-only layout semantics.');
$assert(str_contains($view, '.status--warning'), 'Warning status styling is missing.');
$assert(str_contains($view, '.status--error'), 'Blocking error status styling is missing.');
$assert(str_contains($view, 'grid-template-columns: 112px minmax(0, 1fr);'), 'Desktop status layout does not allocate a stable label column.');
$assert(str_contains($view, 'width: 100%;'), 'Desktop status layout does not use the full installer-card width.');
$assert(str_contains($view, '.status-message { grid-column: 2; min-width: 0; }'), 'Desktop status message cell does not release intrinsic minimum width.');
$assert(str_contains($view, '.status { grid-template-columns: 1fr; align-items: start; }'), 'Mobile status layout does not preserve readable wrapping.');
$assert(str_contains($view, 'width: min(calc(100% - 32px), 720px)'), 'Responsive installer-card width foundation is missing.');
$assert(str_contains($view, 'body { align-items: flex-start; }'), 'Small-screen overflow behavior is not defined.');

fwrite(STDOUT, "WU1 installer shell/progression assertions: {$assertions}\n");
