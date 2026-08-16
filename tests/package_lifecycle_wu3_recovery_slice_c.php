<?php

$base = dirname(__DIR__);
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    if (!$condition) throw new RuntimeException($message);
    $assertions++;
};

$service = file_get_contents($base . '/app/Core/PackageLifecycleService.php');
$manager = file_get_contents($base . '/app/Core/SystemManagerLifecycleService.php');
$status = file_get_contents($base . '/app/Core/PackageLifecycleStatus.php');
$factory = file_get_contents($base . '/app/Core/PackageLifecycleFactory.php');
$boundary = file_get_contents($base . '/app/Core/NormalWebcoreProtectedMutationBoundary.php');

$assert(is_string($service) && str_contains($service, 'recoveryIdentity() === null'), 'Retry does not require persisted recovery identity.');
$assert(str_contains($service, 'recoveryManifestIdentity() === null'), 'Retry does not require persisted manifest identity.');
$assert(str_contains($service, 'recoveryState() !== \\Copot\\Core\\BackupRecovery\\RecoveryLifecycleState::READY'), 'Retry does not require READY recovery state.');
$assert(str_contains($service, 'recoveryEvidenceValidator'), 'Retry does not validate persisted recovery evidence.');
$assert(is_string($factory) && str_contains($factory, 'operationIdentity() === $operation->operationId()'), 'Recovery binding does not match operation identity.');
$assert(str_contains($factory, '!$record->mutationStarted()'), 'Post-mutation recovery is incorrectly retry-eligible.');
$assert(str_contains($service, "'recovery_state' =>") && str_contains($service, 'recoveryState()'), 'Status does not expose persisted recovery state.');
$assert(is_string($manager) && !str_contains($manager, 'recovery->capture'), 'System Manager still captures recovery directly.');
$assert(!str_contains($manager, 'authorizeMutation'), 'System Manager bypasses the lifecycle boundary.');
$assert(str_contains($manager, 'lifecycle->apply'), 'System Manager does not delegate execution to Package Lifecycle.');
$assert(!str_contains($manager, 'return $this->execute($zip, $action);'), 'Retry must not silently recapture a recovery set.');
$assert(str_contains($manager, 'next_eligible_action'), 'System Manager result omits next eligible action.');
$assert(str_contains($manager, "'phase'"), 'System Manager result omits lifecycle phase.');
$assert(str_contains($factory, 'normalProtectedMutationBoundary'), 'Factory does not inject the production boundary.');
$assert(str_contains($factory, 'recoveryComposition->store->read'), 'Factory does not provide persisted recovery validation.');
$assert(is_string($boundary) && str_contains($boundary, 'new NormalWebcoreRecoveryCaptureRequest'), 'Boundary does not build a request from mutation context.');
$assert(str_contains($boundary, '->ready()'), 'Boundary does not prove READY.');
$assert(str_contains($boundary, 'authorizeMutation()'), 'Boundary does not authorize the live session.');
$assert(str_contains($boundary, 'completeMutation()'), 'Boundary does not record recovery completion.');
$assert(str_contains($boundary, 'failMutation('), 'Boundary does not record recovery-required failure.');
$assert(substr_count($boundary, 'close()') >= 1, 'Boundary does not close the live session.');
$assert(is_string($service) && str_contains($service, "'recovery_state'"), 'Lifecycle status omits recovery state.');
$assert(str_contains($manager, 'lifecycle->reconcile'), 'Reconciliation is not delegated to the separate lifecycle path.');
$assert(!str_contains($manager, 'WU4'), 'System Manager exposes WU4 Case C.');

echo "package_lifecycle_wu3_recovery_slice_c: {$assertions} assertions passed\n";
