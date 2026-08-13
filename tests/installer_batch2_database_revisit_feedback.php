<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$bootstrap = file_get_contents($base . '/bootstrap/installer.php');
$view = file_get_contents($base . '/resources/views/installer/index.php');
$css = file_get_contents($base . '/public/installer-assets/css/installer.css');

if (!is_string($bootstrap) || !is_string($view) || !is_string($css)) {
    throw new RuntimeException('Database revisit sources could not be read.');
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(str_contains($bootstrap, '$databaseFeedback = null;'), 'Revisit feedback baseline is not an empty payload.');
$assert(str_contains($bootstrap, "['kind' => 'success', 'message' => \$message]"), 'Current Database success does not activate feedback.');
$assert(str_contains($bootstrap, "['kind' => 'error', 'message' => \$message]"), 'Current Database failure does not activate feedback.');
$assert(str_contains($bootstrap, '$databaseContextualState = $currentStep === \'database\'') && str_contains($bootstrap, '&& is_array($databaseFeedback);'), 'Global status suppression is not limited to a current Database feedback event.');
$assert(str_contains($view, '$activeDatabaseFeedback === null ? \'hidden\' : \'\''), 'Rehydrated staged inspection is not hidden from the user.');
$assert(str_contains($view, '$activeDatabaseFeedback === null ? \'\' : htmlspecialchars($databaseFeedbackLabel'), 'Quiet revisit feedback contains a pre-rendered label.');
$assert(str_contains($view, '$activeDatabaseFeedback === null ? \'\' : htmlspecialchars((string) ($activeDatabaseFeedback[\'message\'] ?? \'\')'), 'Quiet revisit feedback contains stale result text.');
$assert(str_contains($css, '#database_feedback[hidden]') && str_contains($css, 'display: none;'), 'Hidden Database feedback still reserves layout height.');
$assert(str_contains($view, 'is_array($databaseResult ?? null)'), 'Staged Database result remains available for compatibility advisories.');
$assert(str_contains($view, 'Database fields changed. Test the connection again.'), 'Field-change feedback remains available after revisit.');
$assert(str_contains($view, "let tested = <?= !empty(\$databaseStaged) ? 'true' : 'false' ?>;"), 'Staged Database revisit does not arm field-change feedback.');
$assert(str_contains($view, "button.textContent = 'Test Database'"), 'Database Test reset behavior is not preserved.');
$assert(str_contains($bootstrap, 'No COPOT schema or tables were created.'), 'Pre-install mutation invariant is not preserved.');

fwrite(STDOUT, "Database revisit feedback assertions: {$assertions}\n");
