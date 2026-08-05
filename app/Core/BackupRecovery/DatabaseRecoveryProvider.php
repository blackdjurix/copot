<?php

namespace Copot\Core\BackupRecovery;

interface DatabaseRecoveryProvider
{
    public function capture(DatabaseCaptureContext $context): DatabaseRecoveryArtifact;

    public function verifyCaptured(DatabaseRecoveryArtifact $artifact): DatabaseVerificationResult;

    public function restore(DatabaseRecoveryArtifact $artifact, DatabaseRestoreContext $context): void;

    public function verifyRestored(DatabaseRecoveryArtifact $artifact, DatabaseRestoreContext $context): DatabaseVerificationResult;
}
