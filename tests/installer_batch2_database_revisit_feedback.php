<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$bootstrap = file_get_contents($base . '/bootstrap/installer.php');
$view = file_get_contents($base . '/resources/views/installer/index.php');

if (!is_string($bootstrap) || !is_string($view)) {
    throw new RuntimeException('Database revisit sources could not be read.');
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(str_contains($bootstrap, '$databaseFeedbackActive = false;'), 'Revisit feedback baseline is not inactive.');
$assert(str_contains($bootstrap, '$databaseFeedbackActive = true;'), 'Current Database operation does not enable contextual feedback.');
$assert(str_contains($bootstrap, '$databaseContextualState = $currentStep === \'database\'') && str_contains($bootstrap, '&& ($databaseFeedbackActive || $errors !== []);'), 'Global status suppression is not limited to current-operation feedback or errors.');
$assert(str_contains($view, 'empty($databaseFeedbackActive) && empty($errors) ? \'hidden\''), 'Rehydrated staged inspection is not hidden from the user.');
$assert(str_contains($view, 'is_array($databaseResult ?? null)'), 'Staged Database result remains available for compatibility advisories.');
$assert(str_contains($view, 'Database fields changed. Test the connection again.'), 'Field-change feedback remains available after revisit.');
$assert(str_contains($view, "button.textContent = 'Test Database'"), 'Database Test reset behavior is not preserved.');
$assert(str_contains($bootstrap, 'No COPOT schema or tables were created.'), 'Pre-install mutation invariant is not preserved.');

fwrite(STDOUT, "Database revisit feedback assertions: {$assertions}\n");
