<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\RecoveryIdentity;

/** Typed bridge between provisional bootstrap evidence and canonical V3 execution. */
final class LegacyBootstrapV3ExecutionContext
{
    private string $identity;

    public function __construct(
        private string $installationIdentity,
        private LifecycleOperationRecord $operation,
        private LegacyBootstrapIdentityHandoff $handoff,
        private V3SourceRelativePlan $plan,
        private RecoveryIdentity $recovery,
        private LegacyBootstrapRecoveryBindingResult $recoveryBinding
    ) {
        $artifact = $handoff->artifact();
        if ($installationIdentity !== $artifact->installationIdentity()
            || $operation->classification() !== LegacyBootstrapPreparation::OPERATION_TYPE
            || $operation->operationId() !== $artifact->operationIdentity()
            || $operation->applyPlanIdentity() !== $handoff->applyPlan()->identity()
            || $operation->archiveSha256() !== $handoff->target()->archiveIdentity()
            || $operation->payloadIdentity() !== $handoff->target()->payloadIdentity()
            || $plan->targetFingerprint() !== $artifact->targetInventoryFingerprint()
            || $plan->sourceReference() !== null
            || $plan->coverage() !== 'full'
            || $operation->operationId() === $plan->operationIdentity()
            || $operation->recoveryIdentity() !== $recovery->value()
            || $operation->recoveryManifestIdentity() === null
            || $operation->recoveryState() !== \Copot\Core\BackupRecovery\RecoveryLifecycleState::READY
            || !$recoveryBinding->bound()
            || $recoveryBinding->mutationAuthorized()
            || $recoveryBinding->operationalReady()
        ) {
            throw new \InvalidArgumentException('Bootstrap V3 execution context identity or authority is invalid.');
        }
        foreach ($plan->actions() as $action) {
            if (!$action instanceof V3SourceRelativeAction || $action->action() !== V3SourceRelativeAction::DELIVER) {
                throw new \InvalidArgumentException('Bootstrap V3 execution requires a full-coverage deliver-only plan.');
            }
        }
        $binding = $recoveryBinding->toArray();
        if ($binding['operation_identity'] !== $operation->operationId() || $binding['recovery_identity'] !== $recovery->value() || $binding['manifest_identity'] !== $operation->recoveryManifestIdentity()) {
            throw new \InvalidArgumentException('Bootstrap recovery binding does not match the execution context.');
        }
        $this->identity = hash('sha256', json_encode([
            'installation_identity' => $installationIdentity,
            'bootstrap_operation_identity' => $operation->operationId(),
            'v3_plan_identity' => $plan->identity(),
            'recovery_identity' => $recovery->value(),
            'recovery_binding_identity' => $recoveryBinding->identity(),
            'source_fingerprint' => $artifact->sourceInventoryFingerprint(),
            'target_fingerprint' => $artifact->targetInventoryFingerprint(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function identity(): string { return $this->identity; }
    public function installationIdentity(): string { return $this->installationIdentity; }
    public function operation(): LifecycleOperationRecord { return $this->operation; }
    public function handoff(): LegacyBootstrapIdentityHandoff { return $this->handoff; }
    public function plan(): V3SourceRelativePlan { return $this->plan; }
    public function recovery(): RecoveryIdentity { return $this->recovery; }
    public function recoveryBinding(): LegacyBootstrapRecoveryBindingResult { return $this->recoveryBinding; }
    public function provisionalSourceFingerprint(): string { return $this->handoff->artifact()->sourceInventoryFingerprint(); }
    public function operationalReady(): bool { return false; }
    public function mutationAuthorized(): bool { return false; }

    public function toArray(): array
    {
        return [
            'execution_context_identity' => $this->identity,
            'operation_type' => LegacyBootstrapPreparation::OPERATION_TYPE,
            'installation_identity' => $this->installationIdentity,
            'bootstrap_operation_identity' => $this->operation->operationId(),
            'v3_plan_identity' => $this->plan->identity(),
            'recovery_identity' => $this->recovery->value(),
            'provisional_source_fingerprint' => $this->provisionalSourceFingerprint(),
            'target_inventory_fingerprint' => $this->handoff->artifact()->targetInventoryFingerprint(),
            'recovery_binding_identity' => $this->recoveryBinding->identity(),
            'provisional_source_evidence' => true,
            'mutation_authorized' => false,
            'operational_ready' => false,
        ];
    }
}
