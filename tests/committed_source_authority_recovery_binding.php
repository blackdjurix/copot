<?php

use Copot\Core\BackupRecovery\ApplicationWriteProtectionEvidence;
use Copot\Core\BackupRecovery\CommittedSourceAuthorityRecoveryAdapter;
use Copot\Core\BackupRecovery\CommittedSourceAuthorityRecoveryBinding;
use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\BackupRecovery\RecoveryArtifactRecord;
use Copot\Core\BackupRecovery\RecoveryDomainIdentity;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryLifecycleRecord;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryManifest;
use Copot\Core\BackupRecovery\ScopedDatabaseConsistencyEvidence;
use Copot\Core\CanonicalTargetInventory;
use Copot\Core\CanonicalTargetInventoryEntry;
use Copot\Core\CommittedLifecycleState;
use Copot\Core\CommittedSourceAuthorityPlanner;
use Copot\Core\DatabaseTableOwnershipCatalog;
use Copot\Core\InstallationIdentity;
use Copot\Core\InstallerOwnershipProof;
use Copot\Core\LifecycleOperationStore;
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
    $installation = new InstallationIdentity('inst_' . str_repeat('1', 32)); $migration = str_repeat('c', 64);
    $committed = new CommittedLifecycleState('0.13.0', 'release-test', null, 3, 'schema-test', $migration, new DateTimeImmutable('2026-10-21T00:00:00+00:00'));
    $scope = DatabaseScope::fromOwnershipCatalog($installation->value(), 'copot_test', '', DatabaseTableOwnershipCatalog::current());
    $proof = new InstallerOwnershipProof($installation, '', 'core-schema-generation:test', $migration);
    $plan = (new CommittedSourceAuthorityPlanner())->plan($committed, $installation, $manifest, $inventory, $scope, $proof);
    $recovery = \Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureService::forOperation($plan->operation()->operationId(), $plan->applyPlan()->identity());
    $manifestIdentity = str_repeat('d', 64);
    $domains = [
        new RecoveryDomainIdentity('database.webcore', 'database.webcore', $scope->identity(), str_repeat('1', 64)),
        new RecoveryDomainIdentity('filesystem.package-owned', 'filesystem.package-owned', $plan->applyPlan()->identity(), str_repeat('2', 64)),
        new RecoveryDomainIdentity('filesystem.lifecycle.committed', 'filesystem.lifecycle.committed', $plan->committedLifecycleIdentity(), str_repeat('3', 64)),
        new RecoveryDomainIdentity('filesystem.lifecycle.installed-lock', 'filesystem.lifecycle.installed-lock', $installation->value(), str_repeat('4', 64)),
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

    $badManifest = new RecoveryManifest($recovery, 'other-operation', $plan->sourcePlan()->packageEvidenceIdentity(), $plan->operation()->releaseIdentity(), $plan->operation()->archiveSha256(), $plan->applyPlan()->identity(), $domains, $plan->committedLifecycleIdentity(), $plan->migrationStateIdentity());
    $throws(static fn () => CommittedSourceAuthorityRecoveryBinding::fromCaptured($plan, $badManifest, $record, $application, $database));
    $stale = new ScopedDatabaseConsistencyEvidence($scope, $recovery, str_repeat('7', 64), str_repeat('8', 64), $now - 100, $now - 50);
    $throws(static fn () => CommittedSourceAuthorityRecoveryBinding::fromCaptured($plan, $recoveryManifest, $record, $application, $stale));
    $mutated = new RecoveryLifecycleRecord($recovery, $recoveryManifest->identity(), $plan->operation()->operationId(), RecoveryLifecycleState::READY, true, true, $recovery->value(), $recoveryManifest->identity(), $recoveryManifest->identity());
    $throws(static fn () => CommittedSourceAuthorityRecoveryBinding::fromCaptured($plan, $recoveryManifest, $mutated, $application, $database));
} finally { $session->cleanup(); @rmdir($root . '/staging'); @rmdir($root . '/live'); @rmdir($root . '/storage/.copot-lifecycle'); @rmdir($root . '/storage'); @rmdir($root); }
echo "Committed source-authority recovery binding tests passed ({$assertions} assertions)." . PHP_EOL;
