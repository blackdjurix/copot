<?php

namespace Copot\Core;

use DateTimeImmutable;

final class FailedLoginAttempt
{
    public function __construct(
        private int $failureCount,
        private DateTimeImmutable $windowStartedAt,
        private ?DateTimeImmutable $lockedUntil
    ) {
        if ($failureCount < 0) {
            throw new \InvalidArgumentException('Failed-login count is invalid.');
        }
    }

    public function failureCount(): int { return $this->failureCount; }
    public function windowStartedAt(): DateTimeImmutable { return $this->windowStartedAt; }
    public function lockedUntil(): ?DateTimeImmutable { return $this->lockedUntil; }
}
