<?php

namespace Copot\Core;

final class TargetCompatibleExtraState
{
    public const COMPATIBLE = 'compatible';
    public const UNKNOWN = 'unknown';
    public const AMBIGUOUS = 'ambiguous';
    public const CONTRADICTORY = 'contradictory';
    public const UNSAFE = 'unsafe';
    public const UNSUPPORTED = 'unsupported';

    public function __construct(
        private string $identity,
        private string $state,
        private string $detail
    ) {
        if ($identity === '' || trim($identity) !== $identity || preg_match('/[\x00-\x1F\x7F]/', $identity) === 1) {
            throw new \InvalidArgumentException('Extra state identity is invalid.');
        }
        if (!in_array($state, [self::COMPATIBLE, self::UNKNOWN, self::AMBIGUOUS, self::CONTRADICTORY, self::UNSAFE, self::UNSUPPORTED], true)) {
            throw new \InvalidArgumentException('Extra state classification is unsupported.');
        }
        if ($detail === '' || trim($detail) !== $detail || preg_match('/[\x00-\x1F\x7F]/', $detail) === 1) {
            throw new \InvalidArgumentException('Extra state detail is invalid.');
        }
    }

    public function identity(): string { return $this->identity; }
    public function state(): string { return $this->state; }
    public function detail(): string { return $this->detail; }

    public function toArray(): array
    {
        return ['identity' => $this->identity, 'state' => $this->state, 'detail' => $this->detail];
    }
}
