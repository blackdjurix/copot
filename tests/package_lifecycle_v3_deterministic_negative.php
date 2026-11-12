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
$hash = static fn (string $bytes): string => hash('sha256', $bytes);

$cases = [
    'add' => [
        'source' => [['path' => 'app/base.php', 'content' => 'base']],
        'target' => [['path' => 'app/base.php', 'content' => 'base'], ['path' => 'app/a-first.php', 'content' => 'new-a'], ['path' => 'app/z-second.php', 'content' => 'new-z']],
        'delivered' => [['path' => 'app/a-first.php', 'content' => 'new-a'], ['path' => 'app/z-second.php', 'content' => 'new-z']],
        'removals' => [], 'renames' => [],
    ],
    'replace' => [
        'source' => [['path' => 'app/a-first.php', 'content' => 'old-a'], ['path' => 'app/z-second.php', 'content' => 'old-z']],
        'target' => [['path' => 'app/a-first.php', 'content' => 'new-a'], ['path' => 'app/z-second.php', 'content' => 'new-z']],
        'delivered' => [['path' => 'app/a-first.php', 'content' => 'new-a'], ['path' => 'app/z-second.php', 'content' => 'new-z']],
        'removals' => [], 'renames' => [],
    ],
    'remove' => [
        'source' => [['path' => 'app/old-a.php', 'content' => 'old-a'], ['path' => 'app/old-z.php', 'content' => 'old-z'], ['path' => 'app/keep.php', 'content' => 'keep-old'], ['path' => 'app/also.php', 'content' => 'also-old']],
        'target' => [['path' => 'app/keep.php', 'content' => 'keep-new'], ['path' => 'app/also.php', 'content' => 'also-new']],
        'delivered' => [['path' => 'app/also.php', 'content' => 'also-new'], ['path' => 'app/keep.php', 'content' => 'keep-new']],
        'removals' => [
            ['root' => 'APP_ROOT', 'path' => 'app/old-a.php', 'ownership' => 'package-owned', 'expected_source_byte_size' => 5, 'expected_source_sha256' => 'old-a', 'reason' => 'obsolete'],
            ['root' => 'APP_ROOT', 'path' => 'app/old-z.php', 'ownership' => 'package-owned', 'expected_source_byte_size' => 5, 'expected_source_sha256' => 'old-z', 'reason' => 'obsolete'],
        ], 'renames' => [],
    ],
    'rename' => [
        'source' => [['path' => 'app/old-a.php', 'content' => 'old-a'], ['path' => 'app/old-z.php', 'content' => 'old-z']],
        'target' => [['path' => 'app/new-a.php', 'content' => 'new-a'], ['path' => 'app/new-z.php', 'content' => 'new-z']],
        'delivered' => [['path' => 'app/new-a.php', 'content' => 'new-a'], ['path' => 'app/new-z.php', 'content' => 'new-z']],
        'removals' => [
            ['root' => 'APP_ROOT', 'path' => 'app/old-a.php', 'ownership' => 'package-owned', 'expected_source_byte_size' => 5, 'expected_source_sha256' => 'old-a', 'reason' => 'renamed'],
            ['root' => 'APP_ROOT', 'path' => 'app/old-z.php', 'ownership' => 'package-owned', 'expected_source_byte_size' => 5, 'expected_source_sha256' => 'old-z', 'reason' => 'renamed'],
        ],
        'renames' => [
            ['source_root' => 'APP_ROOT', 'source_path' => 'app/old-a.php', 'target_root' => 'APP_ROOT', 'target_path' => 'app/new-a.php'],
            ['source_root' => 'APP_ROOT', 'source_path' => 'app/old-z.php', 'target_root' => 'APP_ROOT', 'target_path' => 'app/new-z.php'],
        ],
    ],
];

$makeFixture = static function (string $kind, array $case) use ($base, $hash, $copyTree): array {
    $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-v3-deterministic-' . $kind . '-' . bin2hex(random_bytes(4));
    $recoveryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-v3-deterministic-recovery-' . $kind . '-' . bin2hex(random_bytes(4));
    $stagingRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-v3-deterministic-staging-' . $kind . '-' . bin2hex(random_bytes(4));
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
    putenv('COPOT_RECOVERY_ROOT=' . $recoveryRoot);
    putenv('COPOT_MARIADB_ADMIN_USERNAME=');
    putenv('COPOT_MARIADB_ADMIN_PASSWORD=');
    $_ENV['COPOT_RECOVERY_ROOT'] = $recoveryRoot;
    $_ENV['COPOT_MARIADB_ADMIN_USERNAME'] = '';
    $_ENV['COPOT_MARIADB_ADMIN_PASSWORD'] = '';

    $storage = $root . DIRECTORY_SEPARATOR . 'storage';
    $sourceInventory = new CanonicalTargetInventory(array_map(static fn (array $entry): CanonicalTargetInventoryEntry => new CanonicalTargetInventoryEntry('APP_ROOT', 'package-owned', $entry['path'], strlen($entry['content']), hash('sha256', $entry['content'])), $case['source']));
    $sourceStore = new SourceInventoryArtifactStore($storage);
    $sourceArtifact = $sourceStore->publish($sourceInventory, 'fixture-source', 3, 'fixture-tree');
    $committedAt = new DateTimeImmutable('now');
    (new CommittedLifecycleStateStore($storage))->write(new CommittedLifecycleState('0.13.0', 'fixture-source', 'fixture-tree', 3, hash('sha256', 'schema'), hash('sha256', 'migration'), $committedAt, hash('sha256', 'package'), $sourceArtifact->reference()));
    (new InstallationState($storage))->replaceMarker('0.13.0', $committedAt->format(DATE_ATOM));

    $targetInventory = new CanonicalTargetInventory(array_map(static fn (array $entry): CanonicalTargetInventoryEntry => new CanonicalTargetInventoryEntry('APP_ROOT', 'package-owned', $entry['path'], strlen($entry['content']), hash('sha256', $entry['content'])), $case['target']));
    $manifest = [
        'package_type' => PackageContract::WEBCORE_PACKAGE_TYPE, 'manifest_contract_version' => 3,
        'target_webcore_version' => '0.13.0', 'release_identity' => 'fixture-' . $kind,
        'source_tree_identity' => 'fixture-tree',
        'source_compatibility' => ['minimum_source_version' => '0.13.0', 'maximum_source_version' => null],
        'runtime_compatibility' => ['minimum_php_version' => '8.2.0', 'minimum_database_versions' => [], 'required_extensions' => ['json']],
        'inventory' => array_map(static fn (array $entry): array => ['path' => $entry['path'], 'byte_size' => strlen($entry['content']), 'sha256' => hash('sha256', $entry['content']), 'ownership' => PackageOwnership::PACKAGE_OWNED], $case['delivered']),
        'migration_declaration' => ['declares_core_migrations' => false, 'declaration_identity' => null], 'target_requirements' => [], 'coverage' => 'partial',
        'target_inventory' => ['serialization_version' => 1, 'fingerprint' => $targetInventory->fingerprint(), 'entries' => array_map(static fn (CanonicalTargetInventoryEntry $entry): array => $entry->toArray(), $targetInventory->entries())],
        'source_state_requirements' => ['allowed_inventory_fingerprints' => [$sourceInventory->fingerprint()]],
        'removals' => array_map(static function (array $entry): array { $entry['expected_source_sha256'] = hash('sha256', $entry['expected_source_sha256']); return $entry; }, $case['removals']),
        'renames' => $case['renames'],
    ];
    $zip = $root . DIRECTORY_SEPARATOR . 'fixture.zip';
    $archive = new ZipArchive();
    if ($archive->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Fixture ZIP could not be created.');
    $archive->addFromString('.copot/package.json', json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    foreach ($case['delivered'] as $entry) $archive->addFromString($entry['path'], $entry['content']);
    $archive->close();

    $intake = new ZipIntakeService($root, $stagingRoot);
    $payload = $intake->intake($zip);
    $package = (new PackageManifestReader())->read($payload);
    $plan = (new PackageV3SourceRelativePlanner())->plan($package, $sourceArtifact);
    $payload->cleanup();
    $confirmation = $plan->removalActions() === [] ? null : new DestructivePlanConfirmation($plan->operationIdentity(), $plan->identity(), $plan->removalSetDigest(), new DestructiveActorAuthorizationEvidence('deterministic-actor', 'system.webcore.manage'));
    $trace = $root . DIRECTORY_SEPARATOR . 'trace.jsonl';
    return compact('root', 'recoveryRoot', 'stagingRoot', 'storage', 'zip', 'sourceInventory', 'sourceArtifact', 'targetInventory', 'plan', 'confirmation', 'trace');
};

$assertOutcome = static function (array $fixture, array $case, string $label) use ($assert): void {
    $root = $fixture['root'];
    foreach ($case['source'] as $entry) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry['path']);
        $assert(is_file($path) && file_get_contents($path) === $entry['content'], $label . ' did not restore original bytes for ' . $entry['path']);
    }
    foreach ($case['target'] as $entry) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry['path']);
        $original = false;
        foreach ($case['source'] as $source) if ($source['path'] === $entry['path']) $original = true;
        if (!$original) $assert(!file_exists($path), $label . ' left operation-created path ' . $entry['path']);
    }
    $assert(file_get_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . '.htaccess') === 'protected', $label . ' changed .htaccess.');
    $recoveryRoot = (new \Copot\Core\BackupRecovery\RecoveryRootResolver($root, $fixture['recoveryRoot'], [$fixture['storage'], $root . DIRECTORY_SEPARATOR . 'storage'], [$root . DIRECTORY_SEPARATOR . 'public']))->resolve();
    $recoveryRootPath = $recoveryRoot->path();
    $statePaths = glob($recoveryRootPath . DIRECTORY_SEPARATOR . 'recovery-sets' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'state' . DIRECTORY_SEPARATOR . 'recovery-lifecycle.json') ?: [];
    $recoveryRecord = null;
    foreach ($statePaths as $statePath) {
        $state = json_decode((string) @file_get_contents($statePath), true);
        if (is_array($state) && ($state['operation_identity'] ?? null) === $fixture['plan']->operationIdentity()) {
            $recoveryRecord = (new \Copot\Core\BackupRecovery\RecoveryLifecycleStore($recoveryRoot))->read(new \Copot\Core\BackupRecovery\RecoveryIdentity((string) $state['recovery_identity']));
            break;
        }
    }
    $assert((new LifecycleOperationStore($fixture['storage']))->read() === null, $label . ' left an active operation after verified rollback settlement.');
    $assert($recoveryRecord instanceof \Copot\Core\BackupRecovery\RecoveryLifecycleRecord, $label . ' lost recovery evidence after operation settlement.');
    $assert($recoveryRecord->state() === \Copot\Core\BackupRecovery\RecoveryLifecycleState::RESTORED, $label . ' recovery did not reach RESTORED.');
    $guard = new \Copot\Core\BackupRecovery\InstallationScopedMaintenanceGuard(new \Copot\Core\InstallationMutex($fixture['storage']), $root);
    $lease = $guard->admit('http');
    $assert($lease !== null, $label . ' left Guard admission blocked.');
    $lease?->release();
    $assert(is_file($fixture['trace']) && str_contains((string) file_get_contents($fixture['trace']), 'terminal'), $label . ' has no terminal LifecycleTrace.');
};
$assertRetryTrace = static function (array $fixture, string $cycleId, string $label, bool $attemptRequired) use ($assert): void {
    $events = [];
    foreach (preg_split('/\R/', (string) @file_get_contents($fixture['trace'])) ?: [] as $line) {
        $event = json_decode($line, true);
        if (is_array($event)) $events[] = $event;
    }
    $runs = array_values(array_unique(array_map(static fn (array $event): string => (string) ($event['run_id'] ?? ''), $events)));
    $runs = array_values(array_filter($runs, static fn (string $run): bool => $run !== ''));
    $assert(count($runs) >= 2, $label . ' did not create a distinct retry trace run.');
    $terminals = array_values(array_filter($events, static fn (array $event): bool => ($event['cycle_id'] ?? null) === $cycleId && ($event['stage'] ?? null) === 'terminal'));
    $assert(count($terminals) === 1 && ($terminals[0]['operation_identity'] ?? null) === $fixture['plan']->operationIdentity(), $label . ' terminal trace was not correlated to the operation.');
    $starts = array_values(array_filter($events, static fn (array $event): bool => ($event['cycle_id'] ?? null) === $cycleId && ($event['stage'] ?? null) === 'retry' && ($event['status'] ?? null) === 'START'));
    $assert(count($starts) === 1 && isset($starts[0]['recovery_identity']), $label . ' retry trace lost the persisted recovery identity.');
    if ($attemptRequired) {
        $attempts = array_values(array_filter($events, static fn (array $event): bool => ($event['cycle_id'] ?? null) === $cycleId && isset($event['recovery_attempt_identity'])));
        $assert(count($attempts) >= 1 && str_starts_with((string) $attempts[0]['recovery_attempt_identity'], 'retry-'), $label . ' retry trace lost recovery-attempt correlation.');
        $ready = array_values(array_filter($events, static fn (array $event): bool => ($event['cycle_id'] ?? null) === $cycleId && ($event['stage'] ?? null) === 'recovery' && ($event['status'] ?? null) === 'READY' && isset($event['recovery_attempt_identity'], $event['recovery_identity'])));
        $assert(count($ready) >= 1 && $ready[0]['recovery_identity'] !== $starts[0]['recovery_identity'] && $ready[0]['recovery_attempt_identity'] === $attempts[0]['recovery_attempt_identity'], $label . ' retry trace did not bind the new recovery identity to the attempt.');
    }
};

$workerCode = <<<'PHP'
<?php
declare(strict_types=1);
$config = json_decode((string) file_get_contents($argv[1]), true, 16, JSON_THROW_ON_ERROR);
putenv('COPOT_RECOVERY_ROOT=' . $config['recoveryRoot']);
putenv('COPOT_MARIADB_ADMIN_USERNAME=');
putenv('COPOT_MARIADB_ADMIN_PASSWORD=');
$_ENV['COPOT_RECOVERY_ROOT'] = $config['recoveryRoot'];
$_ENV['COPOT_MARIADB_ADMIN_USERNAME'] = '';
$_ENV['COPOT_MARIADB_ADMIN_PASSWORD'] = '';
require $config['base'] . '/bootstrap/autoload.php';
$service = Copot\Core\PackageLifecycleFactory::forProject($config['root'], new Copot\Core\LifecycleTrace($config['trace'], 'deterministic-worker'));
$property = new ReflectionProperty($service, 'intake');
$property->setValue($service, new Copot\Core\ZipIntakeService($config['root'], $config['stagingRoot']));
$property = new ReflectionProperty($service, 'v3BeforeActivation');
$property->setValue($service, static function (string $path) use ($config): void {
    if ($path === $config['faultPath']) {
        file_put_contents($config['faultFlag'], 'reached');
        exit(137);
    }
});
$confirmation = $config['confirmation'] === null ? null : Copot\Core\DestructivePlanConfirmation::fromArray($config['confirmation']);
$service->apply($config['zip'], false, $confirmation, 'deterministic-actor');
PHP;

foreach ($cases as $kind => $case) {
    foreach (['partial', 'finalization'] as $fault) {
        $fixture = $makeFixture($kind, $case);
        try {
            $service = PackageLifecycleFactory::forProject($fixture['root'], new LifecycleTrace($fixture['trace'], 'deterministic-' . $kind . '-' . $fault));
            $intakeProperty = new ReflectionProperty($service, 'intake');
            $intakeProperty->setValue($service, new ZipIntakeService($fixture['root'], $fixture['stagingRoot']));
            if ($fault === 'partial') {
                $faultPath = $case['delivered'][1]['path'];
                $property = new ReflectionProperty($service, 'v3BeforeActivation');
                $property->setValue($service, static function (string $path) use ($faultPath): void { if ($path === $faultPath) throw new RuntimeException('deterministic partial mutation fault'); });
            } else {
                $healthProperty = new ReflectionProperty($service, 'healthCoordinator');
                $health = $healthProperty->getValue($service);
                $hook = new ReflectionProperty($health, 'filesystemOnlyFinalizationHook');
                $hook->setValue($health, static function (): never { throw new RuntimeException('deterministic finalization fault'); });
            }
            $result = $service->apply($fixture['zip'], false, $fixture['confirmation'], 'deterministic-actor');
            $assert(!$result->accepted(), strtoupper($kind) . ' ' . $fault . ' unexpectedly succeeded.');
            $expectedStatus = $fault === 'finalization' ? 'failed' : 'indeterminate';
            $assert($result->status() === $expectedStatus, strtoupper($kind) . ' ' . $fault . ' changed its public failure status: ' . $result->status() . ' (' . $result->reason() . ')');
            $assertOutcome($fixture, $case, strtoupper($kind) . ' ' . $fault);
        } finally {
            $remove($fixture['root']); $remove($fixture['recoveryRoot']); $remove($fixture['stagingRoot']);
        }
    }

    $fixture = $makeFixture($kind, $case);
    try {
        $faultFlag = $fixture['root'] . DIRECTORY_SEPARATOR . 'fault.flag';
        $worker = $fixture['root'] . DIRECTORY_SEPARATOR . 'worker.php';
        $configPath = $fixture['root'] . DIRECTORY_SEPARATOR . 'worker.json';
        file_put_contents($worker, $workerCode);
        file_put_contents($configPath, json_encode([
            'base' => $base, 'root' => $fixture['root'], 'recoveryRoot' => $fixture['recoveryRoot'], 'stagingRoot' => $fixture['stagingRoot'],
            'zip' => $fixture['zip'], 'trace' => $fixture['trace'], 'faultPath' => $case['delivered'][1]['path'], 'faultFlag' => $faultFlag,
            'confirmation' => $fixture['confirmation']?->toArray(),
        ], JSON_THROW_ON_ERROR));
        $pipes = [];
        $process = proc_open([PHP_BINARY, $worker, $configPath], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $fixture['root']);
        if (!is_resource($process)) throw new RuntimeException('Deterministic worker could not start.');
        $deadline = microtime(true) + 15;
        do {
            $status = proc_get_status($process);
            if (!$status['running']) break;
            usleep(10000);
        } while (microtime(true) < $deadline);
        if (($status['running'] ?? false) === true) { proc_terminate($process); throw new RuntimeException('Deterministic worker exceeded timeout.'); }
        $exitCode = proc_close($process);
        $assert((string) @file_get_contents($faultFlag) === 'reached', strtoupper($kind) . ' interruption fault checkpoint was not reached.');
        $assert($exitCode === 137, strtoupper($kind) . ' worker did not terminate at the controlled checkpoint.');
        $operation = (new LifecycleOperationStore($fixture['storage']))->read();
        $assert($operation instanceof \Copot\Core\LifecycleOperationRecord && $operation->operationId() === $fixture['plan']->operationIdentity() && $operation->recoveryState() === \Copot\Core\BackupRecovery\RecoveryLifecycleState::READY, strtoupper($kind) . ' interruption did not persist retryable recovery identity; phase=' . ($operation?->phase() ?? 'none') . '; cursor=' . ($operation?->fileCursor() ?? -1));
        $oldRecovery = $operation->recoveryIdentity();
        $retryService = PackageLifecycleFactory::forProject($fixture['root'], new LifecycleTrace($fixture['trace'], 'deterministic-retry-' . $kind));
        $retry = $retryService->retry($operation->operationId());
        $assert($retry->accepted() && $retry->status() === 'completed', strtoupper($kind) . ' retry did not complete: ' . $retry->reason() . '; persisted_phase=' . $operation->phase() . '; persisted_recovery=' . ($operation->recoveryState() ?? 'null') . '; persisted_cursor=' . $operation->fileCursor());
        foreach ($case['target'] as $entry) {
            $path = $fixture['root'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry['path']);
            $assert(is_file($path) && file_get_contents($path) === $entry['content'], strtoupper($kind) . ' retry target bytes are wrong.');
        }
        $assert((new LifecycleOperationStore($fixture['storage']))->read() === null, strtoupper($kind) . ' retry left an active operation.');
        $guard = new \Copot\Core\BackupRecovery\InstallationScopedMaintenanceGuard(new \Copot\Core\InstallationMutex($fixture['storage']), $fixture['root']);
        $lease = $guard->admit('http'); $assert($lease !== null, strtoupper($kind) . ' retry left Guard admission blocked.'); $lease?->release();
        $recoveryDirs = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture['recoveryRoot'], FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) if ($file->isFile() && $file->getFilename() === 'recovery-lifecycle.json') { $data = json_decode((string) file_get_contents($file->getPathname()), true); if (is_array($data) && isset($data['recovery_identity'])) $recoveryDirs[] = $data['recovery_identity']; }
        $assert(in_array($oldRecovery, $recoveryDirs, true) && count(array_unique($recoveryDirs)) >= 2, strtoupper($kind) . ' retry did not publish a distinct recovery attempt identity.');
        $assertRetryTrace($fixture, 'deterministic-retry-' . $kind, strtoupper($kind) . ' retry trace', true);
    } finally {
        if (isset($process) && is_resource($process)) @proc_terminate($process);
        $remove($fixture['root']); $remove($fixture['recoveryRoot']); $remove($fixture['stagingRoot']);
    }

    $fixture = $makeFixture($kind, $case);
    try {
        $faultFlag = $fixture['root'] . DIRECTORY_SEPARATOR . 'fault.flag';
        $worker = $fixture['root'] . DIRECTORY_SEPARATOR . 'worker.php';
        $configPath = $fixture['root'] . DIRECTORY_SEPARATOR . 'worker.json';
        file_put_contents($worker, $workerCode);
        file_put_contents($configPath, json_encode(['base' => $base, 'root' => $fixture['root'], 'recoveryRoot' => $fixture['recoveryRoot'], 'stagingRoot' => $fixture['stagingRoot'], 'zip' => $fixture['zip'], 'trace' => $fixture['trace'], 'faultPath' => $case['delivered'][1]['path'], 'faultFlag' => $faultFlag, 'confirmation' => $fixture['confirmation']?->toArray()], JSON_THROW_ON_ERROR));
        $pipes = [];
        $process = proc_open([PHP_BINARY, $worker, $configPath], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $fixture['root']);
        if (!is_resource($process)) throw new RuntimeException('Corruption worker could not start.');
        $deadline = microtime(true) + 15;
        do { $status = proc_get_status($process); if (!$status['running']) break; usleep(10000); } while (microtime(true) < $deadline);
        if (($status['running'] ?? false) === true) { proc_terminate($process); throw new RuntimeException('Corruption worker exceeded timeout.'); }
        proc_close($process);
        $operation = (new LifecycleOperationStore($fixture['storage']))->read();
        $assert($operation instanceof \Copot\Core\LifecycleOperationRecord && is_string($operation->recoveryIdentity()), strtoupper($kind) . ' corruption setup lost operation.');
        $recoveryStorageRoot = (new \Copot\Core\BackupRecovery\RecoveryRootResolver($fixture['root'], $fixture['recoveryRoot'], [$fixture['storage'], $fixture['root'] . DIRECTORY_SEPARATOR . 'storage'], [$fixture['root'] . DIRECTORY_SEPARATOR . 'public']))->resolve();
        $artifactStore = new \Copot\Core\BackupRecovery\RecoveryArtifactStore($recoveryStorageRoot);
        $decoded = $artifactStore->readManifest(new \Copot\Core\BackupRecovery\RecoveryIdentity($operation->recoveryIdentity()));
        $linkage = null;
        foreach ($decoded['artifacts'] as $artifact) if ($artifact->domainIdentifier() === 'filesystem.action-linkage') $linkage = $artifact;
        if (!$linkage instanceof \Copot\Core\BackupRecovery\RecoveryArtifactRecord) throw new RuntimeException('Action-linkage artifact was not published.');
        $policy = new \Copot\Core\BackupRecovery\RecoveryStoragePathPolicy($recoveryStorageRoot);
        file_put_contents($policy->artifactPath(new \Copot\Core\BackupRecovery\RecoveryIdentity($operation->recoveryIdentity()), $linkage), 'corrupted');
        $retryService = PackageLifecycleFactory::forProject($fixture['root'], new LifecycleTrace($fixture['trace'], 'deterministic-corruption-retry-' . $kind));
        $retry = $retryService->retry($operation->operationId());
        $assert(!$retry->accepted() && in_array($retry->status(), ['rejected', 'blocked'], true), strtoupper($kind) . ' corrupted recovery evidence was accepted.');
        $assert((new LifecycleOperationStore($fixture['storage']))->read() instanceof \Copot\Core\LifecycleOperationRecord, strtoupper($kind) . ' corrupted retry destructively cleared the operation.');
        $assertRetryTrace($fixture, 'deterministic-corruption-retry-' . $kind, strtoupper($kind) . ' corrupted retry trace', false);
    } finally {
        if (isset($process) && is_resource($process)) @proc_terminate($process);
        $remove($fixture['root']); $remove($fixture['recoveryRoot']); $remove($fixture['stagingRoot']);
    }
}

echo "Deterministic V3 negative-path assertions: {$assertions}\n";

