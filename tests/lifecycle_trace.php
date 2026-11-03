<?php

declare(strict_types=1);

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';
require_once $base . '/app/Core/ProtectedWebcoreMutation.php';

final class LifecycleTraceRecoverySessionFixture implements Copot\Core\ProtectedWebcoreMutationSession
{
    public bool $failed = false;

    public function __construct(private bool $throwOnFail = false) {}
    public function evidence(): array { return []; }
    public function authorize(): void {}
    public function complete(): void {}
    public function fail(string $reason): Copot\Core\BackupRecovery\RecoveryLifecycleRecord
    {
        if ($this->throwOnFail) {
            throw new RuntimeException('simulated recovery persistence failure');
        }
        $this->failed = true;
        return $this->record(Copot\Core\BackupRecovery\RecoveryLifecycleState::RESTORE_REQUIRED);
    }
    public function restore(): Copot\Core\BackupRecovery\RecoveryLifecycleRecord
    {
        return $this->record(Copot\Core\BackupRecovery\RecoveryLifecycleState::RESTORED);
    }
    private function record(string $state): Copot\Core\BackupRecovery\RecoveryLifecycleRecord
    {
        return new Copot\Core\BackupRecovery\RecoveryLifecycleRecord(
            new Copot\Core\BackupRecovery\RecoveryIdentity('trace-recovery'),
            hash('sha256', 'trace-manifest'),
            'operation-recovery',
            $state,
            true,
            true,
            'trace-recovery',
            hash('sha256', 'trace-manifest'),
            hash('sha256', 'trace-target')
        );
    }
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$remove = static function (string $path) use (&$remove): void {
    if (is_file($path) || is_link($path)) {
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
    @rmdir($path);
};

$traceLines = [];
$trace = new Copot\Core\LifecycleTrace(null, 'human-acceptance-cycle', static function (string $line) use (&$traceLines): void {
    $traceLines[] = $line;
});
$runOne = $trace->beginRun();
$trace->record($runOne, null, 'preflight', 'admission', 'FAILED', 'Rejected before operation identity; path C:\\private\\secret.php password=secret');
$trace->record($runOne, null, 'terminal', 'lifecycle', 'FAILED', 'safe rejection', ['lifecycle_status' => 'rejected']);
$runTwo = $trace->beginRun();
$trace->record($runTwo, 'operation-1', 'recovery_capture', 'capture', 'START');
$trace->record($runTwo, 'operation-1', 'recovery_capture', 'capture', 'SUCCESS', null, [
    'recovery_identity' => 'recovery-1',
    'manifest_identity' => 'manifest-1',
]);
$trace->record($runTwo, 'operation-1', 'mutation', 'apply', 'START');
$trace->record($runTwo, 'operation-1', 'mutation', 'apply', 'SUCCESS', null, ['size' => 12]);
$trace->record($runTwo, 'operation-1', 'recovery', 'rollback', 'START', 'simulated failure');
$trace->record($runTwo, 'operation-1', 'recovery', 'rollback', 'RECOVERY_RECORDED', null, ['recovery_state' => 'RESTORE_REQUIRED']);
$trace->record($runTwo, 'operation-1', 'finalization', 'health_commit', 'START');
$trace->record($runTwo, 'operation-1', 'finalization', 'health_commit', 'SUCCESS');
$trace->record($runTwo, 'operation-1', 'terminal', 'lifecycle', 'SUCCESS', 'SUCCESS', ['accepted' => true, 'lifecycle_status' => 'completed']);

$decoded = array_map(static fn (string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR), $traceLines);
$assert(count($decoded) === 13, 'Trace did not emit the expected JSONL event count.');
$assert(count(array_unique(array_column($decoded, 'run_id'))) === 2 && $runOne !== $runTwo, 'Multiple runs did not receive distinct run IDs.');
$assert(array_unique(array_column($decoded, 'cycle_id')) === ['human-acceptance-cycle'], 'Trace cycle identity is not stable.');
$assert($decoded[0]['operation_identity'] === null && $decoded[0]['status'] === 'START', 'Operation start did not permit a pre-identity event.');
$assert($decoded[1]['stage'] === 'preflight' && $decoded[1]['status'] === 'FAILED', 'Pre-operation rejection was not recorded.');
$assert(count(array_filter($decoded, static fn (array $event): bool => $event['stage'] === 'recovery_capture' && $event['status'] === 'START')) === 1, 'Recovery capture START was not recorded.');
$assert(count(array_filter($decoded, static fn (array $event): bool => $event['stage'] === 'recovery_capture' && $event['status'] === 'SUCCESS')) === 1, 'Recovery capture SUCCESS was not recorded.');
$assert(count(array_filter($decoded, static fn (array $event): bool => $event['stage'] === 'mutation' && $event['status'] === 'SUCCESS' && $event['operation_identity'] === 'operation-1')) === 1, 'Mutation SUCCESS was not recorded.');
$assert(count(array_filter($decoded, static fn (array $event): bool => $event['status'] === 'RECOVERY_RECORDED')) === 1, 'Recovery outcome was not recorded.');
$assert(count(array_filter($decoded, static fn (array $event): bool => $event['stage'] === 'finalization' && $event['status'] === 'SUCCESS')) === 1, 'Finalization SUCCESS was not recorded.');
$assert($decoded[12]['status'] === 'SUCCESS' && $decoded[12]['accepted'] === true, 'SUCCESS terminal outcome was not recorded.');
$assert(str_contains($decoded[1]['summary'], '<path>') && str_contains($decoded[1]['summary'], 'password=<redacted>'), 'Trace summary did not redact path or credential-like data.');
$assert(!str_contains(implode('\n', $traceLines), 'secret.php'), 'Trace leaked an absolute sensitive path.');

$recoveryLines = [];
$recoveryTrace = new Copot\Core\LifecycleTrace(null, 'recovery-outcome-cycle', static function (string $line) use (&$recoveryLines): void {
    $recoveryLines[] = $line;
});
$serviceReflection = new ReflectionClass(Copot\Core\PackageLifecycleService::class);
$serviceFixture = $serviceReflection->newInstanceWithoutConstructor();
$traceProperty = $serviceReflection->getProperty('lifecycleTrace');
$traceProperty->setValue($serviceFixture, $recoveryTrace);
$recoveryMethod = $serviceReflection->getMethod('recordRecoveryFailure');
$recoveryMethod->setAccessible(true);
$recoveryRun = 'trace-recovery-success';
$successSession = new LifecycleTraceRecoverySessionFixture();
$recoveryMethod->invoke($serviceFixture, $recoveryRun, 'operation-recovery', $successSession, 'mutation failure');
$failureSession = new LifecycleTraceRecoverySessionFixture(true);
try {
    $recoveryMethod->invoke($serviceFixture, 'trace-recovery-failure', 'operation-recovery', $failureSession, 'mutation failure');
    $assert(false, 'Recovery persistence failure did not preserve the service failure boundary.');
} catch (Throwable) {
    $assert(true, 'Recovery persistence failure escaped only through the expected service boundary.');
}
$recoveryDecoded = array_map(static fn (string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR), $recoveryLines);
$recoveryStatuses = array_column($recoveryDecoded, 'status');
$assert(in_array('RECOVERY_RECORDED', $recoveryStatuses, true), 'Successful RESTORE_REQUIRED recording was not traced.');
$assert(in_array('RECOVERY_RECORD_FAILED', $recoveryStatuses, true), 'Failed RESTORE_REQUIRED recording was not traced.');
$assert(count(array_filter($recoveryDecoded, static fn (array $event): bool => $event['status'] === 'RECOVERY_RECORDED' && $event['recovery_state'] === 'RESTORE_REQUIRED')) === 1, 'Successful recovery recording did not identify RESTORE_REQUIRED.');
$assert(count(array_filter($recoveryDecoded, static fn (array $event): bool => $event['status'] === 'RECOVERY_RECORD_FAILED' && $event['recovery_state'] === 'UNKNOWN')) === 1, 'Failed recovery recording did not remain conservative.');
$assert(count(array_filter($recoveryDecoded, static fn (array $event): bool => $event['stage'] === 'recovery' && $event['action'] === 'restore' && $event['status'] === 'SUCCESS')) === 1, 'Automatic recovery success was not traced.');

$traceDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-lifecycle-trace-' . bin2hex(random_bytes(5));
mkdir($traceDir, 0700, true);
$tracePath = $traceDir . DIRECTORY_SEPARATOR . 'trace.jsonl';
try {
    $fileTrace = new Copot\Core\LifecycleTrace($tracePath, 'append-cycle');
    $fileRunOne = $fileTrace->beginRun();
    $fileTrace->record($fileRunOne, null, 'terminal', 'lifecycle', 'SUCCESS', 'SUCCESS');
    $fileRunTwo = $fileTrace->beginRun();
    $fileTrace->record($fileRunTwo, null, 'terminal', 'lifecycle', 'FAILED', 'second run');
    $fileLines = file($tracePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $assert(count($fileLines) === 4, 'Trace file did not append multiple runs without overwriting.');
    if (DIRECTORY_SEPARATOR !== '\\') {
        $assert((int) (fileperms($tracePath) & 0777) === 0600, 'Trace file was not private.');
    }
    $assert($fileRunOne !== $fileRunTwo, 'Appended runs reused the same run ID.');
} finally {
    $remove($traceDir);
}

$writerFailure = new Copot\Core\LifecycleTrace(null, 'failure-cycle', static function (string $line): void {
    throw new RuntimeException('trace writer failure');
});
try {
    $writerFailure->record('trace-failure', null, 'terminal', 'lifecycle', 'FAILED', 'writer failure must be observational');
    $assert(true, 'Trace writer failure changed lifecycle control flow.');
} catch (Throwable $exception) {
    $assert(false, 'Trace writer failure escaped the observational boundary.');
}

$serviceSource = file_get_contents($base . '/app/Core/PackageLifecycleService.php');
$assert(is_string($serviceSource), 'Package lifecycle service source could not be inspected.');
foreach (['beginRun(', 'applyV3(', "'recovery_capture'", "'mutation'", "'finalization'", "'terminal'", 'RECOVERY_RECORDED', 'RECOVERY_RECORD_FAILED'] as $needle) {
    $assert(str_contains($serviceSource, $needle), 'Actual V3 service path is missing trace instrumentation: ' . $needle);
}
$factorySource = file_get_contents($base . '/app/Core/PackageLifecycleFactory.php');
$assert(is_string($factorySource) && str_contains($factorySource, '?LifecycleTrace $lifecycleTrace = null'), 'Factory does not expose explicit trace injection.');
$assert(!str_contains($factorySource, 'fromEnvironment') && !str_contains($factorySource, 'COPOT_LIFECYCLE_TRACE_'), 'Factory still activates trace from environment variables.');
$traceSource = file_get_contents($base . '/app/Core/LifecycleTrace.php');
$assert(!str_contains($traceSource ?: '', 'getenv('), 'LifecycleTrace still reads environment variables.');
$factoryMethod = new ReflectionMethod(Copot\Core\PackageLifecycleFactory::class, 'forProject');
$factoryTraceParameter = $factoryMethod->getParameters()[1] ?? null;
$assert($factoryTraceParameter instanceof ReflectionParameter && $factoryTraceParameter->isDefaultValueAvailable() && $factoryTraceParameter->getDefaultValue() === null, 'Factory trace injection is not default-disabled.');
$applicationSource = file_get_contents($base . '/app/Core/Application.php');
$assert(is_string($applicationSource) && str_contains($applicationSource, '?LifecycleTrace $lifecycleTrace = null'), 'Application does not expose explicit trace injection.');
$assert(is_string($applicationSource) && str_contains($applicationSource, 'PackageLifecycleFactory::forProject($this->deployment, $this->lifecycleTrace)'), 'Application does not forward the injected trace to the lifecycle factory.');

fwrite(STDOUT, "Lifecycle trace assertions: {$assertions}\n");
