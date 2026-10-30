<?php

declare(strict_types=1);

use Copot\Core\AppliedMigrationRecord;
use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\CanonicalTargetInventory;
use Copot\Core\CanonicalTargetInventoryEntry;
use Copot\Core\CanonicalSchemaBaselineVerifier;
use Copot\Core\CommittedLifecycleState;
use Copot\Core\CommittedLifecycleStateStore;
use Copot\Core\CommittedSourceAuthorityMetadataGate;
use Copot\Core\CommittedSourceAuthorityPlan;
use Copot\Core\CommittedSourceAuthorityPlanner;
use Copot\Core\CoreMigrationLedger;
use Copot\Core\CoreMigrationRegistry;
use Copot\Core\CoreMigrationStateIdentity;
use Copot\Core\DatabaseTableOwnershipCatalog;
use Copot\Core\InstallationIdentity;
use Copot\Core\InstallationLock;
use Copot\Core\InstallationMutex;
use Copot\Core\InstallationState;
use Copot\Core\BackupRecovery\InstallationScopedMaintenanceGuard;
use Copot\Core\InstallerOwnershipProof;
use Copot\Core\LifecycleExecutionAttemptIdentity;
use Copot\Core\LifecycleOperationRecord;
use Copot\Core\LifecycleOperationStore;
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
use Copot\Core\BackupRecovery\RecoveryIdentity;

require dirname(__DIR__) . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$throws = static function (callable $callback) use ($assert): void {
    try {
        $callback();
    } catch (Throwable) {
        $assert(true, 'Expected first-capture rejection.');
        return;
    }
    $assert(false, 'First-capture invariant was bypassed.');
};
$remove = static function (string $path) use (&$remove): void {
    if (is_file($path) || is_link($path)) { @unlink($path); return; }
    if (!is_dir($path)) { return; }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') { $remove($path . DIRECTORY_SEPARATOR . $entry); }
    }
    @rmdir($path);
};

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-first-capture-' . bin2hex(random_bytes(6));
$storage = $root . DIRECTORY_SEPARATOR . 'storage';
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
    $session = StagingSession::create($live, $root . DIRECTORY_SEPARATOR . 'staging');
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
    $installation = new InstallationIdentity('inst_' . str_repeat('f', 32));
    $installationState = new InstallationState($storage);
    $installationState->createMarker('0.13.0');

    $pdo = new PDO('sqlite:' . $root . DIRECTORY_SEPARATOR . 'first-capture.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE core_migration_history (migration_id TEXT, sequence_number INTEGER, target_webcore_version TEXT, target_schema_identity TEXT, migration_checksum TEXT, applied_at TEXT)');
    $registry = CoreMigrationRegistry::forProject(dirname(__DIR__));
    $descriptor = $registry->migrations()[0];
    $applied = new AppliedMigrationRecord($descriptor->id(), $descriptor->sequence(), $descriptor->targetWebcoreVersion(), $descriptor->targetSchemaIdentity(), $descriptor->checksum(), new DateTimeImmutable());
    $pdo->exec('INSERT INTO core_migration_history VALUES (' . implode(',', array_map(static fn (string $value): string => $pdo->quote($value), [$applied->migrationId(), (string)$applied->sequence(), $applied->targetWebcoreVersion(), $applied->targetSchemaIdentity(), $applied->checksum(), $applied->appliedAt()->format('Y-m-d H:i:s')])) . ')');
    $migrationIdentity = CoreMigrationStateIdentity::fromRecords([$applied]);
    $committed = new CommittedLifecycleState('0.13.0', 'release-test', null, 3, $descriptor->targetSchemaIdentity(), $migrationIdentity, new DateTimeImmutable($installationState->readMarker()['installed_at']));
    $committedStore = new CommittedLifecycleStateStore($storage);
    $committedStore->write($committed);
    $scope = DatabaseScope::fromOwnershipCatalog($installation->value(), 'copot_first_capture', '', DatabaseTableOwnershipCatalog::current());
    $proof = new InstallerOwnershipProof($installation, '', 'core-schema-generation:test', $migrationIdentity);
    $planner = new CommittedSourceAuthorityPlanner();
    $plan = $planner->plan($committed, $installation, $manifest, $target, $scope, $proof);
    $assert($plan->authorityOnly(), 'Fixture did not compose an AUTHORITY_ONLY plan.');

    $emptyOperationsRoot = $root . DIRECTORY_SEPARATOR . 'empty-operations';
    mkdir($emptyOperationsRoot, 0700, true);
    $emptyOperations = new LifecycleOperationStore($emptyOperationsRoot);
    $emptyGate = new CommittedSourceAuthorityMetadataGate(
        $committedStore,
        $installationState,
        new CoreMigrationLedger(),
        static fn (): PDO => $pdo,
        new CanonicalSchemaBaselineVerifier(),
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql',
        $emptyOperations
    );
    $assert($emptyGate->assert($committed) instanceof CommittedLifecycleState, 'Fresh planning gate did not remain available without an operation.');

    $operationsRoot = $root . DIRECTORY_SEPARATOR . 'operations';
    mkdir($operationsRoot, 0700, true);
    $operations = new LifecycleOperationStore($operationsRoot);
    $attemptA = LifecycleExecutionAttemptIdentity::create();
    $attemptB = LifecycleExecutionAttemptIdentity::create();
    $persisted = $plan->operation()->withExecutionAttemptIdentity($attemptA);
    $operations->create($persisted);
    $gate = new CommittedSourceAuthorityMetadataGate(
        $committedStore,
        $installationState,
        new CoreMigrationLedger(),
        static fn (): PDO => $pdo,
        new CanonicalSchemaBaselineVerifier(),
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql',
        $operations
    );

    $boundPlan = $plan->withOperation($persisted);
    $assert($gate->assertFirstCapture($committed, $boundPlan, $persisted) instanceof CommittedLifecycleState, 'Eligible existing first-capture state was rejected.');
    $assert($gate->assertFirstCapture($committed, $boundPlan, (new LifecycleOperationStore($operationsRoot))->read()) instanceof CommittedLifecycleState, 'Fresh-process reread rejected the same exact operation.');
    $assert($gate->assertFirstCapture($committed, $boundPlan, $persisted) instanceof CommittedLifecycleState, 'Duplicate first-capture invocation was not idempotent at the gate.');
    $throws(static fn () => $emptyGate->assertFirstCapture($committed, $boundPlan, $persisted));

    $attemptPlan = $plan->withOperation($plan->operation()->withExecutionAttemptIdentity($attemptB));
    $throws(static fn () => $gate->assertFirstCapture($committed, $attemptPlan, $persisted));

    $historicalRoot = $root . DIRECTORY_SEPARATOR . 'historical-operations';
    mkdir($historicalRoot, 0700, true);
    $historicalOperations = new LifecycleOperationStore($historicalRoot);
    $historical = $plan->operation();
    $historicalOperations->create($historical);
    $historicalGate = new CommittedSourceAuthorityMetadataGate(
        $committedStore,
        $installationState,
        new CoreMigrationLedger(),
        static fn (): PDO => $pdo,
        new CanonicalSchemaBaselineVerifier(),
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql',
        $historicalOperations
    );
    $throws(static fn () => $historicalGate->assertFirstCapture($committed, $historical, $historical));

    $conflict = LifecycleOperationRecord::fromArray(array_replace($persisted->toArray(), ['operation_id' => 'conflicting-operation']));
    $throws(static fn () => $gate->assertFirstCapture($committed, $boundPlan, $conflict));

    $changedPlan = LifecycleOperationRecord::fromArray(array_replace($persisted->toArray(), ['apply_plan_identity' => str_repeat('c', 64)]));
    $throws(static fn () => $gate->assertFirstCapture($committed, $boundPlan, $changedPlan));

    $phaseChanged = $persisted->advance(LifecycleOperationRecord::AWAITING_WU6, 0, null, null, 'fixture');
    $throws(static fn () => $gate->assertFirstCapture($committed, $boundPlan, $phaseChanged));

    $partial = $persisted->bindRecovery('recovery-fixture', str_repeat('d', 64), 'READY');
    $throws(static fn () => $gate->assertFirstCapture($committed, $boundPlan, $partial));

    $serviceSource = file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'PackageLifecycleService.php');
    $gateSource = file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'CommittedSourceAuthorityMetadataGate.php');
    $assert((new ReflectionClass(PackageLifecycleService::class))->hasMethod('continueCommittedSourceAuthorityFirstCaptureFromRuntime'), 'Public first-capture continuation entrypoint is missing.');
    $assert((new ReflectionClass(CommittedSourceAuthorityMetadataGate::class))->hasMethod('assertFirstCapture'), 'Dedicated first-capture metadata gate is missing.');
    $assert(str_contains($serviceSource, 'captureOrRehydrate($plan, $recoveryRequest, $created || $existingFirstCapture)'), 'Capture permission is not explicitly guarded by eligible first-capture state.');
    $assert(str_contains($serviceSource, 'Installation mutex is unavailable for first-capture continuation.'), 'First-capture continuation does not establish a canonical preflight mutex boundary.');
    $assert(str_contains($serviceSource, 'prepareSourceAuthorityPlan($plan, $request, true, false)'), 'First-capture continuation can create a replacement operation after a race.');
    $assert(str_contains($serviceSource, 'scopedMaintenanceGuard->adopt($preflightLock)'), 'First-capture continuation does not transfer the exact preflight lock to the Guard.');
    $assert(str_contains($serviceSource, '$preparation = $this->prepareSourceAuthorityPlan'), 'First-capture continuation does not retain the preparation owner through return.');
    $assert(str_contains($gateSource, 'recoveryIdentity() !== null') && str_contains($gateSource, 'executionAttemptIdentity()'), 'First-capture gate does not enforce absent recovery and attempt-aware state.');
    $assert(str_contains($gateSource, '$expected->executionAttemptIdentity() === null'), 'First-capture gate still permits a null candidate execution attempt.');

    $lockRoot = $root . DIRECTORY_SEPARATOR . 'lock-ownership';
    mkdir($lockRoot, 0700, true);
    $lockMutex = new InstallationMutex($lockRoot);
    $serviceLock = $lockMutex->acquire();
    $assert($serviceLock instanceof InstallationLock, 'Fixture could not acquire the service InstallationLock.');
    $guard = new InstallationScopedMaintenanceGuard($lockMutex, $lockRoot);
    $guard->adopt($serviceLock);
    $lockIdentity = new RecoveryIdentity('lock-transfer-fixture');
    $assert($guard->enter($lockIdentity), 'Guard did not adopt the service-owned InstallationLock.');
    $assert($guard->activeLock() === $serviceLock, 'Guard did not retain the exact InstallationLock object.');
    $assert($lockMutex->acquire() === null, 'A nested InstallationMutex acquisition was possible during scoped admission.');
    $assert($guard->leave($lockIdentity), 'Guard did not release the transferred InstallationLock.');
    $replacementLock = $lockMutex->acquire();
    $assert($replacementLock instanceof InstallationLock, 'InstallationLock was leaked after canonical Guard release.');
    $replacementLock->release();

    echo "committed_source_authority_first_capture_continuation: {$assertions} assertions passed\n";
} finally {
    if ($session instanceof StagingSession) { $session->cleanup(); }
    $remove($root);
}
