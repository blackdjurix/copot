<?php

declare(strict_types=1);

use Copot\Core\CanonicalSchemaBaselineVerifier;
use Copot\Core\CoreMigrationDescriptor;
use Copot\Core\CoreMigrationLedger;
use Copot\Core\CoreMigrationRegistry;
use Copot\Core\ExistingInstallEvidence;
use Copot\Core\FilesystemReconciliationAction;
use Copot\Core\InstallationState;
use Copot\Core\InstalledStateInspection;
use Copot\Core\InstalledStateSnapshot;
use Copot\Core\LegacyClassification;
use Copot\Core\LegacyReconciliationPlanner;
use Copot\Core\LegacyRuntimeClassifier;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageContract;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageInventoryVerifier;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageManifest;
use Copot\Core\PackageOwnership;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\RuntimeCompatibilityContext;
use Copot\Core\StagedFile;
use Copot\Core\StagedPayload;
use Copot\Core\StagingSession;
use Copot\Core\TrustedWebcorePackageTarget;
use Copot\Core\LiveTreePathGuard;

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

$remove = static function (string $path) use (&$remove): void {
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) { return; }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') { $remove($path . DIRECTORY_SEPARATOR . $entry); }
    }
    @rmdir($path);
};

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-iu2-wu1-' . bin2hex(random_bytes(6));
$storage = $root . DIRECTORY_SEPARATOR . 'storage';
$stagingRoot = $root . DIRECTORY_SEPARATOR . 'staging';
$schemaPath = $root . DIRECTORY_SEPARATOR . 'schema.sql';
mkdir($storage, 0700, true);
mkdir($stagingRoot, 0700, true);

try {
    $pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $schema = <<<'SQL'
CREATE TABLE `users` (
  `id` TEXT
);
CREATE TABLE `core_migration_history` (
  `migration_id` TEXT,
  `sequence_number` TEXT,
  `target_webcore_version` TEXT,
  `target_schema_identity` TEXT,
  `migration_checksum` TEXT,
  `applied_at` TEXT
);
SQL;
    file_put_contents($schemaPath, $schema);
    $pdo->exec($schema);

    $state = new InstallationState($storage);
    $state->createMarker('0.8.0');
    $installed = (new \Copot\Core\InstalledStateInspector())->inspect($state, new ExistingInstallEvidence(true, true));
    $registry = new CoreMigrationRegistry('core-set-1', []);
    $classifier = new LegacyRuntimeClassifier(new CanonicalSchemaBaselineVerifier());
    $canonical = $classifier->classify($installed, $pdo, $schemaPath, $registry);
    $assert($canonical->classification() === LegacyClassification::CANONICAL_SCHEMA_BASELINE, 'Canonical baseline was not classified deterministically.');

    $pdo->exec("INSERT INTO core_migration_history VALUES ('unknown', '10', '0.11.0', 'schema-11', '" . str_repeat('a', 64) . "', '2026-01-01')");
    $unknown = $classifier->classify($installed, $pdo, $schemaPath, $registry);
    $assert($unknown->classification() === LegacyClassification::UNKNOWN_OR_UNPROVABLE, 'Unknown migration history was accepted.');
    $pdo->exec('DELETE FROM core_migration_history');

    $committed = $classifier->classify(
        InstalledStateInspection::committed(new InstalledStateSnapshot('0.13.0', new DateTimeImmutable(), 'release', null, 1, 'schema', 'migration')),
        $pdo,
        $schemaPath,
        $registry
    );
    $assert($committed->classification() === LegacyClassification::COMMITTED_LIFECYCLE_STATE, 'Committed state was not classified separately.');
    $session = StagingSession::create($root, $stagingRoot);
    mkdir($session->payloadPath() . DIRECTORY_SEPARATOR . 'app', 0700, true);
    file_put_contents($session->payloadPath() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'target.txt', 'target');
    $targetFile = new StagedFile('app/target.txt', 6, hash('sha256', 'target'));
    $payload = new StagedPayload($session, str_repeat('b', 64), [$targetFile]);
    $package = new PackageContract(
        PackageContract::WEBCORE_PACKAGE_TYPE,
        PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION,
        '0.13.0',
        'trusted-release',
        null,
        new PackageCompatibility('0.0.0'),
        new PackageRuntimeCompatibility('8.0.0', ['sqlite' => '3.0.0'], ['json']),
        [new PackageInventoryEntry('app/target.txt', 6, hash('sha256', 'target'), PackageOwnership::PACKAGE_OWNED)],
        new PackageMigrationDeclaration(false)
    );
    $target = TrustedWebcorePackageTarget::fromManifest(new PackageManifest($package, '.copot/package.json', $payload), new PackageInventoryVerifier());
    try {
        (new LegacyReconciliationPlanner())->plan(
            $target, $committed, new RuntimeCompatibilityContext(PHP_VERSION, ['sqlite' => '3.0'], ['json']), $registry, new LiveTreePathGuard($root)
        );
        $assert(false, 'Committed lifecycle state produced a reconciliation plan.');
    } catch (Throwable) {
        $assert(true, 'Committed lifecycle state was excluded from reconciliation planning.');
    }
    $payload->cleanup();
} catch (PDOException $exception) {
    if (str_contains(strtolower($exception->getMessage()), 'could not find driver')) {
        fwrite(STDERR, "WU1 focused tests unavailable: PDO SQLite driver is unavailable.\n");
        exit(77);
    }
    throw $exception;
} finally {
    $remove($root);
}

echo "IU2 WU1 classification and planning: {$assertions} assertions passed\n";
