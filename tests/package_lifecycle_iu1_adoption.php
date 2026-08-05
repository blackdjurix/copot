<?php

declare(strict_types=1);

use Copot\Core\CanonicalSchemaBaselineVerifier;
use Copot\Core\CommittedLifecycleStateStore;
use Copot\Core\CoreMigrationHealthVerifier;
use Copot\Core\CoreMigrationLedger;
use Copot\Core\CoreMigrationRegistry;
use Copot\Core\DatabaseHealthVerifier;
use Copot\Core\ExistingInstallEvidence;
use Copot\Core\HealthIntegrityCommitCoordinator;
use Copot\Core\InstallationMutex;
use Copot\Core\InstallationState;
use Copot\Core\InstalledStateInspector;
use Copot\Core\LiveFileActivationCapability;
use Copot\Core\LiveTreePathGuard;
use Copot\Core\MaintenanceCoordinator;
use Copot\Core\PackageApplyTemporaryRoot;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageContract;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageInventoryVerifier;
use Copot\Core\PackageLifecycleService;
use Copot\Core\PackageManifestReader;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageOwnedFileApplier;
use Copot\Core\PackageOwnership;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\RuntimeCompatibilityContext;
use Copot\Core\RuntimeHealthVerifier;
use Copot\Core\StagedArchiveExtractor;
use Copot\Core\TargetPackageIntegrityVerifier;
use Copot\Core\TransitionPlanner;
use Copot\Core\WebcoreApplyCoordinator;
use Copot\Core\ZipIntakeService;

require dirname(__DIR__) . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) { throw new RuntimeException($message); }
};

$remove = static function (string $path) use (&$remove): void {
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) { return; }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') { $remove($path . DIRECTORY_SEPARATOR . $entry); }
    }
    @rmdir($path);
};

$tables = [
    'users' => ['id'], 'roles' => ['id'], 'permissions' => ['id'],
    'user_roles' => ['user_id', 'role_id'], 'role_permissions' => ['role_id', 'permission_id'],
    'settings' => ['id'], 'modules' => ['id'], 'module_permissions' => ['id'],
    'themes' => ['id'], 'content' => ['id'], 'taxonomy_types' => ['id'],
    'taxonomy_terms' => ['id'], 'taxonomy_assignments' => ['taxonomy_term_id'],
    'core_migration_history' => ['migration_id', 'sequence_number', 'target_webcore_version', 'target_schema_identity', 'migration_checksum', 'applied_at'],
];

$makeScenario = static function (string $markerVersion = '1.0.0', string $liveContent = 'target', bool $healthPass = true) use ($tables): array {
    $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-iu1-' . bin2hex(random_bytes(6));
    $storage = $root . DIRECTORY_SEPARATOR . 'storage';
    $live = $root . DIRECTORY_SEPARATOR . 'app';
    mkdir($storage, 0700, true);
    mkdir($live, 0700, true);
    file_put_contents($live . DIRECTORY_SEPARATOR . 'target.txt', $liveContent);

    $schemaPath = $root . DIRECTORY_SEPARATOR . 'schema.sql';
    $schema = '';
    foreach ($tables as $table => $columns) {
        $definitions = array_map(static fn (string $column): string => '`' . $column . '` TEXT', $columns);
        $schema .= 'CREATE TABLE `' . $table . '` (' . "\n  " . implode(",\n  ", $definitions) . "\n);\n";
    }
    file_put_contents($schemaPath, $schema);

    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    foreach ($tables as $table => $columns) {
        $definitions = array_map(static fn (string $column): string => '`' . $column . '` TEXT', $columns);
        $pdo->exec('CREATE TABLE `' . $table . '` (' . implode(', ', $definitions) . ')');
    }

    $state = new InstallationState($storage);
    $state->createMarker($markerVersion);
    $zipPath = $root . DIRECTORY_SEPARATOR . 'package.zip';
    $content = 'target';
    $inventory = [[
        'path' => 'app/target.txt', 'byte_size' => strlen($content),
        'sha256' => hash('sha256', $content), 'ownership' => PackageOwnership::PACKAGE_OWNED,
    ]];
    $manifest = [
        'package_type' => PackageContract::WEBCORE_PACKAGE_TYPE,
        'manifest_contract_version' => PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION,
        'target_webcore_version' => '1.0.0', 'release_identity' => 'trusted-release-1',
        'source_tree_identity' => null,
        'source_compatibility' => ['minimum_source_version' => '0.0.0', 'maximum_source_version' => null],
        'runtime_compatibility' => ['minimum_php_version' => '8.0.0', 'minimum_database_versions' => ['sqlite' => '3.0.0'], 'required_extensions' => ['json', 'pdo', 'pdo_sqlite']],
        'inventory' => $inventory, 'migration_declaration' => ['declares_core_migrations' => false, 'declaration_identity' => null],
    ];
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('.copot/package.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    $zip->addFromString('app/target.txt', $content);
    $zip->close();

    $operationStore = new Copot\Core\LifecycleOperationStore($storage);
    $maintenance = new MaintenanceCoordinator($operationStore);
    $mutex = new InstallationMutex($storage);
    $guard = new LiveTreePathGuard($root);
    $temporary = PackageApplyTemporaryRoot::forProject($root);
    $applier = new PackageOwnedFileApplier($guard, new LiveFileActivationCapability(true, true), $temporary);
    $migrationRunner = static fn (Copot\Core\CoreMigrationPlan $plan): Copot\Core\MigrationRunResult => Copot\Core\MigrationRunResult::noop();
    $database = static fn (): PDO => $pdo;
    $registry = new CoreMigrationRegistry('copot-core-current', []);
    $health = new HealthIntegrityCommitCoordinator(
        $mutex, $maintenance, $state, new CommittedLifecycleStateStore($storage),
        new TargetPackageIntegrityVerifier(), new DatabaseHealthVerifier(), new CoreMigrationHealthVerifier(),
        new RuntimeHealthVerifier(), $registry
    );
    $service = new PackageLifecycleService(
        new ZipIntakeService($root, $root . '-staging'),
        new PackageManifestReader(), new PackageInventoryVerifier(), new InstalledStateInspector(new CommittedLifecycleStateStore($storage)),
        $state, static fn (): ExistingInstallEvidence => new ExistingInstallEvidence(true, true), new TransitionPlanner(),
        new Copot\Core\CoreMigrationPlanner(), $registry, new CoreMigrationLedger(), $database,
        new WebcoreApplyCoordinator($mutex, $maintenance, $applier, $migrationRunner), $health, $guard, $maintenance, $mutex,
        static fn (): RuntimeCompatibilityContext => new RuntimeCompatibilityContext(PHP_VERSION, ['sqlite' => '3.0.0'], get_loaded_extensions()),
        static function () use ($healthPass): array {
            return [
                'bootstrap' => static function () use ($healthPass): bool { return $healthPass; },
                'runtime' => static function () use ($healthPass): bool { return $healthPass; },
            ];
        },
        new CanonicalSchemaBaselineVerifier(), $schemaPath
    );
    return [$root, $service, $zipPath, $state, $pdo, $schemaPath];
};

$scenarios = [];
try {
    [$root, $service, $zip, $state, $pdo] = $makeScenario(); $scenarios[] = $root;
    $result = $service->adopt($zip);
    $assert($result->accepted() && $result->status() === 'completed', 'Exact-match adoption did not complete.');
    $committedStore = new CommittedLifecycleStateStore($root . '/storage');
    $assert((new InstalledStateInspector($committedStore))->inspect($state, new ExistingInstallEvidence(true, true))->status() === Copot\Core\InstalledStateStatus::COMMITTED, 'Adoption did not establish COMMITTED state.');
    $assert($committedStore->read()?->packageIntegrityIdentity() !== null, 'Adoption did not commit package integrity identity.');
    $assert(file_get_contents($root . '/app/target.txt') === 'target', 'Adoption changed a package-owned file.');
    $repeat = $service->adopt($zip);
    $assert(!$repeat->accepted(), 'Committed state was re-adopted.');
    $assert($service->status()['installed_state'] === 'committed', 'Status did not report committed state.');

    [$root, $service, $zip, $state] = $makeScenario('1.1.0'); $scenarios[] = $root;
    $result = $service->adopt($zip);
    $assert(!$result->accepted() && (new InstalledStateInspector(new CommittedLifecycleStateStore($root . '/storage')))->inspect($state, new ExistingInstallEvidence(true, true))->status() === Copot\Core\InstalledStateStatus::LEGACY, 'Version mismatch was not rejected while legacy.');

    [$root, $service, $zip, $state] = $makeScenario('1.0.0', 'drifted'); $scenarios[] = $root;
    $result = $service->adopt($zip);
    $assert(!$result->accepted() && (new InstalledStateInspector(new CommittedLifecycleStateStore($root . '/storage')))->inspect($state, new ExistingInstallEvidence(true, true))->status() === Copot\Core\InstalledStateStatus::LEGACY, 'Package-owned hash drift was not rejected while legacy.');

    [$root, $service, $zip, $state, $pdo] = $makeScenario(); $scenarios[] = $root;
    $pdo->exec('ALTER TABLE users ADD COLUMN unexpected TEXT');
    $result = $service->adopt($zip);
    $assert(!$result->accepted() && (new InstalledStateInspector(new CommittedLifecycleStateStore($root . '/storage')))->inspect($state, new ExistingInstallEvidence(true, true))->status() === Copot\Core\InstalledStateStatus::LEGACY, 'Canonical schema mismatch was not rejected while legacy.');

    [$root, $service, $zip, $state] = $makeScenario('1.0.0', 'target', false); $scenarios[] = $root;
    $result = $service->adopt($zip);
    $assert(!$result->accepted() && (new InstalledStateInspector(new CommittedLifecycleStateStore($root . '/storage')))->inspect($state, new ExistingInstallEvidence(true, true))->status() === Copot\Core\InstalledStateStatus::LEGACY, 'Failed health gate did not preserve legacy state.');
} finally {
    foreach ($scenarios as $scenario) { $remove($scenario); $remove($scenario . '-staging'); }
}

echo "IU1 exact-match adoption: {$assertions} assertions passed\n";
