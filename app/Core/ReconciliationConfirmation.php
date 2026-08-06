<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\RecoveryIdentity;

final class ReconciliationConfirmation
{
    private string $bindingIdentity;

    public function __construct(
        private string $operationIdentity,
        private string $planIdentity,
        private RecoveryIdentity $recoveryIdentity,
        private string $targetIdentity
    ) {
        foreach ([$operationIdentity, $planIdentity, $targetIdentity] as $value) {
            if ($value === '' || trim($value) !== $value || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                throw new \InvalidArgumentException('Reconciliation confirmation identity is invalid.');
            }
        }

        $this->bindingIdentity = hash('sha256', json_encode([
            'operation_identity' => $operationIdentity,
            'plan_identity' => $planIdentity,
            'recovery_identity' => $recoveryIdentity->value(),
            'target_identity' => $targetIdentity,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public static function forPlan(LegacyReconciliationPlan $plan, RecoveryIdentity $recoveryIdentity): self
    {
        return new self($plan->operationIdentity(), $plan->identity(), $recoveryIdentity, $plan->target()->packageIdentity());
    }

    public function operationIdentity(): string { return $this->operationIdentity; }
    public function planIdentity(): string { return $this->planIdentity; }
    public function recoveryIdentity(): RecoveryIdentity { return $this->recoveryIdentity; }
    public function targetIdentity(): string { return $this->targetIdentity; }
    public function bindingIdentity(): string { return $this->bindingIdentity; }

    public function matches(LegacyReconciliationPlan $plan, RecoveryIdentity $recoveryIdentity): bool
    {
        return $this->operationIdentity === $plan->operationIdentity()
            && $this->planIdentity === $plan->identity()
            && $this->recoveryIdentity->equals($recoveryIdentity)
            && $this->targetIdentity === $plan->target()->packageIdentity();
    }
}
