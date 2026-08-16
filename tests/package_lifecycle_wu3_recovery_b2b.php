<?php

declare(strict_types=1);

use Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureRequest;
use Copot\Core\NormalWebcoreProtectedMutationBoundary;
use Copot\Core\PackageLifecycleFactory;
use Copot\Core\PackageLifecycleService;
use Copot\Core\PackageContract;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageMigrationDeclaration;
use Copot\Core\PackageOwnership;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\TransitionPlan;
use Copot\Core\CoreMigrationPlan;
use Copot\Core\LifecycleOperationRecord;
use Copot\Core\StagedPayload;
use Copot\Core\StagingSession;
use Copot\Core\WebcoreApplyPlan;
use Copot\Core\WebcoreMutationContext;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$property = static function (object $object, string $name): mixed {
    return (new ReflectionProperty($object, $name))->getValue($object);
};

$boundarySource = file_get_contents($base . '/app/Core/NormalWebcoreProtectedMutationBoundary.php');
$coordinatorSource = file_get_contents($base . '/app/Core/WebcoreApplyCoordinator.php');
$factorySource = file_get_contents($base . '/app/Core/PackageLifecycleFactory.php');
$maintenanceSource = file_get_contents($base . '/app/Core/BackupRecovery/InstallationRecoveryMaintenance.php');
$assert(is_string($boundarySource) && is_string($coordinatorSource) && is_string($factorySource) && is_string($maintenanceSource), 'B2b source fixtures could not be read.');

$assert(str_contains($factorySource, '$normalRecoveryBoundary = $recoveryComposition?->normalProtectedMutationBoundary('), 'Factory does not build the production protected boundary from B2a composition.');
$assert(str_contains($factorySource, 'new RuntimeRegistry($storage, $installationIdentity, $mutex), $normalRecoveryBoundary'), 'Factory does not inject the production protected boundary into WebcoreApplyCoordinator.');
$assert(str_contains($boundarySource, 'new NormalWebcoreRecoveryCaptureRequest('), 'Boundary does not build the normal capture request.');
$assert(str_contains($boundarySource, '$operation->operationId()'), 'Operation identity is not preserved in the recovery request.');
$assert(str_contains($boundarySource, '$context->applyPlan()'), 'Apply-plan identity is not preserved in the recovery request.');
$assert(str_contains($boundarySource, '$this->installationIdentity'), 'Installation identity is not preserved in the recovery request.');
$assert(str_contains($boundarySource, '$this->namespaceIdentity'), 'Namespace identity is not preserved in the recovery request.');
$assert(str_contains($boundarySource, '$this->deploymentIdentity'), 'Deployment identity is not preserved in the recovery request.');
$assert(str_contains($boundarySource, '$session->ready()'), 'Boundary does not prove READY before returning a protected session.');
$assert(str_contains($boundarySource, '$this->session->authorizeMutation()'), 'Protected session does not authorize the live mutation permit.');
$assert(str_contains($boundarySource, '$this->session->completeMutation()'), 'Protected session does not record successful mutation completion.');
$assert(str_contains($boundarySource, '$this->session->failMutation($reason)'), 'Protected session does not record recovery-required mutation failure.');
$assert(!str_contains($boundarySource, '->acquire()'), 'Recovery boundary reacquires the InstallationMutex.');
$assert(str_contains($boundarySource, '$this->maintenance->adopt($lock)'), 'Recovery boundary does not adopt the coordinator-owned lock.');
$assert(str_contains($maintenanceSource, 'public function adopt(InstallationLock $lock)'), 'Maintenance cannot adopt the coordinator-owned lock.');

$operationPosition = strpos($coordinatorSource, '$record = new LifecycleOperationRecord(');
$contextPosition = strpos($coordinatorSource, 'new WebcoreMutationContext($record, $applyPlan, $transition, $migrationPlan, $lock)');
$readyPosition = strpos($coordinatorSource, '$evidence = $session->evidence();');
$bindingPosition = strpos($coordinatorSource, '$record = $record->bindRecovery(');
$permitPosition = strpos($coordinatorSource, '$session->authorize();');
$applyPosition = strpos($coordinatorSource, '$apply = $this->applier->apply(');
$migrationPosition = strpos($coordinatorSource, '$migration = ($this->migrationRunner)(');
$successPosition = strpos($coordinatorSource, '$session?->complete();');
$assert($operationPosition !== false && $contextPosition > $operationPosition, 'Recovery context is not created after the lifecycle operation.');
$assert($readyPosition > $contextPosition && $bindingPosition > $readyPosition && $permitPosition > $bindingPosition, 'READY, binding, and permit ordering is not preserved.');
$assert($applyPosition > $permitPosition && $migrationPosition > $applyPosition, 'Mutation occurs before permit or migration ordering changed.');
$assert($successPosition > $migrationPosition, 'Recovery success is not recorded after Core migration execution.');
$assert(substr_count($coordinatorSource, '$this->mutex->acquire()') === 2, 'WebcoreApplyCoordinator no longer has the sole two existing mutex acquisition sites.');
$assert(substr_count($boundarySource, '$this->session->completeMutation()') === 1 && substr_count($boundarySource, '$this->session->failMutation($reason)') === 1, 'Protected session completion/failure is not single-entry.');
$assert(str_contains($coordinatorSource, 'try { $session?->fail($apply->reason()); } catch (\\Throwable) {}'), 'Post-file-apply failure does not retain recovery evidence.');
$assert(str_contains($coordinatorSource, 'try { $session?->fail($reason); } catch (\\Throwable) {}'), 'Post-migration failure does not retain recovery evidence.');

$savedRoot = getenv('COPOT_RECOVERY_ROOT');
$savedAdmin = getenv('COPOT_MARIADB_ADMIN_USERNAME');
$savedPassword = getenv('COPOT_MARIADB_ADMIN_PASSWORD');
putenv('COPOT_MARIADB_ADMIN_USERNAME');
putenv('COPOT_MARIADB_ADMIN_PASSWORD');
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu3-b2b-' . bin2hex(random_bytes(5));
$stagingRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu3-b2b-stage-' . bin2hex(random_bytes(5));
mkdir($root, 0700, true);
mkdir($stagingRoot, 0700, true);
$identityPath = $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'installation-identity.json';
$identityExisted = is_file($identityPath);

try {
    putenv('COPOT_RECOVERY_ROOT=' . $root);
    $_ENV['COPOT_RECOVERY_ROOT'] = $root;
    $service = PackageLifecycleFactory::forProject($base);
    $assert($service instanceof PackageLifecycleService, 'Production lifecycle service was not constructed.');
    $applyCoordinator = $property($service, 'applyCoordinator');
    $boundary = $property($applyCoordinator, 'recoveryBoundary');
    $assert($boundary instanceof NormalWebcoreProtectedMutationBoundary, 'Production protected boundary was not injected.');

    $session = StagingSession::create($base, $stagingRoot, 'inst_' . str_repeat('a', 32));
    $payload = new StagedPayload($session, str_repeat('a', 64), []);
    $applyPlan = WebcoreApplyPlan::fromPayload($payload);
    $package = new PackageContract(
        PackageContract::WEBCORE_PACKAGE_TYPE,
        PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION,
        '0.13.0',
        'b2b-release',
        'b2b-tree',
        new PackageCompatibility('0.0.0'),
        new PackageRuntimeCompatibility('8.0', ['mysql' => '8.0'], ['json']),
        [new PackageInventoryEntry('app/Core/Version.php', 1, str_repeat('b', 64), PackageOwnership::PACKAGE_OWNED)],
        new PackageMigrationDeclaration(false)
    );
    $transition = TransitionPlan::allow(TransitionPlan::INSTALL, $package);
    $migration = CoreMigrationPlan::allow('0.12.0', '0.13.0', null, null, []);
    $now = gmdate(DATE_ATOM);
    $operation = new LifecycleOperationRecord(
        'b2b-operation',
        TransitionPlan::INSTALL,
        '0.13.0',
        'b2b-release',
        str_repeat('a', 64),
        $session->path(),
        str_repeat('c', 64),
        $applyPlan->identity(),
        LifecycleOperationRecord::PREPARING,
        0,
        null,
        str_repeat('d', 64),
        null,
        $now,
        $now
    );
    $context = new WebcoreMutationContext($operation, $applyPlan, $transition, $migration);
    $request = $boundary->requestFor($context);
    $assert($request instanceof NormalWebcoreRecoveryCaptureRequest, 'Boundary request factory returned the wrong request type.');
    $assert($request->operationId() === 'b2b-operation', 'Request lost operation identity.');
    $assert($request->applyPlan()->identity() === $applyPlan->identity(), 'Request lost apply-plan identity.');
    $assert($request->installationIdentity() === $property($boundary, 'installationIdentity'), 'Request lost installation identity.');
    $assert($request->namespaceIdentity() === $property($boundary, 'namespaceIdentity'), 'Request lost namespace identity.');
    $assert($request->deploymentIdentity() === $property($boundary, 'deploymentIdentity'), 'Request lost deployment identity.');
    $assert($request->archiveIdentity() === str_repeat('a', 64), 'Request lost archive identity.');
    $payload->cleanup();
} finally {
    foreach (['COPOT_RECOVERY_ROOT' => $savedRoot, 'COPOT_MARIADB_ADMIN_USERNAME' => $savedAdmin, 'COPOT_MARIADB_ADMIN_PASSWORD' => $savedPassword] as $name => $value) {
        if ($value === false || $value === null) {
            putenv($name);
            unset($_ENV[$name]);
        } else {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }
    }
    $remove = static function (string $path) use (&$remove): void {
        if (is_file($path) || is_link($path)) { @unlink($path); return; }
        if (!is_dir($path)) { return; }
        foreach (scandir($path) ?: [] as $entry) { if ($entry !== '.' && $entry !== '..') { $remove($path . DIRECTORY_SEPARATOR . $entry); } }
        @rmdir($path);
    };
    $remove($root);
    $remove($stagingRoot);
    if (!$identityExisted && is_file($identityPath)) { @unlink($identityPath); @rmdir(dirname($identityPath)); }
}

echo "package_lifecycle_wu3_recovery_b2b: {$assertions} assertions passed\n";
