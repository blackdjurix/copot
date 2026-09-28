<?php

namespace Copot\Core;

use DateTimeImmutable;
use DateTimeZone;

final class FailedLoginThrottle
{
    public const FAILURE_WINDOW_SECONDS = 15 * 60;
    public const LOCKOUT_SECONDS = 15 * 60;
    public const FIRST_DELAY_MILLISECONDS = 250;
    public const SECOND_DELAY_MILLISECONDS = 1000;
    public const LOCKOUT_THRESHOLD = 10;

    /** @var callable():DateTimeImmutable */
    private $clock;

    public function __construct(private FailedLoginAttemptRepository $attempts, ?callable $clock = null)
    {
        $this->clock = $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public static function targetHash(string $email): string
    {
        return hash('sha256', 'copot-login-target-v1:' . strtolower(trim($email)));
    }

    public function beforeAttempt(string $targetHash, ?DateTimeImmutable $now = null): FailedLoginThrottleDecision
    {
        $now ??= ($this->clock)();
        $state = $this->attempts->active($targetHash, $now, self::FAILURE_WINDOW_SECONDS);

        return new FailedLoginThrottleDecision(
            $state?->lockedUntil() !== null && $state->lockedUntil() > $now,
            $state?->failureCount() ?? 0,
            0
        );
    }

    public function recordFailure(string $targetHash, ?DateTimeImmutable $now = null): FailedLoginThrottleDecision
    {
        $now ??= ($this->clock)();
        $state = $this->attempts->active($targetHash, $now, self::FAILURE_WINDOW_SECONDS);
        $count = ($state?->failureCount() ?? 0) + 1;
        $windowStartedAt = $state?->windowStartedAt() ?? $now;
        $lockedUntil = $count >= self::LOCKOUT_THRESHOLD ? $now->modify('+' . self::LOCKOUT_SECONDS . ' seconds') : null;
        $this->attempts->save($targetHash, $count, $windowStartedAt, $now, $lockedUntil);

        return new FailedLoginThrottleDecision(
            $lockedUntil !== null,
            $count,
            $count >= self::LOCKOUT_THRESHOLD
                ? 0
                : ($count >= 8
                    ? self::SECOND_DELAY_MILLISECONDS
                    : ($count >= 5 ? self::FIRST_DELAY_MILLISECONDS : 0))
        );
    }

    public function recordSuccess(string $targetHash): void
    {
        $this->attempts->clear($targetHash);
    }
}
