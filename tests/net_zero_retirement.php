<?php

declare(strict_types=1);

use Copot\Core\LifecycleOperationRecord;
use Copot\Core\LifecycleOperationStore;
use Copot\Core\MaintenanceCoordinator;
use Copot\Core\NetZeroRetirementContext;
use Copot\Core\NetZeroRetirementEvidenceStore;
use Copot\Core\NetZeroRetirementService;
use Copot\Core\NetZeroRetirementVerifier;
use Copot\Core\PackageLifecycleService;
use Copot\Core\StagedFile;
use Copot\Core\StagedPayload;
use Copot\Core\StagingSession;
use Copot\Core\WebcoreApplyPlan;
use Copot\Core\InstallationMutex;

require dirname(__DIR__) . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    if (!$condition) throw new RuntimeException($message);
    $assertions++;
};
$state = [
    'migration_ledger' => hash('sha256', 'ledger-before'),
    'schema' => hash('sha256', 'schema-before'),
    'capability' => hash('sha256', 'capability-before'),
    'committed_state' => hash('sha256', 'committed-before'),
    'persistent_state' => hash('sha256', 'persistent-before'),
    'operator_state' => hash('sha256', 'operator-before'),
];
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-net-zero-' . bin2hex(random_bytes(6));
$runtime = $root . DIRECTORY_SEPARATOR . 'runtime';
$stagingRoot = $root . DIRECTORY_SEPARATOR . 'staging';
$accepted = $root . DIRECTORY_SEPARATOR . 'accepted';
mkdir($runtime . DIRECTORY_SEPARATOR . 'storage', 0700, true);
mkdir($accepted, 0700, true);
$session = StagingSession::create($runtime, $stagingRoot);
$path = 'app/Core/Example.php';
$bytes = "<?php return 'net-zero';\n";
mkdir($session->payloadPath() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core', 0700, true);
mkdir($runtime . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core', 0700, true);
mkdir($accepted . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core', 0700, true);
file_put_contents($session->payloadPath() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path), $bytes);
file_put_contents($runtime . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path), $bytes);
file_put_contents($accepted . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path), $bytes);
$archive = 'retained archive';
file_put_contents($session->archivePath(), $archive);
$file = new StagedFile($path, strlen($bytes), hash('sha256', $bytes));
$payload = new StagedPayload($session, hash('sha256', $archive), [$file]);
$plan = WebcoreApplyPlan::fromPayload($payload);
$payloadIdentity = hash('sha256', $path . ':' . $file->sha256());
$now = gmdate(DATE_ATOM);
$operation = new LifecycleOperationRecord(
    'operation-net-zero', 'database_update', '0.13.0', 'release', $payload->archiveSha256(),
    $session->path(), $payloadIdentity, $plan->identity(), LifecycleOperationRecord::BLOCKED,
    1, $path, hash('sha256', 'migration-plan'), null, $now, $now
);
$makeOperation = static function (array $overrides = []) use ($operation, $payload, $plan, $payloadIdentity, $path, $now): LifecycleOperationRecord {
    $values = [
        'id' => $operation->operationId(), 'archive' => $operation->archiveSha256(), 'payload' => $payloadIdentity,
        'apply' => $plan->identity(), 'phase' => LifecycleOperationRecord::BLOCKED, 'cursor' => 1,
        'last' => $path, 'migration' => $operation->migrationPlanIdentity(), 'outcome' => null,
        'recovery' => null,
    ];
    $values = array_replace($values, $overrides);
    $record = new LifecycleOperationRecord($values['id'], 'database_update', '0.13.0', 'release', $values['archive'], $payload->stagingPath(), $values['payload'], $values['apply'], $values['phase'], $values['cursor'], $values['last'], $values['migration'], $values['outcome'], $now, $now);
    if (is_array($values['recovery'])) $record = $record->bindRecovery(...$values['recovery']);
    return $record;
};
$expectRejected = static function (callable $attempt, string $label) use (&$assert): void {
    $rejected = false;
    try { $attempt(); } catch (RuntimeException) { $rejected = true; }
    $assert($rejected, $label . ' was accepted.');
};

try {
    $context = new NetZeroRetirementContext(
        $operation->operationId(), $runtime, $accepted, hash('sha256', 'accepted-main'), $state,
        static fn (): array => $state, $operation->migrationPlanIdentity()
    );
    $verification = (new NetZeroRetirementVerifier())->verify($operation, $plan, $context);
    $assert(strlen($verification->identity()) === 64, 'Valid net-zero verification did not produce a forensic identity.');

    $store = new LifecycleOperationStore($runtime . DIRECTORY_SEPARATOR . 'storage');
    $maintenance = new MaintenanceCoordinator($store);
    $maintenance->enter($operation);
    $evidence = new NetZeroRetirementEvidenceStore($runtime . DIRECTORY_SEPARATOR . 'storage');
    $service = new NetZeroRetirementService($maintenance, new InstallationMutex($runtime . DIRECTORY_SEPARATOR . 'storage'), new NetZeroRetirementVerifier(), $evidence);
    $result = $service->retire($operation->operationId(), $plan, $context);
    $assert($result->status() === LifecycleOperationRecord::RETIRED_NET_ZERO, 'Retirement did not return the distinct terminal disposition.');
    $assert(!$result->accepted(), 'Net-zero retirement was incorrectly reported as successful completion.');
    $assert($maintenance->record() === null, 'Active operation was not cleared after evidence publication.');
    $assert(is_file($evidence->path($operation->operationId())), 'Durable retirement evidence was not written.');

    $assert((new LifecycleOperationRecord(
        'terminal', 'database_update', '0.13.0', 'release', $payload->archiveSha256(), $session->path(),
        $payloadIdentity, $plan->identity(), LifecycleOperationRecord::RETIRED_NET_ZERO, 1, $path,
        $operation->migrationPlanIdentity(), null, $now, $now
    ))->isTerminal(), 'Retired disposition is not terminal.');
    $repeatStore = new LifecycleOperationStore($runtime . DIRECTORY_SEPARATOR . 'storage');
    $repeatStore->create($operation);
    $repeatService = new NetZeroRetirementService(new MaintenanceCoordinator($repeatStore), new InstallationMutex($runtime . DIRECTORY_SEPARATOR . 'storage'), $verifier = new NetZeroRetirementVerifier(), $evidence);
    $repeat = $repeatService->retire($operation->operationId(), $plan, $context);
    $assert(($repeat->status() === LifecycleOperationRecord::RETIRED_NET_ZERO && $repeatStore->read() === null) || ($repeat->status() === 'rejected' && $repeatStore->read() !== null), 'Repeated retirement was neither idempotent nor deterministically rejected.');
    @unlink($runtime . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'active-operation.json');

    $clearStore = new LifecycleOperationStore($runtime . DIRECTORY_SEPARATOR . 'storage');
    $clearStore->create($operation);
    $clearFailureEvidence = new NetZeroRetirementEvidenceStore($runtime . DIRECTORY_SEPARATOR . 'storage');
    @unlink($clearFailureEvidence->path($operation->operationId()));
    $clearFailureService = new NetZeroRetirementService(new MaintenanceCoordinator($clearStore), new InstallationMutex($runtime . DIRECTORY_SEPARATOR . 'storage'), $verifier, $clearFailureEvidence, static function (): void { throw new RuntimeException('injected clear failure'); });
    $clearFailure = $clearFailureService->retire($operation->operationId(), $plan, $context);
    $assert($clearFailure->status() === 'rejected' && is_file($clearFailureEvidence->path($operation->operationId())) && $clearStore->read() !== null, 'Clear failure did not preserve durable evidence and active operation.');
    $clearStore->clear($operation->advance(LifecycleOperationRecord::RETIRED_NET_ZERO, 1, $path));

    $retryService = (new ReflectionClass(PackageLifecycleService::class))->newInstanceWithoutConstructor();
    $set = static function (string $property, mixed $value) use ($retryService): void {
        $reflection = new ReflectionProperty($retryService, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($retryService, $value);
    };
    $retryOperations = new LifecycleOperationStore($runtime . DIRECTORY_SEPARATOR . 'storage');
    $retiredRecord = $makeOperation(['phase' => LifecycleOperationRecord::RETIRED_NET_ZERO]);
    $retryOperations->create($retiredRecord);
    $set('maintenance', new MaintenanceCoordinator($retryOperations));
    $set('recoveryEvidenceValidator', static fn (LifecycleOperationRecord $record): bool => true);
    $assert(!$retryService->retryEvidence($operation->operationId()), 'Retired operation was accepted by recovery retry evidence.');
    $retryResult = $retryService->retry($operation->operationId());
    $assert($retryResult->status() === 'rejected', 'Retired operation was accepted by ordinary retry.');
    $retryOperations->clear($retiredRecord);
    $rejectedContext = new NetZeroRetirementContext(
        'different-operation', $runtime, $accepted, hash('sha256', 'accepted-main'), $state, static fn (): array => $state, $operation->migrationPlanIdentity()
    );
    try { (new NetZeroRetirementVerifier())->verify($operation, $plan, $rejectedContext); $assert(false, 'Operation identity mismatch was accepted.'); } catch (RuntimeException) { $assert(true, 'Operation identity mismatch failed closed.'); }

    $verifier = new NetZeroRetirementVerifier();
    $expectRejected(fn () => $verifier->verify($makeOperation(['archive' => hash('sha256', 'wrong-archive')]), $plan, $context), 'Archive identity mismatch');
    $expectRejected(fn () => $verifier->verify($makeOperation(['payload' => hash('sha256', 'wrong-payload')]), $plan, $context), 'Payload identity mismatch');
    $expectRejected(fn () => $verifier->verify($makeOperation(['apply' => hash('sha256', 'wrong-apply')]), $plan, $context), 'Apply-plan identity mismatch');
    $expectRejected(fn () => $verifier->verify($makeOperation(['migration' => hash('sha256', 'wrong-migration')]), $plan, $context), 'Migration-plan identity mismatch');
    $expectRejected(fn () => $verifier->verify($makeOperation(['cursor' => 0]), $plan, $context), 'Cursor mismatch');
    $expectRejected(fn () => $verifier->verify($makeOperation(['last' => 'app/Core/Other.php']), $plan, $context), 'Last-path mismatch');
    $expectRejected(fn () => $verifier->verify($makeOperation(['last' => 'app/Core/Other.php']), $plan, $context), 'Apply-order mismatch');
    file_put_contents($runtime . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path), 'divergent');
    $expectRejected(fn () => $verifier->verify($operation, $plan, $context), 'Divergent runtime file');
    file_put_contents($runtime . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path), $bytes);
    unlink($runtime . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
    $expectRejected(fn () => $verifier->verify($operation, $plan, $context), 'Missing runtime file');
    file_put_contents($runtime . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path), $bytes);
    file_put_contents($accepted . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path), 'different accepted target');
    $expectRejected(fn () => $verifier->verify($operation, $plan, $context), 'Accepted-target mismatch');
    file_put_contents($accepted . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path), $bytes);
    $expectRejected(fn () => $verifier->verify($makeOperation(['outcome' => 'started']), $plan, $context), 'Migration outcome');
    foreach (array_keys($state) as $key) {
        $changed = $state;
        $changed[$key] = hash('sha256', 'changed-' . $key);
        $changedContext = new NetZeroRetirementContext($operation->operationId(), $runtime, $accepted, hash('sha256', 'accepted-main'), $state, static function () use ($changed): array { return $changed; }, $operation->migrationPlanIdentity());
        $expectRejected(fn () => $verifier->verify($operation, $plan, $changedContext), 'State invariant ' . $key);
    }
    $expectRejected(fn () => $verifier->verify($makeOperation(['recovery' => [hash('sha256', 'recovery'), hash('sha256', 'manifest'), 'ready']]), $plan, $context), 'Retrospective recovery evidence');

    $conflictingEvidence = new NetZeroRetirementEvidenceStore($runtime . DIRECTORY_SEPARATOR . 'storage');
    file_put_contents($conflictingEvidence->path($operation->operationId()), "conflicting\n");
    $activeStore = new LifecycleOperationStore($runtime . DIRECTORY_SEPARATOR . 'storage');
    $activeStore->create($operation);
    try {
        $service = new NetZeroRetirementService(new MaintenanceCoordinator($activeStore), new InstallationMutex($runtime . DIRECTORY_SEPARATOR . 'storage'), $verifier, $conflictingEvidence);
        $failed = $service->retire($operation->operationId(), $plan, $context);
        $assert($failed->status() === 'rejected' && $activeStore->read() !== null, 'Evidence write failure did not preserve active operation.');
    } finally { @unlink($conflictingEvidence->path($operation->operationId())); @unlink($runtime . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'active-operation.json'); }

    echo "net_zero_retirement: {$assertions} assertions passed\n";
} finally {
    $remove = static function (string $path) use (&$remove): void {
        if (is_dir($path) && !is_link($path)) foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) $remove($path . DIRECTORY_SEPARATOR . $entry);
        if (is_file($path) || is_link($path)) @unlink($path); elseif (is_dir($path)) @rmdir($path);
    };
    $remove($root);
}
