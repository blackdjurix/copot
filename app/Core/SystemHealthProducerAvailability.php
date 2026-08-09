<?php

namespace Copot\Core;

final class SystemHealthProducerAvailability
{
    public const READY = 'ready';
    public const NOT_APPLICABLE = 'not_applicable';
    public const NOT_ADOPTED = 'not_adopted';
    public const UNAVAILABLE = 'unavailable';
    public const PRODUCER_ERROR = 'producer_error';

    private const VALUES = [
        self::READY,
        self::NOT_APPLICABLE,
        self::NOT_ADOPTED,
        self::UNAVAILABLE,
        self::PRODUCER_ERROR,
    ];

    private function __construct()
    {
    }

    public static function assertValid(string $value): void
    {
        if (!in_array($value, self::VALUES, true)) {
            throw new \InvalidArgumentException('System Health producer availability is invalid.');
        }
    }

    public static function isEvidenceSufficient(string $value): bool
    {
        self::assertValid($value);

        return in_array($value, [self::READY, self::NOT_APPLICABLE], true);
    }
}
