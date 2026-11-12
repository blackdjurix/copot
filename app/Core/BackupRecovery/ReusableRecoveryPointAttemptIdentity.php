<?php

namespace Copot\Core\BackupRecovery;

final class ReusableRecoveryPointAttemptIdentity
{
    public function __construct(private string $value)
    {
        if (preg_match('/^rpa_[a-f0-9]{64}$/D', $value) !== 1) {
            throw new RecoveryInvariantException('Reusable recovery-point attempt identity is invalid.');
        }
    }

    public static function forInvocation(ReusableRecoveryPointIdentity $point, string $invocationIdentity, string $createdAt): self
    {
        if ($invocationIdentity === '' || trim($invocationIdentity) !== $invocationIdentity || preg_match('/[\x00-\x1F\x7F]/', $invocationIdentity) === 1) {
            throw new RecoveryInvariantException('Reusable recovery-point invocation identity is invalid.');
        }
        return new self('rpa_' . hash('sha256', json_encode([
            'point_identity' => $point->value(),
            'invocation_identity' => $invocationIdentity,
            'created_at' => $createdAt,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)));
    }

    public function value(): string { return $this->value; }
    public function equals(self $other): bool { return $this->value === $other->value; }
}

