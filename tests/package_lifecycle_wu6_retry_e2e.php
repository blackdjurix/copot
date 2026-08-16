<?php

declare(strict_types=1);

use Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureService;
use Copot\Core\CommittedLifecycleState;
use Copot\Core\CommittedLifecycleStateStore;
use Copot\Core\CoreMigrationStateIdentity;
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
use Copot\Core\SystemManagerLifecycleService;
use Copot\Core\SystemManagerPackageUpload;
use Copot\Core\TransitionPlan;
use Copot\Core\WebcoreApplyPlan;
use Copot\Core\WebcoreMutationContext;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';
require_once $base . '/app/Core/SystemManagerRecoveryGate.php';

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
        'inventory' => $inventory, 'migration_declaration' => ['declares_core_migrations' => false, 'declaration_identity' => null],
    ];
    $zip = new ZipArchive();
    $assert($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'Disposable retained package could not be created.');
    $zip->addFromString('.copot/package.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    $zip->addFromString('app/Wu6RetryMarker.php', $newContent);
    $zip->close();

    $planned = $service->plan($zipPath);
    $assert($planned->accepted() && $planned->status() === 'planned', 'Retained package did not produce an accepted Repair plan.');
    $transition = $property($planned, 'transition');
    $migration = $property($planned, 'migration');
    $assert($transition instanceof TransitionPlan && $transition->classification() === TransitionPlan::REPAIR, 'Fixture did not resolve to the supported Repair lineage.');
    $assert($migration instanceof CoreMigrationPlan && $migration->isAccepted() && $migration->migrations() === [], 'Fixture unexpectedly requires an unconfigured Core migration.');

    $intake = $property($service, 'intake');
    $reader = $property($service, 'manifestReader');
    $payload = $intake->intake($zipPath);
    $packageManifest = $reader->read($payload);
    $applyPlan = WebcoreApplyPlan::fromPayload($packageManifest->payload());
    copy($zipPath, $payload->archivePath());
    $operationId = 'wu6-e2e-' . bin2hex(random_bytes(6));
    $now = gmdate(DATE_ATOM);
    $payloadIdentity = hash('sha256', implode(':', array_map(static fn ($file): string => $file->path() . ':' . $file->sha256(), $applyPlan->files())));
    $migrationPlanIdentity = hash('sha256', '');
    $operation = new LifecycleOperationRecord($operationId, TransitionPlan::REPAIR, '0.13.0', 'wu6-retry-release', $payload->archiveSha256(), $payload->stagingPath(), $payloadIdentity, $applyPlan->identity(), LifecycleOperationRecord::BLOCKED, 0, null, $migrationPlanIdentity, null, $now, $now, 'fixture-resume');

    $applyCoordinator = $property($service, 'applyCoordinator');
    $boundary = $property($applyCoordinator, 'recoveryBoundary');
    $capture = $property($boundary, 'capture');
    $assert($capture instanceof NormalWebcoreRecoveryCaptureService, 'Factory did not compose the real Normal Webcore recovery capture service.');
    $request = $boundary->requestFor(new WebcoreMutationContext($operation, $applyPlan, $transition, $migration));
    $session = $capture->capture($request);
    $recoveryIdentity = $session->recoveryIdentity()->value();
    $recoveryManifest = $session->manifestIdentity();
    $assert($session->ready(), 'Persisted recovery capture did not reach READY.');
    $session->close();

    $operations = new LifecycleOperationStore($project . DIRECTORY_SEPARATOR . 'storage');
    $operations->create($operation->bindRecovery($recoveryIdentity, $recoveryManifest, \Copot\Core\BackupRecovery\RecoveryLifecycleState::READY));
    $manager = new SystemManagerLifecycleService($service, new \Copot\Core\UnavailableSystemManagerRecoveryGate(), new SystemManagerPackageUpload($project . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'package-staging'));
    $result = $manager->retry($operationId);
    $assert($result['accepted'] === true && $result['status'] === 'completed', 'Production-composed System Manager Retry did not complete.');
    $assert(($result['operation_id'] ?? null) === $operationId, 'System Manager Retry changed the persisted operation identity.');
    $assert(($result['phase'] ?? null) === null || ($result['phase'] ?? null) !== 'awaiting_wu6', 'Retry returned an awaiting_wu6 terminal result.');
    $assert(file_get_contents($liveFile) === $newContent, 'Real package-owned live file mutation did not occur.');
    $assert($committed->read()?->webcoreVersion() === '0.13.0' && $committed->read()?->releaseIdentity() === 'wu6-retry-release', 'Committed lifecycle state did not advance to the retained package.');
    $assert($operations->read() === null, 'Lifecycle operation cleanup did not clear the committed operation.');
    $assert(!is_dir($payload->stagingPath()), 'Retained staging session was not cleaned after successful Retry.');
    $assert($recoveryIdentity !== '' && $recoveryManifest !== '', 'Recovery identity/manifest evidence was not persisted.');
    echo "WU6 persisted Retry E2E acceptance passed ({$assertions} assertions)." . PHP_EOL;
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
