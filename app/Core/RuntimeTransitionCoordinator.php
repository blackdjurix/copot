<?php

namespace Copot\Core;

final class RuntimeTransitionCoordinator
{
    public function __construct(private InstallationMutex $mutex, private RuntimeRegistry $registry)
    {
    }

    public function execute(callable $mutation, array $requirements = []): mixed
    {
        $lock = $this->mutex->acquire();
        if (!$lock instanceof InstallationLock) throw new \RuntimeException('Shared-state transition is busy.');
        try {
            $this->registry->assertTransitionAllowed($requirements);
            return $mutation();
        } finally { $lock->release(); }
    }
}
