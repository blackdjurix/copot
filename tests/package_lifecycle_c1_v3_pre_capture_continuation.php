<?php
declare(strict_types=1);

use Copot\Core\{
    CanonicalTargetInventory,
    CanonicalTargetInventoryEntry,
    CommittedLifecycleState,
    CommittedLifecycleStateStore,
    InstallationState,
    LifecycleOperationRecord,
    LifecycleOperationStore,
    LifecycleTrace,
    PackageContract,
    PackageLifecycleFactory,
    PackageManifestReader,
    PackageOwnership,
    SourceInventoryArtifactStore,
    WebcoreApplyPlan,
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

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-c1-v3-continuation-' . bin2hex(random_bytes(4));
$recoveryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-c1-v3-continuation-recovery-' . bin2hex(random_bytes(4));
mkdir($root . DIRECTORY_SEPARATOR . 'public', 0700, true);
mkdir($root . DIRECTORY_SEPARATOR . 'storage', 0700, true);
mkdir($recoveryRoot, 0700, true);
file_put_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . '.htaccess', 'protected');
mkdir($root . DIRECTORY_SEPARATOR . 'app', 0700, true);
file_put_contents($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'current.php', 'current');
$copyTree($base . DIRECTORY_SEPARATOR . 'config', $root . DIRECTORY_SEPARATOR . 'config');
$copyTree($base . DIRECTORY_SEPARATOR . 'database', $root . DIRECTORY_SEPARATOR . 'database');
$_ENV['COPOT_RECOVERY_ROOT'] = $recoveryRoot;
putenv('COPOT_RECOVERY_ROOT=' . $recoveryRoot);
putenv('COPOT_MARIADB_ADMIN_USERNAME=');
putenv('COPOT_MARIADB_ADMIN_PASSWORD=');

try {
    $sourceEntries = [
        ['path' => 'app/current.php', 'content' => 'current'],
    ];
    $targetEntries = [
        ['path' => 'app/current.php', 'content' => 'current'],
        ['path' => 'app/new.php', 'content' => 'new'],
    ];
    $targetInventory = new CanonicalTargetInventory(array_map(static fn (array $entry): CanonicalTargetInventoryEntry => new CanonicalTargetInventoryEntry(CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PACKAGE_OWNED, $entry['path'], strlen($entry['content']), $hash($entry['content'])), $targetEntries));
    $sourceInventory = new CanonicalTargetInventory(array_map(static fn (array $entry): CanonicalTargetInventoryEntry => new CanonicalTargetInventoryEntry(CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PACKAGE_OWNED, $entry['path'], strlen($entry['content']), $hash($entry['content'])), $sourceEntries));

    $storage = $root . DIRECTORY_SEPARATOR . 'storage';
    $sourceArtifact = (new SourceInventoryArtifactStore($storage))->publish($sourceInventory, 'fixture-source', 3, 'fixture-tree');
    $committedAt = new DateTimeImmutable('now');
    (new CommittedLifecycleStateStore($storage))->write(new CommittedLifecycleState('0.13.0', 'fixture-source', 'fixture-tree', 3, $hash('schema'), $hash('migration'), $committedAt, $hash('package'), $sourceArtifact->reference()));
    (new InstallationState($storage))->replaceMarker('0.13.0', $committedAt->format(DATE_ATOM));

    $manifest = [
        'package_type' => PackageContract::WEBCORE_PACKAGE_TYPE,
        'manifest_contract_version' => 3,
        'target_webcore_version' => '0.13.0',
        'release_identity' => 'fixture-c1-pre-capture',
        'source_tree_identity' => 'fixture-tree',
        'source_compatibility' => ['minimum_source_version' => '0.13.0', 'maximum_source_version' => null],
        'runtime_compatibility' => ['minimum_php_version' => '8.2.0', 'minimum_database_versions' => [], 'required_extensions' => ['json']],
        'inventory' => [['path' => 'app/new.php', 'byte_size' => 3, 'sha256' => $hash('new'), 'ownership' => PackageOwnership::PACKAGE_OWNED]],
        'migration_declaration' => ['declares_core_migrations' => false, 'declaration_identity' => null],
        'target_requirements' => [],
        'coverage' => 'partial',
        'target_inventory' => ['serialization_version' => 1, 'fingerprint' => $targetInventory->fingerprint(), 'entries' => array_map(static fn (CanonicalTargetInventoryEntry $entry): array => $entry->toArray(), $targetInventory->entries())],
        'source_state_requirements' => ['allowed_inventory_fingerprints' => [$sourceInventory->fingerprint()]],
        'removals' => [],
        'renames' => [],
    ];
    $zip = $root . DIRECTORY_SEPARATOR . 'P1-fixture.zip';
    $archive = new ZipArchive();
    if ($archive->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Fixture ZIP could not be created.');
    $archive->addFromString('.copot/package.json', json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $archive->addFromString('app/new.php', 'new');
    $archive->close();

    $service = PackageLifecycleFactory::forProject($root, new LifecycleTrace($root . DIRECTORY_SEPARATOR . 'trace.jsonl', 'c1-pre-capture'));
    $prepare = new ReflectionMethod($service, 'prepare');
    $prepare->setAccessible(true);
    [$payload, $packageManifest, $transition, $migration] = $prepare->invoke($service, $zip);
    $applyPlan = WebcoreApplyPlan::fromPayload($payload->withoutPaths([PackageManifestReader::PATH]));
    $payloadIdentity = $hash(implode(':', array_map(static fn ($file): string => $file->path() . ':' . $file->sha256(), $payload->withoutPaths([PackageManifestReader::PATH])->files())));
    $operationId = (new \Copot\Core\PackageV3SourceRelativePlanner())->plan($packageManifest, $sourceArtifact)->operationIdentity();
    $now = gmdate(DATE_ATOM);
    $record = new LifecycleOperationRecord(
        $operationId,
        $transition->classification(),
        $packageManifest->contract()->targetWebcoreVersion(),
        $packageManifest->contract()->releaseIdentity(),
        $payload->archiveSha256(),
        $payload->stagingPath(),
        $payloadIdentity,
        $applyPlan->identity(),
        LifecycleOperationRecord::BLOCKED,
        0,
        null,
        $hash(''),
        null,
        $now,
        $now,
        'Application writers are not quiescent.'
    );
    (new LifecycleOperationStore($storage))->create($record);

    file_put_contents($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'new.php', 'drift');
    $drift = $service->retry($operationId);
    $assert(!$drift->accepted() && $drift->status() === 'rejected', 'Pre-capture target drift was not rejected before recovery capture.');
    $persistedAfterDrift = (new LifecycleOperationStore($storage))->read();
    $assert($persistedAfterDrift instanceof LifecycleOperationRecord && $persistedAfterDrift->toArray() === $record->toArray(), 'Target drift changed the persisted operation evidence.');
    $assert(!is_dir($recoveryRoot . DIRECTORY_SEPARATOR . 'recovery'), 'Target drift unexpectedly created recovery evidence.');
    unlink($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'new.php');

    $result = $service->retry($operationId);
    $assert($result->accepted() && $result->status() === 'completed', 'Functional V3 pre-capture continuation did not complete: ' . $result->status() . ' / ' . $result->reason());
    $assert(file_get_contents($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'new.php') === 'new', 'Continuation did not deliver the ADD payload.');
    $assert(file_get_contents($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'current.php') === 'current', 'Continuation changed an unrelated source file.');
    $assert(file_get_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . '.htaccess') === 'protected', 'Continuation changed protected .htaccess.');
    $state = (new CommittedLifecycleStateStore($storage))->read();
    $assert($state instanceof CommittedLifecycleState && $state->sourceInventoryReference()?->fingerprint() === $targetInventory->fingerprint(), 'Continuation did not commit the resulting target inventory.');
    $assert(!is_file($storage . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'active-operation.json'), 'Continuation left a persisted active operation.');
    $assert(!is_dir($payload->stagingPath()), 'Successful continuation did not dispose the retained staging session.');
    $assert(is_file($root . DIRECTORY_SEPARATOR . 'trace.jsonl') && str_contains((string) file_get_contents($root . DIRECTORY_SEPARATOR . 'trace.jsonl'), 'terminal'), 'Continuation did not emit terminal lifecycle evidence.');
} finally {
    if (isset($payload) && $payload instanceof \Copot\Core\StagedPayload) { try { $payload->cleanup(); } catch (Throwable) {} }
    $remove($root);
    $remove($recoveryRoot);
}

echo "package_lifecycle_c1_v3_pre_capture_continuation: {$assertions} assertions passed" . PHP_EOL;

