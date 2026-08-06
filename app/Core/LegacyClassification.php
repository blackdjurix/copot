<?php

namespace Copot\Core;

final class LegacyClassification
{
    public const CANONICAL_SCHEMA_BASELINE = 'provable_canonical_schema_baseline';
    public const KNOWN_MIGRATION_PREFIX = 'known_migration_prefix';
    public const UNKNOWN_OR_UNPROVABLE = 'unknown_or_unprovable';
    public const COMMITTED_LIFECYCLE_STATE = 'committed_lifecycle_state';

    private function __construct()
    {
    }

    public static function values(): array
    {
        return [
            self::CANONICAL_SCHEMA_BASELINE,
            self::KNOWN_MIGRATION_PREFIX,
            self::UNKNOWN_OR_UNPROVABLE,
            self::COMMITTED_LIFECYCLE_STATE,
        ];
    }
}
