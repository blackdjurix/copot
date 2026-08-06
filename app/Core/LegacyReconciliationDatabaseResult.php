<?php

namespace Copot\Core;

final class LegacyReconciliationDatabaseResult
{
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const INDETERMINATE = 'indeterminate';

    public function __construct(
        private string $status,
        private string $schemaIdentity,
        private string $migrationStateIdentity,
        private array $appliedMigrationIds = [],
        private string $reason = ''
    ) {}

    public function status(): string { return $this->status; }
    public function schemaIdentity(): string { return $this->schemaIdentity; }
    public function migrationStateIdentity(): string { return $this->migrationStateIdentity; }
    public function appliedMigrationIds(): array { return $this->appliedMigrationIds; }
    public function reason(): string { return $this->reason; }
}
