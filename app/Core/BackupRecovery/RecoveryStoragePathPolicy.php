<?php

namespace Copot\Core\BackupRecovery;

final class RecoveryStoragePathPolicy
{
    public function __construct(private RecoveryStorageRoot $storageRoot)
    {
    }

    public function recoverySetsRoot(): string
    {
        return $this->storageRoot->path() . DIRECTORY_SEPARATOR . 'recovery-sets';
    }

    public function recoverySetRoot(RecoveryIdentity $identity): string
    {
        return $this->recoverySetsRoot() . DIRECTORY_SEPARATOR . hash('sha256', $identity->value());
    }

    public function manifestPath(RecoveryIdentity $identity): string
    {
        return $this->recoverySetRoot($identity) . DIRECTORY_SEPARATOR . 'manifest' . DIRECTORY_SEPARATOR . 'recovery-manifest.json';
    }

    public function artifactPath(RecoveryIdentity $identity, RecoveryArtifactRecord $artifact): string
    {
        return $this->artifactDirectory($identity, $artifact->domainIdentifier()) . DIRECTORY_SEPARATOR . $artifact->artifactIdentity() . '.bin';
    }

    public function artifactMetadataPath(RecoveryIdentity $identity, RecoveryArtifactRecord $artifact): string
    {
        return $this->artifactDirectory($identity, $artifact->domainIdentifier()) . DIRECTORY_SEPARATOR . $artifact->artifactIdentity() . '.json';
    }

    private function artifactDirectory(RecoveryIdentity $identity, string $domainIdentifier): string
    {
        if (preg_match('/^[a-z][a-z0-9._-]*$/D', $domainIdentifier) !== 1) {
            throw new RecoveryStorageException('Recovery domain path segment is invalid.');
        }
        return $this->recoverySetRoot($identity) . DIRECTORY_SEPARATOR . 'artifacts' . DIRECTORY_SEPARATOR . $domainIdentifier;
    }
}
