<?php

namespace Copot\Core;

final class InstalledStateStatus
{
    public const FRESH = 'fresh';
    public const LEGACY = 'legacy';
    public const COMMITTED = 'committed';
    public const INCONSISTENT = 'inconsistent';
    public const INVALID = 'invalid';

    private function __construct()
    {
    }
}
