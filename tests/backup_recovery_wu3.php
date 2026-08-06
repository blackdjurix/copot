<?php

use Copot\Core\BackupRecovery\FilesystemRecoveryBundleCodec;
use Copot\Core\BackupRecovery\FilesystemRecoveryDomain;
use Copot\Core\BackupRecovery\FilesystemRecoveryEntry;
use Copot\Core\BackupRecovery\FilesystemRecoveryException;
use Copot\Core\BackupRecovery\FilesystemRecoveryPathGuard;
use Copot\Core\BackupRecovery\FilesystemRecoveryPlan;
use Copot\Core\BackupRecovery\FilesystemRecoveryResult;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\RecoveryDomainIdentity;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryManifest;
use Copot\Core\BackupRecovery\RecoveryRootResolver;
use Copot\Core\BackupRecovery\RecoveryStorageException;
use Copot\Core\BackupRecovery\RecoveryStoragePathPolicy;
use Copot\Core\LiveTreePathGuard;
use Copot\Core\PackageOwnership;
use Copot\Core\StagedFile;
use Copot\Core\StagedPayload;
use Copot\Core\StagingSession;
use Copot\Core\WebcoreApplyPlan;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) { throw new RuntimeException($message); }
};
$throws = static function (callable $callback, string $message) use ($assert): void {
    try { $callback(); $assert(false, $message); } catch (FilesystemRecoveryException|RecoveryStorageException|InvalidArgumentException) { $assert(true, $message); }
};
$mkdir = static function (string $path): void { if (!mkdir($path, 0700, true) && !is_dir($path)) { throw new RuntimeException('Fixture directory could not be created.'); } };
$remove = static function (string $path) use (&$remove): void {
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) { return; }
    foreach (scandir($path) ?: [] as $entry) { if ($entry !== '.' && $entry !== '..') { $remove($path . DIRECTORY_SEPARATOR . $entry); } }
    @rmdir($path);
};
$hash = static fn (string $bytes): string => hash('sha256', $bytes);

$fixture = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu3-' . bin2hex(random_bytes(8));
$live = $fixture . DIRECTORY_SEPARATOR . 'live';
$storage = $live . DIRECTORY_SEPARATOR . 'storage';
$stagingRoot = $fixture . DIRECTORY_SEPARATOR . 'staging';
$applyRoot = $fixture . DIRECTORY_SEPARATOR . 'apply';
$recoveryRoot = $fixture . DIRECTORY_SEPARATOR . 'recovery-root';
$mkdir($live . DIRECTORY_SEPARATOR . 'app');
$mkdir($storage);
$mkdir($stagingRoot);
$mkdir($applyRoot);
$mkdir($recoveryRoot);
file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'existing.txt', 'old-content');
file_put_contents($live . DIRECTORY_SEPARATOR . 'unrelated.txt', 'operator-data');

try {
    $session = StagingSession::create($live, $stagingRoot);
    $payloadApp = $session->payloadPath() . DIRECTORY_SEPARATOR . 'app';
    $mkdir($payloadApp);
    file_put_contents($payloadApp . DIRECTORY_SEPARATOR . 'existing.txt', 'new-content');
    file_put_contents($payloadApp . DIRECTORY_SEPARATOR . 'created.txt', 'created-content');
    $existingTarget = new StagedFile('app/existing.txt', 11, $hash('new-content'));
    $createdTarget = new StagedFile('app/created.txt', 15, $hash('created-content'));
    $payload = new StagedPayload($session, $hash('archive'), [$createdTarget, $existingTarget]);
    $applyPlan = WebcoreApplyPlan::fromPayload($payload);
    $plan = FilesystemRecoveryPlan::fromApplyPlan($applyPlan);
    $assert(count($plan->entries()) === 2, 'Filesystem recovery scope did not derive from apply-plan files.');
    $assert($plan->entries()[0]->path() === 'app/created.txt' && $plan->entries()[1]->targetHash() === $hash('new-content'), 'Filesystem recovery plan ordering or target identity was not deterministic.');
    $assert(file_get_contents($live . DIRECTORY_SEPARATOR . 'unrelated.txt') === 'operator-data', 'Unrelated live state was changed during plan preparation.');
    $throws(static fn () => FilesystemRecoveryPlan::fromApplyPlan(WebcoreApplyPlan::fromPayload(new StagedPayload($session, $hash('other'), [new StagedFile('.env', 1, $hash('x'))]))), 'Non-package-owned scope was accepted.');

    $root = (new RecoveryRootResolver($live, realpath($recoveryRoot), [$stagingRoot, $applyRoot]))->resolve();
    $store = new RecoveryArtifactStore($root);
    $domain = new FilesystemRecoveryDomain(new FilesystemRecoveryPathGuard(new LiveTreePathGuard($live)), $store);
    $capture = $domain->capture($plan);
    $assert($capture->domainIdentity()->identifier() === 'filesystem.package-owned', 'Filesystem domain identifier was incorrect.');
    $assert($capture->artifact()->artifactIdentity() === $hash($capture->artifactBytes()), 'Filesystem artifact identity was not bound to bundle bytes.');
    $assert($capture->entries()[0]->preOperationState() === FilesystemRecoveryEntry::ABSENT_BEFORE_OPERATION, 'Absent pre-operation state was not captured.');
    $assert($capture->entries()[1]->preOperationState() === FilesystemRecoveryEntry::EXISTING_FILE && $capture->entries()[1]->preImageBytes() === 'old-content', 'Existing pre-image was not captured byte-for-byte.');
    $codec = new FilesystemRecoveryBundleCodec();
    $assert($capture->artifactBytes() === $codec->encode($capture->plan(), $capture->entries()), 'Filesystem bundle encoding was not deterministic.');
    $throws(static fn () => $codec->decode(substr($capture->artifactBytes(), 0, -1) . ',"unknown":1}'), 'Malformed or unknown filesystem bundle fields were accepted.');

    $manifest = new RecoveryManifest(new RecoveryIdentity('wu3-recovery-1'), 'operation-1', 'copot-webcore', 'release-1', $hash('archive'), $applyPlan->identity(), [$capture->domainIdentity()], 'pre-lifecycle', 'pre-ledger');
    $store->publish($manifest, [['record' => $capture->artifact(), 'bytes' => $capture->artifactBytes()]]);
    $assert($store->readArtifact($manifest->recoveryIdentity(), $capture->artifact()) === $capture->artifactBytes(), 'WU2 artifact readback did not verify and return the immutable bundle.');
    $pathPolicy = new RecoveryStoragePathPolicy($root);
    $bundlePath = $pathPolicy->artifactPath($manifest->recoveryIdentity(), $capture->artifact());
    $originalBundle = file_get_contents($bundlePath);
    file_put_contents($bundlePath, 'tampered');
    $throws(static fn () => $store->readArtifact($manifest->recoveryIdentity(), $capture->artifact()), 'Tampered filesystem recovery artifact was accepted.');
    file_put_contents($bundlePath, $originalBundle);

    file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'existing.txt', 'new-content');
    file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'created.txt', 'created-content');
    $restored = $domain->restore($manifest->recoveryIdentity(), $capture->artifact(), $plan);
    $assert($restored->status() === FilesystemRecoveryResult::COMPLETED, 'Filesystem restore did not complete.');
    $assert(file_get_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'existing.txt') === 'old-content', 'Existing file was not restored.');
    $assert(!file_exists($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'created.txt'), 'Operation-created file was not removed.');
    $assert(file_get_contents($live . DIRECTORY_SEPARATOR . 'unrelated.txt') === 'operator-data', 'Restore touched unrelated operator data.');
    $again = $domain->restore($manifest->recoveryIdentity(), $capture->artifact(), $plan);
    $assert($again->status() === FilesystemRecoveryResult::COMPLETED, 'Repeated restore was not idempotent.');

    file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'existing.txt', 'third-party');
    $unexpected = $domain->restore($manifest->recoveryIdentity(), $capture->artifact(), $plan);
    $assert($unexpected->status() === FilesystemRecoveryResult::FAILED && $unexpected->completedPaths() === ['app/created.txt'], 'Unexpected existing-file drift was not rejected with partial progress.');
    file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'existing.txt', 'new-content');
    file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'created.txt', 'created-content');

    $partial = $domain->restore($manifest->recoveryIdentity(), $capture->artifact(), $plan, static function (int $count) use ($live): void {
        if ($count === 1) { file_put_contents($live . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'existing.txt', 'unexpected-after-first-path'); }
    });
    $assert($partial->status() === FilesystemRecoveryResult::FAILED && count($partial->completedPaths()) === 1, 'Partial local restore failure was not surfaced.');
    $assert(!is_dir($root->path() . DIRECTORY_SEPARATOR . 'recovery-sets' . DIRECTORY_SEPARATOR . hash('sha256', $manifest->recoveryIdentity()->value()) . DIRECTORY_SEPARATOR . 'state'), 'WU3 created mutable WU6 state.');

    $nestedParent = $live . DIRECTORY_SEPARATOR . 'nested';
    $guard = new FilesystemRecoveryPathGuard(new LiveTreePathGuard($live));
    $guard->ensureParent('nested/recreated.txt');
    $assert(is_dir($nestedParent), 'Safe parent recreation failed.');
    $remove($nestedParent);
    $junction = getenv('COPOT_WU3_JUNCTION_PATH');
    if (is_string($junction) && $junction !== '') {
        try {
            (new FilesystemRecoveryPathGuard(new LiveTreePathGuard($junction)))->resolve('junction/nested/file.txt');
            $assert(false, 'Nested junction traversal was accepted.');
        } catch (Throwable) {
            $assert(true, 'Nested junction traversal was rejected.');
        }
    }
    $assert(!is_dir($basePath . DIRECTORY_SEPARATOR . '.copot-recovery'), 'WU3 created recovery storage in the repository.');
    echo "Backup & Recovery WU3 focused tests passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $remove($fixture);
}
