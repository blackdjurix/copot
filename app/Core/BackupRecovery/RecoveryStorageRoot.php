<?php

namespace Copot\Core\BackupRecovery;

final class RecoveryStorageRoot
{
    public function __construct(
        private string $projectRoot,
        private string $root,
        private string $projectIdentity
    ) {
    }

    public function projectRoot(): string
    {
        return $this->projectRoot;
    }

    public function path(): string
    {
        return $this->root;
    }

    public function projectIdentity(): string
    {
        return $this->projectIdentity;
    }
}
