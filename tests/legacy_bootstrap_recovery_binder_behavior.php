<?php

$base = dirname(__DIR__);
require $base . '/bootstrap/autoload.php';

use Copot\Core\BackupRecovery\ApplicationWriteProtectionEvidence;
use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureRequest;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\ScopedDatabaseConsistencyEvidence;
use Copot\Core\LegacyBootstrapIdentityHandoff;
use Copot\Core\LegacyBootstrapRecoveryBinder;
use Copot\Core\LegacyBootstrapRecoveryBindingResult;
use Copot\Core\LegacyBootstrapTargetFreshnessResult;
use Copot\Core\LifecycleOperationRecord;
use Copot\Core\LifecycleOperationStore;
use Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureService;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$empty = static fn (string $class): object => (new ReflectionClass($class))->newInstanceWithoutConstructor();

$source = file_get_contents($base . '/app/Core/LegacyBootstrapRecoveryBinder.php');
$assert(is_string($source), 'Bootstrap recovery binder source could not be read.');
$assert(str_contains($source, 'NormalWebcoreRecoveryCaptureService'), 'Binder no longer uses canonical recovery capture.');
$assert(str_contains($source, 'readManifest'), 'Binder no longer rereads recovery manifest.');
$assert(str_contains($source, 'readArtifact'), 'Binder no longer rereads action-linkage artifact.');
$assert(str_contains($source, 'bindRecovery'), 'Binder no longer binds lifecycle recovery evidence.');
$assert(str_contains($source, '->resume('), 'Binder no longer supports canonical recovery resume.');
$assert(!str_contains($source, 'authorizeMutation'), 'Binder must not authorize mutation.');

foreach ([
    LegacyBootstrapRecoveryBindingResult::BOUND,
    LegacyBootstrapRecoveryBindingResult::INCOMPLETE,
    LegacyBootstrapRecoveryBindingResult::STALE,
    LegacyBootstrapRecoveryBindingResult::UNVERIFIED,
] as $status) {
    $result = new LegacyBootstrapRecoveryBindingResult(
        $status,
        'handoff',
        'operation',
        hash('sha256', 'recovery'),
        hash('sha256', 'manifest'),
        $status === LegacyBootstrapRecoveryBindingResult::BOUND ? hash('sha256', 'cross-reference') : null,
        null,
        $status === LegacyBootstrapRecoveryBindingResult::BOUND ? [] : ['diagnostic']
    );
    $assert($result->mutationAuthorized() === false && $result->operationalReady() === false, "{$status} binding granted prohibited authority.");
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-r2-binder-' . bin2hex(random_bytes(6));
mkdir($root . DIRECTORY_SEPARATOR . 'storage', 0700, true);
$operations = new LifecycleOperationStore($root . DIRECTORY_SEPARATOR . 'storage');
$operation = new LifecycleOperationRecord(
    'retired-binder-operation',
    'LEGACY_TO_V3_BOOTSTRAP',
    '0.13.0',
    'retired-release',
    hash('sha256', 'archive'),
    $root . DIRECTORY_SEPARATOR . 'staging',
    hash('sha256', 'payload'),
    hash('sha256', 'plan'),
    LifecycleOperationRecord::PREPARING,
    0,
    null,
    hash('sha256', 'migration'),
    null,
    gmdate(DATE_ATOM),
    gmdate(DATE_ATOM)
);
$operations->create($operation);
$before = $operations->read()?->toArray();
$binder = $empty(LegacyBootstrapRecoveryBinder::class);
try {
    $binder->bind(
        $empty(LegacyBootstrapIdentityHandoff::class),
        $empty(NormalWebcoreRecoveryCaptureRequest::class),
        $empty(LegacyBootstrapTargetFreshnessResult::class),
        $empty(DatabaseScope::class),
        $empty(ApplicationWriteProtectionEvidence::class),
        $empty(ScopedDatabaseConsistencyEvidence::class),
        'retired-binder-test'
    );
} catch (Throwable) {
    // Invalid/pre-retirement handoff is intentionally rejected before any
    // canonical capture or operation binding can be attempted.
}
$assert($operations->read()?->toArray() === $before, 'Rejected recovery-binding input changed operation state.');
$assert(!(new ReflectionClass(NormalWebcoreRecoveryCaptureService::class))->hasMethod('authorizeMutation'), 'Recovery capture service unexpectedly owns mutation authorization.');

echo "Bootstrap recovery binder retirement assertions: {$assertions}" . PHP_EOL;
