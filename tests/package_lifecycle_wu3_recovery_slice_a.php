<?php
$base = dirname(__DIR__); $assertions = 0;
$assert = static function (bool $ok, string $message) use (&$assertions): void { if (!$ok) throw new RuntimeException($message); $assertions++; };
$record = file_get_contents($base . '/app/Core/LifecycleOperationRecord.php');
$coord = file_get_contents($base . '/app/Core/WebcoreApplyCoordinator.php');
$assert(is_string($record) && str_contains($record, 'recoveryIdentity'), 'Recovery identity persistence missing.');
$assert(str_contains($record, 'recoveryManifestIdentity'), 'Manifest binding persistence missing.');
$assert(str_contains($record, 'array_keys($data) === $legacy'), 'Legacy decoding compatibility missing.');
$assert(str_contains($record, 'bindRecovery'), 'Recovery binding method missing.');
$assert(is_string($coord) && str_contains($coord, 'recoveryBoundary->enter'), 'Protected boundary entry missing.');
$assert(str_contains($coord, '$session->authorize()'), 'Authorization step missing.');
$assert(str_contains($coord, '$session?->complete()'), 'Completion step missing.');
$assert(str_contains($coord, '$session?->fail'), 'Failure step missing.');
$assert(substr_count($coord, '$this->mutex->acquire()') === 2, 'Coordinator mutex ownership changed unexpectedly.');
echo "package_lifecycle_wu3_recovery_slice_a: {$assertions} assertions passed\n";
