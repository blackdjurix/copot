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
    PackageV3SourceRelativePlanner,
    SourceInventoryArtifactStore,
    ZipIntakeService
};
use Copot\Core\BackupRecovery\{
    RecoveryIdentity,
    RecoveryLifecycleRecord,
    RecoveryLifecycleState,
    RecoveryLifecycleStore,
    RecoveryRootResolver
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

$makeFixture = static function () use ($base): array {
    $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-v3-settlement-' . bin2hex(random_bytes(4));
    $recoveryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-v3-settlement-recovery-' . bin2hex(random_bytes(4));
    $stagingRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-v3-settlement-staging-' . bin2hex(random_bytes(4));
    mkdir($root . DIRECTORY_SEPARATOR . 'public', 0700, true);
    mkdir($root . DIRECTORY_SEPARATOR . 'storage', 0700, true);
    mkdir($root . DIRECTORY_SEPARATOR . 'resources', 0700, true);
    file_put_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . '.htaccess', 'protected');
    file_put_contents($root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'base.txt', 'base-v1');
    $copy = static function (string $source, string $target) use (&$copy): void {
        if (!is_dir($target) && !mkdir($target, 0700, true) && !is_dir($target)) throw new RuntimeException('Fixture copy target could not be created.');
        foreach (scandir($source) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $from = $source . DIRECTORY_SEPARATOR . $entry;
            $to = $target . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($from) && !is_link($from)) $copy($from, $to);
            elseif (is_file($from) && !copy($from, $to)) throw new RuntimeException('Fixture copy failed.');
        }
    };
    $copy($base . DIRECTORY_SEPARATOR . 'config', $root . DIRECTORY_SEPARATOR . 'config');
    $copy($base . DIRECTORY_SEPARATOR . 'database', $root . DIRECTORY_SEPARATOR . 'database');
    mkdir($recoveryRoot, 0700, true);
    putenv('COPOT_RECOVERY_ROOT=' . $recoveryRoot);
    putenv('COPOT_MARIADB_ADMIN_USERNAME=');
    putenv('COPOT_MARIADB_ADMIN_PASSWORD=');
    $_ENV['COPOT_RECOVERY_ROOT'] = $recoveryRoot;
    $_ENV['COPOT_MARIADB_ADMIN_USERNAME'] = '';
    $_ENV['COPOT_MARIADB_ADMIN_PASSWORD'] = '';

    $sourceEntries = [['path' => 'resources/base.txt', 'content' => 'base-v1']];
    $targetEntries = [
        ['path' => 'resources/base.txt', 'content' => 'base-v1'],
        ['path' => 'resources/c1-n1/01-first.txt', 'content' => 'n1-first-v1'],
        ['path' => 'resources/c1-n1/02-second.txt', 'content' => 'n1-second-v1'],
    ];
    $sourceInventory = new CanonicalTargetInventory([new CanonicalTargetInventoryEntry('APP_ROOT', 'package-owned', 'resources/base.txt', 7, hash('sha256', 'base-v1'))]);
    $storage = $root . DIRECTORY_SEPARATOR . 'storage';
    $artifact = (new SourceInventoryArtifactStore($storage))->publish($sourceInventory, 'fixture-source', 3, 'fixture-tree');
    $at = new DateTimeImmutable('now');
    (new CommittedLifecycleStateStore($storage))->write(new CommittedLifecycleState('0.13.0', 'fixture-source', 'fixture-tree', 3, hash('sha256', 'schema'), hash('sha256', 'migration'), $at, hash('sha256', 'package'), $artifact->reference()));
    (new InstallationState($storage))->replaceMarker('0.13.0', $at->format(DATE_ATOM));
    $targetInventory = new CanonicalTargetInventory(array_map(static fn (array $entry): CanonicalTargetInventoryEntry => new CanonicalTargetInventoryEntry('APP_ROOT', 'package-owned', $entry['path'], strlen($entry['content']), hash('sha256', $entry['content'])), $targetEntries));
    $manifest = [
        'package_type' => PackageContract::WEBCORE_PACKAGE_TYPE, 'manifest_contract_version' => 3,
        'target_webcore_version' => '0.13.0', 'release_identity' => 'fixture-c1-n1-settlement', 'source_tree_identity' => 'fixture-tree',
        'source_compatibility' => ['minimum_source_version' => '0.13.0', 'maximum_source_version' => null],
        'runtime_compatibility' => ['minimum_php_version' => '8.2.0', 'minimum_database_versions' => [], 'required_extensions' => ['json']],
        'inventory' => array_map(static fn (array $entry): array => ['path' => $entry['path'], 'byte_size' => strlen($entry['content']), 'sha256' => hash('sha256', $entry['content']), 'ownership' => PackageOwnership::PACKAGE_OWNED], array_slice($targetEntries, 1)),
        'migration_declaration' => ['declares_core_migrations' => false, 'declaration_identity' => null], 'target_requirements' => [], 'coverage' => 'partial',
        'target_inventory' => ['serialization_version' => 1, 'fingerprint' => $targetInventory->fingerprint(), 'entries' => array_map(static fn (CanonicalTargetInventoryEntry $entry): array => $entry->toArray(), $targetInventory->entries())],
        'source_state_requirements' => ['allowed_inventory_fingerprints' => [$sourceInventory->fingerprint()]],
        'removals' => [], 'renames' => [],
    ];
    $zip = $root . DIRECTORY_SEPARATOR . 'fixture.zip';
    $archive = new ZipArchive();
    if ($archive->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Fixture ZIP could not be created.');
    $archive->addFromString('.copot/package.json', json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    foreach (array_slice($targetEntries, 1) as $entry) $archive->addFromString($entry['path'], $entry['content']);
    $archive->close();
    $payload = (new ZipIntakeService($root, $stagingRoot))->intake($zip);
    $package = (new PackageManifestReader())->read($payload);
    $plan = (new PackageV3SourceRelativePlanner())->plan($package, $artifact);
    $payload->cleanup();
    return compact('root', 'recoveryRoot', 'stagingRoot', 'storage', 'zip', 'plan', 'targetEntries');
};

$inspect = static function (array $fixture): array {
    $operation = (new LifecycleOperationStore($fixture['storage']))->read();
    $recovery = null;
    if ($operation instanceof LifecycleOperationRecord && $operation->recoveryIdentity() !== null) {
        $root = (new RecoveryRootResolver($fixture['root'], $fixture['recoveryRoot'], [$fixture['storage'], $fixture['root'] . DIRECTORY_SEPARATOR . 'storage'], [$fixture['root'] . DIRECTORY_SEPARATOR . 'public']))->resolve();
        $recovery = (new RecoveryLifecycleStore($root))->read(new RecoveryIdentity($operation->recoveryIdentity()));
    }
    return [$operation, $recovery];
};
$assertStableBoundary = static function (array $fixture, callable $assert, string $label): void {
    $assert(file_get_contents($fixture['root'] . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . '.htaccess') === 'protected', $label . ' changed protected .htaccess.');
    $guard = new Copot\Core\BackupRecovery\InstallationScopedMaintenanceGuard(new Copot\Core\InstallationMutex($fixture['storage']), $fixture['root']);
    $lease = $guard->admit('http');
    $assert($lease !== null, $label . ' left Guard/mutex unavailable.');
    $lease?->release();
};
$newService = static function (array $fixture, ?callable $activation = null, ?callable $settlement = null): Copot\Core\PackageLifecycleService {
    $service = PackageLifecycleFactory::forProject($fixture['root'], new LifecycleTrace($fixture['root'] . DIRECTORY_SEPARATOR . 'trace.jsonl', 'settlement-focused'));
    (new ReflectionProperty($service, 'intake'))->setValue($service, new ZipIntakeService($fixture['root'], $fixture['stagingRoot']));
    if ($activation !== null) (new ReflectionProperty($service, 'v3BeforeActivation'))->setValue($service, $activation);
    if ($settlement !== null) (new ReflectionProperty($service, 'v3SettlementHook'))->setValue($service, $settlement);
    return $service;
};
$runWorker = static function (array $fixture, string $window): int {
    $worker = $fixture['root'] . DIRECTORY_SEPARATOR . 'settlement-worker.php';
    $config = $fixture['root'] . DIRECTORY_SEPARATOR . 'settlement-worker.json';
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
$factoryClass = 'Copot' . chr(92) . 'Core' . chr(92) . 'PackageLifecycleFactory';
$traceClass = 'Copot' . chr(92) . 'Core' . chr(92) . 'LifecycleTrace';
$intakeClass = 'Copot' . chr(92) . 'Core' . chr(92) . 'ZipIntakeService';
$service = $factoryClass::forProject($config['root'], new $traceClass($config['trace'], 'settlement-worker'));
(new ReflectionProperty($service, 'intake'))->setValue($service, new $intakeClass($config['root'], $config['stagingRoot']));
if ($config['window'] === 'before-restored') {
    (new ReflectionProperty($service, 'v3BeforeActivation'))->setValue($service, static function (string $path) use ($config): void {
        if ($path === $config['faultPath']) { file_put_contents($config['checkpoint'], 'reached'); exit(137); }
    });
} else {
    (new ReflectionProperty($service, 'v3BeforeActivation'))->setValue($service, static function (string $path) use ($config): void {
        if ($path === $config['faultPath']) throw new RuntimeException('focused partial mutation fault');
    });
    (new ReflectionProperty($service, 'v3SettlementHook'))->setValue($service, static function (string $stage) use ($config): void {
        if ($stage === 'after-restored-before-settlement') { file_put_contents($config['checkpoint'], 'reached'); exit(137); }
    });
}
$service->apply($config['zip']);
PHP;
    file_put_contents($worker, $workerCode);
    file_put_contents($config, json_encode([
        'base' => dirname(__DIR__), 'root' => $fixture['root'], 'recoveryRoot' => $fixture['recoveryRoot'],
        'stagingRoot' => $fixture['stagingRoot'], 'trace' => $fixture['root'] . DIRECTORY_SEPARATOR . 'trace.jsonl',
        'zip' => $fixture['zip'], 'faultPath' => 'resources/c1-n1/02-second.txt', 'window' => $window,
        'checkpoint' => $fixture['root'] . DIRECTORY_SEPARATOR . 'settlement-checkpoint.txt',
    ], JSON_THROW_ON_ERROR));
    $pipes = [];
    $process = proc_open([PHP_BINARY, $worker, $config], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $fixture['root']);
    if (!is_resource($process)) throw new RuntimeException('Settlement worker could not start.');
    $deadline = microtime(true) + 15;
    do { $status = proc_get_status($process); if (!$status['running']) break; usleep(10000); } while (microtime(true) < $deadline);
    if (($status['running'] ?? false) === true) { proc_terminate($process); throw new RuntimeException('Settlement worker exceeded timeout.'); }
    $stdout = is_resource($pipes[1] ?? null) ? stream_get_contents($pipes[1]) : '';
    $stderr = is_resource($pipes[2] ?? null) ? stream_get_contents($pipes[2]) : '';
    foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
    $exitCode = proc_close($process);
    if (!is_file($fixture['root'] . DIRECTORY_SEPARATOR . 'settlement-checkpoint.txt')) throw new RuntimeException('Settlement worker did not reach its controlled checkpoint; exit=' . $exitCode . '; stdout=' . trim((string) $stdout) . '; stderr=' . trim((string) $stderr));
    return $exitCode;
};

// 1. Normal success: the correction must not change the successful path.
$fixture = $makeFixture();
try {
    $result = $newService($fixture)->apply($fixture['zip']);
    $assert($result->accepted() && $result->status() === 'completed', 'Normal V3 success changed behavior: ' . $result->reason());
    foreach (array_slice($fixture['targetEntries'], 1) as $entry) $assert(file_get_contents($fixture['root'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry['path'])) === $entry['content'], 'Normal success target bytes are wrong.');
    $assert((new LifecycleOperationStore($fixture['storage']))->read() === null, 'Normal success left an active operation.');
    $assertStableBoundary($fixture, $assert, 'Normal success');
} finally { $remove($fixture['root']); $remove($fixture['recoveryRoot']); $remove($fixture['stagingRoot']); }

// 2. N1 partial mutation: automatic verified restore settles as ROLLED_BACK and clears only the active journal.
$fixture = $makeFixture();
try {
    $faultPath = 'resources/c1-n1/02-second.txt';
    $result = $newService($fixture, static function (string $path) use ($faultPath): void { if ($path === $faultPath) throw new RuntimeException('focused partial mutation fault'); })->apply($fixture['zip']);
    $assert(!$result->accepted() && $result->status() === 'indeterminate', 'N1 partial mutation did not preserve public indeterminate result.');
    $assert(!file_exists($fixture['root'] . DIRECTORY_SEPARATOR . 'resources/c1-n1/01-first.txt') && !file_exists($fixture['root'] . DIRECTORY_SEPARATOR . 'resources/c1-n1/02-second.txt'), 'N1 restore did not remove both operation-created files.');
    $assert((new LifecycleOperationStore($fixture['storage']))->read() === null, 'N1 automatic settlement left an active operation.');
    $root = (new RecoveryRootResolver($fixture['root'], $fixture['recoveryRoot'], [$fixture['storage'], $fixture['root'] . DIRECTORY_SEPARATOR . 'storage'], [$fixture['root'] . DIRECTORY_SEPARATOR . 'public']))->resolve();
    $files = [];
    if (is_dir($fixture['recoveryRoot'])) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture['recoveryRoot'], FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) if ($file->isFile() && $file->getFilename() === 'recovery-lifecycle.json') $files[] = $file->getPathname();
    }
    $assert($files !== [], 'N1 settlement removed recovery evidence.');
    $data = json_decode((string) file_get_contents($files[0]), true);
    $assert(($data['state'] ?? null) === RecoveryLifecycleState::RESTORED, 'N1 recovery evidence did not remain RESTORED.');
    $assertStableBoundary($fixture, $assert, 'N1 automatic settlement');
} finally { $remove($fixture['root']); $remove($fixture['recoveryRoot']); $remove($fixture['stagingRoot']); }

// 3. Settlement persistence failure: RESTORED is retained and retry performs settlement only.
$fixture = $makeFixture();
try {
    $faultPath = 'resources/c1-n1/02-second.txt';
    $result = $newService(
        $fixture,
        static function (string $path) use ($faultPath): void { if ($path === $faultPath) throw new RuntimeException('focused partial mutation fault'); },
        static function (string $stage): void { if ($stage === 'after-restored-before-settlement') throw new RuntimeException('focused settlement persistence fault'); }
    )->apply($fixture['zip']);
    $assert(!$result->accepted() && $result->status() === 'indeterminate' && str_contains($result->reason(), 'settlement failed closed'), 'Settlement persistence failure was not fail-closed.');
    [$operation, $recovery] = $inspect($fixture);
    $assert($operation instanceof LifecycleOperationRecord && $operation->phase() !== LifecycleOperationRecord::ROLLED_BACK, 'Failed settlement advanced operation unexpectedly.');
    $assert($recovery instanceof RecoveryLifecycleRecord && $recovery->state() === RecoveryLifecycleState::RESTORED, 'Failed settlement did not preserve RESTORED recovery.');
    $retry = $newService($fixture)->retry($operation->operationId());
    $assert(!$retry->accepted() && $retry->status() === 'indeterminate', 'RESTORED recovery incorrectly entered retry-forward.');
    $assert((new LifecycleOperationStore($fixture['storage']))->read() === null, 'Settlement retry did not clear the settled operation.');
    $assertStableBoundary($fixture, $assert, 'Settlement retry');
} finally { $remove($fixture['root']); $remove($fixture['recoveryRoot']); $remove($fixture['stagingRoot']); }

// 4. Interruption after terminal phase persisted: idempotent retry clears ROLLED_BACK without applying the package.
$fixture = $makeFixture();
try {
    $faultPath = 'resources/c1-n1/02-second.txt';
    $result = $newService(
        $fixture,
        static function (string $path) use ($faultPath): void { if ($path === $faultPath) throw new RuntimeException('focused partial mutation fault'); },
        static function (string $stage): void { if ($stage === 'after-rolled-back-before-clear') throw new RuntimeException('focused clear interruption'); }
    )->apply($fixture['zip']);
    [$operation, $recovery] = $inspect($fixture);
    $assert(!$result->accepted() && $operation instanceof LifecycleOperationRecord && $operation->phase() === LifecycleOperationRecord::ROLLED_BACK, 'Terminal rollback phase was not persisted before clear interruption.');
    $assert($recovery instanceof RecoveryLifecycleRecord && $recovery->state() === RecoveryLifecycleState::RESTORED, 'Terminal interruption lost RESTORED recovery.');
    $retry = $newService($fixture)->retry($operation->operationId());
    $assert(!$retry->accepted() && $retry->status() === 'indeterminate', 'Terminal rollback retry returned the wrong public result.');
    $assert((new LifecycleOperationStore($fixture['storage']))->read() === null, 'Idempotent terminal rollback retry did not clear operation.');
    $assert(!file_exists($fixture['root'] . DIRECTORY_SEPARATOR . 'resources/c1-n1/01-first.txt'), 'Terminal rollback retry re-applied a file.');
    $assertStableBoundary($fixture, $assert, 'Terminal rollback retry');
} finally { $remove($fixture['root']); $remove($fixture['recoveryRoot']); $remove($fixture['stagingRoot']); }

// 5. Identity mismatch is fail-closed and does not clear the active operation.
$fixture = $makeFixture();
try {
    $faultPath = 'resources/c1-n1/02-second.txt';
    $service = $newService($fixture, static function (string $path) use ($faultPath): void { if ($path === $faultPath) throw new RuntimeException('focused partial mutation fault'); }, static function (string $stage): void { if ($stage === 'after-restored-before-settlement') throw new RuntimeException('hold after restore'); });
    $service->apply($fixture['zip']);
    [$operation, $recovery] = $inspect($fixture);
    $assert($operation instanceof LifecycleOperationRecord && $recovery instanceof RecoveryLifecycleRecord, 'Identity mismatch setup did not retain evidence.');
    $reader = new ReflectionProperty($service, 'v3RecoveryRecordReader');
    $reader->setValue($service, static fn (LifecycleOperationRecord $ignored): RecoveryLifecycleRecord => new RecoveryLifecycleRecord(new RecoveryIdentity('mismatch-recovery'), str_repeat('a', 64), 'mismatch-operation', RecoveryLifecycleState::RESTORED, true, true));
    $retry = $service->retry($operation->operationId());
    $assert(!$retry->accepted() && $retry->status() === 'indeterminate' && str_contains($retry->reason(), 'identity mismatch'), 'Identity mismatch was not fail-closed.');
    $assert((new LifecycleOperationStore($fixture['storage']))->read() instanceof LifecycleOperationRecord, 'Identity mismatch destructively cleared operation.');
    $assertStableBoundary($fixture, $assert, 'Identity mismatch');
} finally { $remove($fixture['root']); $remove($fixture['recoveryRoot']); $remove($fixture['stagingRoot']); }

// 6. Process interruption before RESTORED leaves READY retry evidence and uses retry-forward.
$fixture = $makeFixture();
try {
    $exitCode = $runWorker($fixture, 'before-restored');
    $assert($exitCode !== 0, 'Pre-RESTORED worker did not terminate at the controlled checkpoint; exit=' . $exitCode);
    [$operation, $recovery] = $inspect($fixture);
    $assert($operation instanceof LifecycleOperationRecord && $recovery instanceof RecoveryLifecycleRecord, 'Pre-RESTORED interruption lost persisted evidence.');
    $assert($operation->recoveryState() === RecoveryLifecycleState::READY && $recovery->state() === RecoveryLifecycleState::READY, 'Pre-RESTORED interruption was not READY retry-eligible.');
    $assert($operation->retryAttemptIdentity() !== null, 'Pre-RESTORED interruption lost the persisted retry identity.');
    $expectedRecoveryIdentity = \Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureService::forOperation($operation->operationId(), $operation->applyPlanIdentity(), $operation->retryAttemptIdentity())->value();
    $assert($operation->recoveryIdentity() === $expectedRecoveryIdentity, 'Pre-RESTORED recovery identity is not bound to the persisted retry identity.');
    $assert(is_file($fixture['root'] . DIRECTORY_SEPARATOR . 'resources/c1-n1/01-first.txt'), 'Pre-RESTORED interruption did not reach the deterministic mutation cursor.');
    $retry = $newService($fixture)->retry($operation->operationId());
    $assert($retry->accepted() && $retry->status() === 'completed', 'READY recovery did not retry-forward successfully: ' . $retry->reason());
    foreach (array_slice($fixture['targetEntries'], 1) as $entry) $assert(file_get_contents($fixture['root'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry['path'])) === $entry['content'], 'READY retry-forward target bytes are wrong.');
    $assertStableBoundary($fixture, $assert, 'READY retry-forward');
} finally { $remove($fixture['root']); $remove($fixture['recoveryRoot']); $remove($fixture['stagingRoot']); }

// 7. A mismatched persisted retry identity is fail-closed before recovery continuation.
$fixture = $makeFixture();
try {
    $exitCode = $runWorker($fixture, 'before-restored');
    $assert($exitCode !== 0, 'Retry-identity mismatch worker did not terminate at the controlled checkpoint; exit=' . $exitCode);
    [$operation, $recovery] = $inspect($fixture);
    $assert($operation instanceof LifecycleOperationRecord && $recovery instanceof RecoveryLifecycleRecord, 'Retry-identity mismatch setup did not retain evidence.');
    $activePath = $fixture['storage'] . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'active-operation.json';
    $persisted = json_decode((string) file_get_contents($activePath), true, 32, JSON_THROW_ON_ERROR);
    $persisted['retry_attempt_identity'] = 'tampered-retry-identity';
    file_put_contents($activePath, json_encode($persisted, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $retry = $newService($fixture)->retry($operation->operationId());
    $assert(!$retry->accepted() && $retry->status() === 'blocked', 'Mismatched retry identity was not rejected fail-closed.');
    $assert((new LifecycleOperationStore($fixture['storage']))->read() instanceof LifecycleOperationRecord, 'Retry-identity mismatch cleared the active operation.');
    $assert(is_file($fixture['root'] . DIRECTORY_SEPARATOR . 'resources/c1-n1/01-first.txt'), 'Retry-identity mismatch changed the partially mutated filesystem.');
    $assertStableBoundary($fixture, $assert, 'Retry-identity mismatch');
} finally { $remove($fixture['root']); $remove($fixture['recoveryRoot']); $remove($fixture['stagingRoot']); }

// 8. Process interruption after RESTORED is settlement-only, never retry-forward.
$fixture = $makeFixture();
try {
    $exitCode = $runWorker($fixture, 'after-restored');
    $assert($exitCode !== 0, 'Post-RESTORED worker did not terminate at the controlled checkpoint; exit=' . $exitCode);
    [$operation, $recovery] = $inspect($fixture);
    $assert($operation instanceof LifecycleOperationRecord && $recovery instanceof RecoveryLifecycleRecord, 'Post-RESTORED interruption lost persisted evidence.');
    $assert($recovery->state() === RecoveryLifecycleState::RESTORED, 'Post-RESTORED interruption did not persist RESTORED recovery.');
    $retry = $newService($fixture)->retry($operation->operationId());
    $assert(!$retry->accepted() && $retry->status() === 'indeterminate', 'RESTORED recovery entered retry-forward after process interruption.');
    $assert((new LifecycleOperationStore($fixture['storage']))->read() === null, 'Post-RESTORED settlement did not clear the operation.');
    $assert(!file_exists($fixture['root'] . DIRECTORY_SEPARATOR . 'resources/c1-n1/01-first.txt'), 'Post-RESTORED settlement re-applied a file.');
    $assertStableBoundary($fixture, $assert, 'Post-RESTORED settlement');
} finally { $remove($fixture['root']); $remove($fixture['recoveryRoot']); $remove($fixture['stagingRoot']); }

echo "Focused V3 recovery-settlement assertions: {$assertions}\n";

