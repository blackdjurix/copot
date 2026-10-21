<?php

use Copot\Core\BackupRecovery\ApplicationWriteProtectionEvidence;
use Copot\Core\BackupRecovery\CommittedSourceAuthorityRecoveryAdapter;
use Copot\Core\BackupRecovery\CommittedSourceAuthorityRecoveryBinding;
use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\BackupRecovery\InstallationScopedMaintenanceGuard;
use Copot\Core\BackupRecovery\RecoveryArtifactRecord;
use Copot\Core\BackupRecovery\RecoveryDomainIdentity;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryLifecycleRecord;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryManifest;
use Copot\Core\BackupRecovery\RecoveryStorageRoot;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\RecoveryLifecycleCoordinator;
use Copot\Core\BackupRecovery\ScopedDatabaseConsistencyEvidence;
use Copot\Core\BackupRecovery\UnavailableDatabaseQuiescence;
use Copot\Core\CanonicalTargetInventory;
use Copot\Core\CanonicalTargetInventoryEntry;
use Copot\Core\CommittedLifecycleState;
use Copot\Core\CommittedSourceAuthorityExecutionAdmission;
use Copot\Core\CommittedSourceAuthorityPlanner;
use Copot\Core\DatabaseTableOwnershipCatalog;
use Copot\Core\InstallationIdentity;
use Copot\Core\InstallerOwnershipProof;
use Copot\Core\LifecycleOperationStore;
use Copot\Core\InstallationMutex;
use Copot\Core\CoreMigrationStateIdentity;
use Copot\Core\HealthIntegrityCommitCoordinator;
use Copot\Core\MaintenanceCoordinator;
use Copot\Core\RuntimeHealthVerifier;
use Copot\Core\TargetPackageIntegrityVerifier;
use Copot\Core\DatabaseHealthVerifier;
use Copot\Core\CoreMigrationHealthVerifier;
use Copot\Core\CoreMigrationRegistry;
use Copot\Core\LiveTreePathGuard;
use Copot\Core\HealthIntegrityCommitResult;

final class AuthorityFinalizationStatement
{
    public function __construct(private array $rows = [], private mixed $column = null) {}
    public function fetchAll(...$arguments): array { return $this->rows; }
    public function fetchColumn(...$arguments): mixed { return $this->column; }
}

final class AuthorityFinalizationPdo extends PDO
{
    public function __construct() {}
    public function query($query, ...$arguments)
    {
        if (str_contains($query, 'SELECT 1')) return new AuthorityFinalizationStatement([], 1);
        if (str_contains($query, 'information_schema.tables')) return new AuthorityFinalizationStatement(['users', 'roles', 'permissions', 'user_roles', 'role_permissions', 'settings', 'modules', 'module_permissions', 'themes', 'content', 'media', 'media_usages', 'navigation_menus', 'navigation_items', 'redirects', 'security_login_attempts', 'security_sessions', 'security_events', 'core_migration_history']);
        if (str_contains($query, 'DESCRIBE core_migration_history')) return new AuthorityFinalizationStatement(['migration_id', 'sequence_number', 'target_webcore_version', 'target_schema_identity', 'migration_checksum', 'applied_at']);
        if (str_contains($query, 'FROM core_migration_history')) return new AuthorityFinalizationStatement([]);
        throw new RuntimeException('Unexpected authority finalization query: ' . $query);
    }
}
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageContract;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageManifest;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\StagedFile;
use Copot\Core\StagedPayload;
use Copot\Core\StagingSession;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void { $assertions++; if (!$condition) throw new RuntimeException($message); };
$throws = static function (callable $callback): void { try { $callback(); } catch (Throwable) { return; } throw new RuntimeException('Expected recovery binding rejection.'); };

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-authority-recovery-' . bin2hex(random_bytes(8));
mkdir($root . '/live', 0700, true);
$session = StagingSession::create($root . '/live', $root . '/staging');
try {
    $content = 'target'; $sha = hash('sha256', $content);
    $entry = new CanonicalTargetInventoryEntry('APP_ROOT', CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/target.txt', strlen($content), $sha);
    $inventory = new CanonicalTargetInventory([$entry]);
    $payload = new StagedPayload($session, str_repeat('e', 64), [new StagedFile('app/target.txt', strlen($content), $sha)]);
    $package = new PackageContract(PackageContract::WEBCORE_PACKAGE_TYPE, 3, '0.13.0', 'release-test', 'tree-test', new PackageCompatibility('0.13.0'), new PackageRuntimeCompatibility('8.0', ['sqlite' => '3.0'], ['json']), [new PackageInventoryEntry('app/target.txt', strlen($content), $sha, \Copot\Core\PackageOwnership::PACKAGE_OWNED)], new PackageMigrationDeclaration(false), null, $inventory, 'full');
    $raw = str_repeat('a', 64); $evidence = \Copot\Core\PackageV3Identity::identity($raw, $package->integrityIdentity());
    $manifest = new PackageManifest($package, '.copot/package.json', $payload, $raw, $evidence);
    $installation = new InstallationIdentity('inst_' . str_repeat('1', 32)); $migration = CoreMigrationStateIdentity::fromRecords([]);
    $committed = new CommittedLifecycleState('0.13.0', 'release-test', null, 3, 'schema-test', $migration, new DateTimeImmutable('2026-10-21T00:00:00+00:00'));
    $scope = DatabaseScope::fromOwnershipCatalog($installation->value(), 'copot_test', '', DatabaseTableOwnershipCatalog::current());
    $proof = new InstallerOwnershipProof($installation, '', 'core-schema-generation:test', $migration);
    $plan = (new CommittedSourceAuthorityPlanner())->plan($committed, $installation, $manifest, $inventory, $scope, $proof);
    $recovery = \Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureService::forOperation($plan->operation()->operationId(), $plan->applyPlan()->identity());
    $manifestIdentity = str_repeat('d', 64);
    $domainBytes = ['database.webcore' => 'database', 'filesystem.package-owned' => 'package', 'filesystem.lifecycle.committed' => 'committed', 'filesystem.lifecycle.installed-lock' => 'installed-lock'];
    $domainHashes = array_map(static fn (string $bytes): string => hash('sha256', $bytes), $domainBytes);
    $domains = [
        new RecoveryDomainIdentity('database.webcore', 'database.webcore', $scope->identity(), $domainHashes['database.webcore']),
        new RecoveryDomainIdentity('filesystem.package-owned', 'filesystem.package-owned', $plan->applyPlan()->identity(), $domainHashes['filesystem.package-owned']),
        new RecoveryDomainIdentity('filesystem.lifecycle.committed', 'filesystem.lifecycle.committed', $plan->committedLifecycleIdentity(), $domainHashes['filesystem.lifecycle.committed']),
        new RecoveryDomainIdentity('filesystem.lifecycle.installed-lock', 'filesystem.lifecycle.installed-lock', $installation->value(), $domainHashes['filesystem.lifecycle.installed-lock']),
    ];
    $recoveryManifest = new RecoveryManifest($recovery, $plan->operation()->operationId(), $plan->sourcePlan()->packageEvidenceIdentity(), $plan->operation()->releaseIdentity(), $plan->operation()->archiveSha256(), $plan->applyPlan()->identity(), $domains, $plan->committedLifecycleIdentity(), $plan->migrationStateIdentity());
    $record = new RecoveryLifecycleRecord($recovery, $recoveryManifest->identity(), $plan->operation()->operationId(), RecoveryLifecycleState::READY, false, true, $recovery->value(), $recoveryManifest->identity(), $recoveryManifest->identity());
    $now = time();
    $application = new ApplicationWriteProtectionEvidence($scope, $recovery, str_repeat('5', 64), str_repeat('6', 64), ['background', 'cli', 'http', 'scheduled'], true, true, $now - 1, $now + 100);
    $database = new ScopedDatabaseConsistencyEvidence($scope, $recovery, str_repeat('7', 64), str_repeat('8', 64), $now - 1, $now + 100);
    $binding = CommittedSourceAuthorityRecoveryBinding::fromCaptured($plan, $recoveryManifest, $record, $application, $database);
    $assert($binding->mutationAuthorized() === false, 'Recovery binding granted mutation authority.');
    $assert($binding->toArray()['wu6_required'] === true, 'Recovery binding did not retain WU6 requirement.');
    $assert($binding->crossReference()->databaseScope()->identity() === $scope->identity(), 'Cross-reference lost database scope identity.');

    $request = new \Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureRequest($plan->operation()->operationId(), $installation->value(), $scope->namespaceIdentity(), $plan->committedLifecycleIdentity(), $plan->sourcePlan()->packageEvidenceIdentity(), $plan->operation()->releaseIdentity(), $plan->operation()->archiveSha256(), $plan->applyPlan(), $plan->committedLifecycleIdentity(), $plan->migrationStateIdentity(), $scope->databaseIdentity(), null, $scope, $database, $application, $plan->sourcePlan());
    CommittedSourceAuthorityRecoveryAdapter::assertRequest($plan, $request);
    $assert(true, 'Canonical capture request binding passed.');

    $operationRoot = $root . '/storage'; mkdir($operationRoot, 0700, true);
    $operations = new LifecycleOperationStore($operationRoot); $operations->create($plan->operation());
    $bound = $plan->operation()->bindRecovery($recovery->value(), $recoveryManifest->identity(), RecoveryLifecycleState::READY); $operations->save($bound);
    $assert($operations->read()?->recoveryManifestIdentity() === $recoveryManifest->identity(), 'Persisted operation recovery binding did not reread exactly.');

    $recoveryStorage = new RecoveryStorageRoot($root . '/live', $root . '/recovery', 'authority-test');
    $artifactStore = new RecoveryArtifactStore($recoveryStorage);
    $artifactStore->publish($recoveryManifest, array_map(
        static fn (RecoveryArtifactRecord $record, string $bytes): array => ['record' => $record, 'bytes' => $bytes],
        [
            new RecoveryArtifactRecord('database.webcore', $domainHashes['database.webcore'], strlen($domainBytes['database.webcore'])),
            new RecoveryArtifactRecord('filesystem.package-owned', $domainHashes['filesystem.package-owned'], strlen($domainBytes['filesystem.package-owned'])),
            new RecoveryArtifactRecord('filesystem.lifecycle.committed', $domainHashes['filesystem.lifecycle.committed'], strlen($domainBytes['filesystem.lifecycle.committed'])),
            new RecoveryArtifactRecord('filesystem.lifecycle.installed-lock', $domainHashes['filesystem.lifecycle.installed-lock'], strlen($domainBytes['filesystem.lifecycle.installed-lock'])),
        ], $domainBytes
    ));
    $guard = new InstallationScopedMaintenanceGuard(new InstallationMutex($operationRoot), $operationRoot);
    $coordinator = new RecoveryLifecycleCoordinator(new Copot\Core\BackupRecovery\RecoveryLifecycleStore($recoveryStorage), new InstallationMutex($operationRoot), new UnavailableDatabaseQuiescence(), $guard);
    $coordinator->enterScopedMaintenance($recovery, $application, $database);
    $admission = CommittedSourceAuthorityExecutionAdmission::issue($plan, $binding, $coordinator, $operations, $artifactStore, $application, $database);
    $assert($admission->mutationAuthorized() === false && $admission->wu6Required(), 'Authority admission granted mutation or lost WU6 requirement.');
    $awaiting = $admission->advanceToAwaitingWu6($coordinator, $operations, $artifactStore, $application, $database);
    $assert($awaiting->phase() === \Copot\Core\LifecycleOperationRecord::AWAITING_WU6 && $awaiting->fileCursor() === 0 && $awaiting->migrationOutcome() === null, 'Authority-only handoff created mutation or migration evidence.');

    mkdir($root . '/live/app', 0700, true);
    file_put_contents($root . '/live/app/target.txt', $content);
    $installationState = new \Copot\Core\InstallationState($operationRoot);
    $installationState->createMarker('0.13.0');
    $committedStore = new \Copot\Core\CommittedLifecycleStateStore($operationRoot);
    $committedStore->write($committed);
    $healthCoordinator = new HealthIntegrityCommitCoordinator(
        new InstallationMutex($operationRoot),
        new MaintenanceCoordinator($operations),
        $installationState,
        $committedStore,
        new TargetPackageIntegrityVerifier(),
        new DatabaseHealthVerifier(),
        new CoreMigrationHealthVerifier(),
        new RuntimeHealthVerifier(),
        new CoreMigrationRegistry('authority-finalization-test', [])
    );
    $sourceArtifacts = new \Copot\Core\SourceInventoryArtifactStore($operationRoot, static fn ($resource): bool => true);
    $final = $healthCoordinator->finalizeCommittedSourceAuthority(
        $admission,
        $coordinator,
        $operations,
        $artifactStore,
        new LiveTreePathGuard($root . '/live'),
        new AuthorityFinalizationPdo(),
        $sourceArtifacts,
        ['bootstrap' => static fn (): bool => true, 'public' => static fn (): bool => true, 'admin' => static fn (): bool => true],
        $coordinator->activeLifecycleLock()
    );
    $assert($final->status() === HealthIntegrityCommitResult::COMPLETED, 'Canonical source-authority WU6 finalization did not complete: ' . $final->reason());
    $assert($committedStore->read()?->sourceInventoryReference() !== null, 'SourceInventoryReference was not durably committed.');
    $assert($operations->read() === null, 'Completed authority operation was not cleared through canonical lifecycle cleanup.');
    $coordinator->leaveMaintenance($recovery);

    $badManifest = new RecoveryManifest($recovery, 'other-operation', $plan->sourcePlan()->packageEvidenceIdentity(), $plan->operation()->releaseIdentity(), $plan->operation()->archiveSha256(), $plan->applyPlan()->identity(), $domains, $plan->committedLifecycleIdentity(), $plan->migrationStateIdentity());
    $throws(static fn () => CommittedSourceAuthorityRecoveryBinding::fromCaptured($plan, $badManifest, $record, $application, $database));
    $stale = new ScopedDatabaseConsistencyEvidence($scope, $recovery, str_repeat('7', 64), str_repeat('8', 64), $now - 100, $now - 50);
    $throws(static fn () => CommittedSourceAuthorityRecoveryBinding::fromCaptured($plan, $recoveryManifest, $record, $application, $stale));
    $mutated = new RecoveryLifecycleRecord($recovery, $recoveryManifest->identity(), $plan->operation()->operationId(), RecoveryLifecycleState::READY, true, true, $recovery->value(), $recoveryManifest->identity(), $recoveryManifest->identity());
    $throws(static fn () => CommittedSourceAuthorityRecoveryBinding::fromCaptured($plan, $recoveryManifest, $mutated, $application, $database));
} finally { $session->cleanup(); @rmdir($root . '/staging'); @rmdir($root . '/live'); @rmdir($root . '/storage/.copot-lifecycle'); @rmdir($root . '/storage'); @rmdir($root); }
echo "Committed source-authority recovery binding tests passed ({$assertions} assertions)." . PHP_EOL;
