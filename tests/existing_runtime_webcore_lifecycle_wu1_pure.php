<?php

declare(strict_types=1);

use Copot\Core\CoreMigrationPlan;
use Copot\Core\CoreMigrationRegistry;
use Copot\Core\ExistingInstallEvidence;
use Copot\Core\FilesystemReconciliationAction;
use Copot\Core\InstalledStateSnapshot;
use Copot\Core\LegacyClassification;
use Copot\Core\LegacyClassificationResult;
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
use Copot\Core\RuntimeCompatibilityContext;
use Copot\Core\StagedFile;
use Copot\Core\StagedPayload;
use Copot\Core\StagingSession;
use Copot\Core\TrustedWebcorePackageTarget;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) { throw new RuntimeException($message); }
};
$remove = static function (string $path) use (&$remove): void {
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) { return; }
    foreach (scandir($path) ?: [] as $entry) { if ($entry !== '.' && $entry !== '..') { $remove($path . DIRECTORY_SEPARATOR . $entry); } }
    @rmdir($path);
};

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-iu2-wu1-pure-' . bin2hex(random_bytes(6));
$stagingRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-iu2-wu1-staging-' . bin2hex(random_bytes(6));
mkdir($root . DIRECTORY_SEPARATOR . 'storage', 0700, true);
mkdir($root . DIRECTORY_SEPARATOR . 'app', 0700, true);
mkdir($stagingRoot, 0700, true);
file_put_contents($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'replace.txt', 'old');
file_put_contents($root . DIRECTORY_SEPARATOR . 'keep.txt', 'operator-data');

try {
    $session = StagingSession::create($root, $stagingRoot);
    mkdir($session->payloadPath() . DIRECTORY_SEPARATOR . 'app', 0700, true);
    file_put_contents($session->payloadPath() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'replace.txt', 'new');
    file_put_contents($session->payloadPath() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'create.txt', 'created');
    $replace = new StagedFile('app/replace.txt', 3, hash('sha256', 'new'));
    $create = new StagedFile('app/create.txt', 7, hash('sha256', 'created'));
    $payload = new StagedPayload($session, str_repeat('c', 64), [$replace, $create]);
    $package = new PackageContract(
        PackageContract::WEBCORE_PACKAGE_TYPE,
        PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION,
        '0.13.0',
        'trusted-release',
        null,
        new PackageCompatibility('0.0.0'),
        new PackageRuntimeCompatibility('8.0.0', ['sqlite' => '3.0.0'], ['json']),
        [
            new PackageInventoryEntry('app/replace.txt', 3, hash('sha256', 'new'), PackageOwnership::PACKAGE_OWNED),
            new PackageInventoryEntry('app/create.txt', 7, hash('sha256', 'created'), PackageOwnership::PACKAGE_OWNED),
        ],
        new PackageMigrationDeclaration(false)
    );
    $target = TrustedWebcorePackageTarget::fromManifest(new PackageManifest($package, '.copot/package.json', $payload), new PackageInventoryVerifier());
    $classification = LegacyClassificationResult::canonicalBaseline('canonical-schema:verified');
    $runtime = new RuntimeCompatibilityContext(PHP_VERSION, ['sqlite' => '3.0.0'], ['json']);
    $planner = new LegacyReconciliationPlanner();
    $registry = new CoreMigrationRegistry('core-current', []);
    $guard = new LiveTreePathGuard($root);
    $plan = $planner->plan($target, $classification, $runtime, $registry, $guard);
    $repeat = $planner->plan($target, $classification, $runtime, $registry, $guard);

    $actions = $plan->filesystemActions();
    $assert(count($actions) === 2, 'Plan did not contain exactly the package inventory actions.');
    $assert($actions[0]->action() === FilesystemReconciliationAction::CREATE, 'Missing file was not planned as create.');
    $assert($actions[1]->action() === FilesystemReconciliationAction::REPLACE, 'Drifted file was not planned as replace.');
    $assert($plan->identity() === $repeat->identity(), 'Equivalent reconciliation plans did not have stable identities.');
    $assert($plan->operationIdentity() === $repeat->operationIdentity(), 'Equivalent operation identities were not stable.');
    $assert(!str_contains(json_encode($plan->toArray(), JSON_THROW_ON_ERROR), 'delete'), 'Reconciliation plan introduced stale-file deletion.');
    $assert(file_get_contents($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'replace.txt') === 'old', 'Planning mutated a live package-owned file.');
    $assert(file_get_contents($root . DIRECTORY_SEPARATOR . 'keep.txt') === 'operator-data', 'Planning changed an unrelated operator-owned file.');

    foreach ([
        LegacyClassificationResult::unknown('not proven'),
        LegacyClassificationResult::committed(new InstalledStateSnapshot('0.13.0', new DateTimeImmutable(), 'release', null, 1, 'schema', 'migration')),
    ] as $rejectedClassification) {
        try {
            $planner->plan($target, $rejectedClassification, $runtime, $registry, $guard);
            $assert(false, 'A non-plannable classification produced a reconciliation plan.');
        } catch (Throwable) {
            $assert(true, 'Non-plannable classification failed closed.');
        }
    }
    $payload->cleanup();
} finally {
    $remove($root);
    $remove($stagingRoot);
}

echo "IU2 WU1 pure classification/planning: {$assertions} assertions passed\n";
