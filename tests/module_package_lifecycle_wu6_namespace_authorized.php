<?php

declare(strict_types=1);

use Copot\Core\AuthorizedMigrationContext;
use Copot\Core\DatabaseTableNames;
use Copot\Core\DatabaseTableOwner;
use Copot\Core\DatabaseTableOwnershipCatalog;
use Copot\Core\InstallationIdentity;
use Copot\Core\MigrationAuthorizationContext;
use Copot\Core\MigrationSchemaSurface;
use Copot\Core\ModuleIdentity;
use Copot\Core\ModuleMigrationDeclaration;
use Copot\Core\ModuleMigrationDescriptor;
use Copot\Core\ModuleMigrationLedger;
use Copot\Core\ModuleMigrationReconciliationResult;
use Copot\Core\ModuleMigrationReconciler;
use Copot\Core\ModuleMigrationRegistry;
use Copot\Core\PackageCompatibility;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu6-module-' . bin2hex(random_bytes(5));
mkdir($root . DIRECTORY_SEPARATOR . 'alpha', 0700, true);
mkdir($root . DIRECTORY_SEPARATOR . 'beta', 0700, true);
$remove = static function (string $path) use (&$remove): void {
    if (is_file($path) || is_link($path)) { @unlink($path); return; }
    if (!is_dir($path)) { return; }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') { $remove($path . DIRECTORY_SEPARATOR . $entry); }
    }
    @rmdir($path);
};

try {
    $module = new ModuleIdentity('content');
    $migration = new ModuleMigrationDescriptor(
        'wu6.namespace.column',
        1,
        new PackageCompatibility('1.0.0'),
        '1.1.0',
        'module-schema-2',
        ModuleMigrationDescriptor::TRANSACTIONAL,
        'wu6-namespace-migration',
        static function (AuthorizedMigrationContext $context): void {
            $context->addColumn('content', 'wu6_marker', 'INTEGER');
        },
        null,
        null,
        false,
        new MigrationSchemaSurface(['content'])
    );
    $registry = new ModuleMigrationRegistry($module, new ModuleMigrationDeclaration($module, true, 'wu6-namespace-lineage', [$migration]));
    $executed = [];

    foreach (['alpha', 'beta'] as $namespace) {
        $tables = new DatabaseTableNames($namespace);
        $connection = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $connection->exec('CREATE TABLE ' . $tables->moduleTable('content') . ' (id INTEGER PRIMARY KEY)');
        $storage = $root . DIRECTORY_SEPARATOR . $namespace;
        $ledger = new ModuleMigrationLedger($storage, $tables);
        $ledger->initializeBaseline($module, 'module-schema-1');
        $before = $ledger->stateIdentity($module);
        $authorize = static function (ModuleMigrationDescriptor $descriptor) use ($connection, $module, $tables, $namespace): AuthorizedMigrationContext {
            $catalog = DatabaseTableOwnershipCatalog::current();
            $authorization = new MigrationAuthorizationContext(
                InstallationIdentity::generate(),
                $tables,
                'wu6-' . $namespace,
                'upgrade',
                DatabaseTableOwner::module($module),
                $descriptor->id(),
                $descriptor->checksum(),
                '1.0.0',
                $descriptor->targetPackageVersion(),
                true,
                $descriptor->schemaSurface(),
                $catalog->extensions()
            );
            return new AuthorizedMigrationContext($connection, $authorization, $catalog);
        };
        $result = (new ModuleMigrationReconciler($ledger, $tables))->reconcile(
            $connection,
            $module,
            '1.1.0',
            $registry,
            $before,
            null,
            $authorize,
            '1.0.0'
        );
        $assert($result->status() === ModuleMigrationReconciliationResult::COMPLETED, $namespace . ' authorized Module migration did not complete.');
        $assert((int) $connection->query("SELECT COUNT(*) FROM pragma_table_info('{$tables->moduleTable('content')}') WHERE name = 'wu6_marker'")->fetchColumn() === 1, $namespace . ' migration did not target its namespace.');
        $executed[] = $tables->moduleTable('content');
    }

    $assert($executed === ['alpha_content', 'beta_content'], 'Authorized Module migration crossed namespace lineage.');
    echo "WU6 authorized Module namespace acceptance passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $remove($root);
}
