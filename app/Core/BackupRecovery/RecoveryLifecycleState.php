<?php

namespace Copot\Core\BackupRecovery;

final class RecoveryLifecycleState
{
    public const CREATED = 'CREATED';
    public const CAPTURING = 'CAPTURING';
    public const CAPTURED = 'CAPTURED';
    public const READY = 'READY';
    public const RESTORING = 'RESTORING';
    public const VERIFYING = 'VERIFYING';
    public const RESTORED = 'RESTORED';
    public const CLEANUP_PENDING = 'CLEANUP_PENDING';
    public const CLEANED = 'CLEANED';
    public const FAILED_BEFORE_MUTATION = 'FAILED_BEFORE_MUTATION';
    public const RESTORE_REQUIRED = 'RESTORE_REQUIRED';
    public const RESTORE_INDETERMINATE = 'RESTORE_INDETERMINATE';
    public const VERIFICATION_FAILED = 'VERIFICATION_FAILED';

    private const TRANSITIONS = [
        self::CREATED => [self::CAPTURING, self::FAILED_BEFORE_MUTATION, self::RESTORE_INDETERMINATE],
        self::CAPTURING => [self::CAPTURED, self::FAILED_BEFORE_MUTATION, self::RESTORE_INDETERMINATE],
        self::CAPTURED => [self::READY, self::FAILED_BEFORE_MUTATION, self::RESTORE_INDETERMINATE],
        self::READY => [self::RESTORING, self::CLEANUP_PENDING, self::RESTORE_REQUIRED, self::RESTORE_INDETERMINATE],
        self::RESTORING => [self::VERIFYING, self::RESTORE_REQUIRED, self::RESTORE_INDETERMINATE, self::VERIFICATION_FAILED],
        self::VERIFYING => [self::RESTORED, self::VERIFICATION_FAILED, self::RESTORE_REQUIRED, self::RESTORE_INDETERMINATE],
        self::RESTORED => [self::CLEANUP_PENDING],
        self::CLEANUP_PENDING => [self::CLEANED, self::RESTORE_REQUIRED, self::RESTORE_INDETERMINATE],
        self::CLEANED => [],
        self::FAILED_BEFORE_MUTATION => [self::CAPTURING, self::CLEANED],
        self::RESTORE_REQUIRED => [self::RESTORING, self::RESTORE_INDETERMINATE, self::CLEANUP_PENDING],
        self::RESTORE_INDETERMINATE => [self::RESTORING],
        self::VERIFICATION_FAILED => [self::RESTORING, self::RESTORE_INDETERMINATE],
    ];

    public static function all(): array { return array_keys(self::TRANSITIONS); }

    public static function canTransition(string $from, string $to): bool
    {
        return isset(self::TRANSITIONS[$from]) && in_array($to, self::TRANSITIONS[$from], true);
    }

    public static function assert(string $state): void
    {
        if (!in_array($state, self::all(), true)) {
            throw new RecoveryInvariantException('Recovery lifecycle state is invalid.');
        }
    }
}
