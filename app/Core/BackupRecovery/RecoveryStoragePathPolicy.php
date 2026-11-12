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

    public function reusableRecoveryPointsRoot(): string
    {
        return $this->storageRoot->path() . DIRECTORY_SEPARATOR . 'recovery-points';
    }

    public function reusableRecoveryPointRoot(ReusableRecoveryPointIdentity $identity): string
    {
        return $this->reusableRecoveryPointsRoot() . DIRECTORY_SEPARATOR . hash('sha256', $identity->value());
    }

    public function reusableRecoveryPointManifestPath(ReusableRecoveryPointIdentity $identity): string
    {
        return $this->reusableRecoveryPointRoot($identity) . DIRECTORY_SEPARATOR . 'manifest' . DIRECTORY_SEPARATOR . 'recovery-point-manifest.json';
    }

    public function reusableRecoveryPointArtifactPath(ReusableRecoveryPointIdentity $identity, RecoveryArtifactRecord $artifact): string
    {
        return $this->reusableRecoveryPointArtifactDirectory($identity, $artifact->domainIdentifier()) . DIRECTORY_SEPARATOR . $artifact->artifactIdentity() . '.bin';
    }

    public function reusableRecoveryPointArtifactMetadataPath(ReusableRecoveryPointIdentity $identity, RecoveryArtifactRecord $artifact): string
    {
        return $this->reusableRecoveryPointArtifactDirectory($identity, $artifact->domainIdentifier()) . DIRECTORY_SEPARATOR . $artifact->artifactIdentity() . '.json';
    }

    public function reusableRecoveryPointAttemptPath(ReusableRecoveryPointIdentity $point, ReusableRecoveryPointAttemptIdentity $attempt): string
    {
        return $this->reusableRecoveryPointRoot($point) . DIRECTORY_SEPARATOR . 'attempts' . DIRECTORY_SEPARATOR . $attempt->value() . '.json';
    }

    public function reusableRecoveryPointActiveAttemptPath(string $installationIdentity): string
    {
        if (preg_match('/^inst_[a-f0-9]{32}$/D', $installationIdentity) !== 1) {
            throw new RecoveryStorageException('Reusable recovery installation identity is invalid.');
        }
        return $this->reusableRecoveryPointsRoot() . DIRECTORY_SEPARATOR . 'active-attempts' . DIRECTORY_SEPARATOR . hash('sha256', $installationIdentity) . '.json';
    }

    private function artifactDirectory(RecoveryIdentity $identity, string $domainIdentifier): string
    {
        if (preg_match('/^[a-z][a-z0-9._-]*$/D', $domainIdentifier) !== 1) {
            throw new RecoveryStorageException('Recovery domain path segment is invalid.');
        }
        return $this->recoverySetRoot($identity) . DIRECTORY_SEPARATOR . 'artifacts' . DIRECTORY_SEPARATOR . $domainIdentifier;
    }

    private function reusableRecoveryPointArtifactDirectory(ReusableRecoveryPointIdentity $identity, string $domainIdentifier): string
    {
        if (preg_match('/^[a-z][a-z0-9._-]*$/D', $domainIdentifier) !== 1) {
            throw new RecoveryStorageException('Recovery-point domain path segment is invalid.');
        }
        return $this->reusableRecoveryPointRoot($identity) . DIRECTORY_SEPARATOR . 'artifacts' . DIRECTORY_SEPARATOR . $domainIdentifier;
    }
}
