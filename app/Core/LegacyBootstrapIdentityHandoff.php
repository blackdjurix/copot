<?php

namespace Copot\Core;

/** Typed identity-only handoff. It grants no mutation or committed-source authority. */
final class LegacyBootstrapIdentityHandoff
{
    private string $identity;

    public function __construct(
        private LegacyBootstrapSourceArtifact $artifact,
        private TrustedWebcorePackageTarget $target,
        private WebcoreApplyPlan $applyPlan,
        private LegacyBootstrapMigrationInput $migrationInput,
        private CoreMigrationPlan $migrationPlan
    ) {
        if (!$migrationPlan->isAccepted()) throw new \InvalidArgumentException('Bootstrap identity handoff requires an accepted migration plan.');
        $this->identity = hash('sha256', json_encode([
            'operation_identity' => $artifact->operationIdentity(),
            'preparation_plan_identity' => $artifact->planIdentity(),
            'installation_identity' => $artifact->installationIdentity(),
            'package_evidence_identity' => $artifact->packageIdentity(),
            'trusted_target_identity' => $target->packageIdentity(),
            'archive_identity' => $target->archiveIdentity(),
            'apply_plan_identity' => $applyPlan->identity(),
            'source_inventory_fingerprint' => $artifact->sourceInventoryFingerprint(),
            'target_inventory_fingerprint' => $artifact->targetInventoryFingerprint(),
            'migration_input_identity' => $migrationInput->identity(),
            'migration_plan' => self::migrationPlanArray($migrationPlan),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function identity(): string { return $this->identity; }
    public function artifact(): LegacyBootstrapSourceArtifact { return $this->artifact; }
    public function target(): TrustedWebcorePackageTarget { return $this->target; }
    public function applyPlan(): WebcoreApplyPlan { return $this->applyPlan; }
    public function migrationInput(): LegacyBootstrapMigrationInput { return $this->migrationInput; }
    public function migrationPlan(): CoreMigrationPlan { return $this->migrationPlan; }
    public function operationalReady(): bool { return false; }
    public function targetFreshnessVerified(): bool { return false; }
    public function recoveryBound(): bool { return false; }

    public function toArray(): array
    {
        return [
            'handoff_identity' => $this->identity,
            'operation_type' => LegacyBootstrapPreparation::OPERATION_TYPE,
            'operation_identity' => $this->artifact->operationIdentity(),
            'preparation_plan_identity' => $this->artifact->planIdentity(),
            'installation_identity' => $this->artifact->installationIdentity(),
            'package_evidence_identity' => $this->artifact->packageIdentity(),
            'trusted_target' => $this->target->toArray(),
            'apply_plan_identity' => $this->applyPlan->identity(),
            'source_inventory_fingerprint' => $this->artifact->sourceInventoryFingerprint(),
            'target_inventory_fingerprint' => $this->artifact->targetInventoryFingerprint(),
            'migration_input' => $this->migrationInput->toArray(),
            'migration_plan' => self::migrationPlanArray($this->migrationPlan),
            'core_migration_plan_available' => true,
            'migration_execution_authorized' => false,
            'provisional_source_evidence' => true,
            'target_freshness_verified' => false,
            'recovery_bound' => false,
            'operational_ready' => false,
        ];
    }

    private static function migrationPlanArray(CoreMigrationPlan $plan): array
    {
        return [
            'initial_webcore_version' => $plan->initialWebcoreVersion(),
            'virtual_final_webcore_version' => $plan->virtualFinalWebcoreVersion(),
            'initial_schema_identity' => $plan->initialSchemaIdentity(),
            'virtual_final_schema_identity' => $plan->virtualFinalSchemaIdentity(),
            'fresh_baseline' => $plan->isFreshBaseline(),
            'migrations' => array_map(static fn (CoreMigrationDescriptor $migration): array => [
                'id' => $migration->id(), 'sequence' => $migration->sequence(),
                'target_webcore_version' => $migration->targetWebcoreVersion(),
                'target_schema_identity' => $migration->targetSchemaIdentity(), 'checksum' => $migration->checksum(),
            ], $plan->migrations()),
        ];
    }
}
