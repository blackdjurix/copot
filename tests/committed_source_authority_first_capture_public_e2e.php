<?php

declare(strict_types=1);

use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\CanonicalTargetInventory;
use Copot\Core\CanonicalTargetInventoryEntry;
use Copot\Core\CommittedLifecycleState;
use Copot\Core\CommittedLifecycleStateStore;
use Copot\Core\Config;
use Copot\Core\CoreMigrationLedger;
use Copot\Core\CoreMigrationStateIdentity;
use Copot\Core\Database;
use Copot\Core\DatabaseTableNames;
use Copot\Core\DatabaseTableOwnershipCatalog;
use Copot\Core\Env;
use Copot\Core\InstallationIdentityStore;
use Copot\Core\InstallationState;
use Copot\Core\InstallerOwnershipProof;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\LifecycleExecutionAttemptIdentity;
use Copot\Core\LifecycleOperationRecord;
use Copot\Core\LifecycleOperationStore;
use Copot\Core\PackageContract;
use Copot\Core\PackageLifecycleFactory;
use Copot\Core\PackageLifecycleService;
use Copot\Core\PackageManifestReader;
use Copot\Core\PackageOwnership;
use Copot\Core\PackageTargetRequirements;
use Copot\Core\CoreSchemaGenerationStore;
use Copot\Core\CoreSchemaMaterializer;
use Copot\Core\CanonicalSchemaBaselineVerifier;
use Copot\Core\CoreMigrationRegistry;
use Copot\Core\DeploymentContext;
use Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureService;
use Copot\Core\BackupRecovery\RecoveryArtifactRecord;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\RecoveryDomainIdentity;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryLifecycleRecord;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;
use Copot\Core\BackupRecovery\RecoveryManifest;
use Copot\Core\BackupRecovery\RecoveryManifestCodec;
use Copot\Core\BackupRecovery\RecoveryRootResolver;
use Copot\Core\BackupRecovery\RecoveryStoragePathPolicy;
use Copot\Core\BackupRecovery\RecoveryStorageRoot;

$base = dirname(__DIR__);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;
    if (!$condition) throw new RuntimeException($message);
};
$copy = static function (string $source, string $destination) use (&$copy): void {
    if (is_dir($source)) {
        if (!is_dir($destination) && !mkdir($destination, 0700, true) && !is_dir($destination)) throw new RuntimeException('Fixture directory could not be created.');
        foreach (scandir($source) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.git' || $entry === 'storage') continue;
            $copy($source . DIRECTORY_SEPARATOR . $entry, $destination . DIRECTORY_SEPARATOR . $entry);
        }
        return;
    }
    if (!is_file($source) || !copy($source, $destination)) throw new RuntimeException('Fixture copy failed: ' . $source);
};
$remove = static function (string $path) use (&$remove): void {
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry);
        @rmdir($path);
    } elseif (is_file($path) || is_link($path)) @unlink($path);
};

$host = (string) (getenv('COPOT_TEST_DB_HOST') ?: '127.0.0.1');
$port = (int) (getenv('COPOT_TEST_DB_PORT') ?: 3306);
$username = (string) (getenv('COPOT_TEST_DB_USERNAME') ?: 'root');
$password = (string) (getenv('COPOT_TEST_DB_PASSWORD') ?: '');
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'copot_first_public_' . bin2hex(random_bytes(6));
$sandbox = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-first-public-' . bin2hex(random_bytes(6));
$project = $sandbox . DIRECTORY_SEPARATOR . 'project';
$public = $project . DIRECTORY_SEPARATOR . 'public';
$recovery = $sandbox . DIRECTORY_SEPARATOR . 'recovery';
$zipPath = $sandbox . DIRECTORY_SEPARATOR . 'first-capture.zip';
$databaseQuoted = '`' . $database . '`';

try {
    $server->exec('CREATE DATABASE ' . $databaseQuoted . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $copy($base, $project);
    mkdir($project . DIRECTORY_SEPARATOR . 'storage', 0700, true);
    mkdir($recovery, 0700, true);
    $markerPath = $project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'FirstCaptureMarker.php';
    $markerBytes = "<?php\nreturn 'first-capture';\n";
    file_put_contents($markerPath, $markerBytes);
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
    $generation = (new CoreSchemaMaterializer())->generationIdentity($project . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql');
    (new CoreSchemaGenerationStore($tables))->record($pdo, $generation, 'compatible', '');
    $ledgerIdentity = CoreMigrationStateIdentity::fromRecords((new CoreMigrationLedger($tables))->records($pdo));

    $storage = $project . DIRECTORY_SEPARATOR . 'storage';
    $installationStore = new InstallationIdentityStore($storage);
    $installation = $installationStore->getOrCreate();
    $installationState = new InstallationState($storage);
    $installationState->createMarker('0.13.0');
    $marker = $installationState->readMarker();
    $canonicalSchema = (new CanonicalSchemaBaselineVerifier())->identity($project . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql');
    (new CommittedLifecycleStateStore($storage))->write(new CommittedLifecycleState(
        '0.13.0', 'first-capture-release', null, 3, $canonicalSchema, $ledgerIdentity,
        new DateTimeImmutable((string) $marker['installed_at'])
    ));

    $contentSha = hash('sha256', $markerBytes);
    $target = new CanonicalTargetInventory([
        new CanonicalTargetInventoryEntry(CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/FirstCaptureMarker.php', strlen($markerBytes), $contentSha),
    ]);
    $manifest = [
        'package_type' => PackageContract::WEBCORE_PACKAGE_TYPE,
        'manifest_contract_version' => PackageContract::V3_MANIFEST_CONTRACT_VERSION,
        'target_webcore_version' => '0.13.0', 'release_identity' => 'first-capture-release', 'source_tree_identity' => null,
        'source_compatibility' => ['minimum_source_version' => '0.13.0', 'maximum_source_version' => null],
        'runtime_compatibility' => ['minimum_php_version' => '8.0.0', 'minimum_database_versions' => ['mysql' => '10.0'], 'required_extensions' => ['json', 'pdo', 'pdo_mysql', 'zip']],
        'inventory' => [['path' => 'app/FirstCaptureMarker.php', 'byte_size' => strlen($markerBytes), 'sha256' => $contentSha, 'ownership' => PackageOwnership::PACKAGE_OWNED]],
        'migration_declaration' => ['declares_core_migrations' => false, 'declaration_identity' => null],
        'target_requirements' => [], 'coverage' => 'full',
        'target_inventory' => ['serialization_version' => 1, 'fingerprint' => $target->fingerprint(), 'entries' => [[
            'root' => CanonicalTargetInventoryEntry::APP_ROOT, 'ownership' => CanonicalTargetInventoryEntry::PACKAGE_OWNED,
            'path' => 'app/FirstCaptureMarker.php', 'byte_size' => strlen($markerBytes), 'sha256' => $contentSha,
        ]]],
        'source_state_requirements' => ['allowed_inventory_fingerprints' => []], 'removals' => [], 'renames' => [],
    ];
    $zip = new ZipArchive();
    $assert($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'First-capture package could not be created.');
    $zip->addFromString('.copot/package.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    $zip->addFromString('app/FirstCaptureMarker.php', $markerBytes);
    $zip->close();

    $service = PackageLifecycleFactory::forProject(new DeploymentContext($project, $public, '/first-capture'));
    $assert($service instanceof PackageLifecycleService, 'Production factory did not construct PackageLifecycleService.');
    $intake = (new ReflectionProperty($service, 'intake'))->getValue($service);
    $reader = (new ReflectionProperty($service, 'manifestReader'))->getValue($service);
    $payload = $intake->intake($zipPath);
    $packageManifest = $reader->read($payload);
    $databaseIdentity = (string) (new ReflectionProperty($service, 'authorityDatabaseIdentity'))->getValue($service);
    $namespace = (string) (new ReflectionProperty($service, 'authorityNamespaceIdentity'))->getValue($service);
    $scope = DatabaseScope::fromOwnershipCatalog($installation->value(), $databaseIdentity, $namespace, DatabaseTableOwnershipCatalog::current(), [], ['namespace' => $namespace, 'schema_generation' => $generation]);
    $proof = new InstallerOwnershipProof($installation, $namespace, $generation, $ledgerIdentity);
    $plan = $service->planCommittedSourceAuthority((new CommittedLifecycleStateStore($storage))->read(), $installation, $packageManifest, $target, $scope, $proof);
    $assert($plan->authorityOnly(), 'Public fixture did not compose authority-only planning.');
    $attempt = LifecycleExecutionAttemptIdentity::create();
    $operation = $plan->operation()->withExecutionAttemptIdentity($attempt);
    $operations = new LifecycleOperationStore($storage);
    $operations->create($operation);

    $operationPath = $storage . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'active-operation.json';
    $committedPath = $storage . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'committed-state.json';
    $markerPath = $storage . DIRECTORY_SEPARATOR . 'installed.lock';
    $inventory = static function (string $root): array {
        $result = [];
        if (!is_dir($root)) return $result;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || $file->isLink()) continue;
            $path = $file->getPathname();
            $relative = substr($path, strlen(rtrim($root, '/\\')) + 1);
            $result[$relative] = ['bytes' => (string) file_get_contents($path), 'size' => $file->getSize(), 'sha256' => hash_file('sha256', $path)];
        }
        ksort($result, SORT_STRING);
        return $result;
    };
    $snapshot = static function () use ($operationPath, $committedPath, $markerPath, $recovery, $inventory, $storage, $service): array {
        $guard = (new ReflectionProperty($service, 'scopedMaintenanceGuard'))->getValue($service);
        $probeMutex = new \Copot\Core\InstallationMutex($storage);
        $probeLock = $probeMutex->acquire();
        $available = $probeLock instanceof \Copot\Core\InstallationLock;
        if ($probeLock instanceof \Copot\Core\InstallationLock) $probeLock->release();
        return [
            'operation_bytes' => is_file($operationPath) ? (string) file_get_contents($operationPath) : null,
            'operation_identity' => (new LifecycleOperationStore($storage))->read()?->operationId(),
            'execution_attempt' => (new LifecycleOperationStore($storage))->read()?->executionAttemptIdentity(),
            'committed_bytes' => is_file($committedPath) ? (string) file_get_contents($committedPath) : null,
            'marker_bytes' => is_file($markerPath) ? (string) file_get_contents($markerPath) : null,
            'recovery_inventory' => $inventory($recovery),
            'guard_owns_lock' => $guard?->activeLock() instanceof \Copot\Core\InstallationLock,
            'lock_available' => $available,
        ];
    };
    $assertPreserved = static function (array $before, array $after, string $label) use ($assert): void {
        $assert($before['operation_bytes'] === $after['operation_bytes'], $label . ': persisted operation bytes changed.');
        $assert($before['operation_identity'] === $after['operation_identity'], $label . ': operation identity changed.');
        $assert($before['execution_attempt'] === $after['execution_attempt'], $label . ': execution attempt changed.');
        $assert($before['committed_bytes'] === $after['committed_bytes'], $label . ': committed-state bytes changed.');
        $assert($before['marker_bytes'] === $after['marker_bytes'], $label . ': installation marker bytes changed.');
        $assert($before['recovery_inventory'] === $after['recovery_inventory'], $label . ': recovery artifact inventory changed.');
        $assert($after['guard_owns_lock'] === false, $label . ': Guard retained a lock after failure.');
        $assert($after['lock_available'] === true, $label . ': InstallationLock could not be reacquired after failure.');
    };
    $writeOperation = static function (?LifecycleOperationRecord $record) use ($operationPath): void {
        if ($record === null) {
            if (is_file($operationPath)) @unlink($operationPath);
            return;
        }
        file_put_contents($operationPath, json_encode($record->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    };
    $expectPublicFailure = static function (string $label, ?LifecycleOperationRecord $fixtureOperation) use ($service, $snapshot, $assertPreserved, $assert, $packageManifest, $target, $proof, $writeOperation, $operation): void {
        $writeOperation($fixtureOperation);
        $before = $snapshot();
        $failed = false;
        try {
            $service->continueCommittedSourceAuthorityFirstCaptureFromRuntime($packageManifest, $target, $proof);
        } catch (Throwable) {
            $failed = true;
        }
        $after = $snapshot();
        $assert($failed, $label . ': public continuation unexpectedly succeeded.');
        $assertPreserved($before, $after, $label);
        $writeOperation($operation);
    };
    $historical = $plan->operation();
    $conflicting = LifecycleOperationRecord::fromArray(array_replace($operation->toArray(), ['operation_id' => 'public-conflicting-operation']));
    $changedApplyPlan = LifecycleOperationRecord::fromArray(array_replace($operation->toArray(), ['apply_plan_identity' => str_repeat('e', 64)]));
    $partial = $operation->bindRecovery('public-partial-recovery', str_repeat('f', 64), 'READY');

    $expectPublicFailure('missing persisted operation', null);
    $expectPublicFailure('null execution attempt', $historical);
    $expectPublicFailure('conflicting persisted operation', $conflicting);
    $expectPublicFailure('apply-plan identity mismatch', $changedApplyPlan);
    $expectPublicFailure('partial recovery evidence', $partial);

    $beforePreAdoption = $snapshot();
    $blockingMutex = new \Copot\Core\InstallationMutex($storage);
    $blockingLock = $blockingMutex->acquire();
    $assert($blockingLock instanceof \Copot\Core\InstallationLock, 'Pre-adoption fixture could not acquire blocking InstallationLock.');
    $preAdoptionFailed = false;
    try {
        $service->continueCommittedSourceAuthorityFirstCaptureFromRuntime($packageManifest, $target, $proof);
    } catch (Throwable) {
        $preAdoptionFailed = true;
    } finally {
        $blockingLock->release();
    }
    $afterPreAdoption = $snapshot();
    $assert($preAdoptionFailed, 'Failure-before-adoption fixture unexpectedly succeeded.');
    $assertPreserved($beforePreAdoption, $afterPreAdoption, 'failure before Guard adoption');

    $writeOperation($conflicting);
    $beforePostAdoption = $snapshot();
    $postAdoptionFailed = false;
    try {
        $service->continueCommittedSourceAuthorityFirstCaptureFromRuntime($packageManifest, $target, $proof);
    } catch (Throwable) {
        $postAdoptionFailed = true;
    }
    $afterPostAdoption = $snapshot();
    $assert($postAdoptionFailed, 'Failure-after-adoption fixture unexpectedly succeeded.');
    try {
        $assertPreserved($beforePostAdoption, $afterPostAdoption, 'failure after Guard adoption');
    } finally {
        $writeOperation($operation);
    }

    $beforeOperations = $operations->read();
    $preparation = $service->continueCommittedSourceAuthorityFirstCaptureFromRuntime($packageManifest, $target, $proof);
    $assert($preparation->plan()->operation()->operationId() === $operation->operationId(), 'Public continuation changed operation identity.');
    $assert($preparation->plan()->operation()->executionAttemptIdentity() === $attempt, 'Public continuation changed execution-attempt identity.');
    $assert($preparation->binding()->crossReference()->recoveryIdentity()->value() === \Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureService::forOperation($operation->operationId(), $plan->applyPlan()->identity(), $attempt)->value(), 'Canonical recovery adapter did not bind the exact attempt identity.');
    $afterOperations = $operations->read();
    $assert($afterOperations instanceof \Copot\Core\LifecycleOperationRecord && $afterOperations->recoveryState() === 'READY', 'Public continuation did not persist READY recovery state.');
    $assert($afterOperations->executionAttemptIdentity() === $beforeOperations->executionAttemptIdentity(), 'Public continuation rewrote the persisted execution attempt.');
    $assert($preparation->activeLock() instanceof \Copot\Core\InstallationLock, 'Successful public continuation did not retain the session InstallationLock.');
    $duplicateCaptureBlocked = false;
    try {
        $service->continueCommittedSourceAuthorityFirstCaptureFromRuntime($packageManifest, $target, $proof);
    } catch (Throwable) {
        $duplicateCaptureBlocked = true;
    }
    $assert($duplicateCaptureBlocked, 'Concurrent public continuation bypassed the session-owned InstallationLock.');
    $preparation->close();
    $replacementLock = (new \Copot\Core\InstallationMutex($storage))->acquire();
    $assert($replacementLock instanceof \Copot\Core\InstallationLock, 'Canonical session closure did not release the InstallationLock.');
    $replacementLock->release();

    $projectIdentity = hash('sha256', RecoveryRootResolver::identityPath((string) realpath($project)));
    $recoveryStorageRoot = new RecoveryStorageRoot($project, $recovery . DIRECTORY_SEPARATOR . $projectIdentity, $projectIdentity);
    $recoveryLifecycleStore = new RecoveryLifecycleStore($recoveryStorageRoot);
    $recoveryPolicy = new RecoveryStoragePathPolicy($recoveryStorageRoot);
    $unboundOperation = static function (string $executionAttempt) use ($operation): LifecycleOperationRecord {
        return LifecycleOperationRecord::fromArray(array_replace($operation->toArray(), [
            'execution_attempt_identity' => $executionAttempt,
            'phase' => LifecycleOperationRecord::PREPARING,
            'file_cursor' => 0,
            'last_verified_path' => null,
            'migration_outcome' => null,
            'recovery_identity' => null,
            'recovery_manifest_identity' => null,
            'recovery_state' => null,
        ]));
    };
    $writeLifecycleOnly = static function (string $executionAttempt, string $planIdentity) use ($recoveryLifecycleStore, $recoveryPolicy, $operation): string {
        $identity = NormalWebcoreRecoveryCaptureService::forOperation($operation->operationId(), $planIdentity, $executionAttempt);
        $recoveryLifecycleStore->create(new RecoveryLifecycleRecord(
            $identity,
            hash('sha256', 'public-partial:' . $identity->value()),
            $operation->operationId(),
            RecoveryLifecycleState::CREATED,
            false,
            false,
            null,
            null,
            null,
            null,
            null,
            '',
            '',
            null,
            null,
            false,
            $executionAttempt
        ));
        return $recoveryPolicy->recoverySetRoot($identity);
    };

    $writeReadyEvidence = static function (string $evidenceOperation, string $executionAttempt, string $planIdentity) use ($recoveryStorageRoot, $recoveryPolicy): string {
        $identity = NormalWebcoreRecoveryCaptureService::forOperation($evidenceOperation, $planIdentity, $executionAttempt);
        $bytes = 'public-unrelated:' . $evidenceOperation . ':' . $executionAttempt;
        $artifactIdentity = hash('sha256', $bytes);
        $artifact = new RecoveryArtifactRecord('database.webcore', $artifactIdentity, strlen($bytes));
        $domain = new RecoveryDomainIdentity('database.webcore', 'database.webcore', 'public-unrelated-scope', $artifactIdentity);
        $manifest = new RecoveryManifest(
            $identity,
            $evidenceOperation,
            'public-unrelated-package',
            'public-unrelated-release',
            hash('sha256', 'public-unrelated-archive'),
            $planIdentity,
            [$domain],
            'public-unrelated-lifecycle',
            'public-unrelated-ledger',
            $executionAttempt
        );
        (new RecoveryArtifactStore($recoveryStorageRoot))->publish($manifest, [['record' => $artifact, 'bytes' => $bytes]]);
        $store = new RecoveryLifecycleStore($recoveryStorageRoot);
        $store->create(new RecoveryLifecycleRecord(
            $identity,
            $manifest->identity(),
            $evidenceOperation,
            RecoveryLifecycleState::CREATED,
            false,
            false,
            null,
            null,
            null,
            null,
            null,
            '',
            '',
            null,
            null,
            false,
            $executionAttempt
        ));
        $store->transition($identity, RecoveryLifecycleState::CAPTURING);
        $store->transition($identity, RecoveryLifecycleState::CAPTURED);
        $store->save($store->read($identity)->withCaptureComplete());
        $store->transition($identity, RecoveryLifecycleState::READY);
        $store->save($store->read($identity)->withConfirmation($identity, $manifest->identity(), 'public-unrelated-target'));
        return $recoveryPolicy->recoverySetRoot($identity);
    };

    $writeIncompleteManifest = static function (string $executionAttempt) use ($operation, $plan, $recoveryStorageRoot, $recoveryPolicy): string {
        $identity = NormalWebcoreRecoveryCaptureService::forOperation($operation->operationId(), $plan->applyPlan()->identity(), $executionAttempt);
        $bytes = 'public-incomplete:' . $executionAttempt;
        $artifactIdentity = hash('sha256', $bytes);
        $artifact = new RecoveryArtifactRecord('database.webcore', $artifactIdentity, strlen($bytes));
        $domain = new RecoveryDomainIdentity('database.webcore', 'database.webcore', 'public-incomplete-scope', $artifactIdentity);
        $manifest = new RecoveryManifest(
            $identity,
            $operation->operationId(),
            $plan->sourcePlan()->packageEvidenceIdentity(),
            $plan->operation()->releaseIdentity(),
            $plan->operation()->archiveSha256(),
            $plan->applyPlan()->identity(),
            [$domain],
            $plan->committedLifecycleIdentity(),
            $plan->migrationStateIdentity(),
            $executionAttempt
        );
        $path = $recoveryPolicy->manifestPath($identity);
        mkdir(dirname($path), 0700, true);
        file_put_contents($path, (new RecoveryManifestCodec())->encode($manifest, [$artifact], false));
        return $recoveryPolicy->recoverySetRoot($identity);
    };

    // Rehydrate an exact READY set through the public continuation without
    // producing a second recovery set. The persisted operation is deliberately
    // left unbound so the canonical adapter must discover and bind the set.
    $readyRehydrateOperation = $unboundOperation($attempt);
    $writeOperation($readyRehydrateOperation);
    $readyInventoryBefore = $inventory($recovery);
    $rehydrated = $service->continueCommittedSourceAuthorityFirstCaptureFromRuntime($packageManifest, $target, $proof);
    $readyInventoryAfter = $inventory($recovery);
    $assert($readyInventoryBefore === $readyInventoryAfter, 'Same-attempt READY continuation performed a second capture.');
    $assert($rehydrated->binding()->crossReference()->recoveryIdentity()->value() === NormalWebcoreRecoveryCaptureService::forOperation($operation->operationId(), $plan->applyPlan()->identity(), $attempt)->value(), 'Same-attempt READY continuation used the wrong recovery identity.');
    $assert($rehydrated->activeLock() instanceof \Copot\Core\InstallationLock, 'Same-attempt READY rehydrate did not retain the canonical session lock.');
    $rehydrated->close();

    // Remove the original set inside the isolated fixture, then prove a valid
    // READY set for another operation does not block a fresh capture here.
    $remove($recoveryPolicy->recoverySetRoot(NormalWebcoreRecoveryCaptureService::forOperation($operation->operationId(), $plan->applyPlan()->identity(), $attempt)));
    $writeOperation($unboundOperation(LifecycleExecutionAttemptIdentity::create()));
    $unrelatedSet = $writeReadyEvidence('public-unrelated-operation', LifecycleExecutionAttemptIdentity::create(), hash('sha256', 'public-unrelated-plan'));
    $freshBefore = $inventory($recovery);
    $freshPreparation = $service->continueCommittedSourceAuthorityFirstCaptureFromRuntime($packageManifest, $target, $proof);
    $freshAfter = $inventory($recovery);
    $assert($freshAfter !== $freshBefore, 'Clean public first-capture path did not publish a recovery set.');
    $assert(is_dir($unrelatedSet), 'Unrelated valid recovery evidence was not retained for the public-route test.');
    $assert($freshPreparation->activeLock() instanceof \Copot\Core\InstallationLock, 'Fresh public capture did not retain the canonical session lock.');
    $freshPreparation->close();
    $freshPersisted = $operations->read();
    $assert($freshPersisted instanceof LifecycleOperationRecord && $freshPersisted->recoveryState() === RecoveryLifecycleState::READY, 'Fresh public capture did not bind READY state.');

    $sameAttemptPartialPath = $writeLifecycleOnly($attempt, hash('sha256', 'public-different-plan'));
    $expectPublicFailure('same-attempt lifecycle-only partial recovery', $unboundOperation($attempt));
    $remove($sameAttemptPartialPath);

    $differentAttempt = LifecycleExecutionAttemptIdentity::create();
    $differentAttemptPartialPath = $writeLifecycleOnly($differentAttempt, $plan->applyPlan()->identity());
    $expectPublicFailure('different-attempt lifecycle-only partial recovery', $unboundOperation($differentAttempt));
    $remove($differentAttemptPartialPath);

    $orphanIdentity = new RecoveryIdentity('public-orphan-' . bin2hex(random_bytes(4)));
    $orphanSet = $recoveryPolicy->recoverySetRoot($orphanIdentity) . DIRECTORY_SEPARATOR . 'artifacts';
    mkdir($orphanSet, 0700, true);
    file_put_contents($orphanSet . DIRECTORY_SEPARATOR . 'orphan.bin', 'orphan');
    $orphanAttempt = LifecycleExecutionAttemptIdentity::create();
    $expectPublicFailure('artifact-only orphan recovery residue', $unboundOperation($orphanAttempt));
    $remove(dirname($orphanSet));

    $malformedIdentity = new RecoveryIdentity('public-malformed-' . bin2hex(random_bytes(4)));
    $malformedManifest = $recoveryPolicy->manifestPath($malformedIdentity);
    mkdir(dirname($malformedManifest), 0700, true);
    file_put_contents($malformedManifest, '{"malformed":true}');
    $malformedAttempt = LifecycleExecutionAttemptIdentity::create();
    $expectPublicFailure('malformed recovery manifest', $unboundOperation($malformedAttempt));
    $remove(dirname(dirname($malformedManifest)));

    $incompleteAttempt = LifecycleExecutionAttemptIdentity::create();
    $incompleteManifestPath = $writeIncompleteManifest($incompleteAttempt);
    $expectPublicFailure('incomplete recovery manifest', $unboundOperation($incompleteAttempt));
    $remove($incompleteManifestPath);

    // A recovery set from attempt A must not be bypassed by a fresh capture
    // for the same operation under attempt B.
    $staleAttempt = LifecycleExecutionAttemptIdentity::create();
    $staleOperation = $plan->operation()->withExecutionAttemptIdentity($staleAttempt);
    $writeOperation($staleOperation);
    $beforeStaleAttempt = $snapshot();
    $staleRejected = false;
    $stalePreparation = null;
    try {
        $stalePreparation = $service->continueCommittedSourceAuthorityFirstCaptureFromRuntime($packageManifest, $target, $proof);
    } catch (Throwable) {
        $staleRejected = true;
    }
    if ($stalePreparation instanceof \Copot\Core\CommittedSourceAuthorityPreparation) {
        $stalePreparation->close();
        throw new RuntimeException('Production defect: stale recovery evidence from another execution attempt was bypassed by a duplicate public capture.');
    }
    $afterStaleAttempt = $snapshot();
    $assert($staleRejected, 'Mismatched execution attempt with existing recovery evidence was accepted.');
    $assertPreserved($beforeStaleAttempt, $afterStaleAttempt, 'mismatched execution attempt with stale recovery');
    $writeOperation($operation);

    echo "committed_source_authority_first_capture_public_e2e: {$assertions} assertions passed\n";
} finally {
    try { $server->exec('DROP DATABASE IF EXISTS ' . $databaseQuoted); } catch (Throwable) {}
    $remove($sandbox);
}
