<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\DatabaseTableNames;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\ModuleMigrationContext;
use Copot\Core\ModuleMigrationDescriptor;
use Copot\Core\ModuleMigrationDeclaration;
use Copot\Core\ModuleMigrationLedger;
use Copot\Core\ModuleMigrationReconciler;
use Copot\Core\ModuleMigrationRegistry;
use Copot\Core\ModuleSchemaHealthVerifier;
use Copot\Core\ModuleIdentity;
use Copot\Core\ModuleDefinition;
use Copot\Core\ModuleRepository;
use Copot\Core\PackageCompatibility;
use Copot\Core\ModuleProvisioningContext;
use Copot\Core\ModuleProvisioningDeclaration;
use Copot\Core\ModuleProvisioningReconciler;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$host = (string) Copot\Core\Env::get('DB_HOST', '127.0.0.1');
$port = (int) Copot\Core\Env::get('DB_PORT', '3306');
$username = (string) Copot\Core\Env::get('DB_USERNAME', 'root');
$password = (string) Copot\Core\Env::get('DB_PASSWORD', '');
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$databaseName = 'copot_wu3_' . bin2hex(random_bytes(6));
$server->exec('CREATE DATABASE `' . $databaseName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

$configFor = static function (string $namespace) use ($basePath, $host, $port, $databaseName, $username, $password): Config {
    $config = new Config($basePath . '/config');
    $reflection = new ReflectionClass($config);
    $property = $reflection->getProperty('items');
    $property->setAccessible(true);
    $items = $property->getValue($config);
    $items['database']['connections']['mysql']['host'] = $host;
    $items['database']['connections']['mysql']['port'] = $port;
    $items['database']['connections']['mysql']['database'] = $databaseName;
    $items['database']['connections']['mysql']['username'] = $username;
    $items['database']['connections']['mysql']['password'] = $password;
    $items['database']['connections']['mysql']['namespace'] = $namespace;
    $property->setValue($config, $items);
    return $config;
};

$alpha = new DatabaseTableNames('alpha');
$beta = new DatabaseTableNames('beta');
$empty = new DatabaseTableNames();
$assert($empty->moduleTable('modules') === 'modules', 'Empty namespace changed Module compatibility.');
$assert($alpha->moduleTable('modules') === 'alpha_modules', 'Module table namespace is not deterministic.');
$assert($alpha->moduleTable('content') === 'alpha_content', 'Content table namespace is not deterministic.');
$assert($alpha->moduleTable('module_permissions') === 'alpha_module_permissions', 'Module permission table namespace is not deterministic.');
$assert($alpha->table('users') === 'alpha_users', 'Core ownership did not remain governed by the WU2 boundary.');
$assert($alpha->moduleTable('sample_custom_table') === 'alpha_sample_custom_table', 'Custom Module-owned table namespace is not deterministic.');
$assert($alpha->namespaceStatement("INSERT INTO modules (name) VALUES ('modules.manage')") === "INSERT INTO alpha_modules (name) VALUES ('modules.manage')", 'SQL namespace rewriting altered a literal value.');

$provisioningContext = null;
$provisioning = new ModuleProvisioningReconciler(
    static function (ModuleIdentity $owner, ModuleProvisioningDeclaration $declaration, bool $baseline, ?ModuleProvisioningContext $context) use (&$provisioningContext): void {
        $provisioningContext = $context;
    },
    null,
    $alpha
);
$provisioning->reconcile(new ModuleIdentity('sample'), new ModuleProvisioningDeclaration('module-schema-1'));
$assert($provisioningContext instanceof ModuleProvisioningContext && $provisioningContext->table('content') === 'alpha_content', 'Module provisioning did not receive namespace context.');

$schemaPath = $basePath . '/database/schema.sql';
(new InstallerSchemaRunner($schemaPath))->install([
    'host' => $host, 'port' => $port, 'database' => $databaseName,
    'username' => $username, 'password' => $password, 'namespace' => 'alpha',
]);
(new InstallerSchemaRunner($schemaPath))->install([
    'host' => $host, 'port' => $port, 'database' => $databaseName,
    'username' => $username, 'password' => $password, 'namespace' => 'beta',
]);

$alphaDb = new Database($configFor('alpha'));
$betaDb = new Database($configFor('beta'));
$alphaModules = $alphaDb->tables()->moduleTable('modules');
$betaModules = $betaDb->tables()->moduleTable('modules');
$assert($alphaModules !== $betaModules, 'Independent Module namespaces collided.');
$alphaRepository = new ModuleRepository($alphaDb);
$betaRepository = new ModuleRepository($betaDb);
$definition = new ModuleDefinition('provisioned', 'Provisioned', '1.0.0', 'modules/provisioned');
$alphaRepository->create($definition);
$betaRepository->create($definition);
$assert((int) $alphaDb->connection()->query("SELECT COUNT(*) FROM {$alphaModules}")->fetchColumn() === 1, 'Alpha Module provisioning did not create its namespaced object.');
$assert((int) $betaDb->connection()->query("SELECT COUNT(*) FROM {$betaModules}")->fetchColumn() === 1, 'Beta Module provisioning did not create its namespaced object.');

$module = new ModuleIdentity('migrated');
$storage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu3-' . bin2hex(random_bytes(6));
mkdir($storage, 0700, true);
$alphaLedger = new ModuleMigrationLedger($storage, $alpha);
$betaLedger = new ModuleMigrationLedger($storage, $beta);
$alphaLedger->initializeBaseline($module, 'module-schema-1');
$betaLedger->initializeBaseline($module, 'module-schema-1');
$executed = [];
$migration = new ModuleMigrationDescriptor(
    'sample-001', 1, new PackageCompatibility('1.0.0'), '1.0.0', 'module-schema-2',
    ModuleMigrationDescriptor::TRANSACTIONAL, 'wu3-module-migration',
    static function (PDO $connection, ModuleMigrationContext $context) use (&$executed): void {
        $executed[] = $context->table('modules');
        $connection->exec('INSERT INTO ' . $context->table('modules') . ' (name,title,version,path,status,installed_at,created_at,updated_at) VALUES (\'migrated\',\'Migrated\',\'1.0.0\',\'modules/migrated\',\'disabled\',NOW(),NOW(),NOW())');
    }
);
$registry = new ModuleMigrationRegistry($module, new ModuleMigrationDeclaration($module, true, 'sample-migrations', [$migration]));
$alphaState = $alphaLedger->stateIdentity($module);
$betaState = $betaLedger->stateIdentity($module);
$alphaResult = (new ModuleMigrationReconciler($alphaLedger, $alpha))->reconcile($alphaDb->connection(), $module, '1.0.0', $registry, $alphaState);
$betaResult = (new ModuleMigrationReconciler($betaLedger, $beta))->reconcile($betaDb->connection(), $module, '1.0.0', $registry, $betaState);
$assert($alphaResult->status() === \Copot\Core\ModuleMigrationReconciliationResult::COMPLETED && $betaResult->status() === \Copot\Core\ModuleMigrationReconciliationResult::COMPLETED, 'Namespaced Module migration did not complete in both installations.');
$assert($executed === ['alpha_modules', 'beta_modules'], 'Module migration targeted the wrong namespace.');
$assert((int) $alphaDb->connection()->query("SELECT COUNT(*) FROM {$alphaModules} WHERE name='migrated'")->fetchColumn() === 1, 'Alpha Module migration state was not isolated.');
$assert((int) $betaDb->connection()->query("SELECT COUNT(*) FROM {$betaModules} WHERE name='migrated'")->fetchColumn() === 1, 'Beta Module migration state was not isolated.');
$assert(count($alphaLedger->read($module)['records']) === 1 && count($betaLedger->read($module)['records']) === 1, 'Module migration history was not isolated by namespace.');

$health = new ModuleSchemaHealthVerifier();
$assert($health->verify($alphaDb->connection(), $alpha)->passed(), 'Alpha Module schema health did not resolve namespaced objects.');
$assert($health->verify($betaDb->connection(), $beta)->passed(), 'Beta Module schema health did not resolve namespaced objects.');

$server->exec('DROP DATABASE `' . $databaseName . '`');
@unlink($storage . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'module-migrations' . DIRECTORY_SEPARATOR . 'alpha' . DIRECTORY_SEPARATOR . 'sample.json');
@unlink($storage . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'module-migrations' . DIRECTORY_SEPARATOR . 'beta' . DIRECTORY_SEPARATOR . 'sample.json');
@rmdir($storage . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'module-migrations' . DIRECTORY_SEPARATOR . 'alpha');
@rmdir($storage . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'module-migrations' . DIRECTORY_SEPARATOR . 'beta');
@rmdir($storage . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'module-migrations');
@rmdir($storage . DIRECTORY_SEPARATOR . '.copot-lifecycle');
@rmdir($storage);

echo "WU3 Module namespace focused tests passed ({$assertions} assertions).\n";
