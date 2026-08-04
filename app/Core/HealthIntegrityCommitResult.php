<?php

namespace Copot\Core;

final class HealthIntegrityCommitResult
{
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const CLEANUP_PENDING = 'cleanup_pending';

    public function __construct(private string $status, private HealthGateMatrix $gates, private string $reason = '', private ?string $operationId = null)
    {
    }

    public function status(): string { return $this->status; }
    public function gates(): HealthGateMatrix { return $this->gates; }
    public function reason(): string { return $this->reason; }
    public function operationId(): ?string { return $this->operationId; }
}
