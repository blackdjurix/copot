<?php

declare(strict_types=1);

use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\CommittedLifecycleState;
use Copot\Core\CommittedLifecycleStateStore;
use Copot\Core\CoreMigrationStateIdentity;
use Copot\Core\CoreMigrationRegistry;
use Copot\Core\CanonicalSchemaBaselineVerifier;
use Copot\Core\Database;
use Copot\Core\Config;
use Copot\Core\CoreMigrationPlan;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\InstallationState;
use Copot\Core\LifecycleOperationRecord;
use Copot\Core\LifecycleOperationStore;
use Copot\Core\MaintenanceCoordinator;
use Copot\Core\PackageContract;
use Copot\Core\PackageLifecycleFactory;
use Copot\Core\PackageLifecycleService;
use Copot\Core\PackageManifestReader;
use Copot\Core\PackageOwnership;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\PackageCompatibility;
use Copot\Core\TransitionPlan;
use Copot\Core\WebcoreApplyPlan;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) { throw new RuntimeException($message); }
};
$property = static function (object $object, string $name): mixed {
    return (new ReflectionProperty($object, $name))->getValue($object);
};
$remove = static function (string $path) use (&$remove): void {
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) { return; }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') { $remove($path . DIRECTORY_SEPARATOR . $entry); }
    }
    @rmdir($path);
};
$copyTree = static function (string $source, string $destination) use (&$copyTree): void {
    if (!is_dir($destination) && !mkdir($destination, 0700, true) && !is_dir($destination)) {
        throw new RuntimeException('Disposable project directory could not be created.');
    }
    foreach (scandir($source) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === '.git' || $entry === 'storage') { continue; }
        $from = $source . DIRECTORY_SEPARATOR . $entry;
        $to = $destination . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($from) && !is_link($from)) { $copyTree($from, $to); }
        elseif (is_file($from) && !is_link($from)) { if (!copy($from, $to)) { throw new RuntimeException('Disposable project copy failed.'); } }
    }
};

$saved = [];
foreach (['DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD','DB_NAMESPACE','COPOT_RECOVERY_ROOT','COPOT_MARIADB_ADMIN_USERNAME','COPOT_MARIADB_ADMIN_PASSWORD','COPOT_MARIADB_QUIESCENCE_CONFIRMED'] as $name) {
    $saved[$name] = $_ENV[$name] ?? getenv($name);
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu6-retry-e2e-' . bin2hex(random_bytes(5));
$project = $root . DIRECTORY_SEPARATOR . 'project';
$recovery = $root . DIRECTORY_SEPARATOR . 'recovery';
$databaseName = 'copot_wu6_' . bin2hex(random_bytes(5));
$runtimeUser = 'wu6_' . bin2hex(random_bytes(4));
$runtimePassword = bin2hex(random_bytes(12));
$adminUser = (string) (getenv('WU6_E2E_ADMIN_USERNAME') ?: getenv('DB_USERNAME') ?: 'root');
$adminPassword = (string) (getenv('WU6_E2E_ADMIN_PASSWORD') ?: getenv('DB_PASSWORD') ?: '');
$quiescenceUser = (string) (getenv('WU6_E2E_QUIESCENCE_USERNAME') ?: $adminUser);
$quiescencePassword = (string) (getenv('WU6_E2E_QUIESCENCE_PASSWORD') ?: $adminPassword);
$host = (string) (getenv('WU6_E2E_DB_HOST') ?: getenv('DB_HOST') ?: '127.0.0.1');
$port = (string) (getenv('WU6_E2E_DB_PORT') ?: getenv('DB_PORT') ?: '3306');
$admin = null;

try {
    $copyTree($base, $project);
    file_put_contents($project . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'autoload.php', "<?php\nif (!class_exists('Copot\\Core\\Autoloader', false)) { require __DIR__ . '/../app/Core/Autoloader.php'; (new Copot\\Core\\Autoloader('Copot\\Core', __DIR__ . '/../app/Core'))->register(); }\n");
    $settingsRoute = $project . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'site_settings.php';
    $settingsRouteSource = (string) file_get_contents($settingsRoute);
    $routeClasses = [
        'SystemManagerRecoveryGate.php' => 'SystemManagerRecoveryGate',
        'ModuleActionPolicy.php' => 'ModuleActionPolicy',
        'ModuleInventoryBuilder.php' => 'ModuleInventoryBuilder',
        'ModulePackageOperator.php' => 'ModulePackageOperator',
        'ModuleManagerAdmin.php' => 'ModuleManagerAdmin',
        'SiteSettingsModulesAdmin.php' => 'SiteSettingsModulesAdmin',
        'WebcoreColorScheme.php' => 'Copot\\Core\\WebcoreColorScheme',
        'HomepageHeroImageService.php' => 'Copot\\Core\\HomepageHeroImageService',
    ];
    $settingsRouteSource = preg_replace_callback('/^require_once \\$app->path\(\'([^\']+)\'\);$/m', static function (array $match) use ($routeClasses): string {
        $class = $routeClasses[basename($match[1])] ?? null;
        if ($class === null) { return $match[0]; }
        $check = $class === 'SystemManagerRecoveryGate' ? "interface_exists('{$class}', false) || class_exists('{$class}', false)" : "class_exists('{$class}', false)";
        return "if (!({$check})) { {$match[0]} }";
    }, $settingsRouteSource);
    file_put_contents($settingsRoute, $settingsRouteSource);
    mkdir($project . DIRECTORY_SEPARATOR . 'storage', 0700, true);
    mkdir($recovery, 0700, true);

    $admin = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $adminUser, $adminPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $quote = static fn (string $value): string => '`' . str_replace('`', '``', $value) . '`';
    $admin->exec('CREATE DATABASE ' . $quote($databaseName) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $admin->exec("CREATE USER '" . str_replace("'", "''", $runtimeUser) . "'@'127.0.0.1' IDENTIFIED BY '" . str_replace("'", "''", $runtimePassword) . "'");
    $admin->exec("GRANT ALL PRIVILEGES ON {$quote($databaseName)}.* TO '" . str_replace("'", "''", $runtimeUser) . "'@'127.0.0.1'");
    $admin->exec('FLUSH PRIVILEGES');

    $runtime = new PDO("mysql:host={$host};port={$port};dbname={$databaseName};charset=utf8mb4", $runtimeUser, $runtimePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $schemaPath = $project . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql';
    $schemaRunner = new InstallerSchemaRunner($schemaPath);
    foreach ($schemaRunner->statements((string) file_get_contents($schemaPath)) as $statement) { $runtime->exec($statement); }
    // schema.sql is the current bootstrap source, but its accepted runtime baseline
    // omits the three legacy taxonomy tables required by the health gate. Preserve
    // that unrelated canonical state from the durable baseline for this disposable DB.
    $baselinePath = $project . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'baselines' . DIRECTORY_SEPARATOR . 'webcore-0.8.0.sql';
    $baselineRunner = new InstallerSchemaRunner($baselinePath);
    foreach ($baselineRunner->statements((string) file_get_contents($baselinePath)) as $statement) {
        $normalized = ltrim($statement);
        if (str_starts_with($normalized, 'CREATE TABLE taxonomy_') || str_starts_with($normalized, 'INSERT INTO taxonomy_types')) {
            $runtime->exec($statement);
        }
    }
    foreach (['security_login_attempts', 'security_sessions', 'security_events'] as $migrationOwnedTable) {
        $runtime->exec('DROP TABLE ' . $quote($migrationOwnedTable));
    }
    $theme = json_decode((string) file_get_contents($project . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'theme.json'), true, 16, JSON_THROW_ON_ERROR);
    $themeInsert = $runtime->prepare('INSERT INTO themes (theme_id, name, version, type, path, is_active, metadata, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)');
    $themeNow = gmdate('Y-m-d H:i:s');
    $themeInsert->execute([$theme['id'], $theme['name'], $theme['version'], $theme['type'], 'themes/default', json_encode($theme, JSON_THROW_ON_ERROR), $themeNow, $themeNow]);

    file_put_contents($project . DIRECTORY_SEPARATOR . '.env', implode(PHP_EOL, [
        'DB_HOST="' . $host . '"', 'DB_PORT="' . $port . '"', 'DB_DATABASE="' . $databaseName . '"',
        'DB_USERNAME="' . $runtimeUser . '"', 'DB_PASSWORD="' . $runtimePassword . '"', 'DB_NAMESPACE=""', 'APP_ENV=local',
    ]) . PHP_EOL);
    $liveFile = $project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Wu6RetryMarker.php';
    file_put_contents($liveFile, "<?php\nreturn 'before';\n");

    $_ENV['DB_HOST'] = $host; putenv('DB_HOST=' . $host);
    $_ENV['DB_PORT'] = $port; putenv('DB_PORT=' . $port);
    $_ENV['DB_DATABASE'] = $databaseName; putenv('DB_DATABASE=' . $databaseName);
    $_ENV['DB_USERNAME'] = $runtimeUser; putenv('DB_USERNAME=' . $runtimeUser);
    $_ENV['DB_PASSWORD'] = $runtimePassword; putenv('DB_PASSWORD=' . $runtimePassword);
    $_ENV['DB_NAMESPACE'] = ''; putenv('DB_NAMESPACE=');
    $_ENV['COPOT_RECOVERY_ROOT'] = $recovery; putenv('COPOT_RECOVERY_ROOT=' . $recovery);
    $_ENV['COPOT_MARIADB_ADMIN_USERNAME'] = $quiescenceUser; putenv('COPOT_MARIADB_ADMIN_USERNAME=' . $quiescenceUser);
    $_ENV['COPOT_MARIADB_ADMIN_PASSWORD'] = $quiescencePassword; putenv('COPOT_MARIADB_ADMIN_PASSWORD=' . $quiescencePassword);
    $_ENV['COPOT_MARIADB_QUIESCENCE_CONFIRMED'] = true; putenv('COPOT_MARIADB_QUIESCENCE_CONFIRMED=true');

    $schemaIdentity = (new CanonicalSchemaBaselineVerifier())->identity($schemaPath);
    $migrationIdentity = CoreMigrationStateIdentity::fromRecords([]);
    $installation = new InstallationState($project . DIRECTORY_SEPARATOR . 'storage');
    $committed = new CommittedLifecycleStateStore($project . DIRECTORY_SEPARATOR . 'storage');
    $committed->commit($installation, new CommittedLifecycleState('0.13.0', 'old-release', 'old-tree', 1, $schemaIdentity, $migrationIdentity, new DateTimeImmutable('-1 minute')));

    $service = PackageLifecycleFactory::forProject($project);
    $assert($service instanceof PackageLifecycleService, 'Production PackageLifecycleFactory did not construct the service.');

    $newContent = "<?php\nreturn 'after';\n";
    $zipPath = $root . DIRECTORY_SEPARATOR . 'retained-webcore.zip';
    $inventory = [[
        'path' => 'app/Wu6RetryMarker.php', 'byte_size' => strlen($newContent),
        'sha256' => hash('sha256', $newContent), 'ownership' => PackageOwnership::PACKAGE_OWNED,
    ]];
    $manifest = [
        'package_type' => PackageContract::WEBCORE_PACKAGE_TYPE,
        'manifest_contract_version' => PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION,
        'target_webcore_version' => '0.13.0', 'release_identity' => 'wu6-retry-release', 'source_tree_identity' => 'wu6-retry-tree',
        'source_compatibility' => ['minimum_source_version' => '0.13.0', 'maximum_source_version' => null],
        'runtime_compatibility' => ['minimum_php_version' => '8.0.0', 'minimum_database_versions' => ['mysql' => '10.0'], 'required_extensions' => ['json', 'pdo', 'pdo_mysql', 'zip']],
        'inventory' => $inventory, 'migration_declaration' => ['declares_core_migrations' => true, 'declaration_identity' => CoreMigrationRegistry::IDENTITY], 'target_requirements' => [],
    ];
    $zip = new ZipArchive();
    $assert($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'Disposable retained package could not be created.');
    $zip->addFromString('.copot/package.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    $zip->addFromString('app/Wu6RetryMarker.php', $newContent);
    $zip->close();

    $planned = $service->plan($zipPath);
    $assert($planned->accepted() && $planned->status() === 'planned', 'Retained package did not produce an accepted Repair plan: ' . $planned->reason());
    $transition = $property($planned, 'transition');
    $migration = $property($planned, 'migration');
    $assert($transition instanceof TransitionPlan && $transition->classification() === TransitionPlan::DATABASE_UPDATE, 'Canonical migration-aware planning did not classify the accepted same-version Repair as Database Update: ' . ($transition instanceof TransitionPlan ? $transition->classification() . ' / ' . $transition->reason() : 'invalid transition'));
    $assert($migration instanceof CoreMigrationPlan && $migration->isAccepted(), 'Migration-aware Repair plan was not accepted: ' . ($migration instanceof CoreMigrationPlan ? $migration->reason() : 'invalid migration plan'));
    $assert(count($migration->migrations()) === 1 && $migration->migrations()[0] instanceof \Copot\Core\CoreMigrationDescriptor && $migration->migrations()[0]->id() === 'core.security.persistence', 'Repair plan did not contain exactly core.security.persistence.');

    $intake = $property($service, 'intake');
    $reader = $property($service, 'manifestReader');
    $payload = $intake->intake($zipPath);
    $packageManifest = $reader->read($payload);
    $installedInspector = $property($service, 'installedInspector');
    $runtimeCompatibility = $property($service, 'runtime');
    $rawTransition = $property($service, 'transitionPlanner')->plan(
        $installedInspector->inspect($installation, ($property($service, 'evidence'))()),
        $packageManifest->contract(),
        ($runtimeCompatibility)()
    );
    $assert($rawTransition->accepted() && $rawTransition->classification() === TransitionPlan::REPAIR, 'Underlying same-version transition did not resolve to Repair before migration classification.');
    $applyPlan = WebcoreApplyPlan::fromPayload($packageManifest->payload());
    copy($zipPath, $payload->archivePath());
    $operationId = 'wu6-e2e-' . bin2hex(random_bytes(6));
    $now = gmdate(DATE_ATOM);
    $payloadIdentity = hash('sha256', implode(':', array_map(static fn ($file): string => $file->path() . ':' . $file->sha256(), $applyPlan->files())));
    $migrationPlanIdentity = hash('sha256', implode("\n", array_map(
        static fn (\Copot\Core\CoreMigrationDescriptor $descriptor): string => $descriptor->id() . ':' . $descriptor->checksum(),
        array_filter($migration->migrations(), static fn ($descriptor): bool => $descriptor instanceof \Copot\Core\CoreMigrationDescriptor)
    )));
    $operation = new LifecycleOperationRecord($operationId, $transition->classification(), '0.13.0', 'wu6-retry-release', $payload->archiveSha256(), $payload->stagingPath(), $payloadIdentity, $applyPlan->identity(), LifecycleOperationRecord::BLOCKED, 0, null, $migrationPlanIdentity, null, $now, $now, 'fixture-resume');

    $operations = new LifecycleOperationStore($project . DIRECTORY_SEPARATOR . 'storage');
    $operations->create($operation);
    $runtimeGrants = implode(' ', $runtime->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN));
    $assert(!preg_match('/\bSUPER\b|READ_ONLY ADMIN|SYSTEM_VARIABLES_ADMIN/i', $runtimeGrants), 'Ordinary runtime account has an unexpected read_only bypass privilege.');
    $result = $service->retry($operationId);
    $resultData = $result->toArray();
    $assert($result->accepted() && $result->status() === 'completed', 'Production-composed pre-mutation Retry did not complete: ' . $result->reason());
    $assert(($resultData['operation_id'] ?? null) === $operationId, 'Pre-mutation Retry changed the persisted operation identity.');
    $assert(($resultData['phase'] ?? null) === null || ($resultData['phase'] ?? null) !== 'awaiting_wu6', 'Retry returned an awaiting_wu6 terminal result.');
    $assert(file_get_contents($liveFile) === $newContent, 'Real package-owned live file mutation did not occur.');
    $assert($committed->read()?->webcoreVersion() === '0.13.0' && $committed->read()?->releaseIdentity() === 'wu6-retry-release', 'Committed lifecycle state did not advance to the retained package.');
    $assert($operations->read() === null, 'Lifecycle operation cleanup did not clear the committed operation.');
    $assert(!is_dir($payload->stagingPath()), 'Retained staging session was not cleaned after successful Retry.');
    $migrationRows = (int) $runtime->query("SELECT COUNT(*) FROM core_migration_history WHERE migration_id = 'core.security.persistence'")->fetchColumn();
    $assert($migrationRows === 1, 'Core migration was not recorded exactly once.');
    foreach (['security_login_attempts', 'security_sessions', 'security_events'] as $table) {
        $statement = $runtime->query("SHOW TABLES LIKE " . $runtime->quote($table));
        $assert((string) $statement->fetchColumn() === $table, "Migration-owned table {$table} was not provisioned.");
    }
    $applyCoordinator = $property($service, 'applyCoordinator');
    $boundary = $property($applyCoordinator, 'recoveryBoundary');
    $capture = $property($boundary, 'capture');
    $captureStore = $property($capture, 'store');
    $expectedRecoveryIdentity = 'webcore-' . hash('sha256', $operationId . ':' . $applyPlan->identity());
    $recoveryRecord = $captureStore->read(new RecoveryIdentity($expectedRecoveryIdentity));
    $recoveryManifest = $recoveryRecord->manifestIdentity();
    $assert($recoveryRecord->operationIdentity() === $operationId, 'Recovery evidence changed the operation identity.');
    $assert($recoveryRecord->state() === RecoveryLifecycleState::READY && $recoveryRecord->captureComplete(), 'Pre-mutation recovery capture did not reach READY.');
    $assert($recoveryRecord->mutationStarted() && $recoveryRecord->postReconciliationVerified(), 'Recovery lifecycle did not record the completed mutation verification.');
    $assert($expectedRecoveryIdentity !== '' && $recoveryManifest !== '', 'Recovery identity/manifest evidence was not persisted.');
    $assert((int) $runtime->query('SELECT @@GLOBAL.read_only')->fetchColumn() === 0, 'Isolated MariaDB did not return to read_only=0.');
    echo "WU6 persisted Retry E2E acceptance passed ({$assertions} assertions)." . PHP_EOL;
    echo "migration-plan-identity={$migrationPlanIdentity}" . PHP_EOL;
    echo "recovery-identity={$expectedRecoveryIdentity}" . PHP_EOL;
    echo "recovery-manifest-identity={$recoveryManifest}" . PHP_EOL;
} finally {
    if ($admin instanceof PDO) {
        try { $admin->exec('DROP DATABASE IF EXISTS ' . $quote($databaseName)); } catch (Throwable) {}
        try { $admin->exec("DROP USER IF EXISTS '" . str_replace("'", "''", $runtimeUser) . "'@'127.0.0.1'"); } catch (Throwable) {}
    }
    foreach ($saved as $name => $value) {
        if ($value === false || $value === null) { unset($_ENV[$name]); putenv($name); }
        else { $_ENV[$name] = $value; putenv($name . '=' . (is_bool($value) ? ($value ? 'true' : 'false') : $value)); }
    }
    $remove($root);
}
