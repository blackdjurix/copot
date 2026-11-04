<?php

declare(strict_types=1);

use Copot\Core\{
    CanonicalTargetInventory,
    CanonicalTargetInventoryEntry,
    CommittedLifecycleStateStore,
    CommittedLifecycleState,
    CoreMigrationLedger,
    CoreMigrationRegistry,
    CoreMigrationPlanner,
    ExistingInstallEvidence,
    InstallationState,
    InstalledStateInspector,
    PackageContract,
    PackageLifecycleService,
    PackageManifestReader,
    PackageInventoryVerifier,
    PackageOwnership,
    SourceInventoryArtifactStore,
    RuntimeCompatibilityContext,
    TransitionPlanner,
    ZipIntakeService
};

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $assertions++;
};

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-v3-db-free-prep-' . bin2hex(random_bytes(5));
$live = $root . DIRECTORY_SEPARATOR . 'live';
$stage = $root . DIRECTORY_SEPARATOR . 'stage';
$storage = $root . DIRECTORY_SEPARATOR . 'storage';
mkdir($live . DIRECTORY_SEPARATOR . 'app', 0700, true);
mkdir($stage, 0700, true);
mkdir($storage, 0700, true);
file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'normal.php', 'normal');

$hash = static fn (string $value): string => hash('sha256', $value);
$entry = new CanonicalTargetInventoryEntry(CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/normal.php', 6, $hash('normal'));
$target = new CanonicalTargetInventory([$entry]);

$makeZip = static function (bool $declaresMigration, bool $databaseRequired = false, bool $targetDatabaseRequired = false) use ($root, $entry, $target, $hash): string {
    $manifest = [
        'package_type' => PackageContract::WEBCORE_PACKAGE_TYPE,
        'manifest_contract_version' => 3,
        'target_webcore_version' => '0.13.0',
        'release_identity' => $declaresMigration ? 'db-free-migration' : ($databaseRequired ? 'db-required' : 'db-free-normal'),
        'source_tree_identity' => 'tree',
        'source_compatibility' => ['minimum_source_version' => '0.13.0', 'maximum_source_version' => null],
        'runtime_compatibility' => ['minimum_php_version' => '8.2.0', 'minimum_database_versions' => $databaseRequired ? ['mysql' => '8.0.0'] : [], 'required_extensions' => ['json']],
        'inventory' => [['path' => 'app/normal.php', 'byte_size' => 6, 'sha256' => $hash('normal'), 'ownership' => PackageOwnership::PACKAGE_OWNED]],
        'migration_declaration' => ['declares_core_migrations' => $declaresMigration, 'declaration_identity' => $declaresMigration ? 'declared' : null],
        'target_requirements' => $targetDatabaseRequired ? [['kind' => 'database', 'owner' => 'core', 'identity' => 'mysql', 'operator' => 'minimum_version', 'value' => '8.0.0', 'mandatory' => true]] : [],
        'coverage' => 'full',
        'target_inventory' => ['serialization_version' => 1, 'fingerprint' => $target->fingerprint(), 'entries' => [$entry->toArray()]],
        'source_state_requirements' => ['allowed_inventory_fingerprints' => []],
        'removals' => [],
        'renames' => [],
    ];
    $zipPath = $root . DIRECTORY_SEPARATOR . ($declaresMigration ? 'migration.zip' : 'normal.zip');
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('ZIP could not be created.');
    $raw = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $zip->addFromString('.copot/package.json', $raw);
    $zip->addFromString('app/normal.php', 'normal');
    $zip->close();
    return $zipPath;
};

$service = (new ReflectionClass(PackageLifecycleService::class))->newInstanceWithoutConstructor();
$set = static function (string $property, mixed $value) use ($service): void {
    $reflection = new ReflectionProperty($service, $property);
    $reflection->setAccessible(true);
    $reflection->setValue($service, $value);
};
$databaseTouched = false;
$set('intake', new ZipIntakeService($live, $stage, null, null, null, 'db-free-test'));
$set('manifestReader', new PackageManifestReader());
$set('inventoryVerifier', new PackageInventoryVerifier());
$installationState = new InstallationState($storage);
$committedStore = new CommittedLifecycleStateStore($storage);
$sourceInventoryArtifacts = new SourceInventoryArtifactStore($storage, static fn ($resource): bool => true);
$sourceArtifact = $sourceInventoryArtifacts->publish($target, 'previous-release', 3, 'previous-tree');
$committedAt = new DateTimeImmutable('now');
$committedStore->write(new CommittedLifecycleState(
    '0.13.0',
    'previous-release',
    'previous-tree',
    3,
    'schema-' . hash('sha256', 'schema'),
    hash('sha256', 'migration-ledger'),
    $committedAt,
    hash('sha256', 'previous-package'),
    $sourceArtifact->reference()
));
$installationState->replaceMarker('0.13.0', $committedAt->format(DATE_ATOM));
$set('installationState', $installationState);
$set('committedStore', $committedStore);
$set('sourceInventoryArtifacts', $sourceInventoryArtifacts);
$set('transitionPlanner', new TransitionPlanner());
$set('installedInspector', new InstalledStateInspector($committedStore));
$set('evidence', static fn (): ExistingInstallEvidence => new ExistingInstallEvidence(true, true));
$set('runtime', static fn (): RuntimeCompatibilityContext => new RuntimeCompatibilityContext(PHP_VERSION, ['mysql' => '8.0.0'], ['json']));
$set('migrationPlanner', new CoreMigrationPlanner());
$set('migrationRegistry', new CoreMigrationRegistry(CoreMigrationRegistry::IDENTITY, []));
$set('ledger', new CoreMigrationLedger());
$set('connection', static function () use (&$databaseTouched): never {
    $databaseTouched = true;
    throw new RuntimeException('Database connection must not be requested by V3 filesystem preparation.');
});

try {
    $normal = $service->plan($makeZip(false));
    $assert($normal->accepted() === true && $normal->status() === 'planned', 'Eligible filesystem-only V3 package was not prepared successfully: ' . $normal->status() . ' / ' . $normal->reason());
    $assert($databaseTouched === false, 'V3 preparation requested a database connection before eligibility was proven.');

    $databaseRequired = $service->plan($makeZip(false, true));
    $assert($databaseRequired->accepted() === false, 'Database-bound V3 package was accepted without database verification.');
    $assert($databaseTouched === true, 'Non-empty database requirement did not reach the database verification path.');

    $databaseTouched = false;
    $targetDatabaseRequired = $service->plan($makeZip(false, false, true));
    $assert($targetDatabaseRequired->accepted() === false, 'V3 package with a database target requirement was accepted without database verification.');
    $assert($databaseTouched === true, 'Database target requirement did not reach the database verification path.');

    $databaseTouched = false;
    $migration = $service->plan($makeZip(true));
    $assert($migration->accepted() === false, 'Migration-bearing V3 package was accepted.');
    $assert(str_contains($migration->reason(), 'database migrations'), 'Migration rejection reason is not explicit.');
    $assert($databaseTouched === false, 'Migration rejection touched the database.');

    echo "Database-free V3 preparation assertions: {$assertions}\n";
} finally {
    $remove = static function (string $path) use (&$remove): void {
        if (is_file($path) || is_link($path)) { @unlink($path); return; }
        if (!is_dir($path)) return;
        foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry);
        @rmdir($path);
    };
    $remove($root);
}
