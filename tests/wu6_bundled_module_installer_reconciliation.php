<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$finalizer = (string) file_get_contents($base . '/app/Core/InstallerFinalizer.php');
$schemaState = (string) file_get_contents($base . '/app/Core/InstallerSchemaState.php');
$ownership = (string) file_get_contents($base . '/app/Core/DatabaseTableOwnershipCatalog.php');
$view = (string) file_get_contents($base . '/resources/views/installer/index.php');
$contract = (string) file_get_contents($base . '/docs/40_post_m3_webcore_extension_architecture_reconciliation_contract.md');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(!str_contains($finalizer, 'BASELINE_MODULES'), 'InstallerFinalizer still declares mandatory baseline Modules.');
$assert(!str_contains($finalizer, 'activateDefaultTheme'), 'InstallerFinalizer still requires default Theme activation.');
$assert(!str_contains($finalizer, 'enableBaselineModules'), 'InstallerFinalizer still requires baseline Module enablement.');
$assert(str_contains($finalizer, "'theme' => null"), 'Fresh finalization does not explicitly report no active Theme.');
$assert(str_contains($finalizer, "'modules' => []"), 'Fresh finalization does not explicitly report zero installed optional Modules.');

foreach (['content', 'media', 'media_usages', 'navigation_menus', 'navigation_items', 'redirects'] as $table) {
    $assert(str_contains($schemaState, "'{$table}'"), "Webcore baseline table [{$table}] is not in installer readiness.");
}
foreach (['media_variants', 'navigation_menu_assignments', 'taxonomy_types', 'taxonomy_terms', 'taxonomy_assignments'] as $table) {
    $assert(!str_contains($schemaState, "'{$table}'"), "Module-owned extension table [{$table}] is still required by Webcore installer readiness.");
    $assert(str_contains($ownership, "'{$table}'"), "Module-owned extension table [{$table}] is missing from ownership authority.");
}

$assert(str_contains($view, 'Built-in Public View'), 'Installer review does not identify the no-Theme presentation.');
$assert(str_contains($view, 'Optional Modules and Themes'), 'Installer review does not identify optional extensions.');
$assert(str_contains($contract, 'WU6 technical validation: PASS'), 'Authoritative contract does not record WU6 validation.');
$assert(str_contains($contract, 'WU6 implementation: COMPLETE AND CLOSED'), 'Authoritative contract does not close WU6.');
$assert(str_contains($contract, 'WU7: NOT STARTED'), 'Authoritative contract does not keep WU7 not started.');
$assert(str_contains($contract, 'activate a default Theme or provision a baseline Module set'), 'Contract does not record zero-optional finalization.');

fwrite(STDOUT, "WU6 Bundled Module/Installer assertions: {$assertions}\n");
