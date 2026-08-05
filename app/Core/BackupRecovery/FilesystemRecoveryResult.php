<?php

namespace Copot\Core\BackupRecovery;

final class FilesystemRecoveryResult
{
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';

    /** @param array<int, string> $completedPaths */
    public function __construct(private string $status, private array $completedPaths = [], private string $reason = '')
    {
    }
    public function status(): string { return $this->status; }
    /** @return array<int, string> */
    public function completedPaths(): array { return $this->completedPaths; }
    public function reason(): string { return $this->reason; }
}
