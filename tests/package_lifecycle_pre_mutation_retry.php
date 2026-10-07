<?php
declare(strict_types=1);

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

use Copot\Core\{LifecycleOperationRecord, LifecycleOperationStore, MaintenanceCoordinator, PackageLifecycleService};
use Copot\Core\BackupRecovery\RecoveryLifecycleState;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $assertions++;
};

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-pre-mutation-retry-' . bin2hex(random_bytes(4));
$staging = $tmp . DIRECTORY_SEPARATOR . 'staging';
mkdir($staging, 0700, true);
mkdir($tmp . DIRECTORY_SEPARATOR . 'storage', 0700, true);
file_put_contents($staging . DIRECTORY_SEPARATOR . 'source.zip', 'retained-package');

$now = gmdate(DATE_ATOM);
$operationId = 'pre-mutation-retry';
$make = static function (
    string $phase = LifecycleOperationRecord::BLOCKED,
    int $cursor = 0,
    ?string $lastPath = null,
    ?string $migrationOutcome = null,
    ?string $recoveryIdentity = null,
    ?string $recoveryManifest = null,
    ?string $recoveryState = null
) use ($operationId, $staging, $now): LifecycleOperationRecord {
    return new LifecycleOperationRecord(
        $operationId,
        'database_update',
        '0.13.0',
        'copot-v0.13.0',
        hash('sha256', 'archive'),
        $staging,
        hash('sha256', 'payload'),
        hash('sha256', 'apply-plan'),
        $phase,
        $cursor,
        $lastPath,
        hash('sha256', 'migration-plan'),
        $migrationOutcome,
        $now,
        $now,
        'Database quiescence is unavailable.',
        $recoveryIdentity,
        $recoveryManifest,
        $recoveryState
    );
};

$operations = new LifecycleOperationStore($tmp . DIRECTORY_SEPARATOR . 'storage');
$operations->create($make());
$service = (new ReflectionClass(PackageLifecycleService::class))->newInstanceWithoutConstructor();
$maintenance = new MaintenanceCoordinator($operations);
$set = static function (string $property, mixed $value) use ($service): void {
    $reflection = new ReflectionProperty($service, $property);
    $reflection->setAccessible(true);
    $reflection->setValue($service, $value);
};
$set('maintenance', $maintenance);
$eligible = new ReflectionMethod($service, 'preMutationRetryEligible');
$eligible->setAccessible(true);

$assert($eligible->invoke($service, $operationId) === true, 'Matching pre-mutation operation was not eligible for recovery bootstrap.');
$assert($eligible->invoke($service, 'different-operation') === false, 'Operation identity mismatch was accepted.');

$operations->save($make(LifecycleOperationRecord::BLOCKED, 1));
$assert($eligible->invoke($service, $operationId) === false, 'Non-zero cursor was accepted for pre-mutation bootstrap.');

$operations->save($make(LifecycleOperationRecord::BLOCKED, 0, 'app/example.php'));
$assert($eligible->invoke($service, $operationId) === false, 'Last verified path was accepted for pre-mutation bootstrap.');

$operations->save($make(LifecycleOperationRecord::BLOCKED, 0, null, 'completed'));
$assert($eligible->invoke($service, $operationId) === false, 'Migration outcome was accepted for pre-mutation bootstrap.');

$operations->save($make(LifecycleOperationRecord::BLOCKED, 0, null, null, 'recovery-id', hash('sha256', 'manifest'), RecoveryLifecycleState::READY));
$assert($eligible->invoke($service, $operationId) === false, 'Existing recovery evidence was accepted by the bootstrap branch.');

$serviceSource = file_get_contents($base . '/app/Core/PackageLifecycleService.php');
$assert(is_string($serviceSource) && strpos($serviceSource, '$this->applyCoordinator->execute($applyPlan, $transition, $migration, $record)') !== false, 'Retry does not preserve the existing operation when invoking the coordinator.');
$assert(is_string($serviceSource) && strpos($serviceSource, 'preMutationRetryEligible') !== false, 'Pre-mutation retry eligibility boundary is missing.');

echo "package_lifecycle_pre_mutation_retry: {$assertions} assertions passed" . PHP_EOL;

try {
    if (is_dir($tmp)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($tmp);
    }
} catch (Throwable) {
}
