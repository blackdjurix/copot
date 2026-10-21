<?php

declare(strict_types=1);

use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\CanonicalTargetInventory;
use Copot\Core\CanonicalTargetInventoryEntry;
use Copot\Core\CommittedLifecycleState;
use Copot\Core\CommittedLifecycleStateStore;
use Copot\Core\CommittedLifecycleMetadataReconciliationService;
use Copot\Core\CommittedSourceAuthorityPlan;
use Copot\Core\AppliedMigrationRecord;
use Copot\Core\CoreMigrationLedger;
use Copot\Core\CoreMigrationRegistry;
use Copot\Core\CoreMigrationStateIdentity;
use Copot\Core\DatabaseTableOwnershipCatalog;
use Copot\Core\InstallationIdentity;
use Copot\Core\InstallationState;
use Copot\Core\InstallerOwnershipProof;
use Copot\Core\LifecycleOperationStore;
use Copot\Core\LifecycleOperationRecord;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageContract;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageLifecycleService;
use Copot\Core\PackageManifest;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\PackageV3Identity;
use Copot\Core\StagedFile;
use Copot\Core\StagedPayload;
use Copot\Core\StagingSession;

require dirname(__DIR__) . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$remove = static function (string $path) use (&$remove): void {
    if (is_file($path) || is_link($path)) { @unlink($path); return; }
    if (!is_dir($path)) { return; }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') { $remove($path . DIRECTORY_SEPARATOR . $entry); }
    }
    @rmdir($path);
};

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-source-authority-gate-' . bin2hex(random_bytes(6));
$storage = $root . DIRECTORY_SEPARATOR . 'storage';
$stagingRoot = $root . DIRECTORY_SEPARATOR . 'staging';
mkdir($storage, 0700, true);
$session = null;

try {
    $content = 'target';
    $sha = hash('sha256', $content);
    $target = new CanonicalTargetInventory([
        new CanonicalTargetInventoryEntry('APP_ROOT', CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/target.txt', strlen($content), $sha),
    ]);
    $live = $root . DIRECTORY_SEPARATOR . 'live';
    mkdir($live, 0700, true);
    $session = StagingSession::create($live, $stagingRoot);
    $payload = new StagedPayload($session, str_repeat('a', 64), [new StagedFile('app/target.txt', strlen($content), $sha)]);
    $raw = str_repeat('b', 64);
    $package = new PackageContract(
        PackageContract::WEBCORE_PACKAGE_TYPE,
        PackageContract::V3_MANIFEST_CONTRACT_VERSION,
        '0.13.0',
        'release-test',
        'tree-test',
        new PackageCompatibility('0.13.0'),
        new PackageRuntimeCompatibility('8.0', ['sqlite' => '3.0'], ['json']),
        [new PackageInventoryEntry('app/target.txt', strlen($content), $sha)],
        new PackageMigrationDeclaration(false),
        null,
        $target,
        'full'
    );
    $manifest = new PackageManifest($package, '.copot/package.json', $payload, $raw, PackageV3Identity::identity($raw, $package->integrityIdentity()));
    $installation = new InstallationIdentity('inst_' . str_repeat('4', 32));
    $freshMigrationIdentity = CoreMigrationStateIdentity::fromRecords([]);
    $scope = DatabaseScope::fromOwnershipCatalog($installation->value(), 'copot_gate', '', DatabaseTableOwnershipCatalog::current());
    $proof = new InstallerOwnershipProof($installation, '', 'core-schema-generation:test', $freshMigrationIdentity);
    $pdo = new PDO('sqlite:' . $root . DIRECTORY_SEPARATOR . 'gate.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE core_migration_history (migration_id TEXT, sequence_number INTEGER, target_webcore_version TEXT, target_schema_identity TEXT, migration_checksum TEXT, applied_at TEXT)');
    mkdir($root . DIRECTORY_SEPARATOR . 'operations', 0700, true);
    $operationStore = new LifecycleOperationStore($root . DIRECTORY_SEPARATOR . 'operations');

    $installationState = new InstallationState($storage);
    $installationState->createMarker('0.13.0');
    $committedStore = new CommittedLifecycleStateStore($storage);
    $stale = new CommittedLifecycleState('0.13.0', 'release-test', null, 3, 'schema-test', str_repeat('c', 64), new DateTimeImmutable($installationState->readMarker()['installed_at']));
    $committedStore->write($stale);

    $service = (new ReflectionClass(PackageLifecycleService::class))->newInstanceWithoutConstructor();
    $set = static function (string $property, mixed $value) use ($service): void {
        $reflection = new ReflectionProperty($service, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($service, $value);
    };
    $set('sourceAuthorityPlanner', new Copot\Core\CommittedSourceAuthorityPlanner());
    $set('committedStore', $committedStore);
    $set('installationState', $installationState);
    $set('ledger', new CoreMigrationLedger());
    $set('connection', static fn (): PDO => $pdo);
    $set('operationStore', $operationStore);
    $set('canonicalSchema', new Copot\Core\CanonicalSchemaBaselineVerifier());
    $set('canonicalSchemaPath', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql');

    $throws = static function (callable $callback) use ($assert): void {
        try { $callback(); } catch (Throwable) { $assert(true, 'Expected stale metadata rejection.'); return; }
        $assert(false, 'Stale metadata bypassed the source-authority planning gate.');
    };
    $empty = new CommittedLifecycleState('0.13.0', 'release-test', null, 3, 'schema-test', $freshMigrationIdentity, new DateTimeImmutable($installationState->readMarker()['installed_at']));
    $committedStore->write($empty);
    $throws(static fn () => $service->planCommittedSourceAuthority($empty, $installation, $manifest, $target, $scope, $proof));
    $committedStore->write($stale);
    $throws(static fn () => $service->planCommittedSourceAuthority($stale, $installation, $manifest, $target, $scope, $proof));

    $registry = CoreMigrationRegistry::forProject(dirname(__DIR__));
    $migration = $registry->migrations()[0];
    $record = new AppliedMigrationRecord($migration->id(), $migration->sequence(), $migration->targetWebcoreVersion(), $migration->targetSchemaIdentity(), $migration->checksum(), new DateTimeImmutable());
    $pdo->exec('INSERT INTO core_migration_history VALUES (' . implode(',', array_map(static fn (string $value): string => $pdo->quote($value), [$record->migrationId(), (string) $record->sequence(), $record->targetWebcoreVersion(), $record->targetSchemaIdentity(), $record->checksum(), $record->appliedAt()->format('Y-m-d H:i:s')])) . ')');
    $freshMigrationIdentity = CoreMigrationStateIdentity::fromRecords([$record]);
    $proof = new InstallerOwnershipProof($installation, '', 'core-schema-generation:test', $freshMigrationIdentity);
    $fresh = new CommittedLifecycleState('0.13.0', 'release-test', null, 3, $migration->targetSchemaIdentity(), $freshMigrationIdentity, new DateTimeImmutable($installationState->readMarker()['installed_at']));
    $committedStore->write($fresh);
    $plan = $service->planCommittedSourceAuthority($fresh, $installation, $manifest, $target, $scope, $proof);
    $assert($plan instanceof CommittedSourceAuthorityPlan, 'Fresh metadata did not produce a source-authority plan.');
    $assert($plan->authorityOnly(), 'Fresh exact target did not produce AUTHORITY_ONLY mode.');
    $assert($plan->committedLifecycleIdentity() === Copot\Core\CommittedLifecycleMetadataReconciliationService::stateIdentity($fresh), 'Replanned source authority did not bind the reread committed identity.');
    $assert($plan->operation()->phase() === Copot\Core\LifecycleOperationRecord::PREPARING, 'Planning crossed the persisted operation boundary.');

    $markerPath = $storage . DIRECTORY_SEPARATOR . 'installed.lock';
    $beforeMarkerBytes = file_get_contents($markerPath);
    $beforeCommittedIdentity = Copot\Core\CommittedLifecycleMetadataReconciliationService::stateIdentity($committedStore->read());
    $beforeLedgerCount = (int) $pdo->query('SELECT COUNT(*) FROM core_migration_history')->fetchColumn();
    foreach ([
        'BLOCKED' => LifecycleOperationRecord::BLOCKED,
        'INDETERMINATE' => LifecycleOperationRecord::INDETERMINATE,
        'RECOVERY_REQUIRED' => LifecycleOperationRecord::BLOCKED,
    ] as $label => $phase) {
        $scenarioRoot = $root . DIRECTORY_SEPARATOR . 'operations-' . strtolower($label);
        mkdir($scenarioRoot, 0700, true);
        $scenarioStore = new LifecycleOperationStore($scenarioRoot);
        $metadataOperation = new LifecycleOperationRecord(
            'metadata-fixture-' . strtolower($label),
            CommittedLifecycleMetadataReconciliationService::classify(),
            $fresh->webcoreVersion(),
            $fresh->releaseIdentity(),
            hash('sha256', $label),
            'metadata-reconciliation://' . strtolower($label),
            hash('sha256', 'payload-' . $label),
            hash('sha256', 'plan-' . $label),
            LifecycleOperationRecord::PREPARING,
            0,
            null,
            null,
            null,
            gmdate(DATE_ATOM),
            gmdate(DATE_ATOM),
            'Persisted metadata reconciliation failure fixture.'
        );
        if ($label === 'RECOVERY_REQUIRED') {
            $metadataOperation = $metadataOperation->bindRecovery('recovery-required-' . strtolower($label), 'manifest-' . strtolower($label), 'required');
        }
        $metadataOperation = $metadataOperation->advance($phase, 0, null, null, 'Persisted ' . $label . ' metadata reconciliation fixture.');
        $scenarioStore->create($metadataOperation);
        $set('operationStore', $scenarioStore);
        $persistedBefore = $scenarioStore->read()?->toArray();
        $throws(static fn () => $service->planCommittedSourceAuthority($fresh, $installation, $manifest, $target, $scope, $proof));
        $assert($scenarioStore->read()?->toArray() === $persistedBefore, $label . ' gate attempt mutated the operation journal.');
        $assert(file_get_contents($markerPath) === $beforeMarkerBytes, $label . ' gate attempt mutated the installed marker.');
        $assert(Copot\Core\CommittedLifecycleMetadataReconciliationService::stateIdentity($committedStore->read()) === $beforeCommittedIdentity, $label . ' gate attempt mutated committed metadata.');
        $assert((int) $pdo->query('SELECT COUNT(*) FROM core_migration_history')->fetchColumn() === $beforeLedgerCount, $label . ' gate attempt mutated the migration ledger.');
    }
    $set('operationStore', $operationStore);
    $changed = new CommittedLifecycleState('0.13.0', 'release-test', null, 3, 'changed-schema', $freshMigrationIdentity, new DateTimeImmutable($installationState->readMarker()['installed_at']));
    $committedStore->write($changed);
    $throws(static fn () => $service->planCommittedSourceAuthority($fresh, $installation, $manifest, $target, $scope, $proof));
    $committedStore->write($fresh);
    $replanned = $service->planCommittedSourceAuthority($fresh, $installation, $manifest, $target, $scope, $proof);
    $assert($replanned->committedLifecycleIdentity() === Copot\Core\CommittedLifecycleMetadataReconciliationService::stateIdentity($fresh), 'Successful metadata correction did not force a fresh reread/replan identity.');
    $operationStore->create($plan->operation());
    $throws(static fn () => $service->planCommittedSourceAuthority($fresh, $installation, $manifest, $target, $scope, $proof));

    echo "Committed source-authority metadata gate assertions: {$assertions}" . PHP_EOL;
} finally {
    if ($session instanceof StagingSession) { $session->cleanup(); }
    $remove($root);
}
