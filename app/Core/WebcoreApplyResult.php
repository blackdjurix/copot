<?php

namespace Copot\Core;

final class WebcoreApplyResult
{
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const BLOCKED = 'blocked';
    public const AWAITING_WU6 = 'awaiting_wu6';

    public function __construct(
        private string $status,
        private array $appliedPaths = [],
        private string $reason = '',
        private ?string $operationId = null
    ) {
    }

    public function status(): string { return $this->status; }
    public function appliedPaths(): array { return $this->appliedPaths; }
    public function reason(): string { return $this->reason; }
    public function operationId(): ?string { return $this->operationId; }
}
