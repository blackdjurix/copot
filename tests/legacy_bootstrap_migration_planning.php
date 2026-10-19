<?php

declare(strict_types=1);

use Copot\Core\AppliedMigrationRecord;
use Copot\Core\CanonicalTargetInventory;
use Copot\Core\CanonicalTargetInventoryEntry;
use Copot\Core\CoreMigrationDescriptor;
use Copot\Core\CoreMigrationRegistry;
use Copot\Core\LegacyClassificationResult;
use Copot\Core\LegacyCoreMigrationPlanner;
use Copot\Core\LegacyReconciliationPlanner;
use Copot\Core\LiveTreePathGuard;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageContract;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageInventoryVerifier;
use Copot\Core\PackageManifest;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageOwnership;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\PackageTargetRequirements;
use Copot\Core\RuntimeCompatibilityContext;
use Copot\Core\StagedFile;
use Copot\Core\StagedPayload;
use Copot\Core\StagingSession;
use Copot\Core\TrustedWebcorePackageTarget;

$base = dirname(__DIR__);
require $base . '/bootstrap/autoload.php';
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void { $assertions++; if (!$condition) throw new RuntimeException($message); };
$remove = static function (string $path) use (&$remove): void { if (is_link($path) || is_file($path)) { @unlink($path); return; } if (!is_dir($path)) return; foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry); @rmdir($path); };

$descriptor = static fn (string $id, int $sequence, string $source, string $target, string $schema): CoreMigrationDescriptor => new CoreMigrationDescriptor($id, $sequence, $source, null, $target, $schema, CoreMigrationDescriptor::TRANSACTIONAL, 'source:' . $id, static function (): void {});
$migrationA = $descriptor('core.bootstrap.a', 10, '0.13.0', '0.14.0', 'schema-14');
$migrationB = $descriptor('core.bootstrap.b', 20, '0.14.0', '0.15.0', 'schema-15');
$registry = new CoreMigrationRegistry('core-bootstrap-test', [$migrationA, $migrationB]);
$inventoryEntry = new PackageInventoryEntry('marker.txt', 6, hash('sha256', 'marker'), PackageOwnership::PACKAGE_OWNED);
$targetEntry = new CanonicalTargetInventoryEntry(CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'marker.txt', 6, hash('sha256', 'marker'));
$package = static fn (string $target): PackageContract => new PackageContract(PackageContract::WEBCORE_PACKAGE_TYPE, 3, $target, 'release-' . $target, 'tree-' . $target, new PackageCompatibility('0.13.0'), new PackageRuntimeCompatibility('8.0.0', ['sqlite' => '3.0.0'], ['json']), [$inventoryEntry], new PackageMigrationDeclaration(true, 'core-bootstrap-test'), new PackageTargetRequirements(), new CanonicalTargetInventory([$targetEntry]), 'full');
$planner = new LegacyCoreMigrationPlanner();
$baseline = LegacyClassificationResult::canonicalBaseline('schema-13', '0.13.0');
$baselinePlan = $planner->plan($package('0.15.0'), $baseline, $registry);
$assert($baselinePlan->isAccepted() && count($baselinePlan->migrations()) === 2, 'Valid canonical baseline did not plan the full forward migration suffix.');
$assert($baselinePlan->initialSchemaIdentity() === 'schema-13' && $baselinePlan->virtualFinalSchemaIdentity() === 'schema-15', 'Canonical baseline schema identities changed.');
$emptyLedgerPlan = $planner->plan($package('0.13.0'), $baseline, new CoreMigrationRegistry('core-bootstrap-test', []));
$assert($emptyLedgerPlan->isAccepted() && $emptyLedgerPlan->migrations() === [], 'Empty-but-valid canonical ledger did not produce a no-op migration plan.');
$recordA = new AppliedMigrationRecord($migrationA->id(), $migrationA->sequence(), $migrationA->targetWebcoreVersion(), $migrationA->targetSchemaIdentity(), $migrationA->checksum(), new DateTimeImmutable('2026-01-01T00:00:00Z'));
$prefixPlan = $planner->plan($package('0.15.0'), LegacyClassificationResult::knownMigrationPrefix([$recordA]), $registry);
$assert($prefixPlan->isAccepted() && array_map(static fn (CoreMigrationDescriptor $migration): string => $migration->id(), $prefixPlan->migrations()) === ['core.bootstrap.b'], 'Known prefix did not plan only the remaining suffix.');
$badRecord = new AppliedMigrationRecord($migrationA->id(), $migrationA->sequence(), $migrationA->targetWebcoreVersion(), $migrationA->targetSchemaIdentity(), str_repeat('a', 64), new DateTimeImmutable('2026-01-01T00:00:00Z'));
$assert(!$planner->plan($package('0.15.0'), LegacyClassificationResult::knownMigrationPrefix([$badRecord]), $registry)->isAccepted(), 'Registry/prefix checksum mismatch was accepted.');
$assert(!$planner->plan($package('0.15.0'), LegacyClassificationResult::unknown('unsupported schema'), $registry)->isAccepted(), 'Unsupported source schema evidence was accepted.');
$assert(!$planner->plan($package('0.12.0'), $baseline, $registry)->isAccepted(), 'Legacy migration downgrade was accepted.');

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-bootstrap-migration-' . bin2hex(random_bytes(6));
$staging = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-bootstrap-migration-staging-' . bin2hex(random_bytes(6));
mkdir($root, 0700, true); mkdir($staging, 0700, true);
try {
    $session = StagingSession::create($root, $staging);
    mkdir($session->payloadPath(), 0700, true);
    file_put_contents($session->payloadPath() . DIRECTORY_SEPARATOR . 'marker.txt', 'marker');
    $payload = new StagedPayload($session, str_repeat('b', 64), [new StagedFile('marker.txt', 6, hash('sha256', 'marker'))]);
    $contract = $package('0.15.0');
    $target = TrustedWebcorePackageTarget::fromManifest(new PackageManifest($contract, '.copot/package.json', $payload), new PackageInventoryVerifier());
    $fullPlan = (new LegacyReconciliationPlanner($planner))->plan($target, $baseline, new RuntimeCompatibilityContext(PHP_VERSION, ['sqlite' => '3.0.0'], ['json']), $registry, new LiveTreePathGuard($root));
    $assert($fullPlan->migrationPlan()->initialWebcoreVersion() === $baselinePlan->initialWebcoreVersion() && $fullPlan->migrationPlan()->virtualFinalWebcoreVersion() === $baselinePlan->virtualFinalWebcoreVersion(), 'Migration-only and full legacy planning versions diverged.');
    $assert(array_map(static fn (CoreMigrationDescriptor $migration): string => $migration->checksum(), $fullPlan->migrationPlan()->migrations()) === array_map(static fn (CoreMigrationDescriptor $migration): string => $migration->checksum(), $baselinePlan->migrations()), 'Migration-only and full legacy planning descriptors diverged.');
    $assert(!is_file($root . DIRECTORY_SEPARATOR . 'marker.txt'), 'Migration-only/full planning mutated the live filesystem.');
    $payload->cleanup();
} finally { $remove($root); $remove($staging); }

echo "Legacy bootstrap migration planning: {$assertions} assertions passed\n";
