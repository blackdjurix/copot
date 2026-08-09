<?php

namespace Copot\Core;

final class SystemHealthOverallStatus
{
    public const OPERATIONAL = 'operational';
    public const ATTENTION_REQUIRED = 'attention_required';
    public const DEGRADED = 'degraded';
    public const CRITICAL = 'critical';

    private function __construct()
    {
    }
}
