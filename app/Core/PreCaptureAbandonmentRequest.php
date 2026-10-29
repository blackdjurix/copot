<?php

namespace Copot\Core;

/** Immutable caller evidence for the pre-capture abandonment boundary. */
final class PreCaptureAbandonmentRequest
{
    public function __construct(
        private string $operationId,
        private $freshness,
        private $faultInjector = null
    ) {}

    public function operationId(): string { return $this->operationId; }
    public function assertFresh(LifecycleOperationRecord $operation): void
    {
        if (!is_callable($this->freshness) || !(($this->freshness)($operation, $this))) {
            throw new \RuntimeException('Pre-capture abandonment freshness evidence is invalid.');
        }
    }
    public function fail(string $stage): void { if (is_callable($this->faultInjector)) { ($this->faultInjector)($stage); } }
    public function toArray(): array { return ['operation_id' => $this->operationId]; }
}
