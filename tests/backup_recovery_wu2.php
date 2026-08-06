<?php

use Copot\Core\BackupRecovery\RecoveryArtifactRecord;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\RecoveryAtomicFileWriter;
use Copot\Core\BackupRecovery\RecoveryDomainIdentity;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryInvariantException;
use Copot\Core\BackupRecovery\RecoveryManifest;
use Copot\Core\BackupRecovery\RecoveryManifestCodec;
use Copot\Core\BackupRecovery\RecoveryRootResolver;
use Copot\Core\BackupRecovery\RecoveryStorageException;
use Copot\Core\BackupRecovery\RecoveryStoragePathPolicy;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) { throw new RuntimeException($message); }
};
$throws = static function (callable $callback, string $message) use ($assert): void {
    try { $callback(); $assert(false, $message); } catch (RecoveryStorageException|RecoveryInvariantException) { $assert(true, $message); }
};
$mkdir = static function (string $path): void { if (!mkdir($path, 0700, true) && !is_dir($path)) { throw new RuntimeException('Fixture directory could not be created.'); } };
$remove = static function (string $path) use (&$remove): void {
    if (!is_dir($path) || is_link($path)) { if (file_exists($path)) { @unlink($path); } return; }
    foreach (scandir($path) ?: [] as $entry) { if ($entry !== '.' && $entry !== '..') { $remove($path . DIRECTORY_SEPARATOR . $entry); } }
    @rmdir($path);
};
$fixture = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu2-' . bin2hex(random_bytes(8));
$mkdir($fixture . DIRECTORY_SEPARATOR . 'project' . DIRECTORY_SEPARATOR . 'staging');
$mkdir($fixture . DIRECTORY_SEPARATOR . 'project' . DIRECTORY_SEPARATOR . 'storage');
$mkdir($fixture . DIRECTORY_SEPARATOR . 'project-2');
$mkdir($fixture . DIRECTORY_SEPARATOR . 'recovery-root');
$mkdir($fixture . DIRECTORY_SEPARATOR . 'document-root');

try {
    $project = realpath($fixture . DIRECTORY_SEPARATOR . 'project');
    $projectTwo = realpath($fixture . DIRECTORY_SEPARATOR . 'project-2');
    $recoveryRoot = realpath($fixture . DIRECTORY_SEPARATOR . 'recovery-root');
    $documentRoot = realpath($fixture . DIRECTORY_SEPARATOR . 'document-root');
    $root = (new RecoveryRootResolver($project, $recoveryRoot, [$project . DIRECTORY_SEPARATOR . 'staging', $project . DIRECTORY_SEPARATOR . 'storage'], [$documentRoot]))->resolve();
    $rootTwo = (new RecoveryRootResolver($projectTwo, $recoveryRoot, [], [$documentRoot]))->resolve();
    $assert($root->projectIdentity() !== $rootTwo->projectIdentity(), 'Distinct projects shared a recovery identity.');
    $assert(str_starts_with(strtolower($root->path()), strtolower($recoveryRoot . DIRECTORY_SEPARATOR)), 'Recovery root escaped the configured base.');
    $assert(!str_starts_with(strtolower($root->path()), strtolower($project . DIRECTORY_SEPARATOR)), 'Recovery root overlapped the project.');
    $assert(RecoveryRootResolver::identityPath('C:\\Site\\COPOT') === RecoveryRootResolver::identityPath('c:/site/copot'), 'Windows identity normalization was not deterministic.');
    $throws(static fn () => (new RecoveryRootResolver($project, null))->resolve(), 'Missing configured recovery root was accepted.');
    $throws(static fn () => (new RecoveryRootResolver($project, 'relative-recovery-root'))->resolve(), 'Relative configured recovery root was accepted.');
    $throws(static fn () => (new RecoveryRootResolver($project, $project))->resolve(), 'Project-local recovery root was accepted.');
    $throws(static fn () => (new RecoveryRootResolver($project, $documentRoot, [], [$documentRoot]))->resolve(), 'HTTP document-root recovery root was accepted.');
    $throws(static fn () => (new RecoveryRootResolver($project, $project . DIRECTORY_SEPARATOR . 'storage', [$project . DIRECTORY_SEPARATOR . 'storage']))->resolve(), 'Lifecycle-storage recovery root was accepted.');
    $throws(static fn () => (new RecoveryRootResolver($project, $fixture . DIRECTORY_SEPARATOR . 'missing-recovery-root'))->resolve(), 'Missing configured recovery root was accepted.');
    $throws(static fn () => (new RecoveryRootResolver($project, $recoveryRoot . DIRECTORY_SEPARATOR . '..'))->resolve(), 'Traversal in configured recovery root was accepted.');
    $throws(static fn () => (new RecoveryRootResolver($project, $recoveryRoot, [dirname($project)]))->resolve(), 'Overlapping excluded root was accepted.');
    $recoveryLink = $fixture . DIRECTORY_SEPARATOR . 'recovery-link';
    if (@symlink($recoveryRoot, $recoveryLink)) {
        $throws(static fn () => (new RecoveryRootResolver($project, $recoveryLink))->resolve(), 'Symlink configured recovery root was accepted.');
    }
    $junction = getenv('COPOT_WU2_JUNCTION_PATH');
    if (is_string($junction) && $junction !== '') {
        $throws(static fn () => (new RecoveryRootResolver($junction, $recoveryRoot))->resolve(), 'Junction project root was accepted.');
        $throws(static fn () => (new RecoveryRootResolver($junction . DIRECTORY_SEPARATOR . 'nested', $recoveryRoot))->resolve(), 'Nested path traversing a junction was accepted.');
    }

    $hash = static fn (string $value): string => hash('sha256', $value);
    $bytes = "schema\nrows\n";
    $database = new RecoveryDomainIdentity('database', 'database.webcore', 'configured-db', $hash($bytes));
    $manifest = new RecoveryManifest(new RecoveryIdentity('recovery-wu2-1'), 'operation-1', 'copot-webcore', 'release-1', $hash('archive'), $hash('plan'), [$database], 'lifecycle-before', 'ledger-before');
    $artifact = new RecoveryArtifactRecord('database', $hash($bytes), strlen($bytes));
    $codec = new RecoveryManifestCodec();
    $encoded = $codec->encode($manifest, [$artifact]);
    $assert($encoded === $codec->encode($manifest, [$artifact]), 'Manifest JSON was not deterministic.');
    $decoded = $codec->decode($encoded);
    $assert(is_string($decoded['identity']) && preg_match('/^[a-f0-9]{64}$/D', $decoded['identity']) === 1, 'Manifest identity was not persisted.');
    $throws(static fn () => $codec->decode(substr($encoded, 0, -1) . ',"unknown":1}'), 'Unknown manifest fields were accepted.');
    $tampered = substr($encoded, 0, -1) . ',"tamper":true}';
    $throws(static fn () => $codec->decode($tampered), 'Tampered manifest was accepted.');

    $store = new RecoveryArtifactStore($root);
    $store->publish($manifest, [['record' => $artifact, 'bytes' => $bytes]]);
    $stored = $store->readManifest(new RecoveryIdentity('recovery-wu2-1'));
    $assert($stored['complete'] === true && $stored['artifacts'][0]->byteSize() === strlen($bytes), 'Published recovery set could not be read and verified.');
    $store->publish($manifest, [['record' => $artifact, 'bytes' => $bytes]]);
    $throws(static fn () => $store->publish($manifest, [['record' => $artifact, 'bytes' => 'tampered']]), 'Artifact identity mismatch was accepted.');
    $artifactPath = (new RecoveryStoragePathPolicy($root))->artifactPath($manifest->recoveryIdentity(), $artifact);
    file_put_contents($artifactPath, 'tamper');
    $throws(static fn () => $store->readManifest(new RecoveryIdentity('recovery-wu2-1')), 'Tampered stored artifact was accepted.');

    $writerRoot = $fixture . DIRECTORY_SEPARATOR . 'writer';
    $mkdir($writerRoot);
    $throws(static fn () => (new RecoveryAtomicFileWriter(true, static fn ($handle): bool => false))->write($writerRoot . DIRECTORY_SEPARATOR . 'failed.json', 'x'), 'Injected fsync failure was accepted.');
    $assert(count(glob($writerRoot . DIRECTORY_SEPARATOR . '*.tmp') ?: []) === 0, 'Temporary recovery files survived failed publication.');
    $assert(!is_dir($root->path() . DIRECTORY_SEPARATOR . 'recovery-sets' . DIRECTORY_SEPARATOR . 'recovery-wu2-1' . DIRECTORY_SEPARATOR . 'state'), 'WU2 created mutable WU6 state.');
    $assert(!is_dir($basePath . DIRECTORY_SEPARATOR . '.copot-recovery'), 'WU2 mutated the production project root.');
} finally {
    $remove($fixture);
}

echo "Backup & Recovery WU2 focused tests passed ({$assertions} assertions)." . PHP_EOL;
