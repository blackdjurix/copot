<?php

namespace Copot\Core;

final class SystemHealthFindingSeverity
{
    public const WARNING = 'warning';
    public const ERROR = 'error';
    public const CRITICAL = 'critical';

    private const RANKS = [
        self::WARNING => 1,
        self::ERROR => 2,
        self::CRITICAL => 3,
    ];

    private function __construct()
    {
    }

    public static function assertValid(string $value): void
    {
        if (!isset(self::RANKS[$value])) {
            throw new \InvalidArgumentException('System Health finding severity is invalid.');
        }
    }

    public static function rank(string $value): int
    {
        self::assertValid($value);

        return self::RANKS[$value];
    }
}
