<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\CoreSchemaGenerationStore;
use Copot\Core\DatabaseTableNames;
use Copot\Core\DeploymentContext;
use Copot\Core\Env;
use Copot\Core\InstallationIdentity;
use Copot\Core\InstallationIdentityStore;
use Copot\Core\InstallationMutex;
use Copot\Core\InstallationRuntimePaths;
use Copot\Core\InstallerDatabaseOccupancy;
use Copot\Core\InstallerDatabaseOccupancyClassifier;
use Copot\Core\InstallerIntent;
use Copot\Core\InstallerRoutingPlanner;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\ModuleIdentity;
use Copot\Core\ModuleMigrationLedger;
use Copot\Core\PackageApplyTemporaryRoot;
use Copot\Core\RuntimeParticipant;
use Copot\Core\RuntimeRegistry;
use Copot\Core\RuntimeTransitionCoordinator;
use Copot\Core\Session;
use Copot\Core\BackupRecovery\RecoveryDomainIdentity;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryStoragePathPolicy;
use Copot\Core\BackupRecovery\RecoveryStorageRoot;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$blocked = static function (callable $operation) use ($assert): void {
    try { $operation(); $assert(false, 'Unsafe operation unexpectedly succeeded.'); }
    catch (Throwable) { $assert(true, 'Unsafe operation failed closed.'); }
};
$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path) || is_link($path)) { if (is_file($path)) @unlink($path); return; }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $child = $path . DIRECTORY_SEPARATOR . $entry;
        is_dir($child) && !is_link($child) ? $removeTree($child) : @unlink($child);
    }
    @rmdir($path);
};

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu6-' . bin2hex(random_bytes(6));
$storageA = $root . DIRECTORY_SEPARATOR . 'installation-a';
$storageB = $root . DIRECTORY_SEPARATOR . 'installation-b';
mkdir($storageA, 0700, true);
mkdir($storageB, 0700, true);
mkdir($storageA . DIRECTORY_SEPARATOR . 'public', 0700, true);
mkdir($storageB . DIRECTORY_SEPARATOR . 'public', 0700, true);

$identityA = (new InstallationIdentityStore($storageA))->getOrCreate();
$identityB = (new InstallationIdentityStore($storageB))->getOrCreate();
$assert($identityA->value() !== $identityB->value(), 'Independent installations shared installation identity.');

$tablesA = new DatabaseTableNames('wu6a');
$tablesB = new DatabaseTableNames('wu6b');
$ownedA = array_merge(
    array_map(fn (string $name): string => $tablesA->table($name), DatabaseTableNames::coreTables()),
    array_map(fn (string $name): string => $tablesA->moduleTable($name), DatabaseTableNames::moduleTables())
);
$ownedB = array_merge(
    array_map(fn (string $name): string => $tablesB->table($name), DatabaseTableNames::coreTables()),
    array_map(fn (string $name): string => $tablesB->moduleTable($name), DatabaseTableNames::moduleTables())
);
$assert(array_intersect($ownedA, $ownedB) === [], 'Independent COPOT ownership sets were not disjoint.');
$assert((new DatabaseTableNames())->table('users') === 'users', 'Legitimate empty namespace changed.');
$assert($tablesA->moduleTable('content') === 'wu6a_content', 'Module namespace did not follow the Core namespace boundary.');

$runtimeA = new RuntimeRegistry($storageA, $identityA, new InstallationMutex($storageA));
$runtimeB = new RuntimeRegistry($storageB, $identityB, new InstallationMutex($storageB));
$runtimeId = RuntimeRegistry::runtimeId();
$runtimeA->register($runtimeId, 'web', ['http', 'worker'], '0.13.0', 'package-a', 'modules-a', 'deploy-a');
$runtimeB->register($runtimeId, 'worker', ['worker'], '0.13.0', 'package-b', 'modules-b', 'deploy-b');
$assert(count($runtimeA->all()) === 1 && count($runtimeB->all()) === 1, 'Runtime Registry crossed installation ownership.');
$runtimeA->heartbeat($runtimeId);
$assert($runtimeA->all()[0]->state() === RuntimeParticipant::ACTIVE, 'Compatible runtime did not become active.');
$runtimeA->evaluateCompatibility($runtimeId, ['package_identity' => 'wrong-package']);
$assert($runtimeA->all()[0]->state() === RuntimeParticipant::INCOMPATIBLE, 'Runtime compatibility failure was not recorded.');
$blocked(fn () => (new RuntimeTransitionCoordinator(new InstallationMutex($storageA), $runtimeA))->execute(static fn (): bool => true));

$config = new Config($basePath . '/config');
$assert((new Session($config, $identityA))->cookieName() !== (new Session($config, $identityB))->cookieName(), 'Installation session cookies collided.');
$pathsA = InstallationRuntimePaths::forInstallation($identityA->value(), $root . DIRECTORY_SEPARATOR . 'runtime');
$pathsB = InstallationRuntimePaths::forInstallation($identityB->value(), $root . DIRECTORY_SEPARATOR . 'runtime');
$assert($pathsA->root() !== $pathsB->root() && $pathsA->packageStaging() !== $pathsB->packageStaging(), 'Runtime filesystem paths collided.');
$assert(PackageApplyTemporaryRoot::forProject($basePath, $identityA->value()) !== PackageApplyTemporaryRoot::forProject($basePath, $identityB->value()), 'Package Lifecycle temporary roots collided.');

$moduleA = new ModuleMigrationLedger($storageA, $tablesA);
$moduleB = new ModuleMigrationLedger($storageB, $tablesB);
$module = new ModuleIdentity('example');
$moduleA->initializeBaseline($module, 'baseline-a');
$moduleB->initializeBaseline($module, 'baseline-b');
$assert($moduleA->stateIdentity($module) !== $moduleB->stateIdentity($module), 'Module migration state crossed installations.');

$recoveryA = new RecoveryStoragePathPolicy(new RecoveryStorageRoot($basePath, $storageA . DIRECTORY_SEPARATOR . 'recovery', $identityA->value()));
$recoveryB = new RecoveryStoragePathPolicy(new RecoveryStorageRoot($basePath, $storageB . DIRECTORY_SEPARATOR . 'recovery', $identityB->value()));
$recovery = new RecoveryIdentity('wu6-' . bin2hex(random_bytes(4)));
$assert($recoveryA->recoverySetRoot($recovery) !== $recoveryB->recoverySetRoot($recovery), 'Backup/Recovery roots crossed installations.');
$domainA = new RecoveryDomainIdentity('filesystem', 'installation-a', $identityA->value(), hash('sha256', 'a'));
$domainB = new RecoveryDomainIdentity('filesystem', 'installation-b', $identityB->value(), hash('sha256', 'b'));
$assert($domainA->identity() !== $domainB->identity(), 'Backup/Recovery domain identity was not installation-scoped.');

$deploymentA = DeploymentContext::forApplicationRoot($storageA, '/copot-a/');
$deploymentB = DeploymentContext::forApplicationRoot($storageB, '/copot-b/');
$assert($deploymentA->appRoot() !== $deploymentB->appRoot() && $deploymentA->basePath() !== $deploymentB->basePath(), 'Portability deployment contexts were not isolated.');

$classifier = new InstallerDatabaseOccupancyClassifier();
$empty = $classifier->classify([]);
$assert($empty->classification() === InstallerDatabaseOccupancy::EMPTY, 'Empty occupancy was not preserved.');
$planner = new InstallerRoutingPlanner();
$assert($planner->plan($empty, InstallerIntent::FRESH)->namespace() === '', 'Fresh empty install did not preserve empty namespace.');
$blocked(fn () => $planner->plan($empty, InstallerIntent::COEXIST, ''));

$databaseName = 'copot_wu6_' . bin2hex(random_bytes(5));
$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', 3306);
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$quotedDatabase = '`' . str_replace('`', '``', $databaseName) . '`';
$server->exec("CREATE DATABASE {$quotedDatabase} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    $runner = new InstallerSchemaRunner($basePath . '/database/schema.sql');
    $connection = ['host' => $host, 'port' => $port, 'database' => $databaseName, 'username' => $username, 'password' => $password, 'namespace' => 'wu6a'];
    $runner->install($connection);
    $connection['namespace'] = 'wu6b';
    $runner->install($connection);
    $database = new PDO("mysql:host={$host};port={$port};dbname={$databaseName};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    (new CoreSchemaGenerationStore($tablesA))->record($database, 'core-schema-generation:wu6', 'compatible', 'wu6a');
    (new CoreSchemaGenerationStore($tablesB))->record($database, 'core-schema-generation:wu6', 'compatible', 'wu6b');
    $physical = array_map('strval', $database->query('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchAll(PDO::FETCH_COLUMN));
    $assert(count(array_intersect($ownedA, $physical)) === count($ownedA), 'DB-backed installation A did not materialize its complete owned set (' . count(array_intersect($ownedA, $physical)) . '/' . count($ownedA) . ').');
    $assert(count(array_intersect($ownedB, $physical)) === count($ownedB), 'DB-backed installation B did not materialize its complete owned set (' . count(array_intersect($ownedB, $physical)) . '/' . count($ownedB) . ').');
    $assert(count(array_intersect($ownedA, $ownedB)) === 0, 'DB-backed ownership sets overlapped.');
} finally {
    $server->exec("DROP DATABASE {$quotedDatabase}");
}

$removeTree($root);
echo "WU6 cross-subsystem acceptance passed ({$assertions} assertions)." . PHP_EOL;
