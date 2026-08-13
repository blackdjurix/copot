<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$bootstrap = file_get_contents($base . '/bootstrap/installer.php');
$view = file_get_contents($base . '/resources/views/installer/index.php');
$css = file_get_contents($base . '/public/installer-assets/css/installer.css');

if (!is_string($bootstrap) || !is_string($view) || !is_string($css)) {
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
$assert(str_contains($bootstrap, "!\$requirementsPassed || !\$requirementsAcknowledged || (!\$administratorStaged && (!\$schemaReady || !\$administratorExists))"), 'Review & Install is not gated by staged Administrator state or completed installation state.');
$assert(str_contains($bootstrap, '\'statusKind\' => $statusKind'), 'Status semantics are not exposed to the view.');
$assert(str_contains($bootstrap, '$showStatus ='), 'Status visibility is not derived from meaningful contextual conditions.');
$assert(str_contains($bootstrap, '\'displayStep\' => $displayStep'), 'Displayed installer phase is not separated from forward lifecycle state.');
$assert(str_contains($bootstrap, '$currentStep === \'requirements\' && $requirementsReview'), 'Fresh Requirements and completed Requirements review status are not distinguished.');

$requirementsGate = strpos($view, 'if (($currentStep ?? \'\') === \'requirements\')');
$databaseStep = strpos($view, 'if (($currentStep ?? \'database\') === \'database\')');
$assert(is_int($requirementsGate) && is_int($databaseStep) && $requirementsGate < $databaseStep, 'Requirements is not the first conditional installer step.');
$assert(str_contains($view, '>Next</a>'), 'Requirements has no visible sequential progression action.');
$assert(str_contains($view, 'stepIsCurrentReview'), 'Current completed-step review is not excluded from self-navigation.');
$assert(str_contains($bootstrap, "'Requirements' => \$deploymentContext->url('/install?step=requirements')"), 'Completed Requirements has no review URL.');
$assert(!str_contains($view, 'Review completed Requirements'), 'Mobile Requirements review affordance remains present.');
$assert(str_contains($view, '>Previous</a>'), 'Database has no explicit Previous path to Requirements.');
$assert(str_contains($view, '>Previous</a>'), 'Installer phases do not use the standard Previous label.');
$assert(str_contains($view, '>Next</button>'), 'Installer phases do not use the standard Next label.');
$assert(str_contains($view, '$stepIsReviewable'), 'Completed-step navigation is not bounded by completed state and review URL.');
$assert(str_contains($css, '.installer-footer'), 'Shared installer footer layout is missing.');
$assert(str_contains($css, 'grid-template-columns: 30% minmax(0, 40%) 30%;'), 'Shared installer footer does not use the locked 30/40/30 geometry.');
$assert(str_contains($css, '.installer-footer__start { grid-column: 1; }') && str_contains($css, '.installer-footer__end { grid-column: 3; }'), 'Shared footer actions are not explicitly placed in the outer columns.');
$assert(str_contains($css, 'justify-content: space-between;'), 'Installer action row does not separate secondary and primary actions.');
$assert(str_contains($view, 'button-secondary'), 'Secondary action styling is missing from the shared action row.');
$assert(str_contains($css, 'grid-template-columns: 30% minmax(0, 40%) 30%;') && str_contains($css, 'align-items: stretch;'), 'Mobile navigation actions are not kept in the shared three-column footer.');
$assert(substr_count($view, 'installer-footer installer-actions') >= 5, 'All installer phases do not use the shared installer footer where actions are available.');
$assert(substr_count($view, 'installer-footer__center') >= 5, 'All installer footers do not retain the shared center gap.');
$assert(str_contains($view, 'installer-footer'), 'Shared navigation footer is missing.');
$assert(str_contains($css, 'min-height: min(760px, calc(100vh - 40px));'), 'Desktop installer shell has no shared viewport-aware footprint.');
$assert(str_contains($css, 'align-items: center;'), 'Desktop installer shell is not vertically centered.');
$assert(str_contains($view, 'phase-form'), 'Installer phase forms do not participate in the shared shell geometry.');
$assert(substr_count($view, 'class="installer-phase"') >= 5, 'All installer phases do not use the shared phase container.');
$assert(substr_count($view, 'class="installer-phase-content" tabindex="0"') >= 5, 'All installer phases do not expose a keyboard-focusable shared content region.');
$assert(str_contains($css, '.installer-phase-content') && str_contains($css, 'overflow-y: auto;') && str_contains($css, 'min-height: 0;'), 'Phase content is not flex-safe and internally scrollable.');
$assert(str_contains($css, '.installer-phase-header') && str_contains($css, '.installer-phase-header {') && str_contains($css, 'flex: none;'), 'Shared phase headers do not have intrinsic non-flexing ownership.');
$assert(str_contains($css, '.installer-footer {') && str_contains($css, 'flex: none;'), 'Shared installer footer is not fixed outside the scroll region.');
$assert(substr_count($view, 'class="installer-phase-header"') >= 5, 'All installer phases do not use the shared dynamic phase header.');
$assert(str_contains($css, 'justify-content: center;') && str_contains($css, 'align-items: center;'), 'Shared footer actions do not center anchor and button content identically.');
$assert(str_contains($view, 'requirements installer-list') && str_contains($view, 'installer-summary installer-list'), 'Requirements and Review do not share the generic installer list primitive.');
$assert(str_contains($css, '.installer-list li') && !str_contains($css, '.requirements li') && !str_contains($css, '.installer-summary li'), 'Requirements and Review retain duplicated list row styling.');
$assert(!str_contains($view, 'mobile-requirements-review'), 'Mobile Requirements review hyperlink remains present.');
$assert(str_contains($view, 'elseif (($currentStep ?? \'\') === \'finalize\')'), 'Finalize is not bounded to the Finalize step.');
$assert(str_contains($css, '.steps .step {') && str_contains($css, 'display: none;'), 'Mobile progress does not hide non-current steps.');
$assert(str_contains($css, '.steps .step-current {'), 'Mobile progress does not preserve the current step.');
$assert(str_contains($css, 'grid-template-rows: auto auto;'), 'Mobile current phase is not presented as two stacked rows.');
$assert(str_contains($view, 'aria-current="step"'), 'Current installer phase is missing its current-step semantic.');
$assert(str_contains($view, 'class="step-state"'), 'Installer phase state is not retained for screen readers.');
$assert(str_contains($css, 'border-top-width: 5px;'), 'Current installer phase lacks a non-color visual cue.');
$assert(str_contains($view, 'status--<?= htmlspecialchars($statusKind'), 'Status presentation is not semantic.');
$assert(str_contains($view, 'class="status-message"'), 'Status message does not have an explicit layout target.');
$assert(str_contains($view, 'showStatusMessage'), 'Dynamic status updates do not preserve status semantics.');
$assert(str_contains($view, 'labels = { info: \'Information\', success: \'Success\', warning: \'Warning\', error: \'Error\' }'), 'Dynamic status updates do not use the accepted label vocabulary.');
$assert(str_contains($view, 'status.replaceChildren()'), 'Dynamic status updates do not preserve the two-line status structure.');
$assert(str_contains($view, "button.value = 'test_database';\n                    button.textContent = 'Test Database';\n                    showStatusMessage(payload.message, 'success');"), 'Successful database tests do not restore the normal Test Database action state.');
$assert(!str_contains($view, 'database_test_result'), 'Database test success still has a duplicate result surface.');
$assert(!str_contains($view, 'result.textContent'), 'Database test success still appends a duplicate result message.');
$assert(str_contains($css, '.status--warning'), 'Warning status styling is missing.');
$assert(str_contains($css, '.status--error'), 'Blocking error status styling is missing.');
$assert(str_contains($css, 'grid-template-columns: 1fr;'), 'Status layout does not use the accepted two-line structure.');
$assert(str_contains($view, 'statusLabel = [\'success\' => \'Success\', \'info\' => \'Information\', \'warning\' => \'Warning\', \'error\' => \'Error\']'), 'Status labels do not use the accepted vocabulary.');
$assert(!str_contains($view, 'Blocking error'), 'Blocking error remains a visible status label.');
$assert(str_contains($view, 'phaseDescriptions'), 'Displayed installer phases do not have contextual descriptions.');
$assert(!str_contains($view, 'Check the server, test a dedicated empty database'), 'Static global installer instruction remains.');
$assert(str_contains($css, 'width: 100%;'), 'Desktop status layout does not use the full installer-card width.');
$assert(str_contains($css, '.status-label,'), 'Status label does not have an explicit status-row placement.');
$assert(str_contains($css, 'grid-column: 1;'), 'Status message does not occupy the full status row.');
$assert(str_contains($css, 'grid-template-columns: 1fr;'), 'Mobile status layout does not preserve readable wrapping.');
$assert(str_contains($css, 'width: min(calc(100% - 32px), 720px)'), 'Responsive installer-card width foundation is missing.');
$assert(str_contains($css, 'align-items: flex-start;'), 'Small-screen overflow behavior is not defined.');

fwrite(STDOUT, "WU1 installer shell/progression assertions: {$assertions}\n");
