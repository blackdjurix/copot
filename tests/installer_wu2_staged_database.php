<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$bootstrap = file_get_contents($base . '/bootstrap/installer.php');
$view = file_get_contents($base . '/resources/views/installer/index.php');

if (!is_string($bootstrap) || !is_string($view)) {
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
$assert(!str_contains($bootstrap, 'new InstallerSchemaRunner'), 'Database progression still constructs the COPOT schema runner.');
$assert(!str_contains($bootstrap, 'new InstallerDatabaseSetup'), 'Database progression still constructs the mutating Database setup service.');
$assert(!str_contains($bootstrap, "action === 'install_database'"), 'Legacy install_database progression remains reachable.');
$assert(str_contains($bootstrap, "'decision_evidence'"), 'Inspection evidence is not retained with the staged decision.');
$assert(str_contains($bootstrap, "'password' => \$configuration['password']"), 'Latest credentials are not retained server-side for revisit.');
$assert(str_contains($view, 'class="database-fields"'), 'Database fields do not use a shared Host and Port row.');
$assert(str_contains($view, 'class="database-row"'), 'Database fields do not use horizontal label/control rows.');
$assert(str_contains($view, 'grid-template-columns: 220px minmax(0, 1fr);'), 'Database desktop rows do not use a consistent label column and flexible control column.');
$assert(str_contains($view, 'class="database-inline-fields"'), 'Host/Port and Username/Password compact groupings are missing.');
$assert(str_contains($view, 'grid-template-columns: 220px minmax(0, 1fr) 90px minmax(0, 1fr);'), 'Paired Database rows do not share the fixed label/control grid.');
$assert(str_contains($view, 'class="database-namespace-row"'), 'DB Namespace does not use a dedicated inline action row.');
$namespaceRowPosition = strpos($view, 'class="database-namespace-row"');
$testButtonPosition = strpos($view, 'id="database_action" class="database-action"');
$assert($namespaceRowPosition !== false && $testButtonPosition !== false && $testButtonPosition > $namespaceRowPosition, 'Test Database is not embedded in the namespace row.');
$assert(str_contains($view, 'name="action" value="test_database"'), 'Test Database is not a distinct operation.');
$assert(str_contains($view, 'name="action" value="stage_database"'), 'Database Next does not stage the decision.');
$assert(str_contains($view, 'Test Database inspects the target only.'), 'Database UI does not explain the non-mutating test boundary.');
$assert(str_contains($view, 'Administrator &amp; Site inputs will be available in the next work unit.'), 'WU2 forward boundary does not keep WU3 form staging out of scope.');
$assert(str_contains($view, ".database-inline-field,\n            .database-row,\n            .database-namespace-row {\n                grid-template-columns: 1fr;"), 'Database fields do not collapse safely on mobile.');
$assert(str_contains($view, 'event.submitter?.value !== \'test_database\''), 'Database Test handler can intercept navigation submission.');

fwrite(STDOUT, "WU2 staged Database assertions: {$assertions}\n");
