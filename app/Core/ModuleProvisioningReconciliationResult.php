<?php

namespace Copot\Core;

final class ModuleProvisioningReconciliationResult
{
    public const COMPLETED = 'completed'; public const FAILED = 'failed';
    public function __construct(private string $status, private string $reason = '', private array $changed = []) {}
    public function status(): string { return $this->status; } public function reason(): string { return $this->reason; } public function changed(): array { return $this->changed; }
}
