<?php

declare(strict_types=1);

use Copot\Core\AuthorizedMigrationContext;
use Copot\Core\CanonicalSchemaBaselineVerifier;
use Copot\Core\CommittedLifecycleState;
use Copot\Core\CoreMigrationLedger;
use Copot\Core\CoreMigrationHealthVerifier;
use Copot\Core\CoreMigrationPlanner;
use Copot\Core\CoreMigrationRegistry;
use Copot\Core\CoreMigrationRunner;
use Copot\Core\CoreMigrationStateIdentity;
use Copot\Core\Database;
use Copot\Core\DatabaseHealthVerifier;
use Copot\Core\DatabaseTableNames;
use Copot\Core\DatabaseTableOwner;
use Copot\Core\DatabaseTableOwnershipCatalog;
use Copot\Core\Env;
use Copot\Core\InstalledStateInspection;
use Copot\Core\InstallationIdentity;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\InstallerSchemaState;
use Copot\Core\MigrationAuthorizationContext;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageContract;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\PackageTargetRequirement;
use Copot\Core\PackageTargetRequirements;
use Copot\Core\TargetCompatibilityCandidate;
use Copot\Core\TargetCompatibilityEvaluator;
use Copot\Core\TargetRequirementObservation;
use Copot\Core\Version;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', 3306);
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_wu2_s2_' . bin2hex(random_bytes(6));
$namespace = '';
$isolatedNamespace = 'wu2s2_' . bin2hex(random_bytes(3));
$quotedDatabase = '`' . $databaseName . '`';
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

try {
    $server->exec('CREATE DATABASE ' . $quotedDatabase . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $configuration = ['host' => $host, 'port' => $port, 'database' => $databaseName, 'username' => $username, 'password' => $password, 'namespace' => $namespace];
    $installed = (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install($configuration);
    $_ENV['DB_DATABASE'] = $databaseName;
    $_ENV['DB_NAMESPACE'] = $namespace;
    putenv('DB_DATABASE=' . $databaseName);
    putenv('DB_NAMESPACE=' . $namespace);
    $tables = new DatabaseTableNames($namespace);
    $isolatedTables = new DatabaseTableNames($isolatedNamespace);
    $connection = new PDO("mysql:host={$host};port={$port};dbname={$databaseName};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

    foreach (['security_login_attempts', 'security_sessions', 'security_events'] as $logical) {
        $assert((bool) $connection->query("SHOW TABLES LIKE '" . $tables->table($logical) . "'")->fetchColumn(), $logical . ' was not present on fresh install.');
        $assert($isolatedTables->table($logical) === $isolatedNamespace . '_' . $logical, $logical . ' did not receive the isolated physical name.');
        $assert(DatabaseTableOwnershipCatalog::current()->owner($logical)->isWebcore(), $logical . ' is not Webcore-owned.');
    }
    $assert($installed > 0, 'Canonical schema installation did not execute.');
    $assert((new InstallerSchemaState(new Database(new Copot\Core\Config($basePath . '/config'))))->isReady(), 'Installer schema readiness did not recognize the expanded schema.');

    $baseline = (new CanonicalSchemaBaselineVerifier($tables))->verify($connection, $basePath . '/database/schema.sql');
    $assert($baseline->passed(), 'Fresh canonical schema baseline verification failed: ' . $baseline->failureReason());

    foreach (['taxonomy_types', 'taxonomy_terms', 'taxonomy_assignments'] as $logical) {
        $connection->exec('CREATE TABLE ' . $tables->moduleTable($logical) . ' (id INT NULL)');
    }
    $health = (new DatabaseHealthVerifier($tables))->verify($connection);
    $assert($health->passed(), 'Database health did not recognize the expanded schema: ' . $health->failureReason());
    foreach (['taxonomy_types', 'taxonomy_terms', 'taxonomy_assignments'] as $logical) {
        $connection->exec('DROP TABLE ' . $tables->moduleTable($logical));
    }

    $registry = CoreMigrationRegistry::forProject($basePath);
    $descriptor = $registry->migrations()[0];
    $assert($registry->identity() === CoreMigrationRegistry::IDENTITY && $descriptor->id() === 'core.security.persistence' && $descriptor->sequence() === 10, 'S2 Core migration identity or sequence is not deterministic.');
    $schemaIdentity = (new CanonicalSchemaBaselineVerifier())->identity($basePath . '/database/schema.sql');
    $requirement = new PackageTargetRequirement(PackageTargetRequirement::SCHEMA, 'webcore', 'core-schema', PackageTargetRequirement::EXACT_IDENTITY, $schemaIdentity);
    $target = new PackageTargetRequirements([$requirement]);
    $package = new PackageContract(PackageContract::WEBCORE_PACKAGE_TYPE, PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION, Version::CURRENT, 'wu2-s2-test', null, new PackageCompatibility(Version::CURRENT), new PackageRuntimeCompatibility('8.2.0', ['mysql' => '8.0.0'], ['json', 'pdo', 'pdo_mysql']), [new PackageInventoryEntry('database/schema.sql', 1, str_repeat('a', 64))], new PackageMigrationDeclaration(true, CoreMigrationRegistry::IDENTITY), $target);

    foreach (['security_events', 'security_sessions', 'security_login_attempts'] as $logical) {
        $connection->exec('DROP TABLE ' . $tables->table($logical));
    }
    $ledger = new CoreMigrationLedger($tables);
    $oldSchemaIdentity = 'canonical-schema:pre-s2';
    $snapshot = (new CommittedLifecycleState(Version::CURRENT, 'wu2-s2-test', null, PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION, $oldSchemaIdentity, CoreMigrationStateIdentity::fromRecords([]), new DateTimeImmutable('now')))->snapshot();
    $plan = (new CoreMigrationPlanner())->plan(InstalledStateInspection::committed($snapshot), $package, $registry, $ledger, $connection);
    $planRepeat = (new CoreMigrationPlanner())->plan(InstalledStateInspection::committed($snapshot), $package, $registry, $ledger, $connection);
    $assert($plan->isAccepted() && count($plan->migrations()) === 1 && $plan->migrations()[0]->id() === $descriptor->id(), 'Existing-install migration planning did not select the S2 migration.');
    $assert($plan->virtualFinalSchemaIdentity() === $schemaIdentity && $planRepeat->migrations()[0]->checksum() === $plan->migrations()[0]->checksum(), 'Existing-install migration planning was not deterministic.');

    $authorization = static fn ($migration): AuthorizedMigrationContext => new AuthorizedMigrationContext(
        $connection,
        new MigrationAuthorizationContext(InstallationIdentity::generate(), $tables, 'wu2-s2-test-operation', 'upgrade', DatabaseTableOwner::webcore(), $migration->id(), $migration->checksum(), Version::CURRENT, Version::CURRENT, true, $migration->schemaSurface()),
        DatabaseTableOwnershipCatalog::current()
    );
    $run = (new CoreMigrationRunner($ledger))->run($connection, $plan, null, $authorization);
    $assert($run->status() === 'completed' && $run->appliedMigrationIds() === ['core.security.persistence'], 'Existing-install migration execution did not complete coherently: ' . $run->status() . ' ' . $run->reason());
    $assert(count($ledger->records($connection)) === 1, 'S2 migration ledger record was not persisted.');
    $migrationHealth = (new CoreMigrationHealthVerifier($ledger))->verify($connection, $registry);
    $assert($migrationHealth->passed(), 'Core migration health did not accept the S2 ledger record: ' . $migrationHealth->failureReason());
    foreach (['security_login_attempts', 'security_sessions', 'security_events'] as $logical) {
        $assert((bool) $connection->query("SHOW TABLES LIKE '" . $tables->table($logical) . "'")->fetchColumn(), $logical . ' was not provisioned by migration.');
    }
    $migratedBaseline = (new CanonicalSchemaBaselineVerifier($tables))->verify($connection, $basePath . '/database/schema.sql', false);
    $assert($migratedBaseline->passed(), 'Migrated schema does not match the canonical S2 schema: ' . $migratedBaseline->failureReason());
    foreach (['taxonomy_types', 'taxonomy_terms', 'taxonomy_assignments'] as $logical) {
        $connection->exec('CREATE TABLE ' . $tables->moduleTable($logical) . ' (id INT NULL)');
    }
    $migratedHealth = (new DatabaseHealthVerifier($tables))->verify($connection);
    $assert($migratedHealth->passed(), 'Database health did not recognize the migrated expanded schema: ' . $migratedHealth->failureReason());

    $evaluator = new TargetCompatibilityEvaluator();
    $satisfied = $evaluator->evaluate($target, new TargetCompatibilityCandidate([TargetRequirementObservation::value($requirement, $schemaIdentity, 'canonical-baseline')]));
    $gap = $evaluator->evaluate($target, new TargetCompatibilityCandidate([TargetRequirementObservation::value($requirement, $oldSchemaIdentity, 'legacy-baseline')]));
    $assert($satisfied->isAdoptionReady() && !$gap->isAdoptionReady(), 'Target-relative adoption did not distinguish satisfied and migration-required schema states.');

    echo "WU2-S2 security persistence tests passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    try { $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase); } catch (Throwable) {}
}
