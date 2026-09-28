<?php

namespace Copot\Core;

use DateTimeImmutable;

final class AuthenticatedSessionRecord
{
    public function __construct(private array $attributes)
    {
    }

    public function identity(): string { return (string) $this->attributes['session_identity']; }
    public function userId(): int { return (int) $this->attributes['user_id']; }
    public function deviceDescriptor(): string { return (string) $this->attributes['device_descriptor']; }
    public function createdAt(): DateTimeImmutable { return new DateTimeImmutable((string) $this->attributes['created_at']); }
    public function lastActiveAt(): DateTimeImmutable { return new DateTimeImmutable((string) $this->attributes['last_active_at']); }
    public function expiresAt(): DateTimeImmutable { return new DateTimeImmutable((string) $this->attributes['expires_at']); }
    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->attributes['revoked_at'] === null ? null : new DateTimeImmutable((string) $this->attributes['revoked_at']);
    }
    public function revocationReason(): ?string { return $this->attributes['revocation_reason'] === null ? null : (string) $this->attributes['revocation_reason']; }
    public function isRevoked(): bool { return $this->revokedAt() instanceof DateTimeImmutable; }
}
