<?php

namespace Copot\Core;

final class LegacyReconciliationPlan
{
    private string $identity;
    private string $operationIdentity;

    public function __construct(
        private TrustedWebcorePackageTarget $target,
        private LegacyClassificationResult $classification,
        private array $filesystemActions,
        private CoreMigrationPlan $migrationPlan,
        private string $preStateIdentity,
        private string $expectedPostStateIdentity,
        private string $expectedMigrationStateIdentity
    ) {
        foreach ($filesystemActions as $action) {
            if (!$action instanceof FilesystemReconciliationAction) {
                throw new \InvalidArgumentException('Reconciliation plan contains an invalid filesystem action.');
            }
        }
        if (!$classification->isLegacyCandidate() || !$migrationPlan->isAccepted()) {
            throw new \InvalidArgumentException('Reconciliation plan requires an accepted legacy candidate and migration plan.');
        }

        $body = [
            'trusted_target' => $target->toArray(),
            'source_classification' => $classification->classification(),
            'source_webcore_version' => $classification->sourceWebcoreVersion(),
            'source_schema_identity' => $classification->sourceSchemaIdentity(),
            'filesystem_actions' => array_map(static fn (FilesystemReconciliationAction $action): array => $action->toArray(), $filesystemActions),
            'migration_plan' => self::migrationArray($migrationPlan),
            'pre_state_identity' => $preStateIdentity,
            'expected_post_state_identity' => $expectedPostStateIdentity,
            'expected_migration_state_identity' => $expectedMigrationStateIdentity,
        ];
        $this->identity = self::hash($body);
        $this->operationIdentity = self::hash(['reconciliation_operation' => $this->identity]);
    }

    public function identity(): string { return $this->identity; }
    public function operationIdentity(): string { return $this->operationIdentity; }
    public function target(): TrustedWebcorePackageTarget { return $this->target; }
    public function classification(): LegacyClassificationResult { return $this->classification; }
    public function filesystemActions(): array { return $this->filesystemActions; }
    public function migrationPlan(): CoreMigrationPlan { return $this->migrationPlan; }
    public function preStateIdentity(): string { return $this->preStateIdentity; }
    public function expectedPostStateIdentity(): string { return $this->expectedPostStateIdentity; }
    public function expectedMigrationStateIdentity(): string { return $this->expectedMigrationStateIdentity; }

    public function toArray(): array
    {
        return [
            'operation_identity' => $this->operationIdentity,
            'plan_identity' => $this->identity,
            'trusted_target' => $this->target->toArray(),
            'source_classification' => $this->classification->classification(),
            'source_webcore_version' => $this->classification->sourceWebcoreVersion(),
            'source_schema_identity' => $this->classification->sourceSchemaIdentity(),
            'filesystem_actions' => array_map(static fn (FilesystemReconciliationAction $action): array => $action->toArray(), $this->filesystemActions),
            'migration_plan' => self::migrationArray($this->migrationPlan),
            'pre_state_identity' => $this->preStateIdentity,
            'expected_post_state_identity' => $this->expectedPostStateIdentity,
            'expected_migration_state_identity' => $this->expectedMigrationStateIdentity,
        ];
    }

    private static function migrationArray(CoreMigrationPlan $plan): array
    {
        return [
            'initial_webcore_version' => $plan->initialWebcoreVersion(),
            'virtual_final_webcore_version' => $plan->virtualFinalWebcoreVersion(),
            'initial_schema_identity' => $plan->initialSchemaIdentity(),
            'virtual_final_schema_identity' => $plan->virtualFinalSchemaIdentity(),
            'fresh_baseline' => $plan->isFreshBaseline(),
            'migrations' => array_map(static fn (CoreMigrationDescriptor $migration): array => [
                'id' => $migration->id(),
                'sequence' => $migration->sequence(),
                'target_webcore_version' => $migration->targetWebcoreVersion(),
                'target_schema_identity' => $migration->targetSchemaIdentity(),
                'checksum' => $migration->checksum(),
            ], $plan->migrations()),
        ];
    }

    private static function hash(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
