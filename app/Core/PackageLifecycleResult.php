<?php

namespace Copot\Core;

final class PackageLifecycleResult
{
    public function __construct(
        private bool $accepted,
        private string $status,
        private string $reason = '',
        private ?TransitionPlan $transition = null,
        private ?CoreMigrationPlan $migration = null,
        private ?string $operationId = null
    ) {
    }

    public function accepted(): bool { return $this->accepted; }
    public function status(): string { return $this->status; }
    public function reason(): string { return $this->reason; }

    public function toArray(): array
    {
        $migrations = [];
        foreach ($this->migration?->migrations() ?? [] as $migration) {
            if ($migration instanceof CoreMigrationDescriptor) {
                $migrations[] = ['id' => $migration->id(), 'sequence' => $migration->sequence(), 'target' => $migration->targetWebcoreVersion()];
            }
        }

        return [
            'accepted' => $this->accepted,
            'status' => $this->status,
            'classification' => $this->transition?->classification(),
            'target_webcore_version' => $this->transition?->package()->targetWebcoreVersion(),
            'operation_id' => $this->operationId,
            'migrations' => $migrations,
            'reason' => $this->reason,
        ];
    }
}
