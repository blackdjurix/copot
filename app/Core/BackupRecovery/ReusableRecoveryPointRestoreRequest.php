<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\DestructiveActorAuthorizationEvidence;
use Copot\Core\SourceInventoryReference;

final class ReusableRecoveryPointRestoreRequest
{
    public function __construct(
        private ReusableRecoveryPointIdentity $pointIdentity,
        private string $installationIdentity,
        private SourceInventoryReference $expectedCurrentSourceInventory,
        private string $invocationIdentity,
        private DestructiveActorAuthorizationEvidence $authorization,
        private ?ReusableRecoveryPointAttemptIdentity $attemptIdentity = null,
        private ?string $createdAt = null
    ) {
        if (preg_match('/^inst_[a-f0-9]{32}$/D', $installationIdentity) !== 1 || $invocationIdentity === '' || trim($invocationIdentity) !== $invocationIdentity || preg_match('/[\x00-\x1F\x7F]/', $invocationIdentity) === 1) throw new RecoveryLifecycleException('Reusable recovery-point restore request identity is invalid.');
        $this->createdAt ??= gmdate(\DATE_ATOM);
        if (\DateTimeImmutable::createFromFormat(\DATE_ATOM, $this->createdAt) === false) throw new RecoveryLifecycleException('Reusable recovery-point restore attempt timestamp is invalid.');
    }
    public function pointIdentity(): ReusableRecoveryPointIdentity { return $this->pointIdentity; }
    public function installationIdentity(): string { return $this->installationIdentity; }
    public function expectedCurrentSourceInventory(): SourceInventoryReference { return $this->expectedCurrentSourceInventory; }
    public function invocationIdentity(): string { return $this->invocationIdentity; }
    public function authorization(): DestructiveActorAuthorizationEvidence { return $this->authorization; }
    public function attemptIdentity(): ?ReusableRecoveryPointAttemptIdentity { return $this->attemptIdentity; }
    public function createdAt(): string { return $this->createdAt; }
}

