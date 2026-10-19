<?php

$base = dirname(__DIR__);
require $base . '/bootstrap/autoload.php';

use Copot\Core\CoreMigrationRegistry;
use Copot\Core\DeploymentContext;
use Copot\Core\ExistingInstallEvidence;
use Copot\Core\InstallationIdentityStore;
use Copot\Core\InstallationState;
use Copot\Core\InstalledStateInspector;
use Copot\Core\LegacyBootstrapSourceArtifactStore;
use Copot\Core\LegacyBootstrapSourceInventory;
use Copot\Core\LegacyBootstrapPreparation;
use Copot\Core\LegacyBootstrapPreparationRunner;
use Copot\Core\LegacyBootstrapPreparationHandoff;
use Copot\Core\LegacyBootstrapReviewedInjectionManifest;
use Copot\Core\LegacyBootstrapLiveInventoryProvider;
use Copot\Core\LegacyBootstrapPostInjectionReconciler;
use Copot\Core\LegacyBootstrapPostInjectionReconciliationResult;
use Copot\Core\LegacyClassificationResult;
use Copot\Core\LegacyToV3BootstrapEntry;
use Copot\Core\LegacyToV3BootstrapPreparationComposer;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageContract;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageInventoryVerifier;
use Copot\Core\PackageManifestReader;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageOwnership;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\PackageTargetRequirements;
use Copot\Core\PackageV3SourceRelativePlanner;
use Copot\Core\StagedFile;
use Copot\Core\StagedPayload;
use Copot\Core\StagingSession;
use Copot\Core\ZipIntakeService;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$throws = static function (callable $callback, string $message) use ($assert): void {
    try { $callback(); } catch (Throwable) { return; }
    $assert(false, $message);
};

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-separate-bootstrap-' . bin2hex(random_bytes(6));
$sourceRoot = $tmp . DIRECTORY_SEPARATOR . 'source';
$appRoot = $tmp . DIRECTORY_SEPARATOR . 'target-app';
$publicRoot = $tmp . DIRECTORY_SEPARATOR . 'target-public';
mkdir($sourceRoot . DIRECTORY_SEPARATOR . 'bootstrap', 0700, true);
mkdir($appRoot . DIRECTORY_SEPARATOR . 'storage', 0700, true);
mkdir($publicRoot, 0700, true);
file_put_contents($sourceRoot . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'autoload.php', '<?php');
file_put_contents($appRoot . DIRECTORY_SEPARATOR . 'legacy.php', '<?php legacy');
file_put_contents($publicRoot . DIRECTORY_SEPARATOR . 'index.php', '<?php public');
file_put_contents($appRoot . DIRECTORY_SEPARATOR . '.env', "DB_DATABASE=separate_bootstrap_test\n");

$state = new InstallationState($appRoot . DIRECTORY_SEPARATOR . 'storage');
$state->createMarker('0.13.0');
$identityStore = new InstallationIdentityStore($appRoot . DIRECTORY_SEPARATOR . 'storage');
$installation = $identityStore->getOrCreate();
$registry = CoreMigrationRegistry::forProject($base);
$artifacts = new LegacyBootstrapSourceArtifactStore($appRoot . DIRECTORY_SEPARATOR . 'storage', $registry);
$targetEntry = new PackageInventoryEntry('future.php', 5, hash('sha256', 'later'), PackageOwnership::PACKAGE_OWNED);
$target = new Copot\Core\CanonicalTargetInventory([
    new Copot\Core\CanonicalTargetInventoryEntry('APP_ROOT', 'package-owned', 'future.php', 5, hash('sha256', 'later')),
]);
$package = new PackageContract(
    PackageContract::WEBCORE_PACKAGE_TYPE,
    3,
    '0.13.0',
    'release-separate-source',
    'tree-separate-source',
    new PackageCompatibility('0.13.0'),
    new PackageRuntimeCompatibility('8.1.0', ['mysql' => '8.0.0'], ['json']),
    [$targetEntry],
    new PackageMigrationDeclaration(),
    new PackageTargetRequirements(),
    $target,
    'full'
);
$zipPath = $tmp . DIRECTORY_SEPARATOR . 'package.zip';
$zip = new ZipArchive();
$assert($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'fixture ZIP could not be created');
$zip->addFromString('.copot/package.json', json_encode([
    'package_type' => PackageContract::WEBCORE_PACKAGE_TYPE,
    'manifest_contract_version' => 3,
    'target_webcore_version' => '0.13.0',
    'release_identity' => 'release-separate-source',
    'source_tree_identity' => 'tree-separate-source',
    'source_compatibility' => ['minimum_source_version' => '0.13.0', 'maximum_source_version' => null],
    'runtime_compatibility' => ['minimum_php_version' => '8.1.0', 'minimum_database_versions' => ['mysql' => '8.0.0'], 'required_extensions' => ['json']],
    'inventory' => [['path' => 'future.php', 'byte_size' => 5, 'sha256' => hash('sha256', 'later'), 'ownership' => 'package_owned']],
    'migration_declaration' => ['declares_core_migrations' => false, 'declaration_identity' => null],
    'target_requirements' => [],
    'coverage' => 'full',
    'target_inventory' => ['serialization_version' => 1, 'fingerprint' => $target->fingerprint(), 'entries' => [['root' => 'APP_ROOT', 'ownership' => 'package-owned', 'path' => 'future.php', 'byte_size' => 5, 'sha256' => hash('sha256', 'later')]]],
    'source_state_requirements' => ['allowed_inventory_fingerprints' => []],
    'removals' => [],
    'renames' => [],
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
$zip->addFromString('future.php', 'later');
$zip->close();

$entry = new LegacyToV3BootstrapEntry(
    $appRoot,
    $publicRoot,
    $identityStore,
    $state,
    new InstalledStateInspector(),
    static fn (): ExistingInstallEvidence => new ExistingInstallEvidence(true, true),
    static fn (Copot\Core\InstalledStateInspection $installed): LegacyClassificationResult => LegacyClassificationResult::canonicalBaseline('schema-separate-source', '0.13.0', $installed->snapshot()),
    static fn (): PDO => throw new RuntimeException('fixture classifier does not require a database connection'),
    $tmp . DIRECTORY_SEPARATOR . 'schema.sql',
    $registry,
    new PackageV3SourceRelativePlanner(),
    $artifacts
);
$context = new DeploymentContext($appRoot, $publicRoot, '/85eb6a4c');
$composer = new LegacyToV3BootstrapPreparationComposer(
    $context,
    new ZipIntakeService($appRoot, null, null, null, null, $installation->value()),
    new PackageManifestReader(),
    new PackageInventoryVerifier(),
    $entry
);
$runner = new LegacyBootstrapPreparationRunner($sourceRoot, $context, $composer);
$preparation = $runner->prepare($zipPath);
$assert($preparation->status() === LegacyBootstrapPreparation::ELIGIBLE, 'separate-source preparation did not delegate successfully');
$assert($preparation->toArray()['operational_ready'] === false, 'unbound preparation claimed operational readiness');
$assert($preparation->artifactPath() !== null, 'unbound preparation did not publish provisional evidence');
$assert($runner->sourceRoot() === realpath($sourceRoot), 'execution source identity was not retained');
$assert($runner->target()->appRoot() === realpath($appRoot) && $runner->target()->publicRoot() === realpath($publicRoot), 'target roots were not retained independently');
$assert($preparation->artifactPath() !== null && str_starts_with($preparation->artifactPath(), realpath($appRoot)), 'artifact was not published under the target lifecycle storage');
$rehydrated = $artifacts->rehydrate($preparation->artifactPath());
$assert($rehydrated->provisional() && !$rehydrated->operationalReady(), 'rehydrated evidence became operational');
$assert($rehydrated->sourceInventory() instanceof LegacyBootstrapSourceInventory, 'original source inventory was not rehydrated');
$before = $rehydrated->sourceInventoryFingerprint();
$injectedPath = $appRoot . DIRECTORY_SEPARATOR . 'injected-tool.php';
$injectedBytes = '<?php tooling';
file_put_contents($injectedPath, $injectedBytes);
$manifestPath = $tmp . DIRECTORY_SEPARATOR . 'reviewed-injection-manifest.json';
$manifestContents = json_encode([
    'installation_identity' => $installation->value(),
    'operation_identity' => $preparation->operationIdentity(),
    'plan_identity' => $preparation->planIdentity(),
    'original_source_inventory_fingerprint' => $before,
    'target_inventory_fingerprint' => $target->fingerprint(),
    'target_app_root' => realpath($appRoot),
    'target_public_root' => realpath($publicRoot),
    'execution_source_root' => realpath($sourceRoot),
    'paths' => ['bootstrap/autoload.php'],
    'changes' => [[
        'action' => 'ADD', 'root' => 'APP_ROOT', 'path' => 'injected-tool.php',
        'byte_size' => strlen($injectedBytes), 'sha256' => hash('sha256', $injectedBytes),
    ]],
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
file_put_contents($manifestPath, $manifestContents);
$manifestIdentity = hash('sha256', $manifestContents);
$handoff = $runner->bindReviewedInjectionManifest($preparation, $manifestPath, $manifestIdentity);
$assert($handoff->preparation()->eligible(), 'typed separate-source handoff was not produced');
$handoffData = $handoff->toArray();
$assert($handoffData['reviewed_injection_manifest_identity'] === $manifestIdentity, 'reviewed injection manifest identity was not bound');
$assert($handoffData['reviewed_injection_manifest_path'] === realpath($manifestPath), 'reviewed injection manifest path was not retained');
$assert($handoffData['original_source_inventory_fingerprint'] === $before, 'handoff did not retain original inventory evidence');
$assert($handoffData['post_injection_freshness_reconciliation_required'] === true && $handoffData['operational_ready'] === false, 'handoff incorrectly claimed post-injection readiness');
$after = $artifacts->rehydrate($preparation->artifactPath());
$assert($after->sourceInventoryFingerprint() === $before, 'post-injection bytes changed immutable pre-injection evidence');
$assert(!array_filter($after->sourceInventory()->entries(), static fn ($entry): bool => $entry->path() === 'injected-tool.php'), 'post-injection tooling was incorrectly added to original evidence');
$reconciler = new LegacyBootstrapPostInjectionReconciler($artifacts, new LegacyBootstrapLiveInventoryProvider($appRoot, $publicRoot));
$reconciled = $reconciler->reconcile($handoff);
$assert($reconciled->status() === LegacyBootstrapPostInjectionReconciliationResult::MATCHED && !$reconciled->operationalReady(), 'reviewed post-injection transition did not reconcile as non-operational evidence');
$assert($reconciled->collisionEvidence() === $rehydrated->collisions(), 'original collision evidence was not retained during reconciliation');
file_put_contents($injectedPath, '<?php unexpected');
$assert($reconciler->reconcile($handoff)->status() === LegacyBootstrapPostInjectionReconciliationResult::STALE, 'unexpected injected byte drift was accepted');
file_put_contents($injectedPath, $injectedBytes);
file_put_contents($appRoot . DIRECTORY_SEPARATOR . 'unreviewed.php', '<?php unreviewed');
$assert($reconciler->reconcile($handoff)->status() === LegacyBootstrapPostInjectionReconciliationResult::STALE, 'unreviewed extra file was accepted');
unlink($appRoot . DIRECTORY_SEPARATOR . 'unreviewed.php');
unlink($injectedPath);
$assert($reconciler->reconcile($handoff)->status() === LegacyBootstrapPostInjectionReconciliationResult::STALE, 'missing reviewed transition file was accepted');
file_put_contents($injectedPath, $injectedBytes);
$artifactContents = file_get_contents($preparation->artifactPath());
$assert(is_string($artifactContents), 'published artifact could not be read for tamper fixture');
file_put_contents($preparation->artifactPath(), '{"corrupt":true}');
$assert($reconciler->reconcile($handoff)->status() === LegacyBootstrapPostInjectionReconciliationResult::UNVERIFIED, 'corrupted provisional artifact was accepted');
file_put_contents($preparation->artifactPath(), $artifactContents);
$mismatchedManifestContents = str_replace($target->fingerprint(), str_repeat('f', 64), $manifestContents);
$mismatchedManifestPath = $tmp . DIRECTORY_SEPARATOR . 'mismatched-target-manifest.json';
file_put_contents($mismatchedManifestPath, $mismatchedManifestContents);
$mismatchedManifest = LegacyBootstrapReviewedInjectionManifest::fromFile($mismatchedManifestPath, hash('sha256', $mismatchedManifestContents));
$mismatchedHandoff = new LegacyBootstrapPreparationHandoff($preparation, $runner->sourceRoot(), $runner->target(), $mismatchedManifest);
$assert($reconciler->reconcile($mismatchedHandoff)->status() === LegacyBootstrapPostInjectionReconciliationResult::STALE, 'target fingerprint manifest mismatch was accepted');
$throws(static fn () => $runner->prepareHandoff($zipPath, $manifestPath, str_repeat('0', 64)), 'manifest hash mismatch was accepted');
$throws(static fn () => $runner->prepareHandoff($zipPath, $tmp . DIRECTORY_SEPARATOR . 'missing-manifest.json', $manifestIdentity), 'missing reviewed injection manifest was accepted');
$wrongBindingPath = $tmp . DIRECTORY_SEPARATOR . 'wrong-binding-manifest.json';
$wrongBinding = json_encode([
    'installation_identity' => 'other-installation',
    'operation_identity' => $preparation->operationIdentity(),
    'plan_identity' => $preparation->planIdentity(),
    'original_source_inventory_fingerprint' => $before,
    'target_inventory_fingerprint' => $target->fingerprint(),
    'target_app_root' => realpath($appRoot),
    'target_public_root' => realpath($publicRoot),
    'execution_source_root' => realpath($sourceRoot),
    'changes' => [],
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
file_put_contents($wrongBindingPath, $wrongBinding);
$throws(static fn () => $runner->bindReviewedInjectionManifest($preparation, $wrongBindingPath, hash('sha256', $wrongBinding)), 'cross-installation manifest binding was accepted');
$wrongOperation = str_replace($preparation->operationIdentity(), $preparation->operationIdentity() . '-mismatch', $manifestContents);
$wrongOperationPath = $tmp . DIRECTORY_SEPARATOR . 'wrong-operation-manifest.json';
file_put_contents($wrongOperationPath, $wrongOperation);
$throws(static fn () => $runner->bindReviewedInjectionManifest($preparation, $wrongOperationPath, hash('sha256', $wrongOperation)), 'operation identity mismatch was accepted');
$throws(static fn () => $runner->bindReviewedInjectionManifest($preparation, $manifestPath, str_repeat('0', 64)), 'manifest hash mismatch was accepted by explicit binding');
mkdir($appRoot . DIRECTORY_SEPARATOR . 'bootstrap', 0700, true);
file_put_contents($appRoot . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'autoload.php', '<?php');
$throws(static fn () => new LegacyBootstrapPreparationRunner($appRoot, $context, $composer), 'source/target aliasing was accepted');
$throws(static fn () => new LegacyBootstrapPreparationRunner($tmp . DIRECTORY_SEPARATOR . 'missing-source', $context, $composer), 'missing source root was accepted');
$throws(static fn () => $artifacts->rehydrate($preparation->artifactPath(), ['installation_identity' => 'wrong-installation']), 'target identity mismatch was accepted');

echo "Separate-source bootstrap preparation assertions: {$assertions}\n";
