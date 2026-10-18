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
use Copot\Core\LegacyBootstrapSourceInventory;
use Copot\Core\LegacyClassificationResult;
use Copot\Core\LegacyToV3BootstrapEntry;
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
use Copot\Core\StagingSession;

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
$artifactStore = new LegacyBootstrapSourceArtifactStore($tmp . '/app/storage');
$target = new CanonicalTargetInventory([
    new CanonicalTargetInventoryEntry('APP_ROOT', 'package-owned', 'future.php', 5, hash('sha256', 'later')),
]);
$package = new PackageContract(
    PackageContract::WEBCORE_PACKAGE_TYPE, 3, '0.14.0', 'release-test', 'tree-test',
    new PackageCompatibility('0.13.0'), new PackageRuntimeCompatibility('8.1.0', ['mysql' => '8.0.0'], ['json']),
    [new PackageInventoryEntry('future.php', 5, hash('sha256', 'later'), PackageOwnership::PACKAGE_OWNED)],
    new PackageMigrationDeclaration(), new PackageTargetRequirements(), $target, 'full'
);
$session = StagingSession::create($tmp . '/app');
$manifest = new PackageManifest($package, '.copot/package.json', new StagedPayload($session, str_repeat('a', 64), []));
$classifier = static fn (): LegacyClassificationResult => LegacyClassificationResult::canonicalBaseline('schema-test', '0.13.0');
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

echo "WU2 legacy bootstrap assertions: {$assertions}\n";
