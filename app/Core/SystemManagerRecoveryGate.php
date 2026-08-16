<?php

namespace Copot\Core;

interface SystemManagerRecoveryGate
{
    /** Capture bounded recovery evidence before any lifecycle mutation. */
    public function capture(string $action, string $targetVersion): string;
}

final class UnavailableSystemManagerRecoveryGate implements SystemManagerRecoveryGate
{
    public function capture(string $action, string $targetVersion): string
    {
        throw new \RuntimeException('Recovery capture is unavailable.');
    }
}
