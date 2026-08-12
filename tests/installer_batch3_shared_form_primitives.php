<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$view = file_get_contents($base . '/resources/views/installer/index.php');

if (!is_string($view)) {
    throw new RuntimeException('Batch 3 installer view could not be read.');
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

foreach (['database-fields', 'database-row', 'database-inline', 'database-control', 'database-help', 'database-namespace-row', 'administrator-fields', 'administrator-row', 'administrator-inline', 'administrator-control', 'administrator-help'] as $obsoleteClass) {
    $assert(!str_contains($view, $obsoleteClass), "Obsolete phase-specific form class remains: {$obsoleteClass}.");
}

$assert(str_contains($view, '--form-label-column: 150px;'), 'Shared form label column is not fixed at 150px.');
$assert(str_contains($view, 'grid-template-columns: var(--form-label-column) minmax(0, 1fr);'), 'Shared single-control row is not fluid.');
$assert(str_contains($view, 'grid-template-columns: var(--form-label-column) minmax(0, 1fr) max-content minmax(0, 1fr);'), 'Shared multi-control row does not reuse the common tracks.');
$assert(str_contains($view, 'class="form-control"'), 'Shared control wrapper is missing.');
$assert(str_contains($view, 'class="form-help"'), 'Shared helper primitive is missing.');
$assert(str_contains($view, 'class="field-error"'), 'Shared field-error primitive is missing.');
$assert(str_contains($view, '.form-help,') && str_contains($view, '.form-error {'), 'Helper and error geometry is not shared.');
$assert(!str_contains($view, 'margin-top: -8px;'), 'Field errors still use negative-margin compensation.');
$assert(str_contains($view, '.form-error {') && str_contains($view, 'margin: 0;'), 'Shared feedback placement does not use stable zero-margin geometry.');
$assert(str_contains($view, 'class="form-action-row"'), 'Database Namespace action variant is not shared.');
$assert(str_contains($view, 'class="form-action-row">') || str_contains($view, 'class="form-action-row"'), 'Shared action row is not present.');
$assert(str_contains($view, '.form-action-row > .database-action'), 'Database Test action is not aligned by the shared action-row variant.');
$assert(str_contains($view, '.form-inline-field,') && str_contains($view, '.form-action-row') && str_contains($view, 'grid-template-columns: 1fr;'), 'Shared mobile rows do not stack.');

fwrite(STDOUT, "Batch 3 shared form assertions: {$assertions}\n");
