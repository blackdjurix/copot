<?php

namespace Copot\Core;

final class LegacyReconciliationIntegratedResult
{
    public const COMPLETED = 'completed';
    public const FAILED_BEFORE_MUTATION = 'failed_before_mutation';
    public const RESTORE_REQUIRED = 'restore_required';

    public function __construct(private string $status, private string $recoveryState, private string $reason = '') {}
    public function status(): string { return $this->status; }
    public function recoveryState(): string { return $this->recoveryState; }
    public function reason(): string { return $this->reason; }
}
