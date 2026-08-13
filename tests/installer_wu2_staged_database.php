<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$bootstrap = file_get_contents($base . '/bootstrap/installer.php');
$view = file_get_contents($base . '/resources/views/installer/index.php');
$css = file_get_contents($base . '/public/installer-assets/css/installer.css');

if (!is_string($bootstrap) || !is_string($view) || !is_string($css)) {
    throw new RuntimeException('WU2 sources could not be read.');
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(str_contains($bootstrap, "installer_database_staged"), 'Database staging does not have an authoritative session key.');
$assert(str_contains($bootstrap, '$session->set($databaseSessionKey, $stagedDatabase)'), 'Database staging is not persisted in the session.');
$assert(str_contains($bootstrap, "'staged' => \$action === 'stage_database'"), 'Successful Database staging does not record forward lifecycle state.');
$assert(str_contains($bootstrap, "url('/install?step=administrator')"), 'Successful Database staging does not advance to the next staged lifecycle boundary.');
$assert(str_contains($bootstrap, "!\$schemaReady && \$databaseStaged"), 'Administrator display does not accept the WU2 staged boundary.');
$assert(str_contains($bootstrap, "['test_database', 'stage_database']"), 'Database progression exposes an unsafe legacy mutation action.');
$assert(str_contains($bootstrap, 'No COPOT schema or tables were created.'), 'Staged Database feedback does not state the mutation invariant.');
$databaseActionBoundary = strpos($bootstrap, 'if ($action === \'finalize_installation\')');
$schemaCommitConstruction = strpos($bootstrap, 'new InstallerSchemaRunner');
$assert($databaseActionBoundary !== false && $schemaCommitConstruction !== false && $schemaCommitConstruction > $databaseActionBoundary, 'COPOT schema materialization is not confined to the Review & Install commit boundary.');
$assert(!str_contains(substr($bootstrap, 0, $databaseActionBoundary ?: 0), 'new InstallerDatabaseSetup'), 'Database progression still constructs the mutating Database setup service.');
$assert(!str_contains($bootstrap, "action === 'install_database'"), 'Legacy install_database progression remains reachable.');
$assert(str_contains($bootstrap, "'decision_evidence'"), 'Inspection evidence is not retained with the staged decision.');
$assert(str_contains($bootstrap, "'password' => \$configuration['password']"), 'Latest credentials are not retained server-side for revisit.');
$assert(str_contains($view, 'class="form-fields"'), 'Database fields do not use the shared form field container.');
$assert(str_contains($view, 'class="form-row"'), 'Database fields do not use shared horizontal label/control rows.');
$assert(str_contains($css, '--form-label-column: 150px;'), 'Shared desktop rows do not define a fixed label column.');
$assert(str_contains($css, 'grid-template-columns: var(--form-label-column) minmax(0, 1fr);'), 'Shared desktop rows do not use a fluid content column.');
$assert(str_contains($view, 'class="form-inline-fields"'), 'Database paired controls do not use shared inline fields.');
$assert(str_contains($css, 'grid-template-columns: var(--form-label-column) minmax(0, 1fr) max-content minmax(0, 1fr);'), 'Paired Database rows do not share the fixed label/control grid.');
$assert(str_contains($view, 'class="form-action-row"'), 'DB Namespace does not use the shared action-row variant.');
$namespaceRowPosition = strpos($view, 'class="form-action-row"');
$testButtonPosition = strpos($view, 'id="database_action" class="database-action"');
$assert($namespaceRowPosition !== false && $testButtonPosition !== false && $testButtonPosition > $namespaceRowPosition, 'Test Database is not embedded in the namespace row.');
$assert(str_contains($css, 'height: 34px;'), 'Database controls do not use the required desktop height.');
$assert(str_contains($css, 'padding: 6px 8px;'), 'Shared form controls do not use the required padding.');
$assert(str_contains($css, '.form-help,') && str_contains($css, 'margin: 0;'), 'Shared form help does not use zero margin.');
$assert(str_contains($css, 'font-size: 13px;') && str_contains($css, 'font-style: italic;'), 'Database help text does not use the required typography.');
$assert(str_contains($view, 'class="installer-footer installer-actions"'), 'Database navigation does not use the shared installer footer.');
$assert(str_contains($css, '.installer-footer'), 'Shared installer footer primitive is missing.');
$assert(str_contains($view, '>Previous</a>'), 'Database Previous label is not exactly Previous.');
$assert(str_contains($view, 'name="action" value="test_database"'), 'Test Database is not a distinct operation.');
$assert(str_contains($view, 'name="action" value="stage_database"'), 'Database Next does not stage the decision.');
$assert(!str_contains($view, 'Test Database inspects the target only.'), 'Obsolete Database technical note remains user-visible.');
$assert(str_contains($view, 'id="database_feedback"'), 'Database feedback does not have a single contextual result surface.');
$assert(!str_contains($bootstrap, "Stage the first administrator and initial site settings before installation."), 'Routine Administrator informational banner remains in the installer flow.');
$assert(str_contains($view, 'name="action" value="stage_administrator"'), 'WU2 forward boundary does not expose the authorized WU3 staging form.');
$assert(str_contains($css, '.form-inline-fields,') && str_contains($css, 'grid-template-columns: 1fr;'), 'Shared form fields do not collapse safely on mobile.');
$assert(str_contains($view, 'event.submitter?.value !== \'test_database\''), 'Database Test handler can intercept navigation submission.');

fwrite(STDOUT, "WU2 staged Database assertions: {$assertions}\n");
