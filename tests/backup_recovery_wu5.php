<?php

declare(strict_types=1);

use Copot\Core\BackupRecovery\DatabaseVerificationResult;
use Copot\Core\BackupRecovery\FilesystemRecoveryPathGuard;
use Copot\Core\BackupRecovery\InstalledLockRecoveryArtifactCodec;
use Copot\Core\BackupRecovery\InstalledLockRecoveryDomain;
use Copot\Core\BackupRecovery\LifecycleRecoveryArtifactCodec;
use Copot\Core\BackupRecovery\LifecycleRecoveryDomain;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\RecoveryArtifactRecord;
use Copot\Core\BackupRecovery\RecoveryDomainIdentity;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryManifest;
use Copot\Core\BackupRecovery\RecoveryRootResolver;
use Copot\Core\BackupRecovery\RecoveryStateVerification;
use Copot\Core\BackupRecovery\RecoveryStorageRoot;
use Copot\Core\CommittedLifecycleState;
use Copot\Core\CommittedLifecycleStateStore;
use Copot\Core\InstallationState;
use Copot\Core\LiveTreePathGuard;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void { $assertions++; if (!$condition) { throw new RuntimeException($message); } };
$remove = static function (string $path) use (&$remove): void {
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) { return; }
    foreach (scandir($path) ?: [] as $entry) { if ($entry !== '.' && $entry !== '..') { $remove($path . DIRECTORY_SEPARATOR . $entry); } }
    @rmdir($path);
};

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu5-' . bin2hex(random_bytes(6));
mkdir($root, 0700, true);
$storage = $root . DIRECTORY_SEPARATOR . 'storage';
mkdir($storage, 0700, true);

try {
    $installation = new InstallationState($storage);
    $installation->createMarker('0.12.0');
    $marker = $installation->readMarker();
    if (!is_array($marker)) { throw new RuntimeException('Marker fixture was not created.'); }
    $store = new CommittedLifecycleStateStore($storage);
    $guard = new FilesystemRecoveryPathGuard(new LiveTreePathGuard($storage));
    $lifecycle = new LifecycleRecoveryDomain($store, $guard);
    $markerDomain = new InstalledLockRecoveryDomain($installation, $guard);

    $absent = $lifecycle->capture();
    $assert($absent->stateKind() === 'ABSENT_BEFORE_OPERATION', 'Lifecycle absence was not explicit.');
    $assert($absent->state() === null, 'Absent lifecycle state unexpectedly contained a committed state.');
    $lifecycle->restore($absent);
    $assert($store->read() === null, 'Already absent lifecycle state was not idempotent.');

    $created = new CommittedLifecycleState('0.12.0', 'mutated-release', 'mutated-tree', 1, 'mutated-schema', str_repeat('a', 64), new DateTimeImmutable($marker['installed_at']));
    $store->write($created);
    $createdArtifact = (new LifecycleRecoveryArtifactCodec())->artifactFromState($created);
    $lifecycle->restore($absent, $createdArtifact->identity());
    $assert($store->read() === null, 'Expected operation-created lifecycle state was not removed.');
    $store->write($created);
    try { $lifecycle->restore($absent, str_repeat('b', 64)); $assert(false, 'Unrelated lifecycle drift was accepted.'); } catch (Throwable) { $assert(true, 'Unrelated lifecycle drift rejected.'); }

    $presentState = new CommittedLifecycleState('0.12.0', 'release-12', 'tree-12', 1, 'schema-12', str_repeat('c', 64), new DateTimeImmutable($marker['installed_at']), 'package-integrity');
    $store->write($presentState);
    $present = $lifecycle->capture();
    $assert($present->stateKind() === 'PRESENT_COMMITTED_STATE', 'Present lifecycle state was not classified.');
    $codec = new LifecycleRecoveryArtifactCodec();
    try { $codec->decode(substr($present->bytes(), 0, -1) . '}'); $assert(false, 'Tampered lifecycle artifact was accepted.'); } catch (Throwable) { $assert(true, 'Tampered lifecycle artifact rejected.'); }
    $mutated = new CommittedLifecycleState('0.13.0', 'target-release', 'target-tree', 1, 'target-schema', str_repeat('d', 64), new DateTimeImmutable($marker['installed_at']));
    $store->write($mutated);
    $lifecycle->restore($present, $codec->artifactFromState($mutated)->identity());
    $assert($store->read()?->toArray() === $presentState->toArray(), 'Present lifecycle state did not restore exactly.');
    $lifecycle->restore($present);
    $assert($store->read()?->toArray() === $presentState->toArray(), 'Already restored lifecycle state was not idempotent.');

    $markerArtifact = $markerDomain->capture();
    $markerCodec = new InstalledLockRecoveryArtifactCodec();
    $installation->replaceMarker('0.13.0', $marker['installed_at']);
    $mutatedMarker = $markerCodec->artifactFromMarker(['version' => '0.13.0', 'installed_at' => $marker['installed_at']]);
    $markerDomain->restore($markerArtifact, $mutatedMarker->identity());
    $assert($installation->readMarker() === $marker, 'installed.lock did not restore exactly.');
    $markerDomain->restore($markerArtifact);
    $assert($installation->readMarker() === $marker, 'Already restored installed.lock was not idempotent.');
    try { $markerCodec->decode(substr($markerArtifact->bytes(), 0, -1) . '}'); $assert(false, 'Tampered installed.lock artifact was accepted.'); } catch (Throwable) { $assert(true, 'Tampered installed.lock artifact rejected.'); }

    $recoveryIdentity = new RecoveryIdentity('wu5-' . bin2hex(random_bytes(4)));
    $databaseVerification = DatabaseVerificationResult::valid(['schema' => str_repeat('e', 64), 'data' => str_repeat('f', 64), 'migration_ledger' => $presentState->migrationStateIdentity(), 'artifact' => str_repeat('1', 64)]);
    $manifest = new RecoveryManifest(
        $recoveryIdentity, 'operation-wu5', 'copot-webcore', 'release-12', str_repeat('2', 64), str_repeat('3', 64),
        [
            new RecoveryDomainIdentity('database.webcore', 'database.webcore', 'configured-database', hash('sha256', 'database-fixture')),
            new RecoveryDomainIdentity('filesystem.lifecycle.committed', 'filesystem.lifecycle.committed', 'committed-state', $present->identity()),
            new RecoveryDomainIdentity('filesystem.lifecycle.installed-lock', 'filesystem.lifecycle.installed-lock', 'installed-lock', $markerArtifact->identity()),
        ],
        $present->identity(), $presentState->migrationStateIdentity()
    );
    $verification = (new RecoveryStateVerification())->verify($manifest, $present, $markerArtifact, $databaseVerification);
    $assert($verification->passed(), 'Valid WU5 cross-domain verification failed.');
    $assert($verification->identities()['migration_ledger'] === $presentState->migrationStateIdentity(), 'WU5 did not reuse the verified migration identity.');
    $badDatabase = DatabaseVerificationResult::valid(['migration_ledger' => str_repeat('9', 64)]);
    $assert(!(new RecoveryStateVerification())->verify($manifest, $present, $markerArtifact, $badDatabase)->passed(), 'Lifecycle/database migration mismatch was accepted.');
    $assert(!(new RecoveryStateVerification())->verify($manifest, $present, $markerArtifact, DatabaseVerificationResult::failed('ledger unavailable'))->passed(), 'Unhealthy database verification was accepted.');

    mkdir($root . DIRECTORY_SEPARATOR . 'recovery', 0700, true);
    $artifactStore = new RecoveryArtifactStore(new RecoveryStorageRoot($root, $root . DIRECTORY_SEPARATOR . 'recovery', hash('sha256', RecoveryRootResolver::identityPath(realpath($root)))));
    $databaseRecord = new RecoveryArtifactRecord('database.webcore', hash('sha256', 'database-fixture'), strlen('database-fixture'));
    $artifactStore->publish($manifest, [['record' => $databaseRecord, 'bytes' => 'database-fixture'], ['record' => $present->record(), 'bytes' => $present->bytes()], ['record' => $markerArtifact->record(), 'bytes' => $markerArtifact->bytes()]]);
    $assert($artifactStore->readArtifact($recoveryIdentity, $present->record()) === $present->bytes(), 'Lifecycle artifact WU2 readback failed.');
    $assert($artifactStore->readArtifact($recoveryIdentity, $markerArtifact->record()) === $markerArtifact->bytes(), 'installed.lock artifact WU2 readback failed.');

    echo "Backup Recovery WU5 assertions: {$assertions}" . PHP_EOL;
} finally {
    $remove($root);
}
