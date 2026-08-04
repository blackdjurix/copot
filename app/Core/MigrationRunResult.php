<?php

namespace Copot\Core;

final class MigrationRunResult
{
    public const NOOP = 'noop';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const INDETERMINATE = 'indeterminate';

    public function __construct(
        private string $status,
        private array $appliedMigrationIds = [],
        private string $reason = ''
    ) {
    }

    public function status(): string { return $this->status; }
    public function appliedMigrationIds(): array { return $this->appliedMigrationIds; }
    public function reason(): string { return $this->reason; }
}
