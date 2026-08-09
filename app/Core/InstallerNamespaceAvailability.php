<?php

namespace Copot\Core;

final class InstallerNamespaceAvailability
{
    public const AVAILABLE = 'available';
    public const PARTIAL_COLLISION = 'partial_collision';
    public const FULL_COLLISION = 'full_collision';
    public const OWNED_BY_COPOT = 'owned_by_copot';
    public const AMBIGUOUS = 'ambiguous';
}
