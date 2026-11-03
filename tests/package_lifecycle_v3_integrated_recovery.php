<?php

declare(strict_types=1);

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

use Copot\Core\BackupRecovery\DatabaseQuiescenceCapability;
use Copot\Core\BackupRecovery\DatabaseQuiescenceLease;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryLifecycleCoordinator;
use Copot\Core\BackupRecovery\RecoveryLifecycleRecord;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;
use Copot\Core\BackupRecovery\RecoveryMaintenanceBoundary;
use Copot\Core\BackupRecovery\RecoveryOrchestrator;
use Copot\Core\BackupRecovery\RecoveryStorageRoot;
use Copot\Core\BackupRecovery\NormalWebcoreRecoverySession;
use Copot\Core\BackupRecovery\InstallationRecoveryMaintenance;
use Copot\Core\InstallationMutex;

final class C0IntegratedRecoveryLease implements DatabaseQuiescenceLease
{
    private bool $active = true;
    public function isActive(): bool { return $this->active; }
    public function release(): void { $this->active = false; }
}

final class C0IntegratedRecoveryQuiescence implements DatabaseQuiescenceCapability
{
    public function isAvailable(): bool { return true; }
    public function acquire(): ?DatabaseQuiescenceLease { return new C0IntegratedRecoveryLease(); }
}

final class C0IntegratedRecoveryMaintenance implements RecoveryMaintenanceBoundary
{
    private bool $active = false;
    public function enter(RecoveryIdentity $identity): bool { $this->active = true; return true; }
    public function isActive(RecoveryIdentity $identity): bool { return $this->active; }
    public function leave(RecoveryIdentity $identity): bool { $this->active = false; return true; }
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-v3-integrated-recovery-' . bin2hex(random_bytes(5));
mkdir($root, 0700, true);
$remove = static function (string $path) use (&$remove): void {
    if (is_file($path) || is_link($path)) { @unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry);
    @rmdir($path);
};

try {
    $identity = new RecoveryIdentity('c0-integrated-recovery');
    $manifest = hash('sha256', 'c0-integrated-manifest');
    $store = new RecoveryLifecycleStore(new RecoveryStorageRoot($root, $root . DIRECTORY_SEPARATOR . 'recovery', 'c0'));
    $mutexRoot = $root . DIRECTORY_SEPARATOR . 'mutex';
    mkdir($mutexRoot, 0700, true);
    $mutex = new InstallationMutex($mutexRoot);
    $maintenance = new C0IntegratedRecoveryMaintenance();
    $coordinator = new RecoveryLifecycleCoordinator($store, $mutex, new C0IntegratedRecoveryQuiescence(), $maintenance);
    $orchestrator = new RecoveryOrchestrator($store, $mutex, new C0IntegratedRecoveryQuiescence(), $maintenance);
    $record = new RecoveryLifecycleRecord(
        $identity,
        $manifest,
        'c0-integrated-operation',
        RecoveryLifecycleState::READY,
        true,
        true,
        $identity->value(),
        $manifest,
        hash('sha256', 'c0-target')
    );
    $store->create($record);
    $coordinator->enterMaintenance($identity);

    $restoreAttempts = 0;
    $filesystemRestored = false;
    $restore = static function () use (&$restoreAttempts, &$filesystemRestored, $orchestrator, $identity): RecoveryLifecycleRecord {
        $restoreAttempts++;
        return $orchestrator->restore(
            $identity,
            static function (): void {},
            static function () use (&$filesystemRestored, &$restoreAttempts): void {
                if ($restoreAttempts === 1) throw new RuntimeException('injected restore interruption');
                $filesystemRestored = true;
            },
            static function (): void {},
            static function (): void {},
            static function () use (&$filesystemRestored): void {
                if (!$filesystemRestored) throw new RuntimeException('filesystem restore was not completed');
            }
        );
    };
    $session = new NormalWebcoreRecoverySession($coordinator, $identity, $manifest, $record, null, null, $restore);
    $required = $session->failMutation('injected V3 mutation failure');
    $assert($required->state() === RecoveryLifecycleState::RESTORE_REQUIRED, 'Failure did not persist RESTORE_REQUIRED.');
    $assert(!$maintenance->isActive($identity), 'Mutation maintenance remained active after failure recording.');
    try { $session->restoreMutation(); $assert(false, 'Interrupted restore was accepted.'); } catch (Throwable) { $assert(true, 'Interrupted restore remained fail-closed.'); }
    $assert($store->read($identity)->state() === RecoveryLifecycleState::RESTORE_REQUIRED, 'Interrupted restore did not remain retryable.');
    $restored = $session->restoreMutation();
    $assert($restored->state() === RecoveryLifecycleState::RESTORED && $store->read($identity)->state() === RecoveryLifecycleState::RESTORED, 'Restore retry did not converge to RESTORED.');
    $assert($restoreAttempts === 2 && $filesystemRestored, 'Recovery orchestration did not retry the filesystem boundary.');
    $assert(!$maintenance->isActive($identity), 'Recovery maintenance remained active after RESTORED.');
    $independentProbe = $mutex->acquire();
    $assert($independentProbe instanceof \Copot\Core\InstallationLock, 'Independent restore did not release its own mutex.');
    $independentProbe?->release();

    $reuseRoot = $root . DIRECTORY_SEPARATOR . 'mutex-reuse';
    mkdir($reuseRoot, 0700, true);
    $reuseStorage = $reuseRoot . DIRECTORY_SEPARATOR . 'storage';
    mkdir($reuseStorage, 0700, true);
    $reuseStore = new RecoveryLifecycleStore(new RecoveryStorageRoot($reuseRoot, $reuseRoot . DIRECTORY_SEPARATOR . 'recovery', 'reuse'));
    $reuseMutex = new InstallationMutex($reuseStorage);
    $reuseMaintenance = new InstallationRecoveryMaintenance($reuseMutex);
    $reuseCoordinator = new RecoveryLifecycleCoordinator($reuseStore, $reuseMutex, new C0IntegratedRecoveryQuiescence(), $reuseMaintenance);
    $reuseOrchestrator = new RecoveryOrchestrator($reuseStore, $reuseMutex, new C0IntegratedRecoveryQuiescence(), $reuseMaintenance);
    $reuseIdentity = new RecoveryIdentity('c0-mutex-reuse');
    $reuseManifest = hash('sha256', 'c0-mutex-reuse-manifest');
    $reuseRecord = new RecoveryLifecycleRecord(
        $reuseIdentity,
        $reuseManifest,
        'c0-mutex-reuse-operation',
        RecoveryLifecycleState::READY,
        true,
        true,
        $reuseIdentity->value(),
        $reuseManifest,
        hash('sha256', 'c0-mutex-reuse-target')
    );
    $reuseStore->create($reuseRecord);
    $owner = $reuseMutex->acquire();
    $assert($owner instanceof \Copot\Core\InstallationLock, 'Mutex reuse fixture could not acquire its owner lock.');
    $reuseMaintenance->adopt($owner);
    $reuseCoordinator->enterMaintenance($reuseIdentity);
    $reuseRestored = false;
    $reuseRestore = static function () use (&$reuseRestored, $reuseOrchestrator, $reuseCoordinator, $reuseIdentity): RecoveryLifecycleRecord {
        return $reuseOrchestrator->restore(
            $reuseIdentity,
            static function (): void {},
            static function () use (&$reuseRestored): void { $reuseRestored = true; },
            static function (): void {},
            static function (): void {},
            static function () use (&$reuseRestored): void {
                if (!$reuseRestored) throw new RuntimeException('Reused-lock filesystem restore did not complete.');
            },
            null,
            null,
            $reuseCoordinator->activeLifecycleLock()
        );
    };
    $reuseSession = new NormalWebcoreRecoverySession($reuseCoordinator, $reuseIdentity, $reuseManifest, $reuseRecord, null, null, $reuseRestore);
    $reuseSession->failMutation('mutex reuse failure');
    $assert($reuseMaintenance->activeLock() === $owner && $reuseMaintenance->isActive($reuseIdentity), 'Automatic recovery did not retain the active lifecycle lock session.');
    $reuseResult = $reuseSession->restoreMutation();
    $assert($reuseResult->state() === RecoveryLifecycleState::RESTORED && $reuseRestored, 'Automatic recovery did not restore through the reused lock session.');
    $assert($reuseMaintenance->activeLock() === null && !$reuseMaintenance->isActive($reuseIdentity), 'Recovery session did not release the reused lock exactly once.');
    $probe = $reuseMutex->acquire();
    $assert($probe instanceof \Copot\Core\InstallationLock, 'Mutex remained locked after reused-session restore.');
    $probe?->release();

    $inactiveLockRoot = $reuseRoot . DIRECTORY_SEPARATOR . 'inactive';
    mkdir($inactiveLockRoot, 0700, true);
    $inactiveLock = (new InstallationMutex($inactiveLockRoot))->acquire();
    try {
        $reuseOrchestrator->restore($reuseIdentity, static function (): void {}, static function (): void {}, static function (): void {}, static function (): void {}, static function (): void {}, null, null, $inactiveLock);
        $assert(false, 'Inactive recovery lock session was accepted.');
    } catch (Throwable) {
        $assert(true, 'Inactive recovery lock session failed closed.');
    } finally {
        $inactiveLock?->release();
    }

    $mismatchOwner = $reuseMutex->acquire();
    $reuseMaintenance->adopt($mismatchOwner);
    $reuseCoordinator->enterMaintenance($reuseIdentity);
    try {
        $reuseOrchestrator->restore(new RecoveryIdentity('c0-mutex-mismatch'), static function (): void {}, static function (): void {}, static function (): void {}, static function (): void {}, static function (): void {}, null, null, $mismatchOwner);
        $assert(false, 'Identity-mismatched recovery lock session was accepted.');
    } catch (Throwable) {
        $assert(true, 'Identity-mismatched recovery lock session failed closed.');
    } finally {
        $reuseCoordinator->leaveMaintenance($reuseIdentity);
    }

    $retryIdentity = new RecoveryIdentity('c0-mutex-retry');
    $retryManifest = hash('sha256', 'c0-mutex-retry-manifest');
    $retryRecord = new RecoveryLifecycleRecord($retryIdentity, $retryManifest, 'c0-mutex-retry-operation', RecoveryLifecycleState::READY, true, true, $retryIdentity->value(), $retryManifest, hash('sha256', 'c0-mutex-retry-target'));
    $reuseStore->create($retryRecord);
    $retryOwner = $reuseMutex->acquire();
    $reuseMaintenance->adopt($retryOwner);
    $reuseCoordinator->enterMaintenance($retryIdentity);
    $retrySession = new NormalWebcoreRecoverySession($reuseCoordinator, $retryIdentity, $retryManifest, $retryRecord, null, null, static function () use ($reuseOrchestrator, $reuseCoordinator, $retryIdentity): RecoveryLifecycleRecord {
        return $reuseOrchestrator->restore($retryIdentity, static function (): void {}, static function (): void { throw new RuntimeException('reused restore interruption'); }, static function (): void {}, static function (): void {}, static function (): void {}, null, null, $reuseCoordinator->activeLifecycleLock());
    });
    $retrySession->failMutation('reused restore interruption');
    try { $retrySession->restoreMutation(); $assert(false, 'Reused-lock interruption was accepted.'); } catch (Throwable) { $assert(true, 'Reused-lock interruption failed closed.'); }
    $assert($reuseStore->read($retryIdentity)->state() === RecoveryLifecycleState::RESTORE_REQUIRED && $reuseMaintenance->activeLock() === null && !$reuseMaintenance->isActive($retryIdentity), 'Interrupted reused-lock restore did not release ownership while preserving retry state.');
    $retryProbe = $reuseMutex->acquire();
    $assert($retryProbe instanceof \Copot\Core\InstallationLock, 'Interrupted reused-lock restore left the mutex file owned.');
    $retryProbe?->release();
    $retried = $reuseOrchestrator->restore($retryIdentity, static function (): void {}, static function (): void {}, static function (): void {}, static function (): void {}, static function (): void {}, null, null);
    $assert($retried->state() === RecoveryLifecycleState::RESTORED, 'Independent retry after reused-lock interruption did not converge.');
    echo "V3 integrated recovery assertions: {$assertions}\n";
} finally {
    $remove($root);
}
