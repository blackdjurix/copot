<?php

namespace Copot\Core;

final class ModulePermissionReconciliationResult
{
    public const COMPLETED = 'completed'; public const FAILED = 'failed';
    public function __construct(private string $status, private string $reason = '', private array $added = [], private array $changed = [], private array $preserved = []) {}
    public function status(): string { return $this->status; } public function reason(): string { return $this->reason; } public function added(): array { return $this->added; } public function changed(): array { return $this->changed; } public function preserved(): array { return $this->preserved; }
}
