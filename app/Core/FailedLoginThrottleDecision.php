<?php

namespace Copot\Core;

final class FailedLoginThrottleDecision
{
    public function __construct(
        private bool $locked,
        private int $failureCount,
        private int $delayMilliseconds
    ) {
        if ($failureCount < 0 || $delayMilliseconds < 0) {
            throw new \InvalidArgumentException('Failed-login throttle decision is invalid.');
        }
    }

    public function isLocked(): bool { return $this->locked; }
    public function failureCount(): int { return $this->failureCount; }
    public function delayMilliseconds(): int { return $this->delayMilliseconds; }
}
