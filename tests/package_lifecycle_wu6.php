<?php

declare(strict_types=1);

use Copot\Core\CommittedLifecycleState;
use Copot\Core\CommittedLifecycleStateStore;
use Copot\Core\CoreMigrationHealthVerifier;
use Copot\Core\CoreMigrationRegistry;
use Copot\Core\CoreMigrationPlan;
use Copot\Core\CoreMigrationStateIdentity;
use Copot\Core\DatabaseHealthVerifier;
use Copot\Core\ExistingInstallEvidence;
use Copot\Core\HealthGateMatrix;
use Copot\Core\InstalledStateInspection;
use Copot\Core\InstalledStateInspector;
use Copot\Core\InstalledStateStatus;
use Copot\Core\InstallationState;
use Copot\Core\InstallationMutex;
use Copot\Core\LifecycleOperationRecord;
use Copot\Core\LifecycleOperationStore;
use Copot\Core\LiveTreePathGuard;
use Copot\Core\MaintenanceCoordinator;
use Copot\Core\MigrationRunResult;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageContract;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageOwnership;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\RuntimeCompatibilityContext;
use Copot\Core\RuntimeHealthVerifier;
use Copot\Core\StagedFile;
use Copot\Core\StagedPayload;
use Copot\Core\StagingSession;
use Copot\Core\TargetPackageIntegrityVerifier;
use Copot\Core\HealthIntegrityCommitCoordinator;
use Copot\Core\WebcoreApplyPlan;

final class Wu6FakeStatement
{
    public function __construct(private array $rows = [], private mixed $column = null) {}
    public function fetchAll(...$arguments): array { return $this->rows; }
    public function fetchColumn(...$arguments): mixed { return $this->column; }
}

final class Wu6FakePdo extends PDO
{
    public function __construct() {}
    public function query($query, ...$arguments)
    {
        if (str_contains($query, 'SELECT 1')) { return new Wu6FakeStatement([], 1); }
        if (str_contains($query, 'information_schema.tables')) {
            return new Wu6FakeStatement([
                'users', 'roles', 'permissions', 'user_roles', 'role_permissions', 'settings',
                'modules', 'module_permissions', 'themes', 'content', 'taxonomy_types',
                'taxonomy_terms', 'taxonomy_assignments', 'core_migration_history',
            ]);
        }
        if (str_contains($query, 'DESCRIBE core_migration_history')) {
            return new Wu6FakeStatement([
                'migration_id', 'sequence_number', 'target_webcore_version', 'target_schema_identity', 'migration_checksum', 'applied_at',
            ]);
        }
        if (str_contains($query, 'FROM core_migration_history')) { return new Wu6FakeStatement([]); }
        throw new RuntimeException('Unexpected fake database query: ' . $query);
    }
}

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

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

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu6-' . bin2hex(random_bytes(6));
mkdir($root, 0700, true);

try {
    $storage = $root . DIRECTORY_SEPARATOR . 'storage';
    $live = $root . DIRECTORY_SEPARATOR . 'live';
    mkdir($storage, 0700, true);
    mkdir($live . DIRECTORY_SEPARATOR . 'app', 0700, true);
    file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'target.txt', 'target');
    file_put_contents($live . DIRECTORY_SEPARATOR . 'extra.txt', 'unclassified extra');

    $state = new InstallationState($storage);
    $state->createMarker('0.12.0');
    $marker = $state->readMarker();
    $store = new CommittedLifecycleStateStore($storage);
    $inspector = new InstalledStateInspector($store);

    $assert($inspector->inspect($state, new ExistingInstallEvidence())->status() === InstalledStateStatus::LEGACY, 'Marker without rich state was not LEGACY.');

    $committed = new CommittedLifecycleState('0.12.0', 'release-12', 'tree-12', 1, 'schema-12', 'migration-12', new DateTimeImmutable($marker['installed_at']));
    $store->write($committed);
    $inspection = $inspector->inspect($state, new ExistingInstallEvidence());
    $assert($inspection->status() === InstalledStateStatus::COMMITTED, 'Compatible rich state was not COMMITTED.');
    $assert($inspection->snapshot()?->migrationStateIdentity() === 'migration-12', 'Rich migration identity was not exposed through WU3 inspection.');

    $state->replaceMarker('0.12.1', $marker['installed_at']);
    $assert($inspector->inspect($state, new ExistingInstallEvidence())->status() === InstalledStateStatus::INCONSISTENT, 'Marker contradiction was not INCONSISTENT.');
    $state->replaceMarker('0.12.0', $marker['installed_at']);
    file_put_contents($storage . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'committed-state.json', '{bad');
    $assert($inspector->inspect($state, new ExistingInstallEvidence())->status() === InstalledStateStatus::INVALID, 'Malformed rich state was not INVALID.');
    $store->write($committed);

    $hash = hash('sha256', 'target');
    $package = new PackageContract(
        PackageContract::WEBCORE_PACKAGE_TYPE,
        PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION,
        '0.12.0',
        'release-12',
        'tree-12',
        new PackageCompatibility('0.0.0'),
        new PackageRuntimeCompatibility('8.0', ['mysql' => '8.0'], ['json']),
        [new PackageInventoryEntry('app/target.txt', 6, $hash, PackageOwnership::PACKAGE_OWNED)],
        new PackageMigrationDeclaration(false)
    );
    $integrity = (new TargetPackageIntegrityVerifier())->verify($package, new LiveTreePathGuard($live));
    $assert($integrity instanceof HealthGateMatrix && $integrity->passed(), 'Valid target package integrity did not pass.');
    $assert(is_file($live . DIRECTORY_SEPARATOR . 'extra.txt'), 'Integrity verification mutated an unclassified live-tree file.');

    file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'target.txt', 'changed');
    $assert(!(new TargetPackageIntegrityVerifier())->verify($package, new LiveTreePathGuard($live))->passed(), 'Changed target file passed integrity.');

    $runtime = (new RuntimeHealthVerifier())->verify([
        'bootstrap' => static fn (): bool => true,
        'public' => static fn (): bool => true,
        'admin' => static fn (): bool => true,
    ]);
    $assert($runtime->passed(), 'Deterministic runtime health checks did not pass.');
    $failedRuntime = (new RuntimeHealthVerifier())->verify(['theme' => static fn (): bool => false]);
    $assert(!$failedRuntime->passed(), 'Failed runtime health check passed.');

    $makeCleanupScenario = static function (array $stateOverrides = []) use ($root): array {
        $scenario = $root . DIRECTORY_SEPARATOR . 'cleanup-' . bin2hex(random_bytes(4));
        $storage = $scenario . DIRECTORY_SEPARATOR . 'storage';
        $live = $scenario . DIRECTORY_SEPARATOR . 'live';
        $stagingRoot = $scenario . DIRECTORY_SEPARATOR . 'staging';
        mkdir($storage, 0700, true);
        mkdir($live . DIRECTORY_SEPARATOR . 'app', 0700, true);
        mkdir($stagingRoot, 0700, true);
        file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'target.txt', 'target');
        $session = StagingSession::create($live, $stagingRoot);
        mkdir($session->payloadPath(), 0700, true);
        mkdir($session->payloadPath() . DIRECTORY_SEPARATOR . 'app', 0700, true);
        file_put_contents($session->payloadPath() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'target.txt', 'target');
        $file = new StagedFile('app/target.txt', 6, hash('sha256', 'target'));
        $payload = new StagedPayload($session, str_repeat('b', 64), [$file]);
        $applyPlan = WebcoreApplyPlan::fromPayload($payload);
        $package = new PackageContract(
            PackageContract::WEBCORE_PACKAGE_TYPE,
            PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION,
            '0.12.0',
            'release-12',
            'tree-12',
            new PackageCompatibility('0.0.0'),
            new PackageRuntimeCompatibility('8.0', ['mysql' => '8.0'], ['json']),
            [new PackageInventoryEntry('app/target.txt', 6, hash('sha256', 'target'), PackageOwnership::PACKAGE_OWNED)],
            new PackageMigrationDeclaration(false)
        );
        $migrationPlan = CoreMigrationPlan::allow('0.12.0', '0.12.0', 'schema-final', 'schema-final', []);
        $installation = new InstallationState($storage);
        $installation->createMarker('0.12.0');
        $marker = $installation->readMarker();
        $stateData = [
            'webcoreVersion' => '0.12.0',
            'releaseIdentity' => 'release-12',
            'sourceTreeIdentity' => 'tree-12',
            'manifestContractVersion' => 1,
            'schemaStateIdentity' => 'schema-final',
            'migrationStateIdentity' => CoreMigrationStateIdentity::fromRecords([]),
        ];
        foreach ($stateOverrides as $key => $value) { $stateData[$key] = $value; }
        $committedStore = new CommittedLifecycleStateStore($storage);
        $committedStore->write(new CommittedLifecycleState(
            $stateData['webcoreVersion'], $stateData['releaseIdentity'], $stateData['sourceTreeIdentity'],
            $stateData['manifestContractVersion'], $stateData['schemaStateIdentity'], $stateData['migrationStateIdentity'],
            new DateTimeImmutable($marker['installed_at'])
        ));
        $now = gmdate(DATE_ATOM);
        $operation = new LifecycleOperationRecord(
            'cleanup-operation', 'repair', '0.12.0', 'release-12', str_repeat('b', 64), $payload->stagingPath(),
            hash('sha256', $file->path() . ':' . $file->sha256()), $applyPlan->identity(), LifecycleOperationRecord::CLEANUP_PENDING,
            1, $file->path(), hash('sha256', ''), MigrationRunResult::NOOP, $now, $now, 'cleanup pending'
        );
        $operationStore = new LifecycleOperationStore($storage);
        $maintenance = new MaintenanceCoordinator($operationStore);
        $maintenance->enter($operation);
        $coordinator = new HealthIntegrityCommitCoordinator(
            new InstallationMutex($storage), $maintenance, $installation, $committedStore,
            new TargetPackageIntegrityVerifier(), new DatabaseHealthVerifier(), new CoreMigrationHealthVerifier(),
            new RuntimeHealthVerifier(), new CoreMigrationRegistry('registry', [])
        );
        return [$scenario, $coordinator, $package, $applyPlan, $migrationPlan, $live, $maintenance, new Wu6FakePdo()];
    };

    [$scenario, $coordinator, $package, $applyPlan, $migrationPlan, $live, $maintenance, $connection] = $makeCleanupScenario();
    $callbackCalls = 0;
    $result = $coordinator->finalize('cleanup-operation', $package, $applyPlan, $migrationPlan, new LiveTreePathGuard($live), $connection, ['must-not-run' => static function () use (&$callbackCalls): bool { $callbackCalls++; return false; }]);
    $assert($result->status() === 'completed' && !$maintenance->isActive(), 'Exact cleanup-pending reconciliation did not complete.');
    $assert($callbackCalls === 0, 'Cleanup retry reran runtime health work.');
    $remove($scenario);

    foreach ([
        ['schemaStateIdentity', 'different-schema', 'schema mismatch'],
        ['migrationStateIdentity', str_repeat('c', 64), 'migration mismatch'],
        ['sourceTreeIdentity', 'different-tree', 'source-tree mismatch'],
        ['manifestContractVersion', 2, 'manifest mismatch'],
    ] as [$field, $value, $label]) {
        [$scenario, $coordinator, $package, $applyPlan, $migrationPlan, $live, $maintenance, $connection] = $makeCleanupScenario([$field => $value]);
        $result = $coordinator->finalize('cleanup-operation', $package, $applyPlan, $migrationPlan, new LiveTreePathGuard($live), $connection);
        $assert($result->status() === 'failed' && $maintenance->isActive(), 'Cleanup ' . $label . ' was accepted.');
        $remove($scenario);
    }
} finally {
    $remove($root);
}

echo "WU6 health, integrity, and commit-state focused tests passed ({$assertions} assertions)." . PHP_EOL;
