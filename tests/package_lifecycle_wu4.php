<?php

declare(strict_types=1);

use Copot\Core\CoreMigrationDescriptor;
use Copot\Core\CoreMigrationLedger;
use Copot\Core\CoreMigrationPlan;
use Copot\Core\CoreMigrationPlanner;
use Copot\Core\CoreMigrationRegistry;
use Copot\Core\CoreMigrationRunner;
use Copot\Core\CoreMigrationStateIdentity;
use Copot\Core\InstalledStateInspection;
use Copot\Core\InstalledStateSnapshot;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\MigrationRunResult;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageContract;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageOwnership;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\AuthorizedMigrationContext;
use Copot\Core\DatabaseTableOwnershipCatalog;
use Copot\Core\DatabaseTableOwner;
use Copot\Core\DatabaseTableNames;
use Copot\Core\InstallationIdentity;
use Copot\Core\MigrationAuthorizationContext;
use Copot\Core\MigrationSchemaSurface;
use Copot\Core\DatabaseLifecycleClassifier;
use Copot\Core\RuntimeCompatibilityContext;
use Copot\Core\TransitionPlan;
use Copot\Core\TransitionPlanner;

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
$throws = static function (callable $callback, string $message) use (&$assertions): void {
    $assertions++;

    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException($message . ' did not throw.');
};

$descriptor = static function (string $id, int $sequence, string $sourceMin, string $sourceMax, string $target, string $schema, string $mode = CoreMigrationDescriptor::TRANSACTIONAL, ?callable $executor = null, ?callable $postcondition = null, ?MigrationSchemaSurface $surface = null): CoreMigrationDescriptor {
    return new CoreMigrationDescriptor(
        $id,
        $sequence,
        $sourceMin,
        $sourceMax,
        $target,
        $schema,
        $mode,
        'source:' . $id,
        $executor ?? static function (AuthorizedMigrationContext $context): void {},
        null,
        $postcondition,
        false,
        $surface
    );
};
$migrationA = $descriptor(
    'core.a',
    10,
    '0.10.0',
    '0.11.0',
    '0.11.0',
    'schema-11',
    CoreMigrationDescriptor::TRANSACTIONAL
);
$migrationB = $descriptor(
    'core.b',
    20,
    '0.11.0',
    '0.12.0',
    '0.12.0',
    'schema-12',
    CoreMigrationDescriptor::TRANSACTIONAL
);
$registry = new CoreMigrationRegistry('core-set-1', [$migrationA, $migrationB]);
$ledger = new CoreMigrationLedger();
$connection = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$connection->exec('CREATE TABLE core_migration_history (migration_id VARCHAR(191) PRIMARY KEY, sequence_number INTEGER UNIQUE, target_webcore_version VARCHAR(64), target_schema_identity VARCHAR(191), migration_checksum CHAR(64), applied_at DATETIME)');
$snapshot = new InstalledStateSnapshot('0.10.0', new DateTimeImmutable('2026-01-01T00:00:00+00:00'), 'release-10', 'tree-10', 1, 'schema-10', CoreMigrationStateIdentity::fromRecords([]));
$installed = InstalledStateInspection::committed($snapshot);
$runtime = new PackageRuntimeCompatibility('8.0', ['sqlite' => '3.0'], ['pdo']);
$inventory = [new PackageInventoryEntry('app/Core/Version.php', 1, str_repeat('a', 64), PackageOwnership::PACKAGE_OWNED)];
$package = static function (string $target, PackageMigrationDeclaration $declaration) use ($runtime, $inventory): PackageContract {
    return new PackageContract(
        PackageContract::WEBCORE_PACKAGE_TYPE,
        PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION,
        $target,
        'release-' . $target,
        'tree-target',
        new PackageCompatibility('0.0.0'),
        $runtime,
        $inventory,
        $declaration
    );
};

$planner = new CoreMigrationPlanner('0.12.0');
$plan = $planner->plan($installed, $package('0.12.0', new PackageMigrationDeclaration(true, 'core-set-1')), $registry, $ledger, $connection);
$assert($plan->isAccepted(), 'Chained migration plan was rejected.');
$assert(array_map(static fn (CoreMigrationDescriptor $migration): string => $migration->id(), $plan->migrations()) === ['core.a', 'core.b'], 'Migration chain did not use evolving virtual state.');
$assert($plan->virtualFinalWebcoreVersion() === '0.12.0', 'Virtual planning state did not reach the package target.');
$assert($plan->virtualFinalSchemaIdentity() === 'schema-12', 'Virtual schema state did not advance.');
$assert($snapshot->webcoreVersion() === '0.10.0', 'Committed installed state was mutated during planning.');

$sameVersionMigration = $descriptor('core.same-version', 10, '0.8.0', '0.11.0', '0.10.0', 'schema-10-forward');
$sameVersionPackage = $package('0.10.0', new PackageMigrationDeclaration(true, 'same-version-set'));
$sameVersionTransition = (new TransitionPlanner('0.12.0'))->plan($installed, $sameVersionPackage, new RuntimeCompatibilityContext('8.0', ['sqlite' => '3.0'], ['pdo']));
$sameVersionPlan = CoreMigrationPlan::allow('0.10.0', '0.10.0', 'schema-10', 'schema-10-forward', [$sameVersionMigration]);
$classified = (new DatabaseLifecycleClassifier())->classify($sameVersionTransition, $sameVersionPlan);
$assert($sameVersionTransition->classification() === TransitionPlan::REPAIR, 'Same-version planning did not begin as Repair.');
$assert($classified->classification() === TransitionPlan::DATABASE_UPDATE, 'Authorized same-version Core migration was not classified as Database-only Update.');
$noForward = (new DatabaseLifecycleClassifier())->classify($sameVersionTransition, CoreMigrationPlan::allow('0.10.0', '0.10.0', 'schema-10', 'schema-10', []));
$assert($noForward->classification() === TransitionPlan::REPAIR, 'Same-version planning without a forward migration left Repair.');

$freshPlan = $planner->plan(InstalledStateInspection::fresh(), $package('0.12.0', new PackageMigrationDeclaration(true, 'core-set-1')), $registry, $ledger, $connection);
$assert($freshPlan->isAccepted() && $freshPlan->isFreshBaseline() && $freshPlan->migrations() === [], 'Fresh canonical planning replayed historical migrations.');
$assert($ledger->records($connection) === [], 'Fresh planning fabricated applied migration rows.');
$noDeclaration = $planner->plan($installed, $package('0.12.0', new PackageMigrationDeclaration()), $registry, $ledger, $connection);
$assert(!$noDeclaration->isAccepted() && str_contains($noDeclaration->reason(), 'declaration'), 'Required migration declaration was not rejected.');
$unknownDeclaration = $planner->plan($installed, $package('0.12.0', new PackageMigrationDeclaration(true, 'unknown')), $registry, $ledger, $connection);
$assert(!$unknownDeclaration->isAccepted() && str_contains($unknownDeclaration->reason(), 'unknown'), 'Unknown migration declaration was not rejected.');
$downgrade = $planner->plan($installed, $package('0.9.0', new PackageMigrationDeclaration()), $registry, $ledger, $connection);
$assert(!$downgrade->isAccepted() && str_contains($downgrade->reason(), 'Downgrade'), 'Migration downgrade was not rejected.');
$legacy = $planner->plan(InstalledStateInspection::legacy($snapshot), $package('0.12.0', new PackageMigrationDeclaration(true, 'core-set-1')), $registry, $ledger, $connection);
$assert(!$legacy->isAccepted() && str_contains($legacy->reason(), 'legacy'), 'Legacy migration adoption was not blocked.');
$ledger->record($connection, $migrationB);
$missingEarlier = $planner->plan($installed, $package('0.12.0', new PackageMigrationDeclaration(true, 'core-set-1')), $registry, $ledger, $connection);
$assert(!$missingEarlier->isAccepted() && str_contains($missingEarlier->reason(), 'prefix'), 'Missing earlier applied migration was not rejected.');
$connection->exec('DELETE FROM core_migration_history');
$ledger->record($connection, $migrationA);
$ledger->record($connection, $migrationB);
$completeRecords = $ledger->records($connection);
$completeSnapshot = new InstalledStateSnapshot('0.12.0', new DateTimeImmutable('2026-01-01T00:00:00+00:00'), 'release-12', 'tree-12', 1, 'schema-12', CoreMigrationStateIdentity::fromRecords($completeRecords));
$completeHistory = $planner->plan(InstalledStateInspection::committed($completeSnapshot), $package('0.12.0', new PackageMigrationDeclaration(true, 'core-set-1')), $registry, $ledger, $connection);
$assert($completeHistory->isAccepted() && $completeHistory->migrations() === [], 'Complete historical ledger was not accepted against the committed state.');
$behindSnapshot = new InstalledStateSnapshot('0.11.0', new DateTimeImmutable('2026-01-01T00:00:00+00:00'), 'release-11', 'tree-11', 1, 'schema-11', CoreMigrationStateIdentity::fromRecords($completeRecords));
$behindPlan = $planner->plan(InstalledStateInspection::committed($behindSnapshot), $package('0.12.0', new PackageMigrationDeclaration(true, 'core-set-1')), $registry, $ledger, $connection);
$assert(!$behindPlan->isAccepted() && str_contains($behindPlan->reason(), 'contradicts'), 'Committed state behind applied history was accepted.');
$inconsistentSnapshot = new InstalledStateSnapshot('0.12.0', new DateTimeImmutable('2026-01-01T00:00:00+00:00'), 'release-12', 'tree-12', 1, 'schema-other', CoreMigrationStateIdentity::fromRecords($completeRecords));
$inconsistentPlan = $planner->plan(InstalledStateInspection::committed($inconsistentSnapshot), $package('0.12.0', new PackageMigrationDeclaration(true, 'core-set-1')), $registry, $ledger, $connection);
$assert(!$inconsistentPlan->isAccepted() && str_contains($inconsistentPlan->reason(), 'contradicts'), 'Inconsistent committed schema state was accepted.');

$prefixConnection = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$prefixConnection->exec('CREATE TABLE core_migration_history (migration_id VARCHAR(191) PRIMARY KEY, sequence_number INTEGER UNIQUE, target_webcore_version VARCHAR(64), target_schema_identity VARCHAR(191), migration_checksum CHAR(64), applied_at DATETIME)');
$ledger->record($prefixConnection, $migrationA);
$prefixSnapshot = new InstalledStateSnapshot('0.11.0', new DateTimeImmutable('2026-01-01T00:00:00+00:00'), 'release-11', 'tree-11', 1, 'schema-11', CoreMigrationStateIdentity::fromRecords($ledger->records($prefixConnection)));
$suffixPlan = $planner->plan(InstalledStateInspection::committed($prefixSnapshot), $package('0.12.0', new PackageMigrationDeclaration(true, 'core-set-1')), $registry, $ledger, $prefixConnection);
$assert($suffixPlan->isAccepted() && array_map(static fn (CoreMigrationDescriptor $migration): string => $migration->id(), $suffixPlan->migrations()) === ['core.b'], 'Valid applied prefix did not plan only the unapplied suffix.');
$modifiedConnection = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$modifiedConnection->exec('CREATE TABLE core_migration_history (migration_id VARCHAR(191) PRIMARY KEY, sequence_number INTEGER UNIQUE, target_webcore_version VARCHAR(64), target_schema_identity VARCHAR(191), migration_checksum CHAR(64), applied_at DATETIME)');
$ledger->record($modifiedConnection, $migrationA);
$modifiedA = $descriptor('core.a', 10, '0.10.0', '0.11.0', '0.11.0', 'schema-11-modified', CoreMigrationDescriptor::TRANSACTIONAL, static function (PDO $connection): void { $connection->exec('CREATE TABLE modified_a (id INTEGER)'); });
$modifiedRegistry = new CoreMigrationRegistry('core-set-1', [$modifiedA, $migrationB]);
$modifiedPlan = $planner->plan(InstalledStateInspection::committed($prefixSnapshot), $package('0.12.0', new PackageMigrationDeclaration(true, 'core-set-1')), $modifiedRegistry, $ledger, $modifiedConnection);
$assert(!$modifiedPlan->isAccepted() && str_contains($modifiedPlan->reason(), 'modified'), 'Modified applied migration history was accepted.');
$unknownConnection = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$unknownConnection->exec('CREATE TABLE core_migration_history (migration_id VARCHAR(191) PRIMARY KEY, sequence_number INTEGER UNIQUE, target_webcore_version VARCHAR(64), target_schema_identity VARCHAR(191), migration_checksum CHAR(64), applied_at DATETIME)');
$unknownConnection->exec("INSERT INTO core_migration_history VALUES ('core.unknown', 10, '0.11.0', 'schema-11', '" . str_repeat('b', 64) . "', '2026-01-01 00:00:00')");
$unknownPlan = $planner->plan($installed, $package('0.12.0', new PackageMigrationDeclaration(true, 'core-set-1')), $registry, $ledger, $unknownConnection);
$assert(!$unknownPlan->isAccepted() && str_contains($unknownPlan->reason(), 'prefix'), 'Unknown applied migration history was accepted.');

$throws(static fn (): CoreMigrationRegistry => new CoreMigrationRegistry('duplicate', [$migrationA, $migrationA]), 'Duplicate migration ID');
$throws(static fn (): CoreMigrationRegistry => new CoreMigrationRegistry('reordered', [$migrationB, $migrationA]), 'Reordered migration registry');
$gapRegistry = new CoreMigrationRegistry('gap', [$migrationA, new CoreMigrationDescriptor('core.gap', 30, '0.11.0', '0.12.0', '0.12.0', 'schema-12', CoreMigrationDescriptor::TRANSACTIONAL, 'source:gap', static function (PDO $connection): void {}),]);
$assert(count($gapRegistry->migrations()) === 2, 'Sequence gaps were rejected.');

$runnerConnection = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$runnerConnection->exec('CREATE TABLE core_migration_history (migration_id VARCHAR(191) PRIMARY KEY, sequence_number INTEGER UNIQUE, target_webcore_version VARCHAR(64), target_schema_identity VARCHAR(191), migration_checksum CHAR(64), applied_at DATETIME)');
$runnerConnection->exec('CREATE TABLE settings (id INTEGER PRIMARY KEY)');
$surface = new MigrationSchemaSurface(['settings']);
$runnerA = $descriptor('core.runner-a', 10, '0.10.0', '0.11.0', '0.11.0', 'schema-11', CoreMigrationDescriptor::TRANSACTIONAL, static function (AuthorizedMigrationContext $context): void { $context->addColumn('settings', 'runner_a', 'INTEGER'); }, static function (AuthorizedMigrationContext $context): bool { return $context->columnExists('settings', 'runner_a'); }, $surface);
$runnerB = $descriptor('core.runner-b', 20, '0.11.0', '0.12.0', '0.12.0', 'schema-12', CoreMigrationDescriptor::TRANSACTIONAL, static function (AuthorizedMigrationContext $context): void { $context->addColumn('settings', 'runner_b', 'INTEGER'); }, null, $surface);
$runnerPlan = CoreMigrationPlan::allow('0.10.0', '0.12.0', 'schema-10', 'schema-12', [$runnerA, $runnerB]);
$authorizeCore = static function (CoreMigrationPlan $plan, PDO $connection): callable { $catalog = DatabaseTableOwnershipCatalog::current(); $identity = InstallationIdentity::generate(); return static function (CoreMigrationDescriptor $migration) use ($plan, $connection, $catalog, $identity): AuthorizedMigrationContext { $authorization = new MigrationAuthorizationContext($identity, new DatabaseTableNames(''), 'wu4-operation', 'upgrade', DatabaseTableOwner::webcore(), $migration->id(), $migration->checksum(), $plan->initialWebcoreVersion(), $plan->virtualFinalWebcoreVersion(), true, $migration->schemaSurface()); return new AuthorizedMigrationContext($connection, $authorization, $catalog); }; };
$run = (new CoreMigrationRunner(new CoreMigrationLedger()))->run($runnerConnection, $runnerPlan, null, $authorizeCore($runnerPlan, $runnerConnection));
$assert($run->status() === MigrationRunResult::COMPLETED && $run->appliedMigrationIds() === ['core.runner-a', 'core.runner-b'], 'Transactional migration runner did not complete.');
$assert(count((new CoreMigrationLedger())->records($runnerConnection)) === 2, 'Applied migration ledger was not recorded transactionally.');

$failureConnection = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$failureConnection->exec('CREATE TABLE core_migration_history (migration_id VARCHAR(191) PRIMARY KEY, sequence_number INTEGER UNIQUE, target_webcore_version VARCHAR(64), target_schema_identity VARCHAR(191), migration_checksum CHAR(64), applied_at DATETIME)');
$failureConnection->exec('CREATE TABLE settings (id INTEGER PRIMARY KEY)');
$failureMigration = $descriptor('core.failure', 10, '0.10.0', '0.11.0', '0.11.0', 'schema-11', CoreMigrationDescriptor::TRANSACTIONAL, static function (AuthorizedMigrationContext $context): void { $context->addColumn('settings', 'rolled_back', 'INTEGER'); }, static function (AuthorizedMigrationContext $context): bool { return false; }, $surface);
$failurePlan = CoreMigrationPlan::allow('0.10.0', '0.11.0', 'schema-10', 'schema-11', [$failureMigration]);
$failedRun = (new CoreMigrationRunner(new CoreMigrationLedger()))->run($failureConnection, $failurePlan, null, $authorizeCore($failurePlan, $failureConnection));
$assert($failedRun->status() === MigrationRunResult::FAILED, 'Failed migration did not return FAILED.');
$assert((int) $failureConnection->query("SELECT COUNT(*) FROM pragma_table_info('settings') WHERE name='rolled_back'")->fetchColumn() === 0, 'Transactional migration effects were not rolled back.');
$assert((new CoreMigrationLedger())->records($failureConnection) === [], 'Failed migration was recorded as applied.');

$nonTransactionalConnection = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$nonTransactionalConnection->exec('CREATE TABLE core_migration_history (migration_id VARCHAR(191) PRIMARY KEY, sequence_number INTEGER UNIQUE, target_webcore_version VARCHAR(64), target_schema_identity VARCHAR(191), migration_checksum CHAR(64), applied_at DATETIME)');
$nonTransactionalConnection->exec('CREATE TABLE settings (id INTEGER PRIMARY KEY)');
$nonTransactional = $descriptor('core.non-transactional', 10, '0.10.0', '0.11.0', '0.11.0', 'schema-11', CoreMigrationDescriptor::NON_TRANSACTIONAL, static function (AuthorizedMigrationContext $context): void { if (!$context->columnExists('settings', 'non_transactional')) $context->addColumn('settings', 'non_transactional', 'INTEGER'); }, null, $surface);
$nonTransactionalPlan = CoreMigrationPlan::allow('0.10.0', '0.11.0', 'schema-10', 'schema-11', [$nonTransactional]);
$nonTransactionalRun = (new CoreMigrationRunner(new CoreMigrationLedger()))->run($nonTransactionalConnection, $nonTransactionalPlan, null, $authorizeCore($nonTransactionalPlan, $nonTransactionalConnection));
$assert($nonTransactionalRun->status() === MigrationRunResult::COMPLETED, 'Non-transactional migration did not complete with verified ledger recording.');
$nonTransactionalConnection->exec('DROP TABLE core_migration_history');
$indeterminateRun = (new CoreMigrationRunner(new CoreMigrationLedger()))->run($nonTransactionalConnection, $nonTransactionalPlan, null, $authorizeCore($nonTransactionalPlan, $nonTransactionalConnection));
$assert($indeterminateRun->status() === MigrationRunResult::INDETERMINATE, 'Ledger failure after non-transactional effects was not indeterminate.');

$schema = (string) file_get_contents($basePath . '/database/schema.sql');
$statements = (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->statements($schema);
$assert(count(array_filter($statements, static fn (string $statement): bool => stripos($statement, 'CREATE TABLE core_migration_history') === 0)) === 1, 'Canonical schema omitted the migration ledger table.');
$assert(!str_contains($schema, 'INSERT INTO core_migration_history'), 'Canonical schema fabricated historical migration rows.');

echo "WU4 migration lifecycle focused tests passed ({$assertions} assertions)." . PHP_EOL;
