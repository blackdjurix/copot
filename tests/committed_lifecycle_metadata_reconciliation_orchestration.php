<?php

declare(strict_types=1);

use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\BackupRecovery\InstallationScopedMaintenanceGuard;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\CommittedLifecycleMetadataReconciliationOrchestrator;
use Copot\Core\DatabaseTableOwnershipCatalog;
use Copot\Core\InstallationIdentity;
use Copot\Core\InstallationMutex;
use Copot\Core\LifecycleOperationStore;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-metadata-orchestration-' . bin2hex(random_bytes(6));
mkdir($root . DIRECTORY_SEPARATOR . 'storage', 0700, true);
try {
    $storage = $root . DIRECTORY_SEPARATOR . 'storage';
    $installation = InstallationIdentity::generate();
    $scope = DatabaseScope::fromOwnershipCatalog($installation->value(), 'fixture_db', '', DatabaseTableOwnershipCatalog::current());
    $guard = new InstallationScopedMaintenanceGuard(new InstallationMutex($storage), $storage);
    $recoveryPlan = CommittedLifecycleMetadataReconciliationOrchestrator::recoveryPlanIdentity(
        CommittedLifecycleMetadataReconciliationOrchestrator::operationIdentity(
            $installation->value(), hash('sha256', 'committed'), hash('sha256', 'marker'), $scope->identity(), 'schema', 'migration'
        ),
        $scope->identity(), hash('sha256', 'committed'), hash('sha256', 'marker')
    );
    $operation = CommittedLifecycleMetadataReconciliationOrchestrator::operationIdentity(
        $installation->value(), hash('sha256', 'committed'), hash('sha256', 'marker'), $scope->identity(), 'schema', 'migration'
    );
    $recovery = new RecoveryIdentity('metadata-recovery-' . substr(hash('sha256', $operation . $recoveryPlan), 0, 16));
    $application = $guard->produceEvidence($recovery, $scope);
    $assert($application->isValidAt(), 'Fresh application protection evidence was not valid.');
    $assert($application->operationIdentity()->equals($recovery), 'Application evidence was not recovery-bound.');
    $assert($application->scope()->identity() === $scope->identity(), 'Application evidence scope drifted.');
    $assert($application->coveredWriterClasses() === ['background', 'cli', 'http', 'scheduled'], 'Writer coverage was incomplete.');
    $assert($operation === CommittedLifecycleMetadataReconciliationOrchestrator::operationIdentity(
        $installation->value(), hash('sha256', 'committed'), hash('sha256', 'marker'), $scope->identity(), 'schema', 'migration'
    ), 'Operation identity was not deterministic.');
    $assert($recoveryPlan === CommittedLifecycleMetadataReconciliationOrchestrator::recoveryPlanIdentity(
        $operation, $scope->identity(), hash('sha256', 'committed'), hash('sha256', 'marker')
    ), 'Recovery preparation identity was not deterministic.');
    $assert((new LifecycleOperationStore($storage))->read() === null, 'Evidence preparation created an unintended lifecycle journal.');
    $assert($guard->enterScoped($recovery, $application), 'Guard could not establish scoped protection.');
    try {
        try { $guard->produceEvidence($recovery, $scope); throw new RuntimeException('Active guard accepted a second evidence preparation.'); }
        catch (RuntimeException $exception) { $assert(str_contains($exception->getMessage(), 'not quiescent'), 'Active guard rejection was not fail-closed.'); }
    } finally { $guard->leave($recovery); }
    echo "committed_lifecycle_metadata_reconciliation_orchestration: {$assertions} assertions passed\n";
} finally {
    $remove = static function (string $path) use (&$remove): void {
        if (is_dir($path) && !is_link($path)) { foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry); @rmdir($path); }
        elseif (is_file($path) || is_link($path)) @unlink($path);
    };
    $remove($root);
}
