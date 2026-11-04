<?php

declare(strict_types=1);

use Copot\Core\{
    CanonicalTargetInventory,
    CanonicalTargetInventoryEntry,
    CommittedLifecycleState,
    CommittedLifecycleStateStore,
    DestructiveActorAuthorizationEvidence,
    DestructivePlanConfirmation,
    InstallationState,
    LifecycleOperationStore,
    LifecycleTrace,
    PackageContract,
    PackageLifecycleFactory,
    PackageManifestReader,
    PackageOwnership,
    PackageV3SourceRelativePlanner,
    SourceInventoryArtifactStore,
    ZipIntakeService
};

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    if (!$condition) throw new RuntimeException($message);
    $assertions++;
};

$hash = static fn (string $value): string => hash('sha256', $value);
$remove = static function (string $path) use (&$remove): void {
    if (is_file($path) || is_link($path)) { @unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry);
    @rmdir($path);
};
$copyTree = static function (string $source, string $target) use (&$copyTree): void {
    if (!is_dir($target) && !mkdir($target, 0700, true) && !is_dir($target)) throw new RuntimeException('Fixture directory could not be created.');
    foreach (scandir($source) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $from = $source . DIRECTORY_SEPARATOR . $entry;
        $to = $target . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($from) && !is_link($from)) $copyTree($from, $to);
        elseif (is_file($from) && !copy($from, $to)) throw new RuntimeException('Fixture file could not be copied.');
    }
};

$makePackage = static function (string $root, string $kind, array $sourceEntries, array $targetEntries, array $delivered, array $removals, array $renames, string $sourceFingerprint) use ($hash): string {
    $manifest = [
        'package_type' => PackageContract::WEBCORE_PACKAGE_TYPE,
        'manifest_contract_version' => 3,
        'target_webcore_version' => '0.13.0',
        'release_identity' => 'fixture-' . $kind,
        'source_tree_identity' => 'fixture-tree',
        'source_compatibility' => ['minimum_source_version' => '0.13.0', 'maximum_source_version' => null],
        'runtime_compatibility' => ['minimum_php_version' => '8.2.0', 'minimum_database_versions' => [], 'required_extensions' => ['json']],
        'inventory' => array_map(static fn (array $entry): array => ['path' => $entry['path'], 'byte_size' => strlen($entry['content']), 'sha256' => $hash($entry['content']), 'ownership' => PackageOwnership::PACKAGE_OWNED], $delivered),
        'migration_declaration' => ['declares_core_migrations' => false, 'declaration_identity' => null],
        'target_requirements' => [],
        'coverage' => 'partial',
        'target_inventory' => ['serialization_version' => 1, 'fingerprint' => (new CanonicalTargetInventory(array_map(static fn (array $entry): CanonicalTargetInventoryEntry => new CanonicalTargetInventoryEntry(CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PACKAGE_OWNED, $entry['path'], strlen($entry['content']), $hash($entry['content'])), $targetEntries)))->fingerprint(), 'entries' => array_map(static fn (array $entry): array => (new CanonicalTargetInventoryEntry(CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PACKAGE_OWNED, $entry['path'], strlen($entry['content']), $hash($entry['content'])))->toArray(), $targetEntries)],
        'source_state_requirements' => ['allowed_inventory_fingerprints' => [$sourceFingerprint]],
        'removals' => $removals,
        'renames' => $renames,
    ];
    $zipPath = $root . DIRECTORY_SEPARATOR . $kind . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Fixture ZIP could not be created.');
    $zip->addFromString('.copot/package.json', json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    foreach ($delivered as $entry) $zip->addFromString($entry['path'], $entry['content']);
    $zip->close();
    return $zipPath;
};

$cases = [
    'add' => [
        'source' => [['path' => 'app/current.php', 'content' => 'current']],
        'target' => [['path' => 'app/current.php', 'content' => 'current'], ['path' => 'app/new.php', 'content' => 'new']],
        'delivered' => [['path' => 'app/new.php', 'content' => 'new']], 'removals' => [], 'renames' => [],
    ],
    'replace' => [
        'source' => [['path' => 'app/current.php', 'content' => 'old']],
        'target' => [['path' => 'app/current.php', 'content' => 'new']],
        'delivered' => [['path' => 'app/current.php', 'content' => 'new']], 'removals' => [], 'renames' => [],
    ],
    'remove' => [
        'source' => [['path' => 'app/old.php', 'content' => 'old'], ['path' => 'app/keep.php', 'content' => 'keep']],
        'target' => [['path' => 'app/keep.php', 'content' => 'keep']],
        'delivered' => [['path' => 'app/keep.php', 'content' => 'keep']],
        'removals' => [['root' => CanonicalTargetInventoryEntry::APP_ROOT, 'path' => 'app/old.php', 'ownership' => CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'expected_source_byte_size' => 3, 'expected_source_sha256' => hash('sha256', 'old'), 'reason' => 'obsolete']],
        'renames' => [],
    ],
    'rename' => [
        'source' => [['path' => 'app/old.php', 'content' => 'old']],
        'target' => [['path' => 'app/new.php', 'content' => 'old']],
        'delivered' => [['path' => 'app/new.php', 'content' => 'old']],
        'removals' => [['root' => CanonicalTargetInventoryEntry::APP_ROOT, 'path' => 'app/old.php', 'ownership' => CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'expected_source_byte_size' => 3, 'expected_source_sha256' => hash('sha256', 'old'), 'reason' => 'renamed']],
        'renames' => [['source_root' => CanonicalTargetInventoryEntry::APP_ROOT, 'source_path' => 'app/old.php', 'target_root' => CanonicalTargetInventoryEntry::APP_ROOT, 'target_path' => 'app/new.php']],
    ],
];

$passedCases = 0;
foreach ($cases as $kind => $case) {
    $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-v3-fs-integration-' . $kind . '-' . bin2hex(random_bytes(4));
    $recoveryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-v3-fs-recovery-' . $kind . '-' . bin2hex(random_bytes(4));
    $stagingRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-v3-fs-staging-' . $kind . '-' . bin2hex(random_bytes(4));
    mkdir($root . DIRECTORY_SEPARATOR . 'public', 0700, true);
    mkdir($root . DIRECTORY_SEPARATOR . 'storage', 0700, true);
    file_put_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . '.htaccess', 'protected');
    foreach ($case['source'] as $entry) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry['path']);
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
        file_put_contents($path, $entry['content']);
    }
    $copyTree($base . DIRECTORY_SEPARATOR . 'config', $root . DIRECTORY_SEPARATOR . 'config');
    $copyTree($base . DIRECTORY_SEPARATOR . 'database', $root . DIRECTORY_SEPARATOR . 'database');
    mkdir($recoveryRoot, 0700, true);
    $_ENV['COPOT_RECOVERY_ROOT'] = $recoveryRoot;
    $_ENV['COPOT_MARIADB_ADMIN_USERNAME'] = '';
    $_ENV['COPOT_MARIADB_ADMIN_PASSWORD'] = '';
    putenv('COPOT_RECOVERY_ROOT=' . $recoveryRoot);
    putenv('COPOT_MARIADB_ADMIN_USERNAME=');
    putenv('COPOT_MARIADB_ADMIN_PASSWORD=');

    try {
        $storage = $root . DIRECTORY_SEPARATOR . 'storage';
        $sourceInventory = new CanonicalTargetInventory(array_map(static fn (array $entry): CanonicalTargetInventoryEntry => new CanonicalTargetInventoryEntry(CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PACKAGE_OWNED, $entry['path'], strlen($entry['content']), hash('sha256', $entry['content'])), $case['source']));
        $sourceStore = new SourceInventoryArtifactStore($storage);
        $sourceArtifact = $sourceStore->publish($sourceInventory, 'fixture-source', 3, 'fixture-tree');
        $committedAt = new DateTimeImmutable('now');
        (new CommittedLifecycleStateStore($storage))->write(new CommittedLifecycleState('0.13.0', 'fixture-source', 'fixture-tree', 3, hash('sha256', 'schema'), hash('sha256', 'migration'), $committedAt, hash('sha256', 'package'), $sourceArtifact->reference()));
        (new InstallationState($storage))->replaceMarker('0.13.0', $committedAt->format(DATE_ATOM));

        $zip = $makePackage($root, $kind, $case['source'], $case['target'], $case['delivered'], $case['removals'], $case['renames'], $sourceInventory->fingerprint());
        $payload = (new ZipIntakeService($root, $stagingRoot, null, null, null, 'fixture-intake-' . $kind))->intake($zip);
        $manifest = (new PackageManifestReader())->read($payload);
        $plan = (new PackageV3SourceRelativePlanner())->plan($manifest, $sourceArtifact);
        $payload->cleanup();
        $confirmation = $plan->removalActions() === [] ? null : new DestructivePlanConfirmation($plan->operationIdentity(), $plan->identity(), $plan->removalSetDigest(), new DestructiveActorAuthorizationEvidence('fixture-actor', 'fixture-capability'));

        $tracePath = $root . DIRECTORY_SEPARATOR . 'trace.jsonl';
        $service = PackageLifecycleFactory::forProject($root, new LifecycleTrace($tracePath, 'c0-integration'));
        $result = $service->apply($zip, false, $confirmation);
        $assert($result->accepted() === true && $result->status() === 'completed', strtoupper($kind) . ' actual PackageLifecycleService apply failed: ' . $result->status() . ' / ' . $result->reason());
        $assert(file_get_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . '.htaccess') === 'protected', strtoupper($kind) . ' changed protected .htaccess.');
        foreach ($case['target'] as $entry) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry['path']);
            $assert(is_file($path) && file_get_contents($path) === $entry['content'], strtoupper($kind) . ' target bytes are wrong for ' . $entry['path']);
        }
        foreach ($case['source'] as $entry) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry['path']);
            if ($kind === 'remove' && $entry['path'] === 'app/old.php' || $kind === 'rename' && $entry['path'] === 'app/old.php') $assert(!file_exists($path), strtoupper($kind) . ' source removal was not applied.');
        }
        $state = (new CommittedLifecycleStateStore($storage))->read();
        $targetInventory = new CanonicalTargetInventory(array_map(static fn (array $entry): CanonicalTargetInventoryEntry => new CanonicalTargetInventoryEntry(CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PACKAGE_OWNED, $entry['path'], strlen($entry['content']), hash('sha256', $entry['content'])), $case['target']));
        $assert($state instanceof CommittedLifecycleState && $state->sourceInventoryReference()?->fingerprint() === $targetInventory->fingerprint(), strtoupper($kind) . ' committed source inventory was not finalized.');
        $assert(!is_file($storage . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'active-operation.json'), strtoupper($kind) . ' left an active operation record.');
        $assert(is_file($tracePath) && str_contains((string) file_get_contents($tracePath), 'terminal'), strtoupper($kind) . ' did not emit terminal LifecycleTrace.');
        $manifestFiles = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($recoveryRoot, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) if ($file->isFile() && $file->getFilename() === 'recovery-manifest.json') $manifestFiles[] = $file->getPathname();
        $assert($manifestFiles !== [], strtoupper($kind) . ' did not publish a recovery manifest.');
        $rawManifest = (string) file_get_contents($manifestFiles[0]);
        $assert(str_contains($rawManifest, 'filesystem.action-linkage') && str_contains($rawManifest, 'filesystem.package-owned'), strtoupper($kind) . ' recovery manifest is incomplete.');
        $passedCases++;
    } finally {
        $remove($root);
        $remove($recoveryRoot);
        $remove($stagingRoot);
    }
}

echo "V3 filesystem integration cases: {$passedCases}/" . count($cases) . " PASS; assertions={$assertions}\n";
