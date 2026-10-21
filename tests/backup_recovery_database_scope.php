<?php

use Copot\Core\BackupRecovery\DatabaseRecoveryException;
use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\DatabaseTableOwnershipCatalog;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) { throw new RuntimeException($message); }
};
$throws = static function (callable $callback): void {
    try { $callback(); } catch (DatabaseRecoveryException) { return; }
    throw new RuntimeException('Expected DatabaseScope rejection.');
};

$catalog = DatabaseTableOwnershipCatalog::current();
$empty = DatabaseScope::fromOwnershipCatalog('installation-empty', 'copot_empty', '', $catalog);
$prefixed = DatabaseScope::fromOwnershipCatalog('installation-prefixed', 'copot_shared', 'alpha', $catalog);
$module = DatabaseScope::fromOwnershipCatalog('installation-module', 'copot_shared', 'alpha', $catalog, ['navigation_menu_assignments']);

$assert(count($empty->physicalTables()) === 19, 'Empty namespace did not use the historical 19-table Webcore baseline.');
$assert(!in_array('core_schema_generation', $empty->physicalTables(), true), 'Historical empty namespace unexpectedly required schema-generation materialization.');
$generation = DatabaseScope::fromOwnershipCatalog('installation-generation', 'copot_shared', 'alpha', $catalog, [], ['namespace' => 'alpha', 'schema_generation' => 'core-schema-generation:fixture']);
$assert(count($generation->physicalTables()) === 20 && in_array('alpha_core_schema_generation', $generation->physicalTables(), true), 'Verified generation profile did not include core_schema_generation.');
$assert($empty->namespaceIdentity() === '', 'Empty namespace identity was not preserved.');
$assert($empty->identity() !== $prefixed->identity(), 'Empty and prefixed scope identities collided.');
$assert(count($module->physicalTables()) === 20 && in_array('alpha_navigation_menu_assignments', $module->physicalTables(), true), 'Verified Module materialization was not added to the scope.');
$empty->assertPhysicalTables($empty->physicalTables());
$assert(true, 'Canonical physical table assertion completed.');
$throws(static fn () => $generation->assertPhysicalTables(array_values(array_diff($generation->physicalTables(), ['alpha_core_schema_generation']))));
$throws(static fn () => $empty->assertPhysicalTables(array_merge($empty->physicalTables(), ['foreign_table'])));
$throws(static fn () => DatabaseScope::fromOwnershipCatalog('installation-invalid', 'copot_empty', '', $catalog, ['users']));
$throws(static fn () => DatabaseScope::fromOwnershipCatalog('installation-invalid', 'copot_empty', '', $catalog, ['not_a_module_table']));
$throws(static fn () => DatabaseScope::fromOwnershipCatalog('installation-invalid', 'copot_empty', 'alpha', $catalog, [], ['namespace' => 'wrong', 'schema_generation' => 'core-schema-generation:fixture']));

echo "DatabaseScope correction tests passed ({$assertions} assertions)." . PHP_EOL;
