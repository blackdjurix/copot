<?php

namespace Copot\Core;

final class MaintenanceCoordinator
{
    public function __construct(private LifecycleOperationStore $store)
    {
    }

    public function record(): ?LifecycleOperationRecord
    {
        return $this->store->read();
    }

    public function isActive(): bool
    {
        $record = $this->record();

        return $record !== null && !$record->isTerminal();
    }

    public function enter(LifecycleOperationRecord $record): void { $this->store->create($record); }
    public function update(LifecycleOperationRecord $record): void { $this->store->save($record); }
    public function clear(LifecycleOperationRecord $record): void { $this->store->clear($record); }
    public function classify(bool $currentExecutorOwnsMutex): string { return $this->store->classify($currentExecutorOwnsMutex); }
}
