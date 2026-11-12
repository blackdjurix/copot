<?php

namespace Copot\Core\BackupRecovery;

final class ReusableRecoveryPointResult
{
    public const CAPTURED = 'captured';
    public const RESTORED = 'restored';

    public function __construct(private string $status, private ReusableRecoveryPointIdentity $pointIdentity, private string $manifestIdentity, private ?ReusableRecoveryPointAttemptIdentity $attemptIdentity = null, private bool $continued = false)
    {
        if (!in_array($status, [self::CAPTURED, self::RESTORED], true) || preg_match('/^[a-f0-9]{64}$/D', $manifestIdentity) !== 1) throw new RecoveryInvariantException('Reusable recovery-point result is invalid.');
    }
    public function status(): string { return $this->status; }
    public function pointIdentity(): ReusableRecoveryPointIdentity { return $this->pointIdentity; }
    public function manifestIdentity(): string { return $this->manifestIdentity; }
    public function attemptIdentity(): ?ReusableRecoveryPointAttemptIdentity { return $this->attemptIdentity; }
    public function continued(): bool { return $this->continued; }
}

