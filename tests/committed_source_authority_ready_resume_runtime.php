<?php

declare(strict_types=1);

use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\BackupRecovery\MySqlRecoveryProvider;
use Copot\Core\CanonicalSchemaBaselineVerifier;
use Copot\Core\CanonicalTargetInventory;
use Copot\Core\CanonicalTargetInventoryEntry;
use Copot\Core\CommittedLifecycleState;
use Copot\Core\CommittedLifecycleStateStore;
use Copot\Core\CoreMigrationLedger;
use Copot\Core\CoreMigrationStateIdentity;
use Copot\Core\CoreSchemaGenerationStore;
use Copot\Core\CoreSchemaMaterializer;
use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\DatabaseTableNames;
use Copot\Core\DatabaseTableOwnershipCatalog;
use Copot\Core\DeploymentContext;
use Copot\Core\Env;
use Copot\Core\InstallationIdentityStore;
use Copot\Core\InstallationState;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\InstallerOwnershipProof;
use Copot\Core\LifecycleExecutionAttemptIdentity;
use Copot\Core\LifecycleOperationRecord;
use Copot\Core\LifecycleOperationStore;
use Copot\Core\PackageContract;
use Copot\Core\PackageLifecycleFactory;
use Copot\Core\PackageLifecycleService;
use Copot\Core\PackageManifestReader;
use Copot\Core\PackageOwnership;

$base = dirname(__DIR__);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$copy = static function (string $source, string $destination) use (&$copy): void {
    if (is_dir($source)) {
        if (!is_dir($destination) && !mkdir($destination, 0700, true) && !is_dir($destination)) {
            throw new RuntimeException('Fixture directory could not be created.');
        }
        foreach (scandir($source) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.git' || $entry === 'storage') continue;
            $copy($source . DIRECTORY_SEPARATOR . $entry, $destination . DIRECTORY_SEPARATOR . $entry);
        }
        return;
    }
    if (!is_file($source) || !copy($source, $destination)) {
        throw new RuntimeException('Fixture copy failed: ' . $source);
    }
};
$remove = static function (string $path) use (&$remove): void {
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry);
        }
        @rmdir($path);
    } elseif (is_file($path) || is_link($path)) {
        @unlink($path);
    }
};
$inventory = static function (string $root): array {
    $result = [];
    if (!is_dir($root)) return $result;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->isLink()) continue;
        $path = $file->getPathname();
        $relative = substr($path, strlen(rtrim($root, '/\\')) + 1);
        $result[$relative] = hash_file('sha256', $path);
    }
    ksort($result, SORT_STRING);
    return $result;
};

$host = (string) (getenv('COPOT_TEST_DB_HOST') ?: '127.0.0.1');
$port = (int) (getenv('COPOT_TEST_DB_PORT') ?: 3306);
$username = (string) (getenv('COPOT_TEST_DB_USERNAME') ?: 'root');
$password = (string) (getenv('COPOT_TEST_DB_PASSWORD') ?: '');
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'copot_ready_resume_' . bin2hex(random_bytes(6));
$sandbox = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-ready-resume-' . bin2hex(random_bytes(6));
$project = $sandbox . DIRECTORY_SEPARATOR . 'project';
$public = $project . DIRECTORY_SEPARATOR . 'public';
$recovery = $sandbox . DIRECTORY_SEPARATOR . 'recovery';
$zipPath = $sandbox . DIRECTORY_SEPARATOR . 'ready-resume.zip';
$payload = null;
$databaseQuoted = '`' . $database . '`';

try {
    $server->exec('CREATE DATABASE ' . $databaseQuoted . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $copy($base, $project);
    mkdir($project . DIRECTORY_SEPARATOR . 'storage', 0700, true);
    mkdir($recovery, 0700, true);
    // The fixture runs the copied runtime in the same PHP process as the
    // repository test harness. Production requests load only the deployed
    // bootstrap; keep the copied bootstrap inert so the harness does not
    // register a second copy of the same project autoloader.
    file_put_contents($project . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'autoload.php', "<?php\n");
    $siteSettingsRoute = $project . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'site_settings.php';
    $siteSettingsSource = file_get_contents($siteSettingsRoute);
    if (!is_string($siteSettingsSource)) {
        throw new RuntimeException('Copied site-settings route could not be read.');
    }
    $siteSettingsSource = str_replace(
        "require_once \$app->path('app/Core/WebcoreColorScheme.php');",
        "if (!class_exists(\\Copot\\Core\\WebcoreColorScheme::class, false)) require_once \$app->path('app/Core/WebcoreColorScheme.php');",
        $siteSettingsSource
    );
    file_put_contents($siteSettingsRoute, $siteSettingsSource);
    $markerBytes = "<?php\nreturn 'ready-resume';\n";
    file_put_contents($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'ReadyResumeMarker.php', $markerBytes);
    file_put_contents($project . DIRECTORY_SEPARATOR . '.env', implode(PHP_EOL, [
        'DB_CONNECTION=mysql', 'DB_HOST=' . $host, 'DB_PORT=' . $port, 'DB_DATABASE=' . $database,
        'DB_USERNAME=' . $username, 'DB_PASSWORD=' . $password, 'DB_NAMESPACE=',
        'COPOT_RECOVERY_ROOT=' . $recovery, '',
    ]));
    Env::load($project . DIRECTORY_SEPARATOR . '.env');

    $config = ['host' => $host, 'port' => $port, 'database' => $database, 'username' => $username, 'password' => $password, 'namespace' => ''];
    (new InstallerSchemaRunner($project . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql'))->install($config);
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $tables = new DatabaseTableNames('');
    $theme = json_decode((string) file_get_contents($project . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'theme.json'), true, 16, JSON_THROW_ON_ERROR);
    $themeInsert = $pdo->prepare('INSERT INTO themes (theme_id, name, version, type, path, is_active, metadata, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)');
    $themeNow = gmdate('Y-m-d H:i:s');
    $themeInsert->execute([$theme['id'], $theme['name'], $theme['version'], $theme['type'], 'themes/default', json_encode($theme, JSON_THROW_ON_ERROR), $themeNow, $themeNow]);
    $generation = (new CoreSchemaMaterializer())->generationIdentity($project . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql');
    (new CoreSchemaGenerationStore($tables))->record($pdo, $generation, 'compatible', '');
    $ledgerIdentity = CoreMigrationStateIdentity::fromRecords((new CoreMigrationLedger($tables))->records($pdo));

    $storage = $project . DIRECTORY_SEPARATOR . 'storage';
    $installation = (new InstallationIdentityStore($storage))->getOrCreate();
    $installationState = new InstallationState($storage);
    $installationState->createMarker('0.13.0');
    $marker = $installationState->readMarker();
    (new CommittedLifecycleStateStore($storage))->write(new CommittedLifecycleState(
        '0.13.0', 'ready-resume-release', null, 3,
        (new CanonicalSchemaBaselineVerifier())->identity($project . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql'),
        $ledgerIdentity,
        new DateTimeImmutable((string) $marker['installed_at'])
    ));

    $contentSha = hash('sha256', $markerBytes);
    $target = new CanonicalTargetInventory([
        new CanonicalTargetInventoryEntry(CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/ReadyResumeMarker.php', strlen($markerBytes), $contentSha),
    ]);
    $manifestData = [
        'package_type' => PackageContract::WEBCORE_PACKAGE_TYPE,
        'manifest_contract_version' => PackageContract::V3_MANIFEST_CONTRACT_VERSION,
        'target_webcore_version' => '0.13.0', 'release_identity' => 'ready-resume-release', 'source_tree_identity' => null,
        'source_compatibility' => ['minimum_source_version' => '0.13.0', 'maximum_source_version' => null],
        'runtime_compatibility' => ['minimum_php_version' => '8.0.0', 'minimum_database_versions' => ['mysql' => '10.0'], 'required_extensions' => ['json', 'pdo', 'pdo_mysql', 'zip']],
        'inventory' => [['path' => 'app/ReadyResumeMarker.php', 'byte_size' => strlen($markerBytes), 'sha256' => $contentSha, 'ownership' => PackageOwnership::PACKAGE_OWNED]],
        'migration_declaration' => ['declares_core_migrations' => false, 'declaration_identity' => null],
        'target_requirements' => [], 'coverage' => 'full',
        'target_inventory' => ['serialization_version' => 1, 'fingerprint' => $target->fingerprint(), 'entries' => [[
            'root' => CanonicalTargetInventoryEntry::APP_ROOT, 'ownership' => CanonicalTargetInventoryEntry::PACKAGE_OWNED,
            'path' => 'app/ReadyResumeMarker.php', 'byte_size' => strlen($markerBytes), 'sha256' => $contentSha,
        ]]],
        'source_state_requirements' => ['allowed_inventory_fingerprints' => []], 'removals' => [], 'renames' => [],
    ];
    $zip = new ZipArchive();
    $assert($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'Ready-resume package could not be created.');
    $zip->addFromString('.copot/package.json', json_encode($manifestData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    $zip->addFromString('app/ReadyResumeMarker.php', $markerBytes);
    $zip->close();

    $service = PackageLifecycleFactory::forProject(new DeploymentContext($project, $public, '/ready-resume'));
    $intake = (new ReflectionProperty($service, 'intake'))->getValue($service);
    $reader = (new ReflectionProperty($service, 'manifestReader'))->getValue($service);
    $payload = $intake->intake($zipPath);
    $packageManifest = $reader->read($payload);
    $databaseIdentity = (string) (new ReflectionProperty($service, 'authorityDatabaseIdentity'))->getValue($service);
    $namespace = (string) (new ReflectionProperty($service, 'authorityNamespaceIdentity'))->getValue($service);
    $scope = DatabaseScope::fromOwnershipCatalog($installation->value(), $databaseIdentity, $namespace, DatabaseTableOwnershipCatalog::current(), [], ['namespace' => $namespace, 'schema_generation' => $generation]);
    $proof = new InstallerOwnershipProof($installation, $namespace, $generation, $ledgerIdentity);
    $plan = $service->planCommittedSourceAuthority((new CommittedLifecycleStateStore($storage))->read(), $installation, $packageManifest, $target, $scope, $proof);
    $assert($plan->authorityOnly() && $plan->migrationPlanIdentity() === null, 'Fixture did not compose AUTHORITY_ONLY planning.');
    $attempt = LifecycleExecutionAttemptIdentity::create();
    $operation = $plan->operation()->withExecutionAttemptIdentity($attempt);
    (new LifecycleOperationStore($storage))->create($operation);

    $first = $service->continueCommittedSourceAuthorityFirstCaptureFromRuntime($packageManifest, $target, $proof);
    $first->close();
    $operations = new LifecycleOperationStore($storage);
    $boundBefore = $operations->read();
    $recoveryBefore = $inventory($recovery);
    $committedBefore = (new CommittedLifecycleStateStore($storage))->read()->toArray();
    $markerBefore = file_get_contents($storage . DIRECTORY_SEPARATOR . 'installed.lock');
    $operationPath = (new ReflectionProperty(LifecycleOperationStore::class, 'path'))->getValue($operations);
    $operationBytes = file_get_contents($operationPath);
    $assert(is_string($operationBytes), 'Persisted READY operation bytes could not be snapshotted.');
    $rejectedStates = [];
    $conflicting = $boundBefore?->toArray() ?? [];
    $conflicting['operation_id'] = 'source-authority-reconciliation-sha256:' . str_repeat('c', 64);
    $rejectedStates['conflicting operation'] = LifecycleOperationRecord::fromArray($conflicting);
    $attemptMismatch = $boundBefore?->toArray() ?? [];
    $attemptMismatch['execution_attempt_identity'] = LifecycleExecutionAttemptIdentity::create();
    $rejectedStates['mismatched attempt'] = LifecycleOperationRecord::fromArray($attemptMismatch);
    $partial = $boundBefore?->toArray() ?? [];
    $partial['recovery_state'] = 'partial';
    $rejectedStates['non-READY recovery'] = LifecycleOperationRecord::fromArray($partial);
    foreach ($rejectedStates as $label => $rejected) {
        $mutatedBytes = json_encode($rejected->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents($operationPath, $mutatedBytes);
        try {
            $service->resumeCommittedSourceAuthorityFromRuntime($packageManifest, $target, $proof);
            $assert(false, $label . ' was accepted by runtime READY resume.');
        } catch (Throwable) {
            $assert(true, $label . ' was rejected.');
        }
        $assert(file_get_contents($operationPath) === $mutatedBytes, $label . ' rejection mutated authoritative operation bytes.');
        $assert($recoveryBefore === $inventory($recovery), $label . ' rejection changed recovery evidence.');
        file_put_contents($operationPath, $operationBytes);
    }
    $assert(file_get_contents($operationPath) === $operationBytes, 'READY operation was not restored byte-for-byte after negative checks.');
    $wrongTarget = new CanonicalTargetInventory([
        new CanonicalTargetInventoryEntry(CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/ReadyResumeMarker.php', strlen($markerBytes), str_repeat('0', 64)),
    ]);
    try {
        $service->resumeCommittedSourceAuthorityFromRuntime($packageManifest, $wrongTarget, $proof);
        $assert(false, 'Stale target inventory was accepted by runtime READY resume.');
    } catch (Throwable) {
        $assert(true, 'Stale target inventory was rejected.');
    }
    $assert(file_get_contents($operationPath) === $operationBytes, 'Stale target rejection mutated authoritative operation bytes.');
    $assert($recoveryBefore === $inventory($recovery), 'Stale target rejection changed recovery evidence.');
    $resumed = $service->resumeCommittedSourceAuthorityFromRuntime($packageManifest, $target, $proof);
    $assert($resumed->plan()->operation()->operationId() === $boundBefore?->operationId(), 'Runtime resume changed operation identity.');
    $assert($resumed->plan()->operation()->executionAttemptIdentity() === $attempt, 'Runtime resume changed execution attempt.');
    $assert($resumed->binding()->crossReference()->recoveryIdentity()->value() === $boundBefore?->recoveryIdentity(), 'Runtime resume changed recovery identity.');
    $assert($recoveryBefore === $inventory($recovery), 'READY runtime resume recaptured recovery evidence.');
    $assert($resumed->activeLock() instanceof \Copot\Core\InstallationLock, 'READY runtime resume did not retain session lock.');

    $db = new Database(new Config($project . DIRECTORY_SEPARATOR . 'config'));
    $dbConnection = $db->connection();
    $dbBefore = (new MySqlRecoveryProvider())->stateIdentity($dbConnection, $scope->databaseIdentity(), $scope);
    $result = $service->executePreparedCommittedSourceAuthority($resumed);
    $assert($result->status() === \Copot\Core\HealthIntegrityCommitResult::COMPLETED, 'Runtime READY WU6 finalization failed: ' . $result->reason());
    $assert($resumed->isClosed(), 'Canonical READY execution did not close its preparation session.');
    $assert($resumed->activeLock() === null, 'Canonical READY execution leaked the InstallationLock.');
    $recoveryComposition = (new ReflectionProperty($service, 'sourceAuthorityRecovery'))->getValue($service);
    $assert(!$recoveryComposition->coordinator()->maintenanceActive($resumed->binding()->crossReference()->recoveryIdentity()), 'Canonical READY execution left the scoped Guard active.');
    $assert((new LifecycleOperationStore($storage))->read() === null, 'Completed runtime operation was not cleared.');
    $committedAfter = (new CommittedLifecycleStateStore($storage))->read();
    $assert($committedAfter?->sourceInventoryReference()?->fingerprint() === $target->fingerprint(), 'Committed source inventory was not published.');
    $assert($committedAfter?->sourceInventoryReference()?->releaseIdentity() === 'ready-resume-release', 'Committed source inventory release binding is wrong.');
    $markerAfter = (new InstallationState($storage))->readMarker();
    $assert($markerAfter === ['version' => $committedAfter?->webcoreVersion(), 'installed_at' => $committedAfter?->committedAt()->format(DATE_ATOM)], 'Installed marker is not linked to committed state.');
    $assert($committedBefore['webcore_version'] === $committedAfter?->webcoreVersion() && $committedBefore['migration_state_identity'] === $committedAfter?->migrationStateIdentity(), 'WU6 changed version or migration identity.');
    $assert($markerBefore === file_get_contents($storage . DIRECTORY_SEPARATOR . 'installed.lock'), 'Authority-only WU6 changed the installed marker bytes unexpectedly.');
    $assert($dbBefore === (new MySqlRecoveryProvider())->stateIdentity($dbConnection, $scope->databaseIdentity(), $scope), 'Authority-only WU6 changed database state.');
    $assert($recoveryBefore === $inventory($recovery), 'WU6 changed recovery artifacts.');
    $assert(file_get_contents($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'ReadyResumeMarker.php') === $markerBytes, 'Authority-only WU6 changed package-owned filesystem.');

    echo "committed_source_authority_ready_resume_runtime: {$assertions} assertions passed\n";
} finally {
    if ($payload instanceof \Copot\Core\StagedPayload) $payload->cleanup();
    try { $server->exec('DROP DATABASE IF EXISTS ' . $databaseQuoted); } catch (Throwable) {}
    $remove($sandbox);
}
