<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\DatabaseScope;

/** Immutable planning result. It contains no executable mutation authority. */
final class CommittedSourceAuthorityPlan
{
    public const AUTHORITY_ONLY = 'AUTHORITY_ONLY';
    public const MUTATION_REQUIRED = 'MUTATION_REQUIRED';

    public function __construct(
        private string $identity,
        private LifecycleOperationRecord $operation,
        private V3SourceRelativePlan $sourcePlan,
        private WebcoreApplyPlan $applyPlan,
        private DatabaseScope $databaseScope,
        private bool $authorityOnly,
        private string $committedLifecycleIdentity,
        private string $migrationStateIdentity,
        private string $mode = self::AUTHORITY_ONLY,
        private ?string $currentTargetInventoryFingerprint = null,
        private ?string $desiredTargetInventoryFingerprint = null,
        private ?string $migrationPlanIdentity = null,
        private ?CommittedSourceAuthorityPreservationPolicy $preservationPolicy = null
    ) {
        if (preg_match('/^[a-f0-9]{64}$/D', $identity) !== 1
            || $operation->classification() !== 'COMMITTED_SOURCE_AUTHORITY_RECONCILIATION'
            || $operation->phase() !== LifecycleOperationRecord::PREPARING
            || $operation->applyPlanIdentity() !== $applyPlan->identity()
            || preg_match('/^[a-f0-9]{64}$/D', $committedLifecycleIdentity) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $migrationStateIdentity) !== 1
            || !in_array($mode, [self::AUTHORITY_ONLY, self::MUTATION_REQUIRED], true)
            || ($mode === self::AUTHORITY_ONLY) !== $authorityOnly
            || ($mode === self::MUTATION_REQUIRED && !is_string($migrationPlanIdentity))
            || ($currentTargetInventoryFingerprint !== null && preg_match('/^[a-f0-9]{64}$/D', $currentTargetInventoryFingerprint) !== 1)
            || ($desiredTargetInventoryFingerprint !== null && preg_match('/^[a-f0-9]{64}$/D', $desiredTargetInventoryFingerprint) !== 1)
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
    public function mutationRequired(): bool { return $this->mode === self::MUTATION_REQUIRED; }
    public function mode(): string { return $this->mode; }
    public function committedLifecycleIdentity(): string { return $this->committedLifecycleIdentity; }
    public function migrationStateIdentity(): string { return $this->migrationStateIdentity; }
    public function currentTargetInventoryFingerprint(): string { return $this->currentTargetInventoryFingerprint ?? $this->sourcePlan->targetFingerprint(); }
    public function desiredTargetInventoryFingerprint(): string { return $this->desiredTargetInventoryFingerprint ?? $this->sourcePlan->targetFingerprint(); }
    public function migrationPlanIdentity(): ?string { return $this->migrationPlanIdentity; }
    public function preservationPolicy(): ?CommittedSourceAuthorityPreservationPolicy { return $this->preservationPolicy; }

    public function assertTargetFingerprint(CanonicalTargetInventory $actual): void
    {
        if (!hash_equals($this->currentTargetInventoryFingerprint(), $actual->fingerprint())) {
            throw new \RuntimeException('Target inventory changed after committed source-authority planning.');
        }
    }

    public function actionCounts(): array
    {
        $counts = ['CREATE' => 0, 'REPLACE' => 0, 'PRESERVE' => 0];
        foreach ($this->sourcePlan->actions() as $action) {
            $key = match ($action->action()) {
                V3SourceRelativeAction::DELIVER => 'CREATE',
                V3SourceRelativeAction::REPLACE => 'REPLACE',
                V3SourceRelativeAction::RETAIN => 'PRESERVE',
                default => null,
            };
            if ($key !== null) { $counts[$key]++; }
        }
        return $counts;
    }
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
            'mode' => $this->mode,
            'current_target_inventory_fingerprint' => $this->currentTargetInventoryFingerprint(),
            'desired_target_inventory_fingerprint' => $this->desiredTargetInventoryFingerprint(),
            'migration_plan_identity' => $this->migrationPlanIdentity,
            'preservation_policy' => $this->preservationPolicy?->toArray(),
            'action_counts' => $this->actionCounts(),
            'authority_only' => $this->authorityOnly,
            'mutation_executed' => false,
            'recovery_required' => true,
            'wu6_required' => true,
        ];
    }
}
