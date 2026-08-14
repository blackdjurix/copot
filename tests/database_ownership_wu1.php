<?php

declare(strict_types=1);

use Copot\Core\DatabaseTableExtensionGrant;
use Copot\Core\DatabaseTableNames;
use Copot\Core\DatabaseTableOwner;
use Copot\Core\DatabaseTableOwnership;
use Copot\Core\DatabaseTableOwnershipCatalog;
use Copot\Core\ModuleIdentity;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$throws = static function (callable $callback, string $message) use ($assert): void {
    try { $callback(); } catch (InvalidArgumentException) { return; }
    $assert(false, $message);
};

$catalog = DatabaseTableOwnershipCatalog::current();
$assert(count($catalog->all()) === 28, 'Locked ownership catalog does not contain every table surface exactly once.');
$assert(count(array_unique(array_map(static fn (DatabaseTableOwnership $entry): string => $entry->logicalName(), $catalog->all()))) === 28, 'Ownership catalog contains duplicate logical identities.');

foreach (['users', 'roles', 'settings', 'themes', 'modules', 'module_permissions', 'core_migration_history', 'core_schema_generation'] as $table) {
    $assert($catalog->owner($table)->isWebcore(), $table . ' is not Webcore-owned.');
}
foreach (['navigation_menus' => 'navigation', 'content' => 'content', 'taxonomy_terms' => 'taxonomy', 'media' => 'media', 'redirects' => 'redirects', 'forms' => 'form-manager'] as $table => $module) {
    $owner = $catalog->owner($table);
    $assert($owner->isModule() && $owner->moduleIdentity()?->value() === $module, $table . ' has the wrong Module owner.');
    $assert($catalog->ownership($table)->isHistoricallyPreProvisioned(), $table . ' lost historical pre-provisioning classification.');
}
$assert(!$catalog->ownership('users')->isHistoricallyPreProvisioned(), 'Webcore table was incorrectly classified as historically Module-provisioned.');
$assert(count($catalog->extensions()) === 2, 'Current catalog did not register the evidenced Media-to-Content extensions.');
$columnGrant = $catalog->extension('media', 'content', DatabaseTableExtensionGrant::ADD_COLUMN, 'featured_media_id');
$indexGrant = $catalog->extension('media', 'content', DatabaseTableExtensionGrant::ADD_INDEX, 'idx_content_featured_media');
$assert($catalog->owner('content')->moduleIdentity()?->value() === 'content', 'Content ownership changed during extension registration.');
$assert($columnGrant->module()->value() === 'media' && $columnGrant->targetOwner()->moduleIdentity()?->value() === 'content', 'Cross-Module extension owner provenance is incomplete.');
$assert($columnGrant->migrationIdentity() === 'database/upgrades/m3_8_media_library.sql' && $columnGrant->lifecycleOperation() === 'm3.8-wu7-pre-m3.8-upgrade', 'Media-to-Content migration provenance is incorrect.');
$assert($indexGrant->kind() === DatabaseTableExtensionGrant::ADD_INDEX && $indexGrant->element() === 'idx_content_featured_media', 'Media-to-Content index provenance is incorrect.');

$alpha = new DatabaseTableNames('alpha');
$empty = new DatabaseTableNames();
$assert($catalog->physicalName('users', $alpha) === 'alpha_users', 'Webcore namespace resolution changed.');
$assert($catalog->physicalName('modules', $alpha) === 'alpha_modules', 'Webcore-owned Module registry did not preserve the established physical boundary.');
$assert($catalog->physicalName('content', $alpha) === 'alpha_content', 'Module namespace resolution changed.');
$assert($catalog->physicalName('content', $empty) === 'content', 'Empty namespace compatibility changed.');

$throws(static fn () => new DatabaseTableOwnershipCatalog([
    new DatabaseTableOwnership('users', DatabaseTableOwner::module('content'), 'database/schema.sql'),
]), 'Unauthorized cross-owner table claim was accepted.');
$throws(static fn () => new DatabaseTableOwnershipCatalog([
    new DatabaseTableOwnership('users', DatabaseTableOwner::webcore(), 'database/schema.sql'),
    new DatabaseTableOwnership('users', DatabaseTableOwner::webcore(), 'database/schema.sql'),
]), 'Shared/duplicate ownership was accepted.');
$throws(static fn () => new DatabaseTableOwnershipCatalog(
    DatabaseTableOwnershipCatalog::current()->all(),
    [new DatabaseTableExtensionGrant('media', 'content', DatabaseTableOwner::webcore(), DatabaseTableExtensionGrant::ADD_COLUMN, 'media_id', 'media.migration.1', 'op-1')]
), 'Unauthorized cross-owner extension was accepted.');
$throws(static fn () => new DatabaseTableExtensionGrant('media', 'content', DatabaseTableOwner::module('content'), 'drop_column', 'legacy', 'media.migration.1', 'op-1'), 'Unsupported extension kind was accepted.');
$throws(static fn () => new DatabaseTableExtensionGrant('media', 'content', DatabaseTableOwner::module('content'), DatabaseTableExtensionGrant::ADD_COLUMN, 'media_id', '', 'op-1'), 'Extension without migration provenance was accepted.');

$grant = new DatabaseTableExtensionGrant(new ModuleIdentity('media'), 'users', DatabaseTableOwner::webcore(), DatabaseTableExtensionGrant::ADD_INDEX, 'idx_media_user', 'media.migration.2', 'operation-2');
$withGrant = new DatabaseTableOwnershipCatalog(DatabaseTableOwnershipCatalog::current()->all(), [$grant]);
$assert($withGrant->extension('media', 'users', DatabaseTableExtensionGrant::ADD_INDEX, 'idx_media_user')->lifecycleOperation() === 'operation-2', 'Extension provenance lookup failed.');
$assert($withGrant->ownership('users')->owner()->isWebcore(), 'Extension provenance changed table ownership.');
$throws(static fn () => $withGrant->extension('media', 'users', DatabaseTableExtensionGrant::ADD_COLUMN, 'idx_media_user'), 'Unauthorized extension lookup did not fail closed.');
$throws(static fn () => new DatabaseTableOwnershipCatalog(DatabaseTableOwnershipCatalog::current()->all(), [
    new DatabaseTableExtensionGrant('media', 'users', DatabaseTableOwner::module('content'), DatabaseTableExtensionGrant::ADD_INDEX, 'idx_wrong_owner', 'media.migration.3', 'operation-3'),
]), 'Invalid target-owner identity was accepted.');
$throws(static fn () => new DatabaseTableOwnershipCatalog(DatabaseTableOwnershipCatalog::current()->all(), [
    new DatabaseTableExtensionGrant('content', 'content', DatabaseTableOwner::module('content'), DatabaseTableExtensionGrant::ADD_COLUMN, 'self_extension', 'content.migration.1', 'operation-4'),
]), 'Same-owner extension was accepted as cross-owner provenance.');

echo "WU1 database ownership tests passed ({$assertions} assertions)." . PHP_EOL;
