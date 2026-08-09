<?php

namespace Copot\Core;

final class SystemHealthReport
{
    public function __construct(
        private SystemHealthContext $context,
        private string $status,
        private array $producers,
        private array $findings
    ) {
        if (!in_array($status, [SystemHealthOverallStatus::OPERATIONAL, SystemHealthOverallStatus::ATTENTION_REQUIRED, SystemHealthOverallStatus::DEGRADED, SystemHealthOverallStatus::CRITICAL], true)) {
            throw new \InvalidArgumentException('System Health report status is invalid.');
        }
    }

    public function status(): string { return $this->status; }
    public function producers(): array { return $this->producers; }
    public function findings(): array { return $this->findings; }
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'producers' => array_map(static fn (SystemHealthProducerResult $result): array => $result->toArray(), $this->producers),
            'findings' => array_map(static fn (SystemHealthFinding $finding): array => $finding->toArray(), $this->findings),
        ];
    }
}
