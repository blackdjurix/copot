<?php

declare(strict_types=1);

use Copot\Core\BackupRecovery\ApplicationWriteProtectionEvidence;
use Copot\Core\BackupRecovery\CommittedSourceAuthorityRecoveryBinding;
use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\BackupRecovery\InstallationScopedMaintenanceGuard;
use Copot\Core\BackupRecovery\RecoveryArtifactRecord;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\RecoveryDomainIdentity;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryLifecycleCoordinator;
use Copot\Core\BackupRecovery\RecoveryLifecycleRecord;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryManifest;
use Copot\Core\BackupRecovery\RecoveryStorageRoot;
use Copot\Core\BackupRecovery\ScopedDatabaseConsistencyEvidence;
use Copot\Core\BackupRecovery\UnavailableDatabaseQuiescence;
use Copot\Core\CanonicalTargetInventory;
use Copot\Core\CanonicalTargetInventoryEntry;
use Copot\Core\CommittedLifecycleState;
use Copot\Core\CommittedSourceAuthorityExecutionAdmission;
use Copot\Core\CommittedSourceAuthorityPlanner;
use Copot\Core\CommittedSourceAuthorityPreservationPolicy;
use Copot\Core\CoreMigrationPlan;
use Copot\Core\CoreMigrationStateIdentity;
use Copot\Core\DatabaseTableOwnershipCatalog;
use Copot\Core\InstallationIdentity;
use Copot\Core\InstallationMutex;
use Copot\Core\InstallerOwnershipProof;
use Copot\Core\LifecycleOperationStore;
use Copot\Core\LiveFileActivationCapability;
use Copot\Core\LiveTreePathGuard;
use Copot\Core\MaintenanceCoordinator;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageContract;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageManifest;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\PackageOwnedFileApplier;
use Copot\Core\PackageLifecycleService;
use Copot\Core\StagedFile;
use Copot\Core\StagedPayload;
use Copot\Core\StagingSession;
use Copot\Core\WebcoreApplyResult;

require dirname(__DIR__) . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    if (!$condition) { throw new RuntimeException($message); }
    $assertions++;
};
$hash = static fn (string $bytes): string => hash('sha256', $bytes);
$remove = static function (string $path) use (&$remove): void {
    if (is_file($path) || is_link($path)) { @unlink($path); return; }
    if (!is_dir($path)) { return; }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') { $remove($path . DIRECTORY_SEPARATOR . $entry); }
    }
    @rmdir($path);
};

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-authority-rehearsal-' . bin2hex(random_bytes(8));
$live = $root . DIRECTORY_SEPARATOR . 'live';
$storage = $root . DIRECTORY_SEPARATOR . 'storage';
$recoveryRoot = $root . DIRECTORY_SEPARATOR . 'recovery';
$applyRoot = $root . DIRECTORY_SEPARATOR . 'apply';
$stagingRoot = $root . DIRECTORY_SEPARATOR . 'staging';
mkdir($live . DIRECTORY_SEPARATOR . 'app', 0700, true);
mkdir($live . DIRECTORY_SEPARATOR . 'public', 0700, true);

try {
    $oldTarget = 'old-target';
    $newTarget = 'new-target';
    $newFile = 'new-file';
    $routing = 'RewriteEngine On\nRewriteBase /fixture/\n';
    $desiredRouting = 'package routing bytes';
    file_put_contents($live . '/app/target.txt', $oldTarget);
    file_put_contents($live . '/public/.htaccess', $routing);

    $actual = new CanonicalTargetInventory([
        new CanonicalTargetInventoryEntry('APP_ROOT', CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/target.txt', strlen($oldTarget), $hash($oldTarget)),
        new CanonicalTargetInventoryEntry('PUBLIC_ROOT', CanonicalTargetInventoryEntry::PACKAGE_OWNED, '.htaccess', strlen($routing), $hash($routing)),
    ]);
    $desired = new CanonicalTargetInventory([
        new CanonicalTargetInventoryEntry('APP_ROOT', CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/target.txt', strlen($newTarget), $hash($newTarget)),
        new CanonicalTargetInventoryEntry('APP_ROOT', CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/new.txt', strlen($newFile), $hash($newFile)),
        new CanonicalTargetInventoryEntry('PUBLIC_ROOT', CanonicalTargetInventoryEntry::PACKAGE_OWNED, '.htaccess', strlen($desiredRouting), $hash($desiredRouting)),
    ]);

    $session = StagingSession::create($live, $stagingRoot);
    mkdir($session->payloadPath() . '/app', 0700, true);
    mkdir($session->payloadPath() . '/public', 0700, true);
    file_put_contents($session->payloadPath() . '/app/target.txt', $newTarget);
    file_put_contents($session->payloadPath() . '/app/new.txt', $newFile);
    file_put_contents($session->payloadPath() . '/public/.htaccess', $desiredRouting);
    $payload = new StagedPayload($session, str_repeat('a', 64), [
        new StagedFile('app/target.txt', strlen($newTarget), $hash($newTarget)),
        new StagedFile('app/new.txt', strlen($newFile), $hash($newFile)),
        new StagedFile('public/.htaccess', strlen($desiredRouting), $hash($desiredRouting)),
    ]);
    $package = new PackageContract(
        PackageContract::WEBCORE_PACKAGE_TYPE,
        3,
        '0.13.0',
        'release-test',
        'tree-test',
        new PackageCompatibility('0.13.0'),
        new PackageRuntimeCompatibility('8.1.0', ['mariadb' => '10.4.0'], ['json']),
        [
            new PackageInventoryEntry('app/target.txt', strlen($newTarget), $hash($newTarget)),
            new PackageInventoryEntry('app/new.txt', strlen($newFile), $hash($newFile)),
            new PackageInventoryEntry('public/.htaccess', strlen($desiredRouting), $hash($desiredRouting)),
        ],
        new PackageMigrationDeclaration(false),
        null,
        $desired,
        'full'
    );
    $rawManifest = str_repeat('b', 64);
    $manifest = new PackageManifest($package, '.copot/package.json', $payload, $rawManifest, \Copot\Core\PackageV3Identity::identity($rawManifest, $package->integrityIdentity()));
    $installation = new InstallationIdentity('inst_' . str_repeat('2', 32));
    $migrationState = CoreMigrationStateIdentity::fromRecords([]);
    $committed = new CommittedLifecycleState('0.13.0', 'release-test', null, 3, 'schema-test', $migrationState, new DateTimeImmutable('2026-10-21T00:00:00+00:00'));
    $scope = new DatabaseScope($installation->value(), 'copot_rehearsal', '', ['users']);
    $proof = new InstallerOwnershipProof($installation, '', 'core-schema-generation:test', $migrationState);
    $migration = CoreMigrationPlan::allow('0.13.0', '0.13.0', 'schema-test', 'schema-test', []);
    $policy = new CommittedSourceAuthorityPreservationPolicy('PUBLIC_ROOT', '.htaccess', CanonicalTargetInventoryEntry::PACKAGE_OWNED, CommittedSourceAuthorityPreservationPolicy::PRESERVE_EXCEPTION, 'runtime-owned', strlen($routing), $hash($routing), $actual->fingerprint());
    $plan = (new CommittedSourceAuthorityPlanner())->plan($committed, $installation, $manifest, $actual, $scope, $proof, $migration, $policy);
    $assert($plan->mutationRequired(), 'Rehearsal did not produce MUTATION_REQUIRED.');
    $assert($plan->actionCounts() === ['CREATE' => 1, 'REPLACE' => 1, 'PRESERVE' => 1], 'Rehearsal action counts are incorrect.');

    $operationRoot = $storage . DIRECTORY_SEPARATOR . 'operations';
    mkdir($operationRoot, 0700, true);
    $operations = new LifecycleOperationStore($operationRoot);
    $operations->create($plan->operation());
    $recovery = \Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureService::forOperation($plan->operation()->operationId(), $plan->applyPlan()->identity());
    $domainBytes = ['database.webcore' => 'db', 'filesystem.package-owned' => 'fs', 'filesystem.lifecycle.committed' => 'lc', 'filesystem.lifecycle.installed-lock' => 'lock'];
    $domainHashes = array_map($hash, $domainBytes);
    $domains = [
        new RecoveryDomainIdentity('database.webcore', 'database.webcore', $scope->identity(), $domainHashes['database.webcore']),
        new RecoveryDomainIdentity('filesystem.package-owned', 'filesystem.package-owned', $plan->applyPlan()->identity(), $domainHashes['filesystem.package-owned']),
        new RecoveryDomainIdentity('filesystem.lifecycle.committed', 'filesystem.lifecycle.committed', $plan->committedLifecycleIdentity(), $domainHashes['filesystem.lifecycle.committed']),
        new RecoveryDomainIdentity('filesystem.lifecycle.installed-lock', 'filesystem.lifecycle.installed-lock', $installation->value(), $domainHashes['filesystem.lifecycle.installed-lock']),
    ];
    $recoveryManifest = new RecoveryManifest($recovery, $plan->operation()->operationId(), $plan->sourcePlan()->packageEvidenceIdentity(), $plan->operation()->releaseIdentity(), $plan->operation()->archiveSha256(), $plan->applyPlan()->identity(), $domains, $plan->committedLifecycleIdentity(), $plan->migrationStateIdentity());
    $record = new RecoveryLifecycleRecord($recovery, $recoveryManifest->identity(), $plan->operation()->operationId(), RecoveryLifecycleState::READY, false, true);
    $recoveryStorage = new RecoveryStorageRoot($live, $recoveryRoot, 'authority-rehearsal');
    $recoveryStore = new \Copot\Core\BackupRecovery\RecoveryLifecycleStore($recoveryStorage);
    $recoveryStore->create($record);
    $artifacts = new RecoveryArtifactStore($recoveryStorage);
    $artifacts->publish($recoveryManifest, array_map(static fn (RecoveryArtifactRecord $record, string $bytes): array => ['record' => $record, 'bytes' => $bytes], [
        new RecoveryArtifactRecord('database.webcore', $domainHashes['database.webcore'], 2),
        new RecoveryArtifactRecord('filesystem.package-owned', $domainHashes['filesystem.package-owned'], 2),
        new RecoveryArtifactRecord('filesystem.lifecycle.committed', $domainHashes['filesystem.lifecycle.committed'], 2),
        new RecoveryArtifactRecord('filesystem.lifecycle.installed-lock', $domainHashes['filesystem.lifecycle.installed-lock'], 4),
    ], $domainBytes));
    $application = new ApplicationWriteProtectionEvidence($scope, $recovery, str_repeat('5', 64), str_repeat('6', 64), ['background', 'cli', 'http', 'scheduled'], true, true, time() - 1, time() + 120);
    $database = new ScopedDatabaseConsistencyEvidence($scope, $recovery, str_repeat('7', 64), str_repeat('8', 64), time() - 1, time() + 120);
    $binding = CommittedSourceAuthorityRecoveryBinding::fromCaptured($plan, $recoveryManifest, $record, $application, $database);
    $guard = new InstallationScopedMaintenanceGuard(new InstallationMutex($operationRoot), $operationRoot);
    $coordinator = new RecoveryLifecycleCoordinator($recoveryStore, new InstallationMutex($operationRoot), new UnavailableDatabaseQuiescence(), $guard);
    $coordinator->enterScopedMaintenance($recovery, $application, $database);
    $operations->save($plan->operation()->bindRecovery($recovery->value(), $recoveryManifest->identity(), RecoveryLifecycleState::READY));
    $admission = CommittedSourceAuthorityExecutionAdmission::issue($plan, $binding, $coordinator, $operations, $artifacts, $application, $database);

    $service = (new ReflectionClass(PackageLifecycleService::class))->newInstanceWithoutConstructor();
    $set = static function (string $property, mixed $value) use ($service): void { $reflection = new ReflectionProperty($service, $property); $reflection->setAccessible(true); $reflection->setValue($service, $value); };
    $set('v3Executor', new \Copot\Core\V3ExplicitApplyExecutor());
    $set('v3Applier', new PackageOwnedFileApplier(new LiveTreePathGuard($live), LiveFileActivationCapability::current(), $applyRoot));
    $set('liveGuard', new LiveTreePathGuard($live));
    $set('operationStore', $operations);
    $residue = $live . '/app/DatabaseScope.php.dbscope-correction-rehearsal.tmp';
    file_put_contents($residue, 'operation residue');
    file_put_contents($live . '/app/new.txt', 'foreign collision');
    $collision = $service->executeCommittedSourceAuthorityMutation($admission, $payload, null, $actual, $coordinator, $operations);
    $assert($collision->status() === WebcoreApplyResult::BLOCKED, 'CREATE collision was not fail-closed.');
    $assert(file_get_contents($live . '/app/target.txt') === $oldTarget, 'Collision preflight changed an existing target.');
    $assert(file_get_contents($residue) === 'operation residue', 'Operation residue was touched by package mutation planning.');
    $assert(!$recoveryStore->read($recovery)->mutationStarted(), 'Collision preflight crossed the recovery mutation boundary.');
    unlink($live . '/app/new.txt');
    unlink($residue);
    $result = $service->executeCommittedSourceAuthorityMutation($admission, $payload, null, $actual, $coordinator, $operations);
    $assert($result->status() === WebcoreApplyResult::AWAITING_WU6, 'Canonical isolated mutation did not reach WU6 handoff: ' . $result->reason());
    $assert(file_get_contents($live . '/app/target.txt') === $newTarget, 'REPLACE did not apply desired bytes.');
    $assert(file_get_contents($live . '/app/new.txt') === $newFile, 'CREATE did not apply desired bytes.');
    $assert(file_get_contents($live . '/public/.htaccess') === $routing, 'PRESERVE policy changed .htaccess bytes.');
    $assert($recoveryStore->read($recovery)->postReconciliationVerified(), 'Post-reconciliation recovery evidence was not recorded.');
    $assert($operations->read()?->phase() === \Copot\Core\LifecycleOperationRecord::AWAITING_WU6, 'Mutation did not stop at AWAITING_WU6.');
    echo "Committed source-authority isolated rehearsal passed ({$assertions} assertions).\n";
} finally {
    if (isset($session)) { $session->cleanup(); }
    $remove($root);
}
