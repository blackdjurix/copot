<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\InstallationLock;
use Copot\Core\InstallationMutex;

/** Existing lifecycle maintenance with durable, fail-closed writer admission. */
final class InstallationScopedMaintenanceGuard extends InstallationRecoveryMaintenance
{
    private string $statePath;
    private string $admissionLockPath;

    public function __construct(InstallationMutex $mutex, string $storagePath)
    {
        parent::__construct($mutex, $storagePath);
        $storagePath = rtrim($storagePath, '/\\');
        $this->statePath = $storagePath . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'application-write-guard.json';
        $this->admissionLockPath = $storagePath . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'application-write-admission.lock';
    }

    public function enter(RecoveryIdentity $identity): bool { return parent::enter($identity); }
    public function isActive(RecoveryIdentity $identity): bool { return parent::isActive($identity); }
    public function leave(RecoveryIdentity $identity): bool
    {
        $released = parent::leave($identity);
        if ($released) { $this->clearState($identity); }
        return $released;
    }

    public function adopt(InstallationLock $lock): void { parent::adopt($lock); }
    public function detach(): void { parent::detach(); }

    public function enterScoped(RecoveryIdentity $identity, ApplicationWriteProtectionEvidence $evidence): bool
    {
        if (!$evidence->isValidAt() || !$evidence->operationIdentity()->equals($identity)) { return false; }
        if (!parent::enter($identity)) { return false; }
        try {
            $this->withAdmissionLock(function () use ($identity, $evidence): void {
                $state = $this->readState();
                if (($state['active'] ?? false) === true || (int) ($state['in_flight'] ?? 0) !== 0) {
                    throw new RecoveryLifecycleException('Application writers are still in flight.');
                }
                $this->writeState([
                    'active' => true,
                    'draining' => true,
                    'identity' => $identity->value(),
                    'evidence_identity' => $evidence->identity(),
                    'in_flight' => 0,
                    'updated_at' => gmdate('c'),
                ]);
            });
            return true;
        } catch (\Throwable $exception) {
            parent::leave($identity);
            return false;
        }
    }

    public function scopedActive(RecoveryIdentity $identity, ApplicationWriteProtectionEvidence $evidence): bool
    {
        $state = $this->readState();
        return ($state['active'] ?? false) === true
            && ($state['identity'] ?? null) === $identity->value()
            && ($state['evidence_identity'] ?? null) === $evidence->identity()
            && (int) ($state['in_flight'] ?? -1) === 0;
    }

    public function admit(string $writerClass): ?ApplicationWriteAdmissionLease
    {
        if (!in_array($writerClass, ['http', 'cli', 'scheduled', 'background'], true)) { return null; }
        $handle = $this->openAdmissionLock();
        if (!flock($handle, LOCK_EX)) { fclose($handle); return null; }
        try {
            $state = $this->readState();
            if (($state['active'] ?? false) === true || ($state['draining'] ?? false) === true) {
                flock($handle, LOCK_UN); fclose($handle); return null;
            }
            $state['in_flight'] = (int) ($state['in_flight'] ?? 0) + 1;
            $state['updated_at'] = gmdate('c');
            $this->writeState($state);
            flock($handle, LOCK_UN); fclose($handle);
            return new ApplicationWriteAdmissionLease(function () use ($writerClass): void { $this->releaseAdmission($writerClass); });
        } catch (\Throwable $exception) {
            flock($handle, LOCK_UN); fclose($handle); return null;
        }
    }

    private function releaseAdmission(string $writerClass): void
    {
        $this->withAdmissionLock(function (): void {
            $state = $this->readState();
            $state['in_flight'] = max(0, (int) ($state['in_flight'] ?? 0) - 1);
            $state['updated_at'] = gmdate('c');
            $this->writeState($state);
        });
    }

    private function clearState(RecoveryIdentity $identity): void
    {
        $this->withAdmissionLock(function () use ($identity): void {
            $state = $this->readState();
            if (($state['identity'] ?? null) === $identity->value()) {
                $this->writeState(['active' => false, 'draining' => false, 'identity' => null, 'evidence_identity' => null, 'in_flight' => 0, 'updated_at' => gmdate('c')]);
            }
        });
    }

    private function openAdmissionLock()
    {
        $directory = dirname($this->admissionLockPath);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) { throw new RecoveryLifecycleException('Application admission storage is unavailable.'); }
        $handle = fopen($this->admissionLockPath, 'c');
        if (!is_resource($handle)) { throw new RecoveryLifecycleException('Application admission lock is unavailable.'); }
        return $handle;
    }

    private function withAdmissionLock(\Closure $callback): void
    {
        $handle = $this->openAdmissionLock();
        try { if (!flock($handle, LOCK_EX)) { throw new RecoveryLifecycleException('Application admission lock could not be acquired.'); } $callback(); flock($handle, LOCK_UN); } finally { fclose($handle); }
    }

    /** @return array<string,mixed> */
    private function readState(): array
    {
        if (!is_file($this->statePath)) { return ['active' => false, 'draining' => false, 'in_flight' => 0]; }
        $decoded = json_decode((string) file_get_contents($this->statePath), true);
        if (!is_array($decoded)) { throw new RecoveryLifecycleException('Application write guard state is unreadable.'); }
        return $decoded;
    }

    /** @param array<string,mixed> $state */
    private function writeState(array $state): void
    {
        $directory = dirname($this->statePath);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) { throw new RecoveryLifecycleException('Application write guard storage is unavailable.'); }
        $temporary = $this->statePath . '.tmp-' . bin2hex(random_bytes(4));
        if (file_put_contents($temporary, json_encode($state, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX) === false || !rename($temporary, $this->statePath)) { @unlink($temporary); throw new RecoveryLifecycleException('Application write guard state could not be persisted.'); }
    }
}
