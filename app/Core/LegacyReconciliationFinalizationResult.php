<?php

namespace Copot\Core;

final class LegacyReconciliationFinalizationResult
{
    public const COMPLETED = 'completed';
    public const ALREADY_FINALIZED = 'already_finalized';
    public const FAILED = 'failed';

    public function __construct(
        private string $status,
        private HealthGateMatrix $gates,
        private string $reason = ''
    ) {}

    public function status(): string { return $this->status; }
    public function gates(): HealthGateMatrix { return $this->gates; }
    public function reason(): string { return $this->reason; }
}
