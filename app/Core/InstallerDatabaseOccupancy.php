<?php

namespace Copot\Core;

final class InstallerDatabaseOccupancy
{
    public const EMPTY = 'empty';
    public const FOREIGN_ONLY = 'foreign_only';
    public const COPOT = 'copot';
    public const MULTIPLE_COPOT = 'multiple_copot';
    public const MIXED = 'mixed';
    public const AMBIGUOUS = 'ambiguous';
}
