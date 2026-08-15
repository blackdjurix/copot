<?php
$base = dirname(__DIR__); $assertions = 0;
$assert = static function (bool $ok, string $message) use (&$assertions): void { if (!$ok) throw new RuntimeException($message); $assertions++; };
$service = file_get_contents($base . '/app/Core/BackupRecovery/NormalWebcoreRecoveryCaptureService.php');
$request = file_get_contents($base . '/app/Core/BackupRecovery/NormalWebcoreRecoveryCaptureRequest.php');
$result = file_get_contents($base . '/app/Core/BackupRecovery/NormalWebcoreRecoveryCaptureResult.php');
$assert(is_string($service) && str_contains($service, 'FilesystemRecoveryPlan::fromApplyPlan'), 'Filesystem domain is not required.');
$assert(str_contains($service, 'DatabaseCaptureContext'), 'Database domain is not required.');
$assert(str_contains($service, '$this->lifecycle->capture()'), 'Lifecycle domain is not required.');
$assert(str_contains($service, '$this->installedLock->capture()'), 'Installed-lock domain is not required.');
$assert(str_contains($service, 'RecoveryLifecycleState::READY'), 'READY state proof is missing.');
$assert(str_contains($service, '$this->artifacts->readManifest'), 'Manifest integrity read-back is missing.');
$assert(str_contains($service, 'FAILED_BEFORE_MUTATION'), 'Capture failure state is missing.');
$assert(is_string($request) && str_contains($request, 'namespaceIdentity'), 'Namespace binding missing.');
$assert(str_contains($request, 'deploymentIdentity'), 'Deployment binding missing.');
$assert(is_string($result) && str_contains($result, 'function ready'), 'Bounded READY result missing.');
echo "package_lifecycle_wu3_recovery_b1: {$assertions} assertions passed\n";
