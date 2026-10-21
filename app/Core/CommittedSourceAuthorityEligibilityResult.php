<?php

namespace Copot\Core;

final class CommittedSourceAuthorityEligibilityResult
{
    public const ELIGIBLE = 'eligible';
    public const BLOCKED = 'blocked';

    public function __construct(
        private string $status,
        private string $reason
    ) {
        if (!in_array($status, [self::ELIGIBLE, self::BLOCKED], true) || $reason === '') {
            throw new \InvalidArgumentException('Committed source-authority eligibility result is invalid.');
        }
    }

    public function status(): string { return $this->status; }
    public function reason(): string { return $this->reason; }
    public function eligible(): bool { return $this->status === self::ELIGIBLE; }
    public function toArray(): array { return ['status' => $this->status, 'reason' => $this->reason]; }
}
