<?php

namespace Copot\Core;

final class ModuleDependencyConflictStatus
{
    public const SATISFIED = 'satisfied';
    public const MISSING_DEPENDENCY = 'missing_dependency';
    public const DEPENDENCY_DISABLED = 'dependency_disabled';
    public const INCOMPATIBLE_DEPENDENCY = 'incompatible_dependency';
    public const DECLARED_CONFLICT = 'declared_conflict';
    public const IDENTITY_CONFLICT = 'identity_conflict';
    public const OWNED_PATH_CONFLICT = 'owned_path_conflict';
    public const SCHEMA_OWNERSHIP_CONFLICT = 'schema_ownership_conflict';
    public const PERMISSION_OWNERSHIP_CONFLICT = 'permission_ownership_conflict';
    public const CYCLIC_DEPENDENCY = 'cyclic_dependency';
    public const RESOLUTION_REQUIRED = 'resolution_required';
    public const UNRESOLVABLE = 'unresolvable';

    private function __construct() {}
}
