<?php

namespace Copot\Core;

use DateTimeImmutable;

final class LifecycleOperationRecord
{
    public const PREPARING = 'preparing';
    public const APPLYING = 'applying';
    public const MIGRATING = 'migrating';
    public const AWAITING_WU6 = 'awaiting_wu6';
    public const BLOCKED = 'blocked';
    public const INDETERMINATE = 'indeterminate';
    public const CLEANUP_PENDING = 'cleanup_pending';
    public const COMPLETED = 'completed';

    private const ACTIVE_STATUSES = [
        self::PREPARING, self::APPLYING, self::MIGRATING,
        self::AWAITING_WU6, self::BLOCKED, self::INDETERMINATE, self::CLEANUP_PENDING,
    ];

    public function __construct(
        private string $operationId,
        private string $classification,
        private string $targetWebcoreVersion,
        private string $releaseIdentity,
        private string $archiveSha256,
        private string $stagingPath,
        private string $payloadIdentity,
        private string $applyPlanIdentity,
        private string $phase,
        private int $fileCursor,
        private ?string $lastVerifiedPath,
        private ?string $migrationPlanIdentity,
        private ?string $migrationOutcome,
        private string $createdAt,
        private string $updatedAt,
        private string $reason = '',
        private ?string $recoveryIdentity = null,
        private ?string $recoveryManifestIdentity = null,
        private ?string $recoveryState = null
    ) {
        self::assertOpaque($operationId, 'Operation identity');
        self::assertOpaque($classification, 'Classification');
        PackageVersion::assertValid($targetWebcoreVersion);
        self::assertOpaque($releaseIdentity, 'Release identity');
        self::assertHash($archiveSha256, 'Archive identity');
        self::assertOpaque($stagingPath, 'Staging path');
        self::assertHash($payloadIdentity, 'Payload identity');
        self::assertHash($applyPlanIdentity, 'Apply plan identity');
        if (!in_array($phase, self::ACTIVE_STATUSES, true) && $phase !== self::COMPLETED) {
            throw new \InvalidArgumentException('Lifecycle operation phase is invalid.');
        }
        if ($fileCursor < 0 || ($lastVerifiedPath !== null && $lastVerifiedPath === '')) {
            throw new \InvalidArgumentException('Lifecycle operation progress is invalid.');
        }
        if ($migrationPlanIdentity !== null) { self::assertHash($migrationPlanIdentity, 'Migration plan identity'); }
        if ($migrationOutcome !== null) { self::assertOpaque($migrationOutcome, 'Migration outcome'); }
        foreach ([$createdAt, $updatedAt] as $timestamp) {
            if (DateTimeImmutable::createFromFormat(DATE_ATOM, $timestamp) === false) {
                throw new \InvalidArgumentException('Lifecycle operation timestamp is invalid.');
            }
        }
        if (strlen($reason) > 1024 || preg_match('/[\x00-\x1F\x7F]/', $reason) === 1) {
            throw new \InvalidArgumentException('Lifecycle operation reason is invalid.');
        }
    }

    public static function fromArray(array $data): self
    {
        $legacy = ['operation_id','classification','target_webcore_version','release_identity','archive_sha256','staging_path','payload_identity','apply_plan_identity','phase','file_cursor','last_verified_path','migration_plan_identity','migration_outcome','created_at','updated_at','reason'];
        $current = [...$legacy, 'recovery_identity','recovery_manifest_identity','recovery_state'];
        if (array_keys($data) !== $legacy && array_keys($data) !== $current) { throw new \InvalidArgumentException('Lifecycle operation record format is invalid.'); }
        if (array_keys($data) === $legacy) { $data['recovery_identity'] = null; $data['recovery_manifest_identity'] = null; $data['recovery_state'] = null; }
        if (!is_int($data['file_cursor'])) { throw new \InvalidArgumentException('Lifecycle operation cursor is invalid.'); }

        return new self(...array_values($data));
    }

    public function toArray(): array
    {
        return [
            'operation_id' => $this->operationId,
            'classification' => $this->classification,
            'target_webcore_version' => $this->targetWebcoreVersion,
            'release_identity' => $this->releaseIdentity,
            'archive_sha256' => $this->archiveSha256,
            'staging_path' => $this->stagingPath,
            'payload_identity' => $this->payloadIdentity,
            'apply_plan_identity' => $this->applyPlanIdentity,
            'phase' => $this->phase,
            'file_cursor' => $this->fileCursor,
            'last_verified_path' => $this->lastVerifiedPath,
            'migration_plan_identity' => $this->migrationPlanIdentity,
            'migration_outcome' => $this->migrationOutcome,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'reason' => $this->reason,
            'recovery_identity' => $this->recoveryIdentity,
            'recovery_manifest_identity' => $this->recoveryManifestIdentity,
            'recovery_state' => $this->recoveryState,
        ];
    }

    public function operationId(): string { return $this->operationId; }
    public function classification(): string { return $this->classification; }
    public function targetWebcoreVersion(): string { return $this->targetWebcoreVersion; }
    public function releaseIdentity(): string { return $this->releaseIdentity; }
    public function archiveSha256(): string { return $this->archiveSha256; }
    public function stagingPath(): string { return $this->stagingPath; }
    public function payloadIdentity(): string { return $this->payloadIdentity; }
    public function applyPlanIdentity(): string { return $this->applyPlanIdentity; }
    public function phase(): string { return $this->phase; }
    public function fileCursor(): int { return $this->fileCursor; }
    public function lastVerifiedPath(): ?string { return $this->lastVerifiedPath; }
    public function migrationPlanIdentity(): ?string { return $this->migrationPlanIdentity; }
    public function migrationOutcome(): ?string { return $this->migrationOutcome; }
    public function isTerminal(): bool { return $this->phase === self::COMPLETED; }
    public function recoveryIdentity(): ?string { return $this->recoveryIdentity; }
    public function recoveryManifestIdentity(): ?string { return $this->recoveryManifestIdentity; }
    public function recoveryState(): ?string { return $this->recoveryState; }
    public function bindRecovery(string $identity, string $manifest, string $state): self
    { return new self($this->operationId, $this->classification, $this->targetWebcoreVersion, $this->releaseIdentity, $this->archiveSha256, $this->stagingPath, $this->payloadIdentity, $this->applyPlanIdentity, $this->phase, $this->fileCursor, $this->lastVerifiedPath, $this->migrationPlanIdentity, $this->migrationOutcome, $this->createdAt, gmdate(DATE_ATOM), $this->reason, $identity, $manifest, $state); }

    public function advance(string $phase, int $cursor, ?string $lastPath = null, ?string $migrationOutcome = null, string $reason = ''): self
    {
        return new self($this->operationId, $this->classification, $this->targetWebcoreVersion, $this->releaseIdentity, $this->archiveSha256, $this->stagingPath, $this->payloadIdentity, $this->applyPlanIdentity, $phase, $cursor, $lastPath ?? $this->lastVerifiedPath, $this->migrationPlanIdentity, $migrationOutcome ?? $this->migrationOutcome, $this->createdAt, gmdate(DATE_ATOM), $reason, $this->recoveryIdentity, $this->recoveryManifestIdentity, $this->recoveryState);
    }

    private static function assertOpaque(string $value, string $label): void
    {
        if ($value === '' || trim($value) !== $value || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) { throw new \InvalidArgumentException($label . ' is invalid.'); }
    }

    private static function assertHash(string $value, string $label): void
    {
        if (preg_match('/^[a-f0-9]{64}$/', strtolower($value)) !== 1) { throw new \InvalidArgumentException($label . ' is invalid.'); }
    }
}
