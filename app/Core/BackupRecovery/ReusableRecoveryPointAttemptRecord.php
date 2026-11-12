<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\SourceInventoryReference;

final class ReusableRecoveryPointAttemptRecord
{
    public const CREATED = 'CREATED';
    public const RESTORING = 'RESTORING';
    public const VERIFYING = 'VERIFYING';
    public const RESTORED = 'RESTORED';
    public const RESTORE_REQUIRED = 'RESTORE_REQUIRED';
    public const RESTORE_INDETERMINATE = 'RESTORE_INDETERMINATE';
    public const VERIFICATION_FAILED = 'VERIFICATION_FAILED';

    private const STATES = [self::CREATED, self::RESTORING, self::VERIFYING, self::RESTORED, self::RESTORE_REQUIRED, self::RESTORE_INDETERMINATE, self::VERIFICATION_FAILED];
    private const STAGES = ['CREATED', 'FILESYSTEM', 'SOURCE_INVENTORY', 'LIFECYCLE', 'INSTALLED_LOCK', 'VERIFYING', 'COMPLETE'];

    public function __construct(
        private ReusableRecoveryPointIdentity $pointIdentity,
        private ReusableRecoveryPointAttemptIdentity $attemptIdentity,
        private string $installationIdentity,
        private string $state,
        private string $stage,
        private SourceInventoryReference $expectedCurrentSourceInventory,
        private string $expectedCurrentLifecycleIdentity,
        private string $expectedCurrentInstalledLockIdentity,
        private string $createdAt,
        private string $updatedAt,
        private string $failureReason = ''
    ) {
        if (preg_match('/^inst_[a-f0-9]{32}$/D', $installationIdentity) !== 1 || !in_array($state, self::STATES, true) || !in_array($stage, self::STAGES, true)) throw new RecoveryLifecycleException('Reusable recovery-point attempt state is invalid.');
        foreach ([$expectedCurrentLifecycleIdentity, $expectedCurrentInstalledLockIdentity] as $identity) if (preg_match('/^[a-f0-9]{64}$/D', $identity) !== 1) throw new RecoveryLifecycleException('Reusable recovery-point expected domain identity is invalid.');
        foreach ([$createdAt, $updatedAt] as $timestamp) if (\DateTimeImmutable::createFromFormat(\DATE_ATOM, $timestamp) === false) throw new RecoveryLifecycleException('Reusable recovery-point attempt timestamp is invalid.');
        if (strlen($failureReason) > 1024 || preg_match('/[\x00-\x1F\x7F]/', $failureReason) === 1) throw new RecoveryLifecycleException('Reusable recovery-point attempt failure is invalid.');
    }

    public static function fromArray(array $data): self
    {
        $keys = ['point_identity','attempt_identity','installation_identity','state','stage','expected_current_source_inventory','expected_current_lifecycle_identity','expected_current_installed_lock_identity','created_at','updated_at','failure_reason'];
        if (array_keys($data) !== $keys || !is_array($data['expected_current_source_inventory'])) throw new RecoveryLifecycleException('Reusable recovery-point attempt format is invalid.');
        return new self(new ReusableRecoveryPointIdentity($data['point_identity']), new ReusableRecoveryPointAttemptIdentity($data['attempt_identity']), $data['installation_identity'], $data['state'], $data['stage'], SourceInventoryReference::fromArray($data['expected_current_source_inventory']), $data['expected_current_lifecycle_identity'], $data['expected_current_installed_lock_identity'], $data['created_at'], $data['updated_at'], $data['failure_reason']);
    }

    public function toArray(): array
    {
        return ['point_identity' => $this->pointIdentity->value(), 'attempt_identity' => $this->attemptIdentity->value(), 'installation_identity' => $this->installationIdentity, 'state' => $this->state, 'stage' => $this->stage, 'expected_current_source_inventory' => $this->expectedCurrentSourceInventory->toArray(), 'expected_current_lifecycle_identity' => $this->expectedCurrentLifecycleIdentity, 'expected_current_installed_lock_identity' => $this->expectedCurrentInstalledLockIdentity, 'created_at' => $this->createdAt, 'updated_at' => $this->updatedAt, 'failure_reason' => $this->failureReason];
    }

    public function pointIdentity(): ReusableRecoveryPointIdentity { return $this->pointIdentity; }
    public function attemptIdentity(): ReusableRecoveryPointAttemptIdentity { return $this->attemptIdentity; }
    public function installationIdentity(): string { return $this->installationIdentity; }
    public function state(): string { return $this->state; }
    public function stage(): string { return $this->stage; }
    public function expectedCurrentSourceInventory(): SourceInventoryReference { return $this->expectedCurrentSourceInventory; }
    public function expectedCurrentLifecycleIdentity(): string { return $this->expectedCurrentLifecycleIdentity; }
    public function expectedCurrentInstalledLockIdentity(): string { return $this->expectedCurrentInstalledLockIdentity; }
    public function failureReason(): string { return $this->failureReason; }
    public function unresolved(): bool { return $this->state !== self::RESTORED; }

    public function withProgress(string $state, string $stage, string $failureReason = ''): self
    {
        return new self($this->pointIdentity, $this->attemptIdentity, $this->installationIdentity, $state, $stage, $this->expectedCurrentSourceInventory, $this->expectedCurrentLifecycleIdentity, $this->expectedCurrentInstalledLockIdentity, $this->createdAt, gmdate(\DATE_ATOM), $failureReason);
    }
}

