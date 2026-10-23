<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\RecoveryIdentity;

final class PreMutationSettlementRequest
{
    public function __construct(
        private string $operationId,
        private RecoveryIdentity $recoveryIdentity,
        private string $manifestIdentity,
        private string $packageIdentity,
        private string $planIdentity,
        private string $policyIdentity,
        private string $targetIdentity,
        private string $databaseIdentity,
        private string $actionJournalIdentity,
        private $freshness,
        private $faultInjector = null
    ) {}

    public function operationId(): string { return $this->operationId; }
    public function recoveryIdentity(): RecoveryIdentity { return $this->recoveryIdentity; }
    public function manifestIdentity(): string { return $this->manifestIdentity; }
    public function packageIdentity(): string { return $this->packageIdentity; }
    public function planIdentity(): string { return $this->planIdentity; }
    public function policyIdentity(): string { return $this->policyIdentity; }
    public function targetIdentity(): string { return $this->targetIdentity; }
    public function databaseIdentity(): string { return $this->databaseIdentity; }
    public function actionJournalIdentity(): string { return $this->actionJournalIdentity; }
    public function assertFresh(LifecycleOperationRecord $operation): void
    {
        if (!is_callable($this->freshness) || !(($this->freshness)($operation, $this))) {
            throw new \RuntimeException('Pre-mutation settlement freshness evidence is invalid.');
        }
    }
    public function fail(string $stage): void { if (is_callable($this->faultInjector)) { ($this->faultInjector)($stage); } }
    public function toArray(): array
    {
        return [
            'operation_id' => $this->operationId, 'recovery_identity' => $this->recoveryIdentity->value(),
            'manifest_identity' => $this->manifestIdentity, 'package_identity' => $this->packageIdentity,
            'plan_identity' => $this->planIdentity, 'policy_identity' => $this->policyIdentity,
            'target_identity' => $this->targetIdentity, 'database_identity' => $this->databaseIdentity,
            'action_journal_identity' => $this->actionJournalIdentity,
        ];
    }
}
