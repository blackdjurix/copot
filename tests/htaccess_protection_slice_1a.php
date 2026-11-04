<?php

declare(strict_types=1);

use Copot\Core\CanonicalTargetInventory;
use Copot\Core\CanonicalTargetInventoryEntry;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageContract;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageManifestReader;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageOwnership;
use Copot\Core\PackageOwnedFileApplier;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\PackageV3SourceRelativePlanner;
use Copot\Core\ProtectedResourcePolicy;
use Copot\Core\SourceInventoryArtifact;
use Copot\Core\SourceInventoryReference;
use Copot\Core\StagedFile;
use Copot\Core\StagedPayload;
use Copot\Core\StagingSession;
use Copot\Core\LiveFileActivationCapability;
use Copot\Core\LiveTreePathGuard;
use Copot\Core\WebcoreApplyPlan;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$throws = static function (callable $callback, string $message) use ($assert): void {
    try { $callback(); } catch (Throwable) { return; }
    $assert(false, $message);
};
$hash = static fn (string $bytes): string => hash('sha256', $bytes);
$remove = static function (string $path) use (&$remove): void {
    if (is_file($path) || is_link($path)) { @unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry);
    @rmdir($path);
};

$assert(ProtectedResourcePolicy::isProtected(CanonicalTargetInventoryEntry::PUBLIC_ROOT, '.htaccess'), 'Protected resource was not recognized.');
$assert(!ProtectedResourcePolicy::isProtected(CanonicalTargetInventoryEntry::APP_ROOT, '.htaccess'), 'Protection leaked to APP_ROOT.');
$throws(static function (): void {
    ProtectedResourcePolicy::assertMutable(CanonicalTargetInventoryEntry::PUBLIC_ROOT, '.htaccess');
}, 'Policy allowed direct protected mutation.');

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-hta-slice-1a-' . bin2hex(random_bytes(5));
mkdir($root, 0700, true);
$liveRoot = $root . DIRECTORY_SEPARATOR . 'live';
$stageRoot = $root . DIRECTORY_SEPARATOR . 'stage';
mkdir($liveRoot, 0700, true);
$stage = StagingSession::create($liveRoot, $stageRoot);

try {
    $normalEntry = new CanonicalTargetInventoryEntry(CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/normal.php', 5, $hash('normal'));
    $normalTarget = new CanonicalTargetInventory([$normalEntry]);
    $baseManifest = [
        'package_type' => PackageContract::WEBCORE_PACKAGE_TYPE,
        'manifest_contract_version' => 3,
        'target_webcore_version' => '0.13.0',
        'release_identity' => 'htaccess-slice-1a',
        'source_tree_identity' => 'tree',
        'source_compatibility' => ['minimum_source_version' => '0.13.0', 'maximum_source_version' => null],
        'runtime_compatibility' => ['minimum_php_version' => '8.2.0', 'minimum_database_versions' => ['mysql' => '8.0.0'], 'required_extensions' => ['json']],
        'inventory' => [['path' => 'app/normal.php', 'byte_size' => 5, 'sha256' => $hash('normal'), 'ownership' => PackageOwnership::PACKAGE_OWNED]],
        'migration_declaration' => ['declares_core_migrations' => false, 'declaration_identity' => null],
        'target_requirements' => [],
        'coverage' => 'full',
        'target_inventory' => ['serialization_version' => 1, 'fingerprint' => $normalTarget->fingerprint(), 'entries' => [$normalEntry->toArray()]],
        'source_state_requirements' => ['allowed_inventory_fingerprints' => []],
        'removals' => [],
        'renames' => [],
    ];
    $writeAndRead = static function (array $data) use ($stage): void {
        $raw = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $manifestPath = $stage->payloadPath() . DIRECTORY_SEPARATOR . '.copot' . DIRECTORY_SEPARATOR . 'package.json';
        if (!is_dir(dirname($manifestPath))) mkdir(dirname($manifestPath), 0700, true);
        file_put_contents($manifestPath, $raw);
        (new PackageManifestReader())->read(new StagedPayload($stage, str_repeat('a', 64), [new StagedFile('.copot/package.json', strlen($raw), hash('sha256', $raw))]));
    };

    $inventoryCase = $baseManifest;
    $inventoryCase['inventory'] = [['path' => 'public/.htaccess', 'byte_size' => 3, 'sha256' => $hash('hta'), 'ownership' => PackageOwnership::PACKAGE_OWNED]];
    $throws(static fn () => $writeAndRead($inventoryCase), 'Manifest inventory allowed .htaccess.');

    $removeCase = $baseManifest;
    $removeCase['removals'] = [['root' => CanonicalTargetInventoryEntry::PUBLIC_ROOT, 'path' => '.htaccess', 'ownership' => PackageOwnership::PACKAGE_OWNED, 'expected_source_byte_size' => 3, 'expected_source_sha256' => $hash('hta'), 'reason' => 'obsolete']];
    $throws(static fn () => $writeAndRead($removeCase), 'Manifest removal allowed .htaccess.');

    foreach ([
        ['source_root' => CanonicalTargetInventoryEntry::PUBLIC_ROOT, 'source_path' => '.htaccess', 'target_root' => CanonicalTargetInventoryEntry::APP_ROOT, 'target_path' => 'app/normal.php'],
        ['source_root' => CanonicalTargetInventoryEntry::APP_ROOT, 'source_path' => 'app/normal.php', 'target_root' => CanonicalTargetInventoryEntry::PUBLIC_ROOT, 'target_path' => '.htaccess'],
    ] as $rename) {
        $renameCase = $baseManifest;
        $renameCase['renames'] = [$rename];
        $throws(static fn () => $writeAndRead($renameCase), 'Manifest rename allowed .htaccess.');
    }

    $sourceEntry = new CanonicalTargetInventoryEntry(CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'app/old.php', 3, $hash('old'));
    $sourceInventory = new CanonicalTargetInventory([$sourceEntry]);
    $sourceReference = new SourceInventoryReference(str_repeat('b', 64), 1, $sourceInventory->fingerprint(), 'source', 3, 'tree');
    $source = new SourceInventoryArtifact($sourceInventory, $sourceReference);
    $contract = new PackageContract(
        PackageContract::WEBCORE_PACKAGE_TYPE,
        3,
        '0.13.0',
        'plan',
        'tree',
        new PackageCompatibility('0.13.0'),
        new PackageRuntimeCompatibility('8.2.0', ['mysql' => '8.0.0'], ['json']),
        [new PackageInventoryEntry('public/.htaccess', 3, $hash('hta'), PackageOwnership::PACKAGE_OWNED)],
        new PackageMigrationDeclaration(),
        null,
        new CanonicalTargetInventory([new CanonicalTargetInventoryEntry(CanonicalTargetInventoryEntry::PUBLIC_ROOT, CanonicalTargetInventoryEntry::PACKAGE_OWNED, '.htaccess', 3, $hash('hta'))]),
        'full',
        [$sourceReference->fingerprint()],
        [],
        []
    );
    $throws(static fn () => (new PackageV3SourceRelativePlanner())->plan($contract, $source), 'Planner allowed .htaccess target.');

    $protectedPayloadPath = $stage->payloadPath() . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_dir(dirname($protectedPayloadPath))) {
        mkdir(dirname($protectedPayloadPath), 0700, true);
    }
    file_put_contents($protectedPayloadPath, 'hta');
    $protectedPayload = new StagedPayload($stage, str_repeat('c', 64), [
        new StagedFile('public/.htaccess', 3, $hash('hta')),
    ]);
    $applier = new PackageOwnedFileApplier(
        new LiveTreePathGuard($liveRoot),
        LiveFileActivationCapability::current(),
        $root . DIRECTORY_SEPARATOR . 'apply'
    );
    $throws(static fn () => $applier->apply(WebcoreApplyPlan::fromPayload($protectedPayload)), 'Filesystem applier allowed .htaccess mutation.');
    $assert(!is_file($liveRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . '.htaccess'), 'Protected live .htaccess was created.');

    echo "Slice 1A .htaccess assertions: {$assertions}\n";
} finally {
    $stage->cleanup();
    $remove($root);
}
