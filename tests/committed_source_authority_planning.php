<?php

use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\CanonicalTargetInventory;
use Copot\Core\CanonicalTargetInventoryEntry;
use Copot\Core\CommittedLifecycleState;
use Copot\Core\CommittedSourceAuthorityEligibility;
use Copot\Core\CommittedSourceAuthorityPlan;
use Copot\Core\CommittedSourceAuthorityPlanner;
use Copot\Core\CommittedSourceAuthorityPreservationPolicy;
use Copot\Core\CoreMigrationPlan;
use Copot\Core\DatabaseTableOwnershipCatalog;
use Copot\Core\InstallationIdentity;
use Copot\Core\InstallerOwnershipProof;
use Copot\Core\LegacyClassificationResult;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageContract;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageManifest;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\StagedFile;
use Copot\Core\StagedPayload;
use Copot\Core\StagingSession;
use Copot\Core\SourceInventoryReference;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$throws = static function (callable $callback): void {
    try {
        $callback();
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException('Expected planning rejection.');
};

$fixtureRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-source-authority-' . bin2hex(random_bytes(8));
mkdir($fixtureRoot, 0700, true);
$session = null;
try {
    $liveRoot = $fixtureRoot . DIRECTORY_SEPARATOR . 'live';
    $stagingRoot = $fixtureRoot . DIRECTORY_SEPARATOR . 'staging';
    mkdir($liveRoot, 0700, true);
    $session = StagingSession::create($liveRoot, $stagingRoot);

    $content = 'target';
    $sha = hash('sha256', $content);
    $targetEntry = new CanonicalTargetInventoryEntry('APP_ROOT', CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/target.txt', strlen($content), $sha);
    $targetInventory = new CanonicalTargetInventory([$targetEntry]);
    $staged = new StagedPayload($session, str_repeat('e', 64), [new StagedFile('app/target.txt', strlen($content), $sha)]);
    $rawManifestSha = str_repeat('a', 64);
    $package = new PackageContract(
        PackageContract::WEBCORE_PACKAGE_TYPE,
        PackageContract::V3_MANIFEST_CONTRACT_VERSION,
        '0.13.0',
        'release-test',
        'tree-test',
        new PackageCompatibility('0.13.0'),
        new PackageRuntimeCompatibility('8.0', ['sqlite' => '3.0'], ['json']),
        [new PackageInventoryEntry('app/target.txt', strlen($content), $sha, \Copot\Core\PackageOwnership::PACKAGE_OWNED)],
        new PackageMigrationDeclaration(false),
        null,
        $targetInventory,
        'full'
    );
    $packageEvidence = \Copot\Core\PackageV3Identity::identity($rawManifestSha, $package->integrityIdentity());
    $manifest = new PackageManifest($package, '.copot/package.json', $staged, $rawManifestSha, $packageEvidence);
    $installation = new InstallationIdentity('inst_' . str_repeat('1', 32));
    $migrationIdentity = str_repeat('c', 64);
    $committed = new CommittedLifecycleState('0.13.0', 'release-test', null, 3, 'schema-test', $migrationIdentity, new DateTimeImmutable('2026-10-21T00:00:00+00:00'));
    $scope = DatabaseScope::fromOwnershipCatalog($installation->value(), 'copot_test', '', DatabaseTableOwnershipCatalog::current());
    $proof = new InstallerOwnershipProof($installation, '', 'core-schema-generation:test', $migrationIdentity);

    $eligibility = (new CommittedSourceAuthorityEligibility())->evaluate($committed, $installation, $manifest, $targetInventory, $scope, $proof);
    $assert($eligibility->eligible(), 'A fully evidenced committed installation was rejected.');

    $plan = (new CommittedSourceAuthorityPlanner())->plan($committed, $installation, $manifest, $targetInventory, $scope, $proof);
    $assert($plan instanceof CommittedSourceAuthorityPlan, 'Planner did not return a typed plan.');
    $assert($plan->authorityOnly(), 'Exact target convergence did not produce an authority-only plan.');
    $assert($plan->operation()->classification() === 'COMMITTED_SOURCE_AUTHORITY_RECONCILIATION', 'Operation classification is incorrect.');
    $assert($plan->operation()->phase() === \Copot\Core\LifecycleOperationRecord::PREPARING, 'Planning escaped the PREPARING phase.');
    $assert($plan->operation()->migrationPlanIdentity() === null && $plan->operation()->migrationOutcome() === null, 'Authority-only planning fabricated migration evidence.');
    $assert($plan->toArray()['mutation_executed'] === false && $plan->toArray()['wu6_required'] === true, 'Plan boundary flags are incorrect.');

    $oldContent = 'old-target';
    $newContent = 'new-file';
    $oldRoute = 'runtime-routing';
    $newRoute = 'package-routing';
    $mutationDesired = new CanonicalTargetInventory([
        new CanonicalTargetInventoryEntry('APP_ROOT', CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/target.txt', strlen($content), $sha),
        new CanonicalTargetInventoryEntry('APP_ROOT', CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/new.php', strlen($newContent), hash('sha256', $newContent)),
    ]);
    $mutationActual = new CanonicalTargetInventory([
        new CanonicalTargetInventoryEntry('APP_ROOT', CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/target.txt', strlen($oldContent), hash('sha256', $oldContent)),
        new CanonicalTargetInventoryEntry('PUBLIC_ROOT', CanonicalTargetInventoryEntry::PACKAGE_OWNED, '.htaccess', strlen($oldRoute), hash('sha256', $oldRoute)),
    ]);
    $mutationPayload = new StagedPayload($session, str_repeat('f', 64), [
        new StagedFile('app/target.txt', strlen($content), $sha),
        new StagedFile('app/new.php', strlen($newContent), hash('sha256', $newContent)),
    ]);
    $mutationPackage = new PackageContract(
        PackageContract::WEBCORE_PACKAGE_TYPE,
        PackageContract::V3_MANIFEST_CONTRACT_VERSION,
        '0.13.0',
        'release-test',
        'tree-test',
        new PackageCompatibility('0.13.0'),
        new PackageRuntimeCompatibility('8.0', ['sqlite' => '3.0'], ['json']),
        [
            new PackageInventoryEntry('app/target.txt', strlen($content), $sha, \Copot\Core\PackageOwnership::PACKAGE_OWNED),
            new PackageInventoryEntry('app/new.php', strlen($newContent), hash('sha256', $newContent), \Copot\Core\PackageOwnership::PACKAGE_OWNED),
        ],
        new PackageMigrationDeclaration(false),
        null,
        $mutationDesired,
        'full'
    );
    $mutationRaw = str_repeat('b', 64);
    $mutationEvidence = \Copot\Core\PackageV3Identity::identity($mutationRaw, $mutationPackage->integrityIdentity());
    $mutationManifest = new PackageManifest($mutationPackage, '.copot/package.json', $mutationPayload, $mutationRaw, $mutationEvidence);
    $noOpMigration = CoreMigrationPlan::allow('0.13.0', '0.13.0', 'schema-test', 'schema-test', []);
    $preservation = new CommittedSourceAuthorityPreservationPolicy(
        'PUBLIC_ROOT',
        '.htaccess',
        CanonicalTargetInventoryEntry::PACKAGE_OWNED,
        CommittedSourceAuthorityPreservationPolicy::PRESERVE_EXCEPTION,
        'runtime-owned',
        strlen($oldRoute),
        hash('sha256', $oldRoute),
        $mutationActual->fingerprint()
    );
    $mutationPlan = (new CommittedSourceAuthorityPlanner())->plan($committed, $installation, $mutationManifest, $mutationActual, $scope, $proof, $noOpMigration, $preservation);
    $assert($mutationPlan->mutationRequired() && !$mutationPlan->authorityOnly(), 'Target drift did not produce a mutation-required plan.');
    $assert($mutationPlan->actionCounts() === ['CREATE' => 1, 'REPLACE' => 1, 'PRESERVE' => 1], 'Mutation action counts are not source-target derived.');
    $assert($mutationPlan->migrationPlanIdentity() === $noOpMigration->identity(), 'Canonical NOOP migration identity was not bound.');
    $assert($mutationPlan->preservationPolicy()?->toArray()['retained_target_sha256'] === hash('sha256', $oldRoute), 'Preservation evidence was not retained.');
    $assert($mutationPlan->identity() === (new CommittedSourceAuthorityPlanner())->plan($committed, $installation, $mutationManifest, $mutationActual, $scope, $proof, $noOpMigration, $preservation)->identity(), 'Mutation plan identity is not deterministic.');
    $mutationPlan->assertTargetFingerprint($mutationActual);
    $throws(static fn () => $mutationPlan->assertTargetFingerprint(new CanonicalTargetInventory([
        new CanonicalTargetInventoryEntry('APP_ROOT', CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/target.txt', strlen($content), $sha),
    ])));
    $collision = new CanonicalTargetInventory([
        ...$mutationActual->entries(),
        new CanonicalTargetInventoryEntry('APP_ROOT', CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/foreign.php', 1, hash('sha256', 'x')),
    ]);
    $throws(static fn () => (new CommittedSourceAuthorityPlanner())->plan($committed, $installation, $mutationManifest, $collision, $scope, $proof, $noOpMigration, $preservation));

    $withAuthority = new CommittedLifecycleState('0.13.0', 'release-test', null, 3, 'schema-test', $migrationIdentity, new DateTimeImmutable('2026-10-21T00:00:00+00:00'), null, new SourceInventoryReference(str_repeat('d', 64), 1, $targetInventory->fingerprint(), 'release-test', 3));
    $assert(!(new CommittedSourceAuthorityEligibility())->evaluate($withAuthority, $installation, $manifest, $targetInventory, $scope, $proof)->eligible(), 'Existing source authority was not rejected.');
    $drift = new CanonicalTargetInventory([new CanonicalTargetInventoryEntry('APP_ROOT', CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/target.txt', strlen($content), str_repeat('f', 64))]);
    $assert(!(new CommittedSourceAuthorityEligibility())->evaluate($committed, $installation, $manifest, $drift, $scope, $proof)->eligible(), 'Target inventory drift was accepted.');
    $wrongScope = DatabaseScope::fromOwnershipCatalog('inst_' . str_repeat('2', 32), 'copot_test', '', DatabaseTableOwnershipCatalog::current());
    $assert(!(new CommittedSourceAuthorityEligibility())->evaluate($committed, $installation, $manifest, $targetInventory, $wrongScope, $proof)->eligible(), 'Cross-installation scope was accepted.');
    $badProof = new InstallerOwnershipProof($installation, '', 'core-schema-generation:test', str_repeat('d', 64));
    $assert(!(new CommittedSourceAuthorityEligibility())->evaluate($committed, $installation, $manifest, $targetInventory, $scope, $badProof)->eligible(), 'Mismatched migration proof was accepted.');
    $throws(static fn () => (new CommittedSourceAuthorityPlanner())->plan($committed, $installation, $manifest, $drift, $scope, $proof));
    $assert(!LegacyClassificationResult::committed($committed->snapshot())->isLegacyCandidate(), 'Committed state became a legacy candidate.');
    $assert(LegacyClassificationResult::canonicalBaseline('schema-test', '0.13.0')->isLegacyCandidate(), 'Ordinary legacy classification changed.');
    $assert(!(new CommittedSourceAuthorityEligibility())->evaluate($committed, $installation, new PackageManifest($package, '.copot/package.json', $staged), $targetInventory, $scope, $proof)->eligible(), 'Missing provenance was accepted.');
    $forged = new PackageManifest($package, '.copot/package.json', $staged, $rawManifestSha, \Copot\Core\PackageV3Identity::identity($rawManifestSha, str_repeat('0', 64)));
    $assert(!(new CommittedSourceAuthorityEligibility())->evaluate($committed, $installation, $forged, $targetInventory, $scope, $proof)->eligible(), 'Forged provenance identity was accepted.');
} finally {
    if ($session instanceof StagingSession) {
        $session->cleanup();
    }
    @rmdir($stagingRoot ?? '');
    @rmdir($liveRoot ?? '');
    @rmdir($fixtureRoot);
}

echo "Committed source-authority planning tests passed ({$assertions} assertions)." . PHP_EOL;
