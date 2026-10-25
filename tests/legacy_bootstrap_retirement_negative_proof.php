<?php

$base = dirname(__DIR__);
require $base . '/bootstrap/autoload.php';

use Copot\Core\LegacyBootstrapExecutionAdmission;
use Copot\Core\LegacyBootstrapPreparation;
use Copot\Core\LegacyBootstrapPreparationRunner;
use Copot\Core\LegacyBootstrapScopedProtectionComposer;
use Copot\Core\LegacyBootstrapSourceArtifactStore;
use Copot\Core\LegacyBootstrapV3ExecutionContext;
use Copot\Core\LegacyBootstrapRecoveryBindingResult;
use Copot\Core\LegacyToV3BootstrapEntry;
use Copot\Core\LegacyToV3BootstrapPreparationComposer;
use Copot\Core\LifecycleOperationStore;
use Copot\Core\PackageManifest;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\ApplicationWriteProtectionEvidence;
use Copot\Core\BackupRecovery\ScopedDatabaseConsistencyEvidence;
use Copot\Core\NormalWebcoreProtectedMutationSession;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$throwsRetired = static function (callable $callback, string $message) use ($assert): void {
    try {
        $callback();
    } catch (Throwable $exception) {
        $assert(str_contains(strtolower($exception->getMessage()), 'retired'), $message . ': wrong rejection');
        return;
    }
    $assert(false, $message . ': call was not rejected');
};
$empty = static function (string $class): object {
    return (new ReflectionClass($class))->newInstanceWithoutConstructor();
};
$snapshot = static function (string $root): array {
    $files = [];
    if (!is_dir($root)) return $files;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $files[$iterator->getSubPathname()] = [
                'size' => $file->getSize(),
                'sha256' => hash_file('sha256', $file->getPathname()),
            ];
        }
    }
    ksort($files);
    return $files;
};

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-r2-retirement-' . bin2hex(random_bytes(6));
mkdir($tmp . DIRECTORY_SEPARATOR . 'storage', 0700, true);
mkdir($tmp . DIRECTORY_SEPARATOR . 'public', 0700, true);
$before = $snapshot($tmp);

$admission = $empty(LegacyBootstrapExecutionAdmission::class);
$context = $empty(LegacyBootstrapV3ExecutionContext::class);
$binding = $empty(LegacyBootstrapRecoveryBindingResult::class);
$session = $empty(NormalWebcoreProtectedMutationSession::class);
$artifacts = $empty(RecoveryArtifactStore::class);
$operations = $empty(LifecycleOperationStore::class);
$protection = $empty(LegacyBootstrapScopedProtectionComposer::class);
$application = $empty(ApplicationWriteProtectionEvidence::class);
$database = $empty(ScopedDatabaseConsistencyEvidence::class);

// issue() must reject before touching any dependency, including uninitialized
// objects intentionally supplied by this disposable proof.
$throwsRetired(static fn () => LegacyBootstrapExecutionAdmission::issue(
    $context, $binding, $session, $artifacts, $operations, $protection, $application, $database
), 'Admission issue');

$throwsRetired(static fn () => $admission->authorize(), 'Pre-existing admission authorize');
$throwsRetired(static fn () => $admission->complete(), 'Pre-existing admission complete');
$throwsRetired(static fn () => $admission->fail('retired test'), 'Pre-existing admission fail');

$authorizedProperty = new ReflectionProperty(LegacyBootstrapExecutionAdmission::class, 'authorized');
$authorizedProperty->setValue($admission, true);
$throwsRetired(static fn () => $admission->complete(), 'Pre-existing authorized admission complete');
$throwsRetired(static fn () => $admission->fail('retired authorized test'), 'Pre-existing authorized admission fail');

// Every preparation boundary rejects before dependency, ZIP, identity, staging,
// manifest, or target access.
$entry = $empty(LegacyToV3BootstrapEntry::class);
$composer = $empty(LegacyToV3BootstrapPreparationComposer::class);
$runner = $empty(LegacyBootstrapPreparationRunner::class);
$manifest = $empty(PackageManifest::class);
$throwsRetired(static fn () => $entry->prepare($manifest), 'Entry preparation');
$throwsRetired(static fn () => $composer->prepare($tmp . DIRECTORY_SEPARATOR . 'missing.zip'), 'Composer preparation');
$throwsRetired(static fn () => $runner->prepare($tmp . DIRECTORY_SEPARATOR . 'missing.zip'), 'Runner preparation');
$throwsRetired(static fn () => $runner->bindReviewedInjectionManifest(
    $empty(LegacyBootstrapPreparation::class),
    $tmp . DIRECTORY_SEPARATOR . 'missing-manifest.json',
    str_repeat('0', 64)
), 'Reviewed injection manifest binding');
$throwsRetired(static fn () => $runner->prepareHandoff(
    $tmp . DIRECTORY_SEPARATOR . 'missing.zip',
    $tmp . DIRECTORY_SEPARATOR . 'missing-manifest.json',
    str_repeat('0', 64)
), 'Preparation handoff');
$throwsRetired(static fn () => LegacyBootstrapPreparationRunner::forSourceAndTarget(
    $tmp . DIRECTORY_SEPARATOR . 'missing-source',
    $tmp . DIRECTORY_SEPARATOR . 'target-app',
    $tmp . DIRECTORY_SEPARATOR . 'target-public'
), 'Runner composition');
$throwsRetired(static fn () => LegacyToV3BootstrapPreparationComposer::forProject($tmp), 'Composer composition');

$artifactStore = new LegacyBootstrapSourceArtifactStore($tmp . DIRECTORY_SEPARATOR . 'storage');
$artifactRoot = $tmp . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'legacy-bootstrap-source';
$assert(!is_dir($artifactRoot), 'Artifact-store construction created a retired storage directory.');
$throwsRetired(static fn () => $artifactStore->publish([]), 'Artifact publication');
$assert(!is_dir($artifactRoot), 'Rejected artifact publication changed storage state.');
$assert((new ReflectionClass(LegacyBootstrapSourceArtifactStore::class))->hasMethod('rehydrate'), 'Read-only artifact rehydration API was removed.');

$after = $snapshot($tmp);
$assert($before === $after, 'Retirement calls changed disposable filesystem state.');

echo "R2 dedicated retirement negative-proof assertions: {$assertions}" . PHP_EOL;
