<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\SourceInventoryReference;

final class ReusableRecoveryPointCaptureRequest
{
    public function __construct(
        private string $installationIdentity,
        private string $originatingOperationIdentity,
        private SourceInventoryReference $sourceInventoryReference,
        private string $creationIdentity,
        private ?string $createdAt = null
    ) {
        if (preg_match('/^inst_[a-f0-9]{32}$/D', $installationIdentity) !== 1) throw new RecoveryLifecycleException('Reusable recovery-point installation identity is invalid.');
        foreach ([$originatingOperationIdentity, $creationIdentity] as $value) if ($value === '' || trim($value) !== $value || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) throw new RecoveryLifecycleException('Reusable recovery-point request identity is invalid.');
        $this->createdAt ??= gmdate(\DATE_ATOM);
        if (\DateTimeImmutable::createFromFormat(\DATE_ATOM, $this->createdAt) === false) throw new RecoveryLifecycleException('Reusable recovery-point creation timestamp is invalid.');
    }
    public function installationIdentity(): string { return $this->installationIdentity; }
    public function originatingOperationIdentity(): string { return $this->originatingOperationIdentity; }
    public function sourceInventoryReference(): SourceInventoryReference { return $this->sourceInventoryReference; }
    public function creationIdentity(): string { return $this->creationIdentity; }
    public function createdAt(): string { return $this->createdAt; }
}

