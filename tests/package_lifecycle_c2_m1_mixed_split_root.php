<?php

declare(strict_types=1);

use Copot\Core\BackupRecovery\FilesystemRecoveryBundleCodec;
use Copot\Core\BackupRecovery\FilesystemRecoveryEntry;
use Copot\Core\BackupRecovery\InstallationScopedMaintenanceGuard;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryLifecycleRecord;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryRootResolver;
use Copot\Core\BackupRecovery\RecoveryStoragePathPolicy;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;
use Copot\Core\{
    CanonicalTargetInventory,
    CanonicalTargetInventoryEntry,
    CommittedLifecycleState,
    CommittedLifecycleStateStore,
    DeploymentContext,
    DestructiveActorAuthorizationEvidence,
    DestructivePlanConfirmation,
    InstallationMutex,
    InstallationState,
    LifecycleOperationRecord,
    LifecycleOperationStore,
    LifecycleTrace,
    PackageContract,
    PackageLifecycleFactory,
    PackageManifestReader,
    PackageOwnership,
    PackageLifecycleService,
    StagedFile,
    StagedPayload,
    PackageV3SourceRelativePlanner,
    SourceInventoryArtifactStore,
    V3ExplicitApplyExecutor,
    V3SourceRelativeAction,
    V3SourceRelativePlan,
    ZipIntakeService
};

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$scenarioStatus = [];
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$hash = static fn (string $bytes): string => hash('sha256', $bytes);
$remove = static function (string $path) use (&$remove): void {
    if (is_file($path) || is_link($path)) {
        @chmod($path, 0700);
        @unlink($path);
        return;
    }
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            $remove($path . DIRECTORY_SEPARATOR . $entry);
        }
    }
    @chmod($path, 0700);
    @rmdir($path);
};
$copyTree = static function (string $source, string $target) use (&$copyTree): void {
    if (!is_dir($target) && !mkdir($target, 0700, true) && !is_dir($target)) {
        throw new RuntimeException('Fixture copy target could not be created.');
    }
    foreach (scandir($source) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $from = $source . DIRECTORY_SEPARATOR . $entry;
        $to = $target . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($from) && !is_link($from)) {
            $copyTree($from, $to);
        } elseif (is_file($from) && !copy($from, $to)) {
            throw new RuntimeException('Fixture copy failed: ' . $from);
        }
    }
};
$write = static function (string $root, string $relative, string $bytes): void {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true) && !is_dir(dirname($path))) {
        throw new RuntimeException('Fixture parent could not be created: ' . dirname($path));
    }
    if (file_put_contents($path, $bytes) !== strlen($bytes)) {
        throw new RuntimeException('Fixture file could not be written: ' . $path);
    }
};
$physical = static function (array $fixture, string $root, string $path): string {
    $baseRoot = $root === CanonicalTargetInventoryEntry::PUBLIC_ROOT
        ? $fixture['publicRoot']
        : $fixture['appRoot'];
    return $baseRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
};
$samePath = static function (string $left, string $right): bool {
    return strtolower(rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, realpath($left) ?: $left), DIRECTORY_SEPARATOR))
        === strtolower(rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, realpath($right) ?: $right), DIRECTORY_SEPARATOR));
};

$makeFixture = static function () use ($base, $copyTree, $write, $hash): array {
    $id = bin2hex(random_bytes(5));
    $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-c2-m1-mixed-' . $id;
    $appRoot = $root . DIRECTORY_SEPARATOR . 'app-root';
    $publicRoot = $root . DIRECTORY_SEPARATOR . 'public-root';
    $recoveryRoot = $root . DIRECTORY_SEPARATOR . 'recovery';
    $stagingRoot = $root . DIRECTORY_SEPARATOR . 'staging';
    $storage = $appRoot . DIRECTORY_SEPARATOR . 'storage';
    mkdir($appRoot . DIRECTORY_SEPARATOR . 'app', 0700, true);
    mkdir($publicRoot . DIRECTORY_SEPARATOR . 'assets', 0700, true);
    mkdir($storage, 0700, true);
    mkdir($recoveryRoot, 0700, true);
    mkdir($stagingRoot, 0700, true);
    $copyTree($base . DIRECTORY_SEPARATOR . 'config', $appRoot . DIRECTORY_SEPARATOR . 'config');
    $copyTree($base . DIRECTORY_SEPARATOR . 'database', $appRoot . DIRECTORY_SEPARATOR . 'database');
    $write($publicRoot, '.htaccess', 'protected');

    $sourceRows = [
        ['root' => CanonicalTargetInventoryEntry::APP_ROOT, 'path' => 'app/replace.php', 'bytes' => 'app-old'],
        ['root' => CanonicalTargetInventoryEntry::APP_ROOT, 'path' => 'app/remove.php', 'bytes' => 'remove-old'],
        ['root' => CanonicalTargetInventoryEntry::APP_ROOT, 'path' => 'app/rename-old.php', 'bytes' => 'rename-old'],
        ['root' => CanonicalTargetInventoryEntry::APP_ROOT, 'path' => 'app/retain.php', 'bytes' => 'retain-app'],
        ['root' => CanonicalTargetInventoryEntry::PUBLIC_ROOT, 'path' => 'assets/replace.css', 'bytes' => 'public-old'],
        ['root' => CanonicalTargetInventoryEntry::PUBLIC_ROOT, 'path' => 'assets/retain.css', 'bytes' => 'retain-public'],
    ];
    $targetRows = [
        ['root' => CanonicalTargetInventoryEntry::APP_ROOT, 'path' => 'app/replace.php', 'bytes' => 'app-new'],
        ['root' => CanonicalTargetInventoryEntry::APP_ROOT, 'path' => 'app/rename-new.php', 'bytes' => 'rename-new'],
        ['root' => CanonicalTargetInventoryEntry::APP_ROOT, 'path' => 'app/retain.php', 'bytes' => 'retain-app'],
        ['root' => CanonicalTargetInventoryEntry::PUBLIC_ROOT, 'path' => 'assets/add.js', 'bytes' => 'public-add'],
        ['root' => CanonicalTargetInventoryEntry::PUBLIC_ROOT, 'path' => 'assets/replace.css', 'bytes' => 'public-new'],
        ['root' => CanonicalTargetInventoryEntry::PUBLIC_ROOT, 'path' => 'assets/retain.css', 'bytes' => 'retain-public'],
    ];
    foreach ($sourceRows as $row) {
        $write($row['root'] === CanonicalTargetInventoryEntry::PUBLIC_ROOT ? $publicRoot : $appRoot, $row['path'], $row['bytes']);
    }

    $sourceInventory = new CanonicalTargetInventory(array_map(
        static fn (array $row): CanonicalTargetInventoryEntry => new CanonicalTargetInventoryEntry(
            $row['root'],
            CanonicalTargetInventoryEntry::PACKAGE_OWNED,
            $row['path'],
            strlen($row['bytes']),
            $hash($row['bytes'])
        ),
        $sourceRows
    ));
    $targetInventory = new CanonicalTargetInventory(array_map(
        static fn (array $row): CanonicalTargetInventoryEntry => new CanonicalTargetInventoryEntry(
            $row['root'],
            CanonicalTargetInventoryEntry::PACKAGE_OWNED,
            $row['path'],
            strlen($row['bytes']),
            $hash($row['bytes'])
        ),
        $targetRows
    ));
    $sourceStore = new SourceInventoryArtifactStore($storage);
    $sourceArtifact = $sourceStore->publish($sourceInventory, 'c2-m1-mixed-source', 3, 'c2-m1-mixed-tree');
    $committedAt = new DateTimeImmutable('now');
    (new CommittedLifecycleStateStore($storage))->write(new CommittedLifecycleState(
        '0.13.0',
        'c2-m1-mixed-source',
        'c2-m1-mixed-tree',
        3,
        $hash('schema'),
        $hash('migration'),
        $committedAt,
        $hash('committed-package'),
        $sourceArtifact->reference()
    ));
    (new InstallationState($storage))->replaceMarker('0.13.0', $committedAt->format(DATE_ATOM));

    $removals = [
        ['root' => CanonicalTargetInventoryEntry::APP_ROOT, 'path' => 'app/remove.php', 'ownership' => CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'expected_source_byte_size' => strlen('remove-old'), 'expected_source_sha256' => $hash('remove-old'), 'reason' => 'obsolete'],
        ['root' => CanonicalTargetInventoryEntry::APP_ROOT, 'path' => 'app/rename-old.php', 'ownership' => CanonicalTargetInventoryEntry::PACKAGE_OWNED, 'expected_source_byte_size' => strlen('rename-old'), 'expected_source_sha256' => $hash('rename-old'), 'reason' => 'renamed'],
    ];
    $renames = [[
        'source_root' => CanonicalTargetInventoryEntry::APP_ROOT,
        'source_path' => 'app/rename-old.php',
        'target_root' => CanonicalTargetInventoryEntry::APP_ROOT,
        'target_path' => 'app/rename-new.php',
    ]];
    $delivered = [
        ['path' => 'app/replace.php', 'bytes' => 'app-new'],
        ['path' => 'app/rename-new.php', 'bytes' => 'rename-new'],
        ['path' => 'public/assets/add.js', 'bytes' => 'public-add'],
        ['path' => 'public/assets/replace.css', 'bytes' => 'public-new'],
    ];
    $manifest = [
        'package_type' => PackageContract::WEBCORE_PACKAGE_TYPE,
        'manifest_contract_version' => 3,
        'target_webcore_version' => '0.13.0',
        'release_identity' => 'c2-m1-mixed-release',
        'source_tree_identity' => 'c2-m1-mixed-tree',
        'source_compatibility' => ['minimum_source_version' => '0.13.0', 'maximum_source_version' => null],
        'runtime_compatibility' => ['minimum_php_version' => '8.2.0', 'minimum_database_versions' => [], 'required_extensions' => ['json']],
        'inventory' => array_map(static fn (array $row): array => [
            'path' => $row['path'],
            'byte_size' => strlen($row['bytes']),
            'sha256' => $hash($row['bytes']),
            'ownership' => PackageOwnership::PACKAGE_OWNED,
        ], $delivered),
        'migration_declaration' => ['declares_core_migrations' => false, 'declaration_identity' => null],
        'target_requirements' => [],
        'coverage' => 'partial',
        'target_inventory' => ['serialization_version' => 1, 'fingerprint' => $targetInventory->fingerprint(), 'entries' => array_map(static fn (CanonicalTargetInventoryEntry $entry): array => $entry->toArray(), $targetInventory->entries())],
        'source_state_requirements' => ['allowed_inventory_fingerprints' => [$sourceInventory->fingerprint()]],
        'removals' => $removals,
        'renames' => $renames,
    ];
    $zip = $root . DIRECTORY_SEPARATOR . 'mixed-v3.zip';
    $archive = new ZipArchive();
    if ($archive->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Mixed V3 package could not be opened.');
    }
    $archive->addFromString('.copot/package.json', json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    foreach ($delivered as $entry) {
        $archive->addFromString($entry['path'], $entry['bytes']);
    }
    $archive->close();

    putenv('COPOT_RECOVERY_ROOT=' . $recoveryRoot);
    putenv('COPOT_MARIADB_ADMIN_USERNAME=');
    putenv('COPOT_MARIADB_ADMIN_PASSWORD=');
    $_ENV['COPOT_RECOVERY_ROOT'] = $recoveryRoot;
    $_ENV['COPOT_MARIADB_ADMIN_USERNAME'] = '';
    $_ENV['COPOT_MARIADB_ADMIN_PASSWORD'] = '';
    $context = new DeploymentContext($appRoot, $publicRoot, '/');
    $payload = (new ZipIntakeService($appRoot, $stagingRoot))->intake($zip);
    $package = (new PackageManifestReader())->read($payload);
    $plan = (new PackageV3SourceRelativePlanner())->plan($package, $sourceArtifact);
    $recoveryPlan = \Copot\Core\BackupRecovery\FilesystemRecoveryPlan::fromV3Plan($plan);
    $payloadForExecutor = $payload->withoutPaths([PackageManifestReader::PATH]);
    $payloadOrder = array_map(static fn ($file): string => $file->path(), $payloadForExecutor->files());
    $expectedPayloadOrder = [];
    foreach ($plan->actions() as $action) {
        if (in_array($action->action(), ['deliver', 'replace'], true)) {
            $expectedPayloadOrder[] = $action->root() === CanonicalTargetInventoryEntry::PUBLIC_ROOT
                ? 'public/' . $action->path()
                : $action->path();
        }
    }
    $payload->cleanup();
    $confirmation = [
        'operation_identity' => $plan->operationIdentity(),
        'plan_identity' => $plan->identity(),
        'removal_set_digest' => $plan->removalSetDigest(),
        'actor_authorization_evidence' => [
            'actor_identity' => 'c2-m1-b-actor',
            'capability_reference' => 'system.webcore.manage',
            'session_identity' => 'c2-m1-b-session',
        ],
        'confirmation_version' => 1,
    ];
    return compact('root', 'appRoot', 'publicRoot', 'recoveryRoot', 'stagingRoot', 'storage', 'zip', 'context', 'sourceRows', 'targetRows', 'sourceInventory', 'targetInventory', 'sourceArtifact', 'plan', 'recoveryPlan', 'confirmation', 'removals', 'payloadOrder', 'expectedPayloadOrder');
};

$serviceFor = static function (array $fixture, ?callable $before = null, ?callable $settlement = null, ?callable $handoff = null, ?callable $after = null): PackageLifecycleService {
    $trace = new LifecycleTrace($fixture['root'] . DIRECTORY_SEPARATOR . 'trace.jsonl', 'c2-m1-b');
    $service = PackageLifecycleFactory::forProject($fixture['context'], $trace);
    (new ReflectionProperty($service, 'intake'))->setValue($service, new ZipIntakeService($fixture['appRoot'], $fixture['stagingRoot']));
    if ($before !== null) {
        (new ReflectionProperty($service, 'v3BeforeActivation'))->setValue($service, $before);
    }
    if ($settlement !== null) {
        (new ReflectionProperty($service, 'v3SettlementHook'))->setValue($service, $settlement);
    }
    if ($handoff !== null) {
        (new ReflectionProperty($service, 'v3RetryHandoffHook'))->setValue($service, $handoff);
    }
    if ($after !== null) {
        (new ReflectionProperty($service, 'v3AfterActivation'))->setValue($service, $after);
    }
    return $service;
};

$loadPayload = static function (array $fixture): StagedPayload {
    $payload = (new ZipIntakeService($fixture['appRoot'], $fixture['stagingRoot']))->intake($fixture['zip']);
    return $payload->withoutPaths([PackageManifestReader::PATH]);
};
$payloadVariant = static function (StagedPayload $basePayload, array $files): StagedPayload {
    $session = (new ReflectionProperty(StagedPayload::class, 'session'))->getValue($basePayload);
    $variant = new StagedPayload($session, $basePayload->archiveSha256(), $files);
    $indexed = [];
    foreach ($files as $file) {
        $indexed[$file->path()] = $file;
    }
    (new ReflectionProperty(StagedPayload::class, 'files'))->setValue($variant, $indexed);
    return $variant;
};
$verifyPayload = static function (V3SourceRelativePlan $plan, StagedPayload $payload): void {
    $method = new ReflectionMethod(V3ExplicitApplyExecutor::class, 'verifyPayload');
    $method->setAccessible(true);
    $method->invoke(new V3ExplicitApplyExecutor(), $plan, $payload);
};
$expectPayloadRejected = static function (callable $callback, callable $assert, string $label): void {
    try {
        $callback();
    } catch (Throwable) {
        $assert(true, $label . ' was rejected.');
        return;
    }
    $assert(false, $label . ' was accepted unexpectedly.');
};

$assertPreState = static function (array $fixture, callable $assert, string $label) use ($physical): void {
    foreach ($fixture['sourceRows'] as $row) {
        $path = $physical($fixture, $row['root'], $row['path']);
        $assert(is_file($path) && file_get_contents($path) === $row['bytes'], $label . ' changed original bytes at ' . $row['root'] . '/' . $row['path']);
    }
    foreach ([
        [CanonicalTargetInventoryEntry::PUBLIC_ROOT, 'assets/add.js'],
        [CanonicalTargetInventoryEntry::APP_ROOT, 'app/rename-new.php'],
    ] as [$root, $path]) {
        $assert(!file_exists($physical($fixture, $root, $path)), $label . ' left original-absence path ' . $root . '/' . $path);
    }
    $assert(file_get_contents($fixture['publicRoot'] . DIRECTORY_SEPARATOR . '.htaccess') === 'protected', $label . ' changed .htaccess.');
};

$assertFinalState = static function (array $fixture, callable $assert, string $label) use ($physical, $samePath): void {
    foreach ($fixture['targetRows'] as $row) {
        $path = $physical($fixture, $row['root'], $row['path']);
        $assert(is_file($path) && file_get_contents($path) === $row['bytes'], $label . ' final bytes are wrong at ' . $row['root'] . '/' . $row['path']);
    }
    foreach ([
        [CanonicalTargetInventoryEntry::APP_ROOT, 'app/remove.php'],
        [CanonicalTargetInventoryEntry::APP_ROOT, 'app/rename-old.php'],
    ] as [$root, $path]) {
        $assert(!file_exists($physical($fixture, $root, $path)), $label . ' left removed path ' . $root . '/' . $path);
    }
    $guard = new \Copot\Core\LiveTreePathGuard($fixture['context']);
    $assert($samePath($guard->destinationForRoot(CanonicalTargetInventoryEntry::APP_ROOT, 'app/replace.php'), $physical($fixture, CanonicalTargetInventoryEntry::APP_ROOT, 'app/replace.php')), $label . ' APP_ROOT mapping is wrong.');
    $assert($samePath($guard->destinationForRoot(CanonicalTargetInventoryEntry::PUBLIC_ROOT, 'assets/add.js'), $physical($fixture, CanonicalTargetInventoryEntry::PUBLIC_ROOT, 'assets/add.js')), $label . ' PUBLIC_ROOT mapping is wrong.');
    $state = (new CommittedLifecycleStateStore($fixture['storage']))->read();
    $assert($state instanceof CommittedLifecycleState && $state->sourceInventoryReference()?->fingerprint() === $fixture['targetInventory']->fingerprint(), $label . ' committed target fingerprint is wrong.');
    $assert((new LifecycleOperationStore($fixture['storage']))->read() === null, $label . ' left an active lifecycle operation.');
    $maintenance = new InstallationScopedMaintenanceGuard(new InstallationMutex($fixture['storage']), $fixture['storage']);
    $lease = $maintenance->admit('http');
    $assert($lease !== null, $label . ' left Guard/mutex unavailable.');
    $lease?->release();
    $assert(file_get_contents($fixture['publicRoot'] . DIRECTORY_SEPARATOR . '.htaccess') === 'protected', $label . ' changed .htaccess.');
};

$findRecovery = static function (array $fixture, string $operationId): array {
    $storageRoot = (new RecoveryRootResolver($fixture['appRoot'], $fixture['recoveryRoot'], [$fixture['storage'], $fixture['appRoot'] . DIRECTORY_SEPARATOR . 'storage'], [$fixture['publicRoot']]))->resolve();
    $statePaths = glob($storageRoot->path() . DIRECTORY_SEPARATOR . 'recovery-sets' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'state' . DIRECTORY_SEPARATOR . 'recovery-lifecycle.json') ?: [];
    foreach ($statePaths as $statePath) {
        $data = json_decode((string) file_get_contents($statePath), true);
        if (!is_array($data) || ($data['operation_identity'] ?? null) !== $operationId || !is_string($data['recovery_identity'] ?? null)) {
            continue;
        }
        $identity = new RecoveryIdentity($data['recovery_identity']);
        $record = (new RecoveryLifecycleStore($storageRoot))->read($identity);
        $artifacts = new RecoveryArtifactStore($storageRoot);
        return [$storageRoot, $identity, $record, $artifacts, $artifacts->readManifest($identity)];
    }
        $files = [];
        foreach ([$fixture['recoveryRoot'], $fixture['appRoot'] . DIRECTORY_SEPARATOR . 'storage'] as $scanRoot) {
            if (!is_dir($scanRoot)) continue;
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) if ($file->isFile()) $files[] = $file->getPathname();
        }
        throw new RuntimeException('Recovery record not found for operation ' . $operationId . '; files=' . json_encode($files));
};

$recoveryArtifact = static function (array $found, string $domain): array {
    [, $identity, , $artifacts, $decoded] = $found;
    foreach (($decoded['artifacts'] ?? []) as $record) {
        if ($record instanceof \Copot\Core\BackupRecovery\RecoveryArtifactRecord && $record->domainIdentifier() === $domain) {
            return [$record, $artifacts->readArtifact($identity, $record), $artifacts];
        }
    }
    throw new RuntimeException('Recovery domain not found: ' . $domain);
};

$assertRecoveryShape = static function (array $fixture, array $found, callable $assert, string $label): void {
    [, $identity, $record, , $manifest] = $found;
    $assert($record instanceof RecoveryLifecycleRecord && $record->operationIdentity() === $fixture['plan']->operationIdentity(), $label . ' recovery operation binding is wrong.');
    $assert($record->recoveryIdentity()->equals($identity), $label . ' recovery identity binding is wrong.');
    $domains = array_map(static fn ($artifact): string => $artifact->domainIdentifier(), $manifest['artifacts'] ?? []);
    $assert(in_array('filesystem.package-owned', $domains, true), $label . ' lacks filesystem package-owned domain.');
    $assert(in_array('filesystem.action-linkage', $domains, true), $label . ' lacks filesystem action-linkage domain.');
    $assert(!in_array('database.webcore', $domains, true), $label . ' unexpectedly contains database recovery domain.');
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
$context = new Copot\Core\DeploymentContext($config['appRoot'], $config['publicRoot'], '/');
$service = Copot\Core\PackageLifecycleFactory::forProject($context, new Copot\Core\LifecycleTrace($config['trace'], 'c2-m1-b-worker'), $config['requireExisting'] ?? false);
(new ReflectionProperty($service, 'intake'))->setValue($service, new Copot\Core\ZipIntakeService($config['appRoot'], $config['stagingRoot']));
if (($config['mode'] ?? '') === 'interrupt') {
    (new ReflectionProperty($service, 'v3BeforeActivation'))->setValue($service, static function (string $path) use ($config): void {
        if ($path === $config['faultPath']) {
            file_put_contents($config['checkpoint'], 'reached');
            exit(137);
        }
    });
}
if (($config['mode'] ?? '') === 'handoff') {
    (new ReflectionProperty($service, 'v3RetryHandoffHook'))->setValue($service, static function (Copot\Core\LifecycleOperationRecord $record) use ($config): void {
        file_put_contents($config['checkpoint'], 'reached');
        exit(137);
    });
}
if (($config['mode'] ?? '') === 'settlement') {
    (new ReflectionProperty($service, 'v3BeforeActivation'))->setValue($service, static function (string $path) use ($config): void {
        if ($path === $config['faultPath']) throw new RuntimeException('mixed settlement mutation fault');
    });
    (new ReflectionProperty($service, 'v3SettlementHook'))->setValue($service, static function (string $stage) use ($config): void {
        if ($stage === 'after-restored-before-settlement') throw new RuntimeException('mixed settlement persistence fault');
    });
}
if (($config['mode'] ?? '') === 'n1') {
    (new ReflectionProperty($service, 'v3Journal'))->setValue($service, static function (string $kind, string $path, int $removed) use ($config): void {
        if ($kind !== 'remove' || $path !== 'app/remove.php' || $removed !== 1) {
            return;
        }
        $operation = (new Copot\Core\LifecycleOperationStore($config['storage']))->read();
        $first = $config['appRoot'] . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'remove.php';
        $second = $config['appRoot'] . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'rename-old.php';
        if (!$operation instanceof Copot\Core\LifecycleOperationRecord
            || $operation->operationId() !== $config['operationId']
            || $operation->recoveryIdentity() === null
            || $operation->fileCursor() !== 9
            || $operation->lastVerifiedPath() !== 'app/remove.php'
            || $operation->recoveryState() !== Copot\Core\BackupRecovery\RecoveryLifecycleState::READY
            || is_file($first)
            || !is_file($second)) {
            throw new RuntimeException('N1 barrier precondition failed.');
        }
        $acknowledgment = [
            'barrier_id' => $config['barrierId'],
            'operation_id' => $operation->operationId(),
            'recovery_identity' => $operation->recoveryIdentity(),
            'kind' => $kind,
            'path' => $path,
            'removed' => $removed,
            'file_cursor' => $operation->fileCursor(),
            'last_verified_path' => $operation->lastVerifiedPath(),
            'first_absent' => !is_file($first),
            'second_exists' => is_file($second),
            'worker_pid' => getmypid(),
        ];
        $temporary = $config['barrierAck'] . '.tmp-' . $config['barrierId'];
        $bytes = json_encode($acknowledgment, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        if (file_put_contents($temporary, $bytes, LOCK_EX) !== strlen($bytes) || !@rename($temporary, $config['barrierAck'])) {
            @unlink($temporary);
            throw new RuntimeException('N1 barrier acknowledgment could not be published.');
        }
        while (true) {
            usleep(1000);
        }
    });
}
if (in_array(($config['mode'] ?? ''), ['apply', 'interrupt', 'settlement', 'n1'], true)) {
    $service->apply($config['zip'], false, $config['confirmation'], 'c2-m1-b-actor');
} else {
    $service->retry((string) $config['operationId']);
}
PHP;

$runWorker = static function (array $fixture, string $mode, ?string $operationId = null, ?string $faultPath = null, bool $requireExisting = false) use ($workerCode): array {
    $worker = $fixture['root'] . DIRECTORY_SEPARATOR . 'worker.php';
    $configPath = $fixture['root'] . DIRECTORY_SEPARATOR . 'worker.json';
    $checkpoint = $fixture['root'] . DIRECTORY_SEPARATOR . 'checkpoint.txt';
    @unlink($checkpoint);
    file_put_contents($worker, $workerCode);
    file_put_contents($configPath, json_encode([
        'base' => dirname(__DIR__),
        'appRoot' => $fixture['appRoot'],
        'publicRoot' => $fixture['publicRoot'],
        'recoveryRoot' => $fixture['recoveryRoot'],
        'stagingRoot' => $fixture['stagingRoot'],
        'zip' => $fixture['zip'],
        'trace' => $fixture['root'] . DIRECTORY_SEPARATOR . 'trace.jsonl',
        'confirmation' => $fixture['confirmation'],
        'mode' => $mode,
        'operationId' => $operationId,
        'faultPath' => $faultPath,
        'checkpoint' => $checkpoint,
        'requireExisting' => $requireExisting,
    ], JSON_THROW_ON_ERROR));
    $pipes = [];
    $process = proc_open([PHP_BINARY, $worker, $configPath], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $fixture['root']);
    if (!is_resource($process)) {
        throw new RuntimeException('C2-M1-B worker could not start.');
    }
    $deadline = microtime(true) + 20;
    do {
        $status = proc_get_status($process);
        if (!$status['running'] || is_file($checkpoint)) {
            break;
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    if (($status['running'] ?? false) === true && !is_file($checkpoint)) {
        proc_terminate($process);
        throw new RuntimeException('C2-M1-B worker did not reach its checkpoint.');
    }
    $stdout = is_resource($pipes[1] ?? null) ? stream_get_contents($pipes[1]) : '';
    $stderr = is_resource($pipes[2] ?? null) ? stream_get_contents($pipes[2]) : '';
    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) fclose($pipe);
    }
    $exitCode = proc_close($process);
    return [$exitCode, is_file($checkpoint), trim((string) $stdout), trim((string) $stderr)];
};

$runScenario = static function (string $name, callable $callback) use (&$scenarioStatus, &$assertions): void {
    $before = $assertions;
    try {
        $callback();
        $scenarioStatus[$name] = 'PASS';
        echo $name . ': PASS (' . ($assertions - $before) . " assertions)\n";
    } catch (Throwable $exception) {
        $scenarioStatus[$name] = $exception->getMessage();
        echo $name . ': FAIL/BLOCKED (' . ($assertions - $before) . ' assertions) - ' . $exception->getMessage() . "\n";
    }
};

$runScenario('PAYLOAD', static function () use ($makeFixture, $loadPayload, $payloadVariant, $verifyPayload, $expectPayloadRejected, $assert, $hash, $remove): void {
    $fixture = $makeFixture();
    try {
        $payload = $loadPayload($fixture);
        $byPath = [];
        foreach ($payload->files() as $file) {
            $byPath[$file->path()] = $file;
        }
        $ordered = array_map(static fn (string $path): StagedFile => $byPath[$path], $fixture['expectedPayloadOrder']);
        $verifyPayload($fixture['plan'], $payloadVariant($payload, $ordered));
        $assert(isset($byPath['app/replace.php'], $byPath['public/assets/replace.css']), 'Payload regression lost APP_ROOT/PUBLIC_ROOT logical path distinction.');
        $assert(true, 'Identical payload entries with insertion order different were accepted.');

        $expectPayloadRejected(
            fn (): mixed => $verifyPayload($fixture['plan'], $payloadVariant($payload, array_values(array_filter($ordered, static fn (StagedFile $file): bool => $file->path() !== 'public/assets/add.js')))),
            $assert,
            'Missing payload'
        );
        $expectPayloadRejected(
            fn (): mixed => $verifyPayload($fixture['plan'], $payloadVariant($payload, [...$ordered, new StagedFile('app/extra.php', 5, $hash('extra'))])),
            $assert,
            'Extra payload'
        );
        $wrongSize = array_map(static fn (StagedFile $file): StagedFile => $file->path() === 'app/replace.php' ? new StagedFile($file->path(), $file->byteSize() + 1, $file->sha256()) : $file, $ordered);
        $expectPayloadRejected(fn (): mixed => $verifyPayload($fixture['plan'], $payloadVariant($payload, $wrongSize)), $assert, 'Wrong byte size');
        $wrongSha = array_map(static fn (StagedFile $file): StagedFile => $file->path() === 'app/replace.php' ? new StagedFile($file->path(), $file->byteSize(), $hash('wrong-sha')) : $file, $ordered);
        $expectPayloadRejected(fn (): mixed => $verifyPayload($fixture['plan'], $payloadVariant($payload, $wrongSha)), $assert, 'Wrong SHA-256');

        $appOnlyAction = new V3SourceRelativeAction(V3SourceRelativeAction::REPLACE, CanonicalTargetInventoryEntry::APP_ROOT, 'app/replace.php', strlen('app-new'), $hash('app-new'));
        $appOnlyPlan = new V3SourceRelativePlan(
            $fixture['plan']->package(),
            $fixture['plan']->packageEvidenceIdentity(),
            $fixture['plan']->sourceFingerprint(),
            $fixture['plan']->sourceReference(),
            [$appOnlyAction],
            $fixture['plan']->targetFingerprint(),
            $fixture['plan']->coverage(),
            []
        );
        $verifyPayload($appOnlyPlan, $payloadVariant($payload, [new StagedFile('app/replace.php', strlen('app-new'), $hash('app-new'))]));
        $assert(true, 'Valid APP_ROOT-only payload was accepted.');
    } finally {
        $remove($fixture['root']);
        $remove($fixture['recoveryRoot']);
        $remove($fixture['stagingRoot']);
    }
});

$runScenario('P1', static function () use ($makeFixture, $serviceFor, $assert, $assertPreState, $assertFinalState, $findRecovery, $assertRecoveryShape, $recoveryArtifact, $samePath, $physical, $remove): void {
    $fixture = $makeFixture();
    try {
        $assert($fixture['context']->isSplitRoot(), 'P1 fixture is not split-root.');
        $assert(count($fixture['sourceInventory']->entries()) === 6, 'P1 source inventory count is not 6.');
        $assert(count($fixture['targetInventory']->entries()) === 6, 'P1 target inventory count is not 6.');
        $assert(count($fixture['plan']->removalActions()) === 2, 'P1 removal action count is not 2.');
    $assert(count($fixture['recoveryPlan']->entries()) === 8, 'P1 recovery entry count is not 8 in the current recovery representation.');
        $counts = array_count_values(array_map(static fn ($action): string => $action->action(), $fixture['plan']->actions()));
    ksort($counts);
    $assert($counts === ['deliver' => 2, 'remove' => 1, 'rename' => 1, 'replace' => 2, 'retain' => 2], 'P1 planner composition is wrong: ' . json_encode($counts));
        $expectedActions = [
            'deliver:APP_ROOT/app/rename-new.php',
            'deliver:PUBLIC_ROOT/assets/add.js',
            'remove:APP_ROOT/app/remove.php',
            'rename:APP_ROOT/app/rename-old.php',
            'replace:APP_ROOT/app/replace.php',
            'replace:PUBLIC_ROOT/assets/replace.css',
            'retain:APP_ROOT/app/retain.php',
            'retain:PUBLIC_ROOT/assets/retain.css',
        ];
        $actualActions = array_map(static fn ($action): string => $action->action() . ':' . $action->root() . '/' . $action->path(), $fixture['plan']->actions());
        $assert($actualActions === $expectedActions, 'P1 planner ordering is wrong: ' . json_encode($actualActions));
        $expectedRemovals = ['APP_ROOT/app/remove.php', 'APP_ROOT/app/rename-old.php'];
        $actualRemovals = array_map(static fn (array $action): string => $action['source_root'] . '/' . $action['source_path'], $fixture['plan']->removalActions());
        $assert($actualRemovals === $expectedRemovals, 'P1 removal declaration/mutation order is wrong.');
        $assertPreState($fixture, $assert, 'P1 pre-state');
        $delivery = [];
        $service = $serviceFor($fixture, null, null, null, static function (string $path) use (&$delivery): void { $delivery[] = $path; });
        $result = $service->apply($fixture['zip'], false, $fixture['confirmation'], 'c2-m1-b-actor');
        $assert($result->accepted() && $result->status() === 'completed', 'P1 apply failed: ' . $result->status() . ' / ' . $result->reason());
        $assert($delivery === ['app/rename-new.php', 'app/replace.php', 'public/assets/add.js', 'public/assets/replace.css'], 'P1 delivery mutation order is wrong: ' . json_encode($delivery));
        $assertFinalState($fixture, $assert, 'P1');
        $found = $findRecovery($fixture, $fixture['plan']->operationIdentity());
        $assertRecoveryShape($fixture, $found, $assert, 'P1');
        $filesystem = $recoveryArtifact($found, 'filesystem.package-owned');
        $decoded = (new FilesystemRecoveryBundleCodec())->decode($filesystem[1]);
        $entries = $decoded['entries'] ?? [];
        $absent = array_values(array_filter($entries, static fn ($entry): bool => $entry->preOperationState() === FilesystemRecoveryEntry::ABSENT_BEFORE_OPERATION));
        $existing = array_values(array_filter($entries, static fn ($entry): bool => $entry->preOperationState() === FilesystemRecoveryEntry::EXISTING_FILE));
        $assert(count($absent) === 2, 'P1 captured original absence count is not 2.');
        $assert(count($existing) === 6, 'P1 captured original existing-byte count is not 6.');
    } finally {
        $remove($fixture['root']);
        $remove($fixture['recoveryRoot']);
        $remove($fixture['stagingRoot']);
    }
});

$runExtended = true;
if ($runExtended) {
$scenarioFilter = getenv('C2_M1_SCENARIO') ?: 'all';
if (in_array($scenarioFilter, ['all', 'N2'], true)) $runScenario('N2', static function () use ($makeFixture, $serviceFor, $assert, $assertPreState, $findRecovery, $assertRecoveryShape, $remove): void {
    $fixture = $makeFixture();
    try {
        $service = $serviceFor(
            $fixture,
            static function (string $path): void { if ($path === 'app/replace.php') throw new RuntimeException('mixed settlement mutation fault'); },
            static function (string $stage): void { if ($stage === 'after-restored-before-settlement') throw new RuntimeException('mixed settlement persistence fault'); }
        );
        $result = $service->apply($fixture['zip'], false, $fixture['confirmation'], 'c2-m1-b-actor');
        $assert(!$result->accepted() && $result->status() === 'indeterminate' && str_contains($result->reason(), 'settlement failed closed'), 'N2 did not fail closed at settlement boundary.');
        $operation = (new LifecycleOperationStore($fixture['storage']))->read();
        $assert($operation instanceof LifecycleOperationRecord, 'N2 lost persisted operation after settlement failure.');
        $found = $findRecovery($fixture, $fixture['plan']->operationIdentity());
        $assertRecoveryShape($fixture, $found, $assert, 'N2');
        $assert($found[2] instanceof RecoveryLifecycleRecord && $found[2]->state() === RecoveryLifecycleState::RESTORED, 'N2 recovery is not RESTORED.');
        $assertPreState($fixture, $assert, 'N2 restored state');
        $retry = $serviceFor($fixture)->retry($operation->operationId());
        $assert(!$retry->accepted() && $retry->status() === 'indeterminate', 'N2 retry did not take settlement-only route.');
        echo 'N2 evidence: operation=' . $operation->operationId() . '; recovery=' . $found[1]->value() . '; settlement=' . $found[2]->state() . '; retry=' . $retry->status() . PHP_EOL;
        $assert((new LifecycleOperationStore($fixture['storage']))->read() === null, 'N2 settlement retry did not clear operation.');
        $assertPreState($fixture, $assert, 'N2 post-settlement state');
        $maintenance = new InstallationScopedMaintenanceGuard(new InstallationMutex($fixture['storage']), $fixture['storage']);
        $lease = $maintenance->admit('http');
        $assert($lease !== null, 'N2 Guard/mutex unavailable after settlement.');
        $lease?->release();
    } finally {
        $remove($fixture['root']);
        $remove($fixture['recoveryRoot']);
        $remove($fixture['stagingRoot']);
    }
});

if (in_array($scenarioFilter, ['all', 'N4'], true)) $runScenario('N4', static function () use ($makeFixture, $runWorker, $serviceFor, $assert, $assertPreState, $assertFinalState, $findRecovery, $assertRecoveryShape, $recoveryArtifact, $remove): void {
    $fixture = $makeFixture();
    try {
        [$exitCode, $checkpoint, $stdout, $stderr] = $runWorker($fixture, 'interrupt', null, 'app/replace.php');
        $assert($checkpoint && $exitCode === 137, 'N4 interruption setup did not reach controlled worker exit: exit=' . $exitCode . ' stdout=' . $stdout . ' stderr=' . $stderr);
        $operation = (new LifecycleOperationStore($fixture['storage']))->read();
        $assert($operation instanceof LifecycleOperationRecord, 'N4 interruption lost operation.');
        $found = $findRecovery($fixture, $operation->operationId());
        $assertRecoveryShape($fixture, $found, $assert, 'N4');
        [$record, $validBytes, $artifacts] = $recoveryArtifact($found, 'filesystem.action-linkage');
        $policy = new RecoveryStoragePathPolicy($found[0]);
        $artifactPath = $policy->artifactPath($found[1], $record);
        $before = [];
        foreach ($fixture['sourceRows'] as $row) {
            $path = $fixture['appRoot'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $row['path']);
            if ($row['root'] === CanonicalTargetInventoryEntry::PUBLIC_ROOT) $path = $fixture['publicRoot'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $row['path']);
            $before[$path] = is_file($path) ? hash('sha256', (string) file_get_contents($path)) : null;
        }
        file_put_contents($artifactPath, 'corrupted-action-linkage');
        $rejected = $serviceFor($fixture)->retry($operation->operationId());
        $assert(!$rejected->accepted() && in_array($rejected->status(), ['rejected', 'blocked'], true), 'N4 corrupted artifact was accepted.');
        foreach ($before as $path => $digest) {
            $current = is_file($path) ? hash('sha256', (string) file_get_contents($path)) : null;
            $assert($current === $digest, 'N4 rejected restore changed live state: ' . $path);
        }
        $assert((new LifecycleOperationStore($fixture['storage']))->read() instanceof LifecycleOperationRecord, 'N4 corrupted rejection cleared operation.');
        file_put_contents($artifactPath, $validBytes);
        $retry = $serviceFor($fixture)->retry($operation->operationId());
        $afterRejected = (new LifecycleOperationStore($fixture['storage']))->read();
        $assert($retry->accepted() && $retry->status() === 'completed', 'N4 valid artifact retry failed: ' . $retry->reason() . '; phase=' . ($afterRejected?->phase() ?? 'none') . '; recovery_state=' . ($afterRejected?->recoveryState() ?? 'none'));
        echo 'N4 evidence: operation=' . $operation->operationId() . '; recovery=' . $operation->recoveryIdentity() . '; rejected_then_valid_retry=' . $retry->status() . PHP_EOL;
        $assertFinalState($fixture, $assert, 'N4');
    } finally {
        $remove($fixture['root']);
        $remove($fixture['recoveryRoot']);
        $remove($fixture['stagingRoot']);
    }
});

if (in_array($scenarioFilter, ['all', 'N3'], true)) $runScenario('N3', static function () use ($makeFixture, $runWorker, $assert, $assertPreState, $assertFinalState, $findRecovery, $assertRecoveryShape, $remove): void {
    $fixture = $makeFixture();
    try {
        [$exitCode, $checkpoint, $stdout, $stderr] = $runWorker($fixture, 'interrupt', null, 'public/assets/replace.css');
        $assert($checkpoint && $exitCode === 137, 'N3 delivery interruption did not reach controlled worker exit: exit=' . $exitCode . ' stdout=' . $stdout . ' stderr=' . $stderr);
        $operation = (new LifecycleOperationStore($fixture['storage']))->read();
        $assert($operation instanceof LifecycleOperationRecord && $operation->operationId() === $fixture['plan']->operationIdentity(), 'N3 operation identity changed before continuation.');
        $oldRecovery = $operation->recoveryIdentity();
        $assert($operation->recoveryState() === RecoveryLifecycleState::READY && $operation->fileCursor() === 3 && $operation->lastVerifiedPath() === 'public/assets/add.js', 'N3 interruption cursor boundary is wrong.');
        $found = $findRecovery($fixture, $operation->operationId());
        $assertRecoveryShape($fixture, $found, $assert, 'N3');
        [$handoffExit, $handoffCheckpoint] = $runWorker($fixture, 'handoff', $operation->operationId(), null, true);
        $assert($handoffCheckpoint && $handoffExit === 137, 'N3 retry handoff did not reach controlled checkpoint.');
        $handoff = (new LifecycleOperationStore($fixture['storage']))->read();
        $assert($handoff instanceof LifecycleOperationRecord && $handoff->operationId() === $operation->operationId(), 'N3 handoff changed operation identity.');
        $assert($handoff->recoveryIdentity() === $oldRecovery && $handoff->recoveryState() === RecoveryLifecycleState::RESTORED, 'N3 handoff recovery binding is wrong.');
        $assert($handoff->retryAttemptIdentity() !== null && str_starts_with($handoff->retryAttemptIdentity(), 'retry-'), 'N3 retry attempt identity is missing.');
        echo 'N3 evidence: operation=' . $operation->operationId() . '; recovery=' . $oldRecovery . '; retry_attempt=' . $handoff->retryAttemptIdentity() . '; handoff_state=' . $handoff->recoveryState() . PHP_EOL;
        $assertPreState($fixture, $assert, 'N3 restored handoff state');
        [$retryExit, $retryCheckpoint] = $runWorker($fixture, 'retry', $handoff->operationId(), null, true);
        $assert(!$retryCheckpoint && $retryExit === 0, 'N3 final continuation failed.');
        $assertFinalState($fixture, $assert, 'N3');
        $all = [];
        $storageRoot = $found[0];
        foreach (glob($storageRoot->path() . DIRECTORY_SEPARATOR . 'recovery-sets' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'state' . DIRECTORY_SEPARATOR . 'recovery-lifecycle.json') ?: [] as $statePath) {
            $data = json_decode((string) file_get_contents($statePath), true);
            if (is_array($data) && ($data['operation_identity'] ?? null) === $operation->operationId()) $all[] = $data['recovery_identity'] ?? null;
        }
        $assert(count(array_unique(array_filter($all))) >= 2, 'N3 did not preserve old and retry recovery identities.');
    } finally {
        $remove($fixture['root']);
        $remove($fixture['recoveryRoot']);
        $remove($fixture['stagingRoot']);
    }
});

if (in_array($scenarioFilter, ['all', 'N1'], true)) $runScenario('N1', static function () use ($makeFixture, $runWorker, $serviceFor, $assert, $assertPreState, $assertFinalState, $remove, $physical, $workerCode): void {
    $fixture = $makeFixture();
    $process = null;
    try {
        $worker = $fixture['root'] . DIRECTORY_SEPARATOR . 'n1-worker.php';
        $configPath = $fixture['root'] . DIRECTORY_SEPARATOR . 'n1-worker.json';
        $barrierId = 'n1-barrier-' . bin2hex(random_bytes(8));
        $barrierAck = $fixture['root'] . DIRECTORY_SEPARATOR . 'n1-barrier-ack.json';
        $config = [
            'base' => dirname(__DIR__),
            'appRoot' => $fixture['appRoot'],
            'publicRoot' => $fixture['publicRoot'],
            'storage' => $fixture['storage'],
            'recoveryRoot' => $fixture['recoveryRoot'],
            'stagingRoot' => $fixture['stagingRoot'],
            'zip' => $fixture['zip'],
            'trace' => $fixture['root'] . DIRECTORY_SEPARATOR . 'trace.jsonl',
            'confirmation' => $fixture['confirmation'],
            'mode' => 'n1',
            'operationId' => $fixture['plan']->operationIdentity(),
            'barrierId' => $barrierId,
            'barrierAck' => $barrierAck,
            'requireExisting' => false,
        ];
        file_put_contents($worker, $workerCode);
        file_put_contents($configPath, json_encode($config, JSON_THROW_ON_ERROR));
        $process = proc_open([PHP_BINARY, $worker, $configPath], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $fixture['root']);
        if (!is_resource($process)) throw new RuntimeException('N1 worker could not start.');
        $deadline = microtime(true) + 20;
        $status = proc_get_status($process);
        while (microtime(true) < $deadline) {
            $status = proc_get_status($process);
            if (is_file($barrierAck)) break;
            if (!$status['running']) break;
            usleep(1000);
        }
        $trigger = is_file($barrierAck) ? json_decode((string) file_get_contents($barrierAck), true) : null;
        $assert(is_array($trigger), 'N1 BLOCKED — REMOVAL BARRIER ACK TIMEOUT: worker did not publish the current-attempt acknowledgment.');
        $assert(($trigger['barrier_id'] ?? null) === $barrierId, 'N1 barrier acknowledgment belongs to another attempt.');
        $assert(($trigger['operation_id'] ?? null) === $fixture['plan']->operationIdentity(), 'N1 barrier operation identity is wrong.');
        $assert(($trigger['kind'] ?? null) === 'remove' && ($trigger['path'] ?? null) === 'app/remove.php' && ($trigger['removed'] ?? null) === 1, 'N1 barrier action identity is wrong.');
        $assert(($trigger['file_cursor'] ?? null) === 9 && ($trigger['last_verified_path'] ?? null) === 'app/remove.php', 'N1 barrier persisted progress is wrong.');
        $assert(($trigger['first_absent'] ?? false) === true && ($trigger['second_exists'] ?? false) === true, 'N1 barrier filesystem proof is wrong.');
        $operation = (new LifecycleOperationStore($fixture['storage']))->read();
        $assert($operation instanceof LifecycleOperationRecord && $operation->operationId() === $fixture['plan']->operationIdentity(), 'N1 barrier operation record is unavailable.');
        $assert(($trigger['recovery_identity'] ?? null) === $operation->recoveryIdentity() && $operation->recoveryState() === RecoveryLifecycleState::READY, 'N1 barrier recovery binding/state is wrong.');
        $assert(isset($status['pid']) && ($trigger['worker_pid'] ?? null) === $status['pid'], 'N1 barrier acknowledgment is not from the current worker.');
        $assert(!is_file($physical($fixture, CanonicalTargetInventoryEntry::APP_ROOT, 'app/remove.php')), 'N1 first removal is not absent at barrier.');
        $assert(is_file($physical($fixture, CanonicalTargetInventoryEntry::APP_ROOT, 'app/rename-old.php')), 'N1 second removal already completed before barrier acknowledgment.');
        proc_terminate($process);
        $stdout = is_resource($pipes[1] ?? null) ? stream_get_contents($pipes[1]) : '';
        $stderr = is_resource($pipes[2] ?? null) ? stream_get_contents($pipes[2]) : '';
        foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        $exitCode = proc_close($process);
        $assert($operation instanceof LifecycleOperationRecord && $operation->fileCursor() === 9 && $operation->lastVerifiedPath() === 'app/remove.php', 'N1 persisted interrupted state changed after kill.');
        $assert($exitCode !== 0, 'N1 worker was not externally interrupted.');
        $assert(is_file($physical($fixture, CanonicalTargetInventoryEntry::PUBLIC_ROOT, 'assets/add.js')), 'N1 delivery state was not present.');
        echo 'N1 evidence: barrier=' . $barrierId . '; operation=' . $operation->operationId() . '; recovery=' . $operation->recoveryIdentity() . '; cursor=' . $operation->fileCursor() . '; last=' . $operation->lastVerifiedPath() . '; worker_exit=' . $exitCode . PHP_EOL;
        $oldRecovery = $operation->recoveryIdentity();
        [$handoffExit, $handoffCheckpoint] = $runWorker($fixture, 'handoff', $operation->operationId(), null, true);
        $assert($handoffCheckpoint && $handoffExit === 137, 'N1 restore handoff did not reach controlled checkpoint.');
        $handoff = (new LifecycleOperationStore($fixture['storage']))->read();
        $assert($handoff instanceof LifecycleOperationRecord && $handoff->operationId() === $fixture['plan']->operationIdentity(), 'N1 handoff operation identity changed.');
        $assert($handoff->recoveryIdentity() === $oldRecovery && $handoff->recoveryState() === RecoveryLifecycleState::RESTORED, 'N1 recovery identity/state is wrong after restore.');
        $assertPreState($fixture, $assert, 'N1 full-operation restore');
        [$retryExit, $retryCheckpoint] = $runWorker($fixture, 'retry', $handoff->operationId(), null, true);
        $assert(!$retryCheckpoint && $retryExit === 0, 'N1 continuation after restore failed.');
        $assertFinalState($fixture, $assert, 'N1');
    } finally {
        if (is_resource($process)) @proc_terminate($process);
        $remove($fixture['root']);
        $remove($fixture['recoveryRoot']);
        $remove($fixture['stagingRoot']);
    }
});
}

echo 'Scenario summary: ' . json_encode($scenarioStatus, JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo 'Total assertions: ' . $assertions . PHP_EOL;
if (in_array(false, array_map(static fn (string $status): bool => $status === 'PASS', $scenarioStatus), true)) {
    exit(1);
}
