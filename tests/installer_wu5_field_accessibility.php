<?php

declare(strict_types=1);

$base = dirname(__DIR__);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$render = static function (string $currentStep, array $errors = [], array $setupErrors = []) use ($base): string {
    $url = static fn (string $path): string => $path;
    $showStatus = false;
    $statusKind = 'info';
    $message = '';
    $steps = [];
    $databaseFeedback = null;
    $requirements = [];
    $requirementsPassed = true;
    $requirementsAcknowledged = true;
    $databaseResult = $currentStep === 'database' ? ['eligible_intents' => [Copot\Core\InstallerIntent::FRESH]] : null;
    $csrfToken = 'test-token';
    $values = ['host' => '127.0.0.1', 'port' => '3306', 'database' => 'copot_test', 'username' => 'copot', 'namespace' => '', 'intent' => Copot\Core\InstallerIntent::FRESH];
    $databaseStaged = $currentStep === 'administrator';
    $administratorStaged = false;
    $setupValues = ['admin_name' => 'Administrator', 'admin_email' => 'admin@example.test', 'site_name' => 'COPOT', 'site_tagline' => '', 'timezone' => 'UTC', 'locale' => 'en_US'];
    $timezones = ['UTC'];
    $locales = ['en_US'];
    $finalizationError = null;
    $installationResult = null;

    ob_start();
    require $base . '/resources/views/installer/index.php';
    return (string) ob_get_clean();
};

$control = static function (string $html, string $id): string {
    $quotedId = preg_quote($id, '/');
    if (preg_match('/<(?:input|select)\\b[^>]*\\bid="' . $quotedId . '"[^>]*>/i', $html, $match) !== 1) {
        throw new RuntimeException("Control {$id} was not rendered.");
    }

    return $match[0];
};

$assertReferencesResolve = static function (string $html) use ($assert): void {
    preg_match_all('/\\bid="([^"]+)"/', $html, $idMatches);
    $ids = $idMatches[1];
    $assert(count($ids) === count(array_unique($ids)), 'Rendered installer IDs must be unique.');
    $knownIds = array_fill_keys($ids, true);

    preg_match_all('/\\baria-describedby="([^"]+)"/', $html, $descriptionMatches);
    foreach ($descriptionMatches[1] as $references) {
        foreach (preg_split('/\\s+/', trim($references)) as $reference) {
            $assert($reference !== '' && isset($knownIds[$reference]), "aria-describedby reference {$reference} does not resolve to a unique rendered ID.");
        }
    }
};

$validDatabase = $render('database');
$assert(!str_contains($validDatabase, 'aria-invalid="true"'), 'Valid Database controls must not be marked invalid.');
$namespaceControl = $control($validDatabase, 'database_namespace');
$assert(str_contains($namespaceControl, 'aria-describedby="database_namespace_help"'), 'Database help must be associated with its control.');
$assert(str_contains($validDatabase, 'id="database_namespace_help" class="form-help"'), 'Database help association target is missing.');
$assert(str_contains($control($validDatabase, 'database_host'), 'required'), 'Existing Database required semantics must remain intact.');
$assert(str_contains($validDatabase, '<label for="database_host">Host</label>'), 'Existing Database labels must remain intact.');
$assertReferencesResolve($validDatabase);

$invalidDatabase = $render('database', ['host' => 'Host is required.', 'namespace' => 'Namespace is invalid.']);
foreach (['database_host' => 'database_host_error', 'database_namespace' => 'database_namespace_error'] as $fieldId => $errorId) {
    $renderedControl = $control($invalidDatabase, $fieldId);
    $assert(str_contains($renderedControl, 'aria-invalid="true"'), "Invalid {$fieldId} must expose aria-invalid.");
    $assert(str_contains($renderedControl, $errorId), "Invalid {$fieldId} must describe its field error.");
    $assert(str_contains($invalidDatabase, 'id="' . $errorId . '" class="field-error"'), "Invalid {$fieldId} error target is missing.");
}
$assert(str_contains($control($invalidDatabase, 'database_namespace'), 'database_namespace_help'), 'Invalid Database namespace must retain its help association.');
$assertReferencesResolve($invalidDatabase);

$invalidAdministrator = $render('administrator', [], [
    'admin_name' => 'Username is required.',
    'admin_email' => 'Email is invalid.',
    'admin_password' => 'Password is too short.',
    'admin_password_confirmation' => 'Passwords do not match.',
    'site_name' => 'Site name is required.',
    'site_tagline' => 'Site tagline is invalid.',
    'timezone' => 'Time zone is invalid.',
    'locale' => 'Locale is invalid.',
]);
foreach (['admin_name', 'admin_email', 'admin_password', 'admin_password_confirmation', 'site_name', 'site_tagline', 'timezone', 'locale'] as $fieldId) {
    $renderedControl = $control($invalidAdministrator, $fieldId);
    $assert(str_contains($renderedControl, 'aria-invalid="true"'), "Invalid {$fieldId} must expose aria-invalid.");
    $assert(str_contains($renderedControl, $fieldId . '_error'), "Invalid {$fieldId} must describe its field error.");
    $assert(str_contains($invalidAdministrator, 'id="' . $fieldId . '_error" class="field-error"'), "Invalid {$fieldId} error target is missing.");
}
$assert(str_contains($control($invalidAdministrator, 'admin_name'), 'required'), 'Existing Administrator required semantics must remain intact.');
$assert(str_contains($invalidAdministrator, '<label for="admin_name">Username</label>'), 'Existing Administrator labels must remain intact.');
$assertReferencesResolve($invalidAdministrator);

fwrite(STDOUT, "WU5 installer field accessibility assertions: {$assertions}" . PHP_EOL);
