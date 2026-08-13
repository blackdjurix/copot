<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$bootstrap = file_get_contents($base . '/bootstrap/installer.php');
$view = file_get_contents($base . '/resources/views/installer/index.php');
$validator = file_get_contents($base . '/app/Core/InstallerAdministratorValidator.php');

if (!is_string($bootstrap) || !is_string($view) || !is_string($validator)) {
    throw new RuntimeException('Batch 2 sources could not be read.');
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(str_contains($view, 'required'), 'Native required validation is not present.');
$assert(str_contains($view, 'type="email"'), 'Native email validation is not present.');
$assert(str_contains($view, 'minlength="10"'), 'Native password minimum validation is not present.');
$assert(str_contains($view, "empty(\$administratorStaged) ? 'required' : ''"), 'Administrator revisit password semantics are not reflected in HTML required handling.');
$assert(str_contains($view, 'pattern="[a-z][a-z0-9_]{0,30}"'), 'Native namespace pattern validation is not present.');
$assert(str_contains($view, 'setCustomValidity(mismatch ? \'Passwords must match.\' : \'\')'), 'Password confirmation custom validity is not present.');
$assert(str_contains($view, "adminPassword.addEventListener('input', updatePasswordConfirmationValidity)"), 'Password changes do not refresh confirmation validity.');
$assert(str_contains($view, "adminPasswordConfirmation.addEventListener('input', updatePasswordConfirmationValidity)"), 'Confirmation changes do not refresh custom validity.');
$assert(str_contains($validator, "'admin_password_confirmation' => 'Password confirmation does not match.'"), 'Server-side password confirmation fallback is missing.');
$assert(str_contains($view, 'id="database_feedback"'), 'Database feedback is not contextualized near the Database action.');
$assert(str_contains($view, 'showStatusMessage(payload.message, \'success\')'), 'Database success does not update its single contextual result.');
$assert(!str_contains($view, 'database_test_result'), 'A duplicate Database result surface remains.');
$assert(!str_contains($view, 'Test Database inspects the target only.'), 'The obsolete technical note remains visible.');
$assert(!str_contains($bootstrap, "Stage the first administrator and initial site settings before installation."), 'Routine Administrator informational banner remains.');
$assert(str_contains($bootstrap, '$databaseContextualState = $currentStep === \'database\''), 'Database contextual state is not separated from global status.');
$assert(str_contains($bootstrap, '$databaseFeedback = null;'), 'Database feedback does not begin with an empty request-scoped payload.');
$assert(str_contains($bootstrap, "['kind' => 'success', 'message' => \$message]"), 'Current Database success does not create an explicit feedback payload.');
$assert(str_contains($bootstrap, "['kind' => 'error', 'message' => \$message]"), 'Current Database failure does not create an explicit feedback payload.');
$assert(str_contains($view, '$activeDatabaseFeedback === null ? \'hidden\' : \'\''), 'Database feedback target is not hidden on staged revisit.');
$assert(str_contains($view, '$activeDatabaseFeedback === null ? \'\' : htmlspecialchars($databaseFeedbackLabel'), 'Hidden Database feedback pre-populates a status label.');
$assert(str_contains($bootstrap, '&& !$databaseContextualState;'), 'Database contextual feedback still leaks into the global status surface.');
$assert(str_contains($bootstrap, 'No COPOT schema or tables were created.'), 'Database staging mutation invariant is not preserved.');
$assert(str_contains($bootstrap, 'Database namespace is already in use. Change the namespace and test again.'), 'Namespace collision feedback does not use the accepted concise wording.');

fwrite(STDOUT, "Batch 2 validation/feedback assertions: {$assertions}\n");
