<?php
$base=dirname(__DIR__);$n=0;$a=static function(bool $v,string $m)use(&$n):void{if(!$v)throw new RuntimeException($m);$n++;};
$s=file_get_contents($base.'/app/Core/PackageLifecycleService.php');$c=file_get_contents($base.'/app/Core/WebcoreApplyCoordinator.php');$b=file_get_contents($base.'/app/Core/NormalWebcoreProtectedMutationBoundary.php');$m=file_get_contents($base.'/app/Core/SystemManagerLifecycleService.php');$f=file_get_contents($base.'/app/Core/PackageLifecycleFactory.php');
$a(str_contains($s,'public function retry(string $operationId)'), 'Package Lifecycle retry entry point missing.');
$a(str_contains($s,'$this->applyCoordinator->execute($applyPlan, $transition, $migration, $record)'), 'Retry does not reuse the existing operation.');
$a(str_contains($s,'$record->applyPlanIdentity()'), 'Retry does not validate retained target identity.');
$a(str_contains($s,'retrySource($operationId)'), 'Retry does not require retained staging.');
$a(str_contains($c,'?LifecycleOperationRecord $existing = null'), 'Coordinator resume entry point missing.');
$a(str_contains($c,'if ($existing instanceof LifecycleOperationRecord)') && str_contains($c,'$record = new LifecycleOperationRecord('), 'Coordinator resume/new-operation split missing.');
$a(str_contains($c,'enterExisting'), 'Coordinator does not reuse the persisted recovery binding.');
$a(str_contains($b,'public function enterExisting'), 'Boundary existing-session entry point missing.');
$a(str_contains($b,'$this->capture->resume'), 'Retry does not reconstruct the persisted recovery session.');
$a(str_contains($m,'$this->lifecycle->retry($operationId)'), 'System Manager does not delegate Retry.');
$a(str_contains($s,'$record->recoveryIdentity() === null'), 'Missing recovery identity is not rejected.');
$a(str_contains($s,'$record->recoveryManifestIdentity() === null'), 'Missing recovery manifest is not rejected.');
$a(str_contains($f,'!$record->mutationStarted()'), 'Post-mutation evidence is not excluded.');
$a(str_contains($b,'new \\Copot\\Core\\BackupRecovery\\RecoveryIdentity($identity)'), 'Retry does not reuse recovery identity.');
echo "package_lifecycle_wu3_recovery_c2: {$n} assertions passed\n";
