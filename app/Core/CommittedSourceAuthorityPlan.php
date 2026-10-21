<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\DatabaseScope;

/** Immutable planning result. It contains no executable mutation authority. */
final class CommittedSourceAuthorityPlan
{
    public function __construct(
        private string $identity,
        private LifecycleOperationRecord $operation,
        private V3SourceRelativePlan $sourcePlan,
        private WebcoreApplyPlan $applyPlan,
        private DatabaseScope $databaseScope,
        private bool $authorityOnly,
        private string $committedLifecycleIdentity,
        private string $migrationStateIdentity
    ) {
        if (preg_match('/^[a-f0-9]{64}$/D', $identity) !== 1
            || $operation->classification() !== 'COMMITTED_SOURCE_AUTHORITY_RECONCILIATION'
            || $operation->phase() !== LifecycleOperationRecord::PREPARING
            || $operation->applyPlanIdentity() !== $applyPlan->identity()
            || preg_match('/^[a-f0-9]{64}$/D', $committedLifecycleIdentity) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $migrationStateIdentity) !== 1
            || ($authorityOnly && array_filter($sourcePlan->actions(), static fn (V3SourceRelativeAction $action): bool => in_array($action->action(), [V3SourceRelativeAction::REPLACE, V3SourceRelativeAction::REMOVE, V3SourceRelativeAction::RENAME], true)) !== [])) {
            throw new \InvalidArgumentException('Committed source-authority plan identity is invalid.');
        }
    }

    public function identity(): string { return $this->identity; }
    public function operation(): LifecycleOperationRecord { return $this->operation; }
    public function sourcePlan(): V3SourceRelativePlan { return $this->sourcePlan; }
    public function applyPlan(): WebcoreApplyPlan { return $this->applyPlan; }
    public function databaseScope(): DatabaseScope { return $this->databaseScope; }
    public function authorityOnly(): bool { return $this->authorityOnly; }
    public function committedLifecycleIdentity(): string { return $this->committedLifecycleIdentity; }
    public function migrationStateIdentity(): string { return $this->migrationStateIdentity; }
    public function toArray(): array
    {
        return [
            'plan_identity' => $this->identity,
            'operation' => $this->operation->toArray(),
            'source_plan_identity' => $this->sourcePlan->identity(),
            'apply_plan_identity' => $this->applyPlan->identity(),
            'installation_identity' => $this->databaseScope->installationIdentity(),
            'database_scope_identity' => $this->databaseScope->identity(),
            'target_inventory_fingerprint' => $this->sourcePlan->targetFingerprint(),
            'committed_lifecycle_identity' => $this->committedLifecycleIdentity,
            'migration_state_identity' => $this->migrationStateIdentity,
            'authority_only' => $this->authorityOnly,
            'mutation_executed' => false,
            'recovery_required' => true,
            'wu6_required' => true,
        ];
    }
}
