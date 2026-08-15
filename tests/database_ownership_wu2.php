<?php
declare(strict_types=1);

use Copot\Core\DatabaseTableExtensionGrant;
use Copot\Core\DatabaseTableOwnershipCatalog;
use Copot\Core\DatabaseTableOwner;
use Copot\Core\DatabaseTableNames;
use Copot\Core\InstallationIdentity;
use Copot\Core\MigrationAuthorizationContext;
use Copot\Core\MigrationCompatibilityResult;
use Copot\Core\MigrationSchemaSurface;
use Copot\Core\ModuleMigrationDescriptor;
use Copot\Core\PackageCompatibility;

$base = dirname(__DIR__); require $base . '/bootstrap/autoload.php';
$assertions = 0; $assert = static function (bool $ok, string $message) use (&$assertions): void { ++$assertions; if (!$ok) throw new RuntimeException($message); };
$throws = static function (callable $call, string $message) use (&$assertions): void { ++$assertions; try { $call(); } catch (Throwable) { return; } throw new RuntimeException($message); };

$catalog = DatabaseTableOwnershipCatalog::current(); $tables = new DatabaseTableNames('wu2');
$grantColumn = $catalog->extension('media', 'content', DatabaseTableExtensionGrant::ADD_COLUMN, 'featured_media_id');
$grantIndex = $catalog->extension('media', 'content', DatabaseTableExtensionGrant::ADD_INDEX, 'idx_content_featured_media');
$surface = new MigrationSchemaSurface(['content']);
$auth = new MigrationAuthorizationContext(InstallationIdentity::generate(), $tables, 'wu2-operation', 'upgrade', DatabaseTableOwner::module('media'), str_repeat('a', 64), str_repeat('b', 64), '0.1.0', '0.2.0', true, $surface, [$grantColumn, $grantIndex]);
$auth->authorizeExtension($catalog, 'content', DatabaseTableExtensionGrant::ADD_COLUMN, 'featured_media_id');
$auth->authorizeExtension($catalog, 'content', DatabaseTableExtensionGrant::ADD_INDEX, 'idx_content_featured_media');
$assert($tables->resolve('content') === 'wu2_content', 'Namespace resolution changed.');
$throws(static fn () => $auth->authorizeExtension($catalog, 'content', DatabaseTableExtensionGrant::ADD_INDEX, 'not-granted'), 'Ungrant extension was accepted.');
$throws(static fn () => $auth->authorizeTable($catalog, 'users'), 'Cross-owner Webcore table was accepted.');
$throws(static fn () => $auth->authorizeTable($catalog, 'content'), 'Extension grant became generic table authority.');
$throws(static fn () => new MigrationSchemaSurface(['content', 'content']), 'Duplicate schema surface was accepted.');
$descriptor = new ModuleMigrationDescriptor('compat', 1, new PackageCompatibility('1.0.0', '2.0.0'), '2.0.0', 'schema-2');
$assert($descriptor->appliesTo('1.5.0'), 'Module source compatibility rejected a supported source.');
$assert(!$descriptor->appliesTo('2.0.0'), 'Module source compatibility accepted the exclusive upper bound.');
$assert(MigrationCompatibilityResult::downgrade()->code() === 'downgrade_unsupported', 'Downgrade classification is unstable.');
$unsupported = MigrationCompatibilityResult::unsupported("raw SQL\npath"); $assert(!str_contains($unsupported->reason(), "\n"), 'Unsupported-state reason was not sanitized.');
$assert(!method_exists('Copot\\Core\\ModuleMigrationContext', 'connection'), 'Module migration context exposes raw PDO.');
$assert(!method_exists('Copot\\Core\\AuthorizedMigrationContext', 'query'), 'Authorized context exposes unrestricted SQL reads.');
echo "WU2 database authority passed ({$assertions} assertions)." . PHP_EOL;
