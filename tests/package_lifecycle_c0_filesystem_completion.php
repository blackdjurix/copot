<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    $assertions++;
};

$service = (string) file_get_contents($root . '/app/Core/PackageLifecycleService.php');
$finalizer = (string) file_get_contents($root . '/app/Core/HealthIntegrityCommitCoordinator.php');
$session = (string) file_get_contents($root . '/app/Core/BackupRecovery/NormalWebcoreRecoverySession.php');
$capture = (string) file_get_contents($root . '/app/Core/BackupRecovery/NormalWebcoreRecoveryCaptureService.php');
$mutation = (string) file_get_contents($root . '/app/Core/ProtectedWebcoreMutation.php');

$assert(str_contains($service, 'v3FilesystemOperationClassifier'), 'V3 filesystem retry classifier is not wired.');
$assert(str_contains($service, 'return $record->phase() === LifecycleOperationRecord::AWAITING_WU6'), 'Retry does not branch to V3 continuation.');
$assert(str_contains($service, '$this->v3RecoveryBoundary->enterExisting'), 'V3 retry does not re-enter the persisted recovery boundary.');
$assert(str_contains($service, "'retry-' . bin2hex(random_bytes(16))"), 'V3 retry does not create a distinct recovery attempt identity.');
$assert(str_contains($service, '$session->settle();'), 'V3 mutation is not settled before finalization.');
$assert(str_contains($service, 'finalizeV3($plan->operationIdentity()'), 'V3 finalization call is missing.');
$assert(str_contains($service, ', null, $this->sourceInventoryArtifacts, [], true)'), 'Normal V3 finalization is not explicitly filesystem-only.');
$assert(str_contains($finalizer, 'bool $filesystemOnly = false'), 'V3 finalizer has no explicit filesystem-only mode.');
$assert(str_contains($finalizer, '$this->databaseHealth->verify($connection)'), 'Database health verifier remains present for non-filesystem finalization.');
$assert(str_contains($finalizer, '$this->migrationHealth->verify($connection, $this->migrationRegistry, null)'), 'Migration-ledger verifier remains present for non-filesystem finalization.');
$assert(str_contains($finalizer, '$filesystemOnly ? $prior->schemaStateIdentity()'), 'Filesystem-only finalization preserves the committed schema identity.');
$assert(str_contains($session, 'public function settleMutation()'), 'Recovery session has no non-closing settlement boundary.');
$assert(str_contains($capture, "isset(\$records['database.webcore'])"), 'Filesystem recovery does not reject database recovery domains.');
$assert(str_contains($mutation, 'public function settle(): void;') && str_contains($mutation, 'public function close(): void;'), 'Mutation session contract lacks explicit settle/close operations.');

echo "C0 filesystem completion source assertions: {$assertions}\n";
