<?php

namespace Copot\Core\BackupRecovery;

final class FilesystemRecoveryCapture
{
    /** @param array<int, FilesystemRecoveryEntry> $entries */
    public function __construct(private FilesystemRecoveryPlan $plan, private array $entries, private string $artifactBytes, private RecoveryArtifactRecord $artifact, private RecoveryDomainIdentity $domainIdentity)
    {
    }
    public function plan(): FilesystemRecoveryPlan { return $this->plan; }
    /** @return array<int, FilesystemRecoveryEntry> */
    public function entries(): array { return $this->entries; }
    public function artifactBytes(): string { return $this->artifactBytes; }
    public function artifact(): RecoveryArtifactRecord { return $this->artifact; }
    public function domainIdentity(): RecoveryDomainIdentity { return $this->domainIdentity; }
}
