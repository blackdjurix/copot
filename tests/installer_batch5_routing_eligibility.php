<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$bootstrap = file_get_contents($base . '/bootstrap/installer.php');
$view = file_get_contents($base . '/resources/views/installer/index.php');

if (!is_string($bootstrap) || !is_string($view)) {
    throw new RuntimeException('Batch 5 routing sources could not be read.');
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(str_contains($bootstrap, '$eligibleIntents = $planner->eligibleIntents($inspection[\'occupancy\']);'), 'Database inspection does not derive eligible intents.');
$assert(str_contains($bootstrap, "if (\$action === 'stage_database')"), 'Selected-route validation is not limited to staging.');
$assert(str_contains($bootstrap, "'eligible_intents' => \$eligibleIntents"), 'Inspection result does not preserve eligible intents.');
$assert(str_contains($bootstrap, "\$values['intent'] = \$eligibleIntents[0] ?? ''"), 'Test Database does not select a valid inspected intent.');
$assert(str_contains($view, 'Test Database to determine eligible installation paths.'), 'Pre-inspection intent placeholder is missing.');
$assert(str_contains($view, "array_filter(\$databaseResult['eligible_intents']"), 'Intent options are not filtered by inspected eligibility.');
$assert(str_contains($view, 'renderEligibleIntents(payload.database?.eligible_intents || [])'), 'Successful inspection does not refresh eligible intent options.');
$assert(str_contains($view, 'renderEligibleIntents([])'), 'Failed inspection does not clear stale intent options.');
$assert(!str_contains($view, 'foreach ([\n                            \\Copot\\Core\\InstallerIntent::FRESH'), 'The full unqualified intent list remains visible before inspection.');
$assert(str_contains($bootstrap, 'No COPOT schema or tables were created.'), 'Pre-Review mutation boundary is not preserved.');

fwrite(STDOUT, "Batch 5 routing/eligibility assertions: {$assertions}\n");
