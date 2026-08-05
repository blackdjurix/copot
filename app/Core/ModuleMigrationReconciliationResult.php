<?php

namespace Copot\Core;

final class ModuleMigrationReconciliationResult
{
    public const COMPLETED = 'completed'; public const NOOP = 'noop'; public const FAILED = 'failed'; public const INDETERMINATE = 'indeterminate';
    public function __construct(private string $status, private array $applied = [], private string $reason = '', private string $stateIdentity = '') {}
    public function status(): string { return $this->status; } public function appliedMigrationIds(): array { return $this->applied; } public function reason(): string { return $this->reason; } public function stateIdentity(): string { return $this->stateIdentity; }
}
