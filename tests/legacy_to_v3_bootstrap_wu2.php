<?php

$base = dirname(__DIR__);
require $base . '/bootstrap/autoload.php';

use Copot\Core\CanonicalSchemaBaselineVerifier;
use Copot\Core\CanonicalTargetInventory;
use Copot\Core\CanonicalTargetInventoryEntry;
use Copot\Core\CoreMigrationRegistry;
use Copot\Core\ExistingInstallEvidence;
use Copot\Core\InstallationIdentityStore;
use Copot\Core\InstallationState;
use Copot\Core\InstalledStateInspector;
use Copot\Core\LegacyBootstrapSourceArtifactStore;
use Copot\Core\LegacyBootstrapSourceArtifact;
use Copot\Core\LegacyBootstrapIdentityComposer;
use Copot\Core\LegacyBootstrapIdentityHandoff;
use Copot\Core\LegacyBootstrapLiveInventoryProvider;
use Copot\Core\LegacyBootstrapTargetFreshnessResult;
use Copot\Core\LegacyBootstrapTargetFreshnessVerifier;
use Copot\Core\LegacyBootstrapSourceInventory;
use Copot\Core\LegacyBootstrapPreparation;
use Copot\Core\LegacyClassificationResult;
use Copot\Core\LegacyToV3BootstrapEntry;
use Copot\Core\LegacyToV3BootstrapPreparationComposer;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageContract;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageManifest;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageOwnership;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\PackageTargetRequirements;
use Copot\Core\PackageV3SourceRelativePlanner;
use Copot\Core\StagedPayload;
use Copot\Core\StagedFile;
use Copot\Core\StagingSession;
use Copot\Core\WebcoreApplyPlan;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu2-' . bin2hex(random_bytes(6));
mkdir($tmp . '/app/storage', 0700, true);
mkdir($tmp . '/public', 0700, true);
file_put_contents($tmp . '/app/legacy.php', '<?php');
file_put_contents($tmp . '/public/index.php', '<?php');

$state = new InstallationState($tmp . '/app/storage');
$state->createMarker('0.13.0');
file_put_contents($tmp . '/app/storage/.env', "DB_DATABASE=test\n");
$identityStore = new InstallationIdentityStore($tmp . '/app/storage');
$identityStore->getOrCreate();
$identity = new InstallationIdentityStore($tmp . '/app/storage');
$artifactStore = new LegacyBootstrapSourceArtifactStore($tmp . '/app/storage', CoreMigrationRegistry::forProject($base));
$target = new CanonicalTargetInventory([
    new CanonicalTargetInventoryEntry('APP_ROOT', 'package-owned', 'future.php', 5, hash('sha256', 'later')),
]);
$package = new PackageContract(
    PackageContract::WEBCORE_PACKAGE_TYPE, 3, '0.13.0', 'release-test', 'tree-test',
    new PackageCompatibility('0.13.0'), new PackageRuntimeCompatibility('8.1.0', ['mysql' => '8.0.0'], ['json']),
    [new PackageInventoryEntry('future.php', 5, hash('sha256', 'later'), PackageOwnership::PACKAGE_OWNED)],
    new PackageMigrationDeclaration(), new PackageTargetRequirements(), $target, 'full'
);
$session = StagingSession::create($tmp . '/app');
mkdir($session->payloadPath(), 0700, true);
file_put_contents($session->payloadPath() . '/future.php', 'later');
$stagedFuture = new StagedFile('future.php', 5, hash('sha256', 'later'));
$manifest = new PackageManifest($package, '.copot/package.json', new StagedPayload($session, str_repeat('a', 64), [$stagedFuture]));
$classificationDrift = false;
$classifier = static function (Copot\Core\InstalledStateInspection $installed) use (&$classificationDrift): LegacyClassificationResult {
    return $classificationDrift ? LegacyClassificationResult::unknown('migration ledger drift') : LegacyClassificationResult::canonicalBaseline('schema-test', '0.13.0', $installed->snapshot());
};
$entry = new LegacyToV3BootstrapEntry(
    $tmp . '/app', $tmp . '/public', $identity, $state, new InstalledStateInspector(),
    static fn (): ExistingInstallEvidence => new ExistingInstallEvidence(true, true), $classifier,
    static fn (): PDO => throw new RuntimeException('not called by test classifier'), $tmp . '/schema.sql',
    CoreMigrationRegistry::forProject($base), new PackageV3SourceRelativePlanner(), $artifactStore
);
$eligible = $entry->prepare($manifest);
$assert($eligible->status() === 'eligible', 'legacy baseline should be eligible when target destinations are absent');
$assert($eligible->legacyClassification()->isLegacyCandidate(), 'legacy classification must remain explicit');
$assert($eligible->sourceInventory() instanceof LegacyBootstrapSourceInventory, 'provisional source inventory must be captured');
$assert($eligible->artifactPath() !== null && is_file($eligible->artifactPath()), 'provisional artifact must be durable and inspectable');
$assert($eligible->collisions()[0]['action'] === 'ADD', 'absent target must classify as ADD');
$rehydrated = $artifactStore->rehydrate($eligible->artifactPath(), [
    'operation_identity' => $eligible->operationIdentity(),
    'installation_identity' => $eligible->installationIdentity(),
    'plan_identity' => $eligible->planIdentity(),
    'package_identity' => PackageV3SourceRelativePlanner::packageIdentity($package),
    'target_inventory_fingerprint' => $target->fingerprint(),
    'archive_identity' => $manifest->payload()->archiveSha256(),
    'payload_identity' => WebcoreApplyPlan::fromPayload($manifest->payload())->identity(),
    'apply_plan_identity' => WebcoreApplyPlan::fromPayload($manifest->payload())->identity(),
    'source_inventory_fingerprint' => $eligible->sourceInventory()->fingerprint(),
]);
$assert($rehydrated instanceof LegacyBootstrapSourceArtifact, 'valid artifact must rehydrate to a typed provisional result');
$assert($rehydrated->sourceInventoryFingerprint() === $eligible->sourceInventory()->fingerprint(), 'rehydrated source fingerprint changed');
$assert($rehydrated->targetInventoryFingerprint() === $target->fingerprint(), 'rehydrated target fingerprint changed');
$assert($rehydrated->classification()->sourceWebcoreVersion() === '0.13.0' && $rehydrated->classification()->sourceSchemaIdentity() === 'schema-test', 'classification source identity did not round-trip');
$assert($rehydrated->archiveIdentity() === $manifest->payload()->archiveSha256(), 'archive identity did not round-trip');
$assert($rehydrated->payloadIdentity() === $rehydrated->applyPlanIdentity(), 'payload/apply-plan identity linkage changed');
$assert($rehydrated->recoveryIdentity() === null, 'preparation artifact must not invent a recovery identity');
$assert($rehydrated->provisional() && !$rehydrated->operationalReady() && !$rehydrated->targetFreshnessVerified(), 'rehydration must remain provisional and non-operational');
$identityComposer = new LegacyBootstrapIdentityComposer($artifactStore, new Copot\Core\PackageInventoryVerifier(), CoreMigrationRegistry::forProject($base));
$handoff = $identityComposer->compose($eligible->artifactPath(), $manifest, $eligible->installationIdentity());
$assert($handoff instanceof LegacyBootstrapIdentityHandoff, 'eligible immutable evidence must produce a typed identity handoff');
$assert(!$handoff->operationalReady() && !$handoff->targetFreshnessVerified() && !$handoff->recoveryBound(), 'identity handoff must remain non-operational');
$assert($handoff->target()->archiveIdentity() === $rehydrated->archiveIdentity() && $handoff->applyPlan()->identity() === $rehydrated->applyPlanIdentity(), 'canonical target/apply identities changed during composition');
$assert($handoff->migrationInput()->sourceMigrationStateIdentity() === Copot\Core\CoreMigrationStateIdentity::fromRecords($rehydrated->classification()->records()), 'migration input changed the classified prefix identity');
$assert($handoff->migrationPlan()->isAccepted() && $handoff->toArray()['core_migration_plan_available'] === true && $handoff->toArray()['migration_execution_authorized'] === false, 'canonical migration plan must remain provisional and non-executable');
$liveInventory = new LegacyBootstrapLiveInventoryProvider($tmp . '/app', $tmp . '/public');
$freshness = new LegacyBootstrapTargetFreshnessVerifier($identity, $state, new InstalledStateInspector(), static fn (): ExistingInstallEvidence => new ExistingInstallEvidence(true, true), $classifier, static fn (): PDO => throw new RuntimeException('not used by fixture classifier'), $tmp . '/schema.sql', CoreMigrationRegistry::forProject($base), $liveInventory);
$matched = $freshness->verify($handoff);
$assert($matched->status() === LegacyBootstrapTargetFreshnessResult::MATCHED && !$matched->operationalReady() && $matched->requiresProtectedBoundaryRevalidation(), 'unchanged target must match without granting operational authority');
$originalLegacyBytes = file_get_contents($tmp . '/app/legacy.php');
file_put_contents($tmp . '/app/legacy.php', '<?php // changed');
$assert($freshness->verify($handoff)->status() === LegacyBootstrapTargetFreshnessResult::STALE, 'changed live bytes must be stale');
file_put_contents($tmp . '/app/legacy.php', $originalLegacyBytes);
file_put_contents($tmp . '/app/future.php', 'later');
$assert($freshness->verify($handoff)->status() === LegacyBootstrapTargetFreshnessResult::STALE, 'added target collision must be stale');
unlink($tmp . '/app/future.php');
$assert($freshness->verify($handoff)->status() === LegacyBootstrapTargetFreshnessResult::MATCHED, 'removed transient collision must restore the immutable evidence match');
mkdir($tmp . '/app/future.php');
$typeResult = $freshness->verify($handoff);
$assert($typeResult->status() === LegacyBootstrapTargetFreshnessResult::STALE && str_contains(implode(' ', $typeResult->diagnostics()), 'Target path type changed'), 'target path/type mismatch must be stale');
rmdir($tmp . '/app/future.php');
$markerPath = $tmp . '/app/storage/installed.lock';
$markerBytes = file_get_contents($markerPath);
file_put_contents($markerPath, str_replace('0.13.0', '0.12.0', $markerBytes));
$assert($freshness->verify($handoff)->status() === LegacyBootstrapTargetFreshnessResult::STALE, 'installed-state marker drift must be stale');
file_put_contents($markerPath, $markerBytes);
$classificationDrift = true;
$assert($freshness->verify($handoff)->status() === LegacyBootstrapTargetFreshnessResult::STALE, 'migration-ledger/classification drift must be stale');
$classificationDrift = false;
$targetProperty = new ReflectionProperty(PackageContract::class, 'targetInventory');
$targetProperty->setValue($package, new CanonicalTargetInventory([new CanonicalTargetInventoryEntry('APP_ROOT', 'package-owned', 'other.php', 5, hash('sha256', 'later'))]));
$assert($freshness->verify($handoff)->status() === LegacyBootstrapTargetFreshnessResult::STALE, 'candidate target fingerprint drift must be stale');
$targetProperty->setValue($package, $target);
$unverified = new LegacyBootstrapTargetFreshnessVerifier($identity, $state, new InstalledStateInspector(), static fn (): ExistingInstallEvidence => new ExistingInstallEvidence(true, true), static fn (): LegacyClassificationResult => throw new RuntimeException('classification unavailable'), static fn (): PDO => throw new RuntimeException('connection unavailable'), $tmp . '/schema.sql', CoreMigrationRegistry::forProject($base), $liveInventory);
$assert($unverified->verify($handoff)->status() === LegacyBootstrapTargetFreshnessResult::UNVERIFIED, 'incomplete classifier evidence must remain unverified');
$assert($identityComposer->compose($eligible->artifactPath(), $manifest, $eligible->installationIdentity())->identity() === $handoff->identity(), 'bootstrap identity handoff reconstruction is not deterministic');
$changedTarget = new CanonicalTargetInventory([new CanonicalTargetInventoryEntry('APP_ROOT', 'package-owned', 'future.php', 6, hash('sha256', 'stale'))]);
$assert($artifactStore->rehydrate($eligible->artifactPath())->targetInventoryFingerprint() !== $changedTarget->fingerprint(), 'rehydration must not claim freshness against a changed target');
$assert($artifactStore->rehydrate($eligible->artifactPath())->toArray() === $rehydrated->toArray(), 'identical artifact reread must be stable');
$artifactContents = file_get_contents($eligible->artifactPath());
$assert(is_string($artifactContents), 'artifact contents must be readable for fixture validation');
$retryRecord = json_decode($artifactContents, true, 32, JSON_THROW_ON_ERROR);
$assert($artifactStore->publish($retryRecord) === $eligible->artifactPath(), 'identical artifact retry must reuse the immutable provisional artifact');
$assert(file_get_contents($eligible->artifactPath()) === $artifactContents, 'identical artifact retry must not overwrite published bytes');
$file = $eligible->artifactPath();
$throws = static function (callable $callback, string $message) use ($assert): void {
    try { $callback(); } catch (Throwable) { return; }
    $assert(false, $message);
};
file_put_contents($file, str_replace('legacy-live-tree-scan', 'corrupted-tree-scan', $artifactContents));
$throws(static fn () => $artifactStore->rehydrate($file), 'corrupted artifact must be rejected');
file_put_contents($file, $artifactContents);
$throws(static fn () => $artifactStore->rehydrate($file, ['operation_identity' => 'legacy-to-v3-bootstrap-sha256:' . str_repeat('a', 64)]), 'identity-mismatched artifact must be rejected');
$throws(static fn () => $identityComposer->compose($file, $manifest, 'wrong-installation'), 'installation identity drift must be rejected');
$archiveDriftManifest = new PackageManifest($package, '.copot/package.json', new StagedPayload($session, str_repeat('b', 64), [$stagedFuture]));
$throws(static fn () => $identityComposer->compose($file, $archiveDriftManifest, $eligible->installationIdentity()), 'archive drift must be rejected');
$payloadDriftManifest = new PackageManifest($package, '.copot/package.json', new StagedPayload($session, str_repeat('a', 64), [new StagedFile('future.php', 5, hash('sha256', 'other'))]));
$throws(static fn () => $identityComposer->compose($file, $payloadDriftManifest, $eligible->installationIdentity()), 'payload drift must be rejected');
$throws(static fn () => $artifactStore->rehydrate($file, ['target_inventory_fingerprint' => str_repeat('a', 64)]), 'stale target evidence must fail identity binding');
$registry = CoreMigrationRegistry::forProject($base);
$descriptor = $registry->migrations()[0];
$prefixRecord = [
    'migration_id' => $descriptor->id(), 'sequence' => $descriptor->sequence(),
    'target_webcore_version' => $descriptor->targetWebcoreVersion(), 'target_schema_identity' => $descriptor->targetSchemaIdentity(),
    'checksum' => str_repeat('a', 64), 'applied_at' => gmdate(DATE_ATOM),
];
$prefixRecordArtifact = json_decode($artifactContents, true, 32, JSON_THROW_ON_ERROR);
$prefixRecordArtifact['legacy_classification']['classification'] = 'known_migration_prefix';
$prefixRecordArtifact['legacy_classification']['source_webcore_version'] = '0.13.0';
$prefixRecordArtifact['legacy_classification']['source_schema_identity'] = 'schema-test';
$prefixRecordArtifact['legacy_classification']['records'] = [$prefixRecord];
file_put_contents($file, json_encode($prefixRecordArtifact, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL);
$throws(static fn () => $artifactStore->rehydrate($file), 'migration-prefix evidence with registry checksum drift must be rejected');
$prefixRecordArtifact['legacy_classification']['records'][0]['checksum'] = $descriptor->checksum();
file_put_contents($file, json_encode($prefixRecordArtifact, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL);
$assert($artifactStore->rehydrate($file)->classification()->records()[0]->checksum() === $descriptor->checksum(), 'validated migration-prefix evidence did not rehydrate');
file_put_contents($file, $artifactContents);
$schemaDrift = json_decode($artifactContents, true, 32, JSON_THROW_ON_ERROR);
$schemaDrift['serialization_version'] = 2;
file_put_contents($file, json_encode($schemaDrift, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL);
$throws(static fn () => $artifactStore->rehydrate($file), 'legacy artifact schema version must be rejected');
$missingPayload = json_decode($artifactContents, true, 32, JSON_THROW_ON_ERROR);
unset($missingPayload['archive_identity']);
file_put_contents($file, json_encode($missingPayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL);
$throws(static fn () => $artifactStore->rehydrate($file), 'artifact without archive identity must be rejected');
$missingPayload = json_decode($artifactContents, true, 32, JSON_THROW_ON_ERROR);
unset($missingPayload['payload_identity'], $missingPayload['apply_plan_identity']);
file_put_contents($file, json_encode($missingPayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL);
$throws(static fn () => $artifactStore->rehydrate($file), 'artifact without payload identity must be rejected');
$missingFingerprint = json_decode($artifactContents, true, 32, JSON_THROW_ON_ERROR);
unset($missingFingerprint['target_inventory_fingerprint']);
file_put_contents($file, json_encode($missingFingerprint, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL);
$throws(static fn () => $artifactStore->rehydrate($file), 'artifact without target fingerprint must be rejected');
file_put_contents($file, $artifactContents);

file_put_contents($tmp . '/app/future.php', 'old!!');
$blocked = $entry->prepare($manifest);
$assert($blocked->status() === 'blocked', 'existing legacy destination must fail closed');
$assert($blocked->collisions()[0]['action'] === 'BLOCK', 'legacy live bytes must not receive package ownership');

$unknownEntry = new LegacyToV3BootstrapEntry(
    $tmp . '/app', $tmp . '/public', $identity, $state, new InstalledStateInspector(),
    static fn (): ExistingInstallEvidence => new ExistingInstallEvidence(true, true),
    static fn (): LegacyClassificationResult => LegacyClassificationResult::unknown('ambiguous baseline'),
    static fn (): PDO => throw new RuntimeException('not called by unknown classifier'), $tmp . '/schema.sql',
    CoreMigrationRegistry::forProject($base), new PackageV3SourceRelativePlanner(), $artifactStore
);
$unsupported = $unknownEntry->prepare($manifest);
$assert($unsupported->status() === 'blocked', 'unsupported legacy evidence must be blocked');
$assert($unsupported->reason() !== '', 'blocked preparation must retain diagnostic reason');

unlink($tmp . '/app/future.php');

$zipPath = $tmp . '/bootstrap-preparation.zip';
$zip = new ZipArchive();
$assert($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'preparation fixture ZIP could not be created');
$manifestData = [
    'package_type' => PackageContract::WEBCORE_PACKAGE_TYPE,
    'manifest_contract_version' => 3,
    'target_webcore_version' => '0.14.0',
    'release_identity' => 'release-composer-test',
    'source_tree_identity' => 'tree-composer-test',
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
];
$zip->addFromString('.copot/package.json', json_encode($manifestData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
$zip->addFromString('future.php', 'later');
$zip->close();
$composer = new LegacyToV3BootstrapPreparationComposer(
    Copot\Core\DeploymentContext::forLegacyApplication($tmp . '/app'),
    new Copot\Core\ZipIntakeService($tmp . '/app', null, null, null, null, $identity->read()?->value()),
    new Copot\Core\PackageManifestReader(),
    new Copot\Core\PackageInventoryVerifier(),
    $entry
);
$composed = $composer->prepare($zipPath);
$assert($composed->eligible(), 'bounded preparation composer must reach the existing preparation entry: ' . $composed->reason());
$assert($composed->toArray()['operational_ready'] === false, 'preparation eligibility must not claim operational readiness');
$assert(!is_file($tmp . '/app/storage/.copot-lifecycle/committed-state.json'), 'preparation must not create committed lifecycle state');

$invalidZipPath = $tmp . '/invalid-bootstrap-preparation.zip';
$invalidZip = new ZipArchive();
$assert($invalidZip->open($invalidZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'invalid fixture ZIP could not be created');
$invalidZip->addFromString('.copot/package.json', '{}');
$invalidZip->close();
$invalid = $composer->prepare($invalidZipPath);
$assert(!$invalid->eligible() && $invalid->status() === 'blocked', 'invalid bootstrap package must be blocked');

echo "WU2 legacy bootstrap assertions: {$assertions}\n";
