<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\CommittedLifecycleState;
use Copot\Core\CommittedSourceAuthorityPlan;

/**
 * Immutable, reread-verified bridge between authority planning and canonical recovery.
 * It grants no mutation authority and cannot publish committed source state.
 */
final class CommittedSourceAuthorityRecoveryBinding
{
    private function __construct(
        private CommittedSourceAuthorityPlan $plan,
        private RecoveryIdentityCrossReference $crossReference,
        private RecoveryManifest $manifest,
        private RecoveryLifecycleRecord $record,
        private ApplicationWriteProtectionEvidence $applicationProtection,
        private ScopedDatabaseConsistencyEvidence $databaseConsistency
    ) {}

    public static function fromCaptured(
        CommittedSourceAuthorityPlan $plan,
        RecoveryManifest $manifest,
        RecoveryLifecycleRecord $record,
        ApplicationWriteProtectionEvidence $applicationProtection,
        ScopedDatabaseConsistencyEvidence $databaseConsistency
    ): self {
        $recovery = NormalWebcoreRecoveryCaptureService::forOperation($plan->operation()->operationId(), $plan->applyPlan()->identity(), $plan->operation()->executionAttemptIdentity());
        $expectedPackage = $plan->sourcePlan()->packageEvidenceIdentity();
        if (!$manifest->recoveryIdentity()->equals($recovery)
            || $manifest->operationIdentity() !== $plan->operation()->operationId()
            || $manifest->targetPackageIdentity() !== $expectedPackage
            || $manifest->targetReleaseIdentity() !== $plan->operation()->releaseIdentity()
            || $manifest->archiveIdentity() !== $plan->operation()->archiveSha256()
            || $manifest->applyPlanIdentity() !== $plan->applyPlan()->identity()
            || $manifest->preOperationLifecycleIdentity() !== $plan->committedLifecycleIdentity()
            || $manifest->preOperationMigrationLedgerIdentity() !== $plan->migrationStateIdentity()) {
            throw new RecoveryLifecycleException('Authority recovery manifest is not bound to the exact plan identities.');
        }
        if ($manifest->executionAttemptIdentity() !== $plan->operation()->executionAttemptIdentity()) {
            throw new RecoveryLifecycleException('Authority recovery manifest is bound to a different execution attempt.');
        }
        if (!$record->recoveryIdentity()->equals($recovery)
            || $record->operationIdentity() !== $plan->operation()->operationId()
            || $record->manifestIdentity() !== $manifest->identity()
            || $record->state() !== RecoveryLifecycleState::READY
            || !$record->captureComplete()
            || $record->mutationStarted()) {
            throw new RecoveryLifecycleException('Authority recovery lifecycle record is not capture-complete and mutation-free.');
        }
        if ($record->executionAttemptIdentity() !== $plan->operation()->executionAttemptIdentity()) {
            throw new RecoveryLifecycleException('Authority recovery lifecycle record is bound to a different execution attempt.');
        }
        if (!$applicationProtection->isValidAt()
            || !$databaseConsistency->isValidAt()
            || !$applicationProtection->operationIdentity()->equals($recovery)
            || !$databaseConsistency->operationIdentity()->equals($recovery)
            || $applicationProtection->scope()->identity() !== $plan->databaseScope()->identity()
            || $databaseConsistency->scope()->identity() !== $plan->databaseScope()->identity()) {
            throw new RecoveryLifecycleException('Authority recovery protection or database evidence is stale or mismatched.');
        }
        $domains = [];
        foreach ($manifest->domainIdentities() as $domain) {
            $domains[$domain->identifier()] = $domain;
        }
        self::assertDomain($domains, 'database.webcore', $plan->databaseScope()->identity());
        self::assertDomain($domains, 'filesystem.package-owned', $plan->applyPlan()->identity());
        self::assertDomain($domains, 'filesystem.lifecycle.committed', $plan->committedLifecycleIdentity());
        self::assertDomain($domains, 'filesystem.lifecycle.installed-lock', $plan->databaseScope()->installationIdentity());

        $crossReference = new RecoveryIdentityCrossReference(
            $plan->operation()->operationId(),
            $recovery,
            $plan->databaseScope()->installationIdentity(),
            $expectedPackage,
            $plan->applyPlan()->identity(),
            $plan->databaseScope(),
            $plan->identity()
        );
        if (!$crossReference->matchesEvidence($applicationProtection, $databaseConsistency)) {
            throw new RecoveryLifecycleException('Authority recovery cross-reference does not match scoped evidence.');
        }
        return new self($plan, $crossReference, $manifest, $record, $applicationProtection, $databaseConsistency);
    }

    public function plan(): CommittedSourceAuthorityPlan { return $this->plan; }
    public function crossReference(): RecoveryIdentityCrossReference { return $this->crossReference; }
    public function manifest(): RecoveryManifest { return $this->manifest; }
    public function record(): RecoveryLifecycleRecord { return $this->record; }
    public function applicationProtection(): ApplicationWriteProtectionEvidence { return $this->applicationProtection; }
    public function databaseConsistency(): ScopedDatabaseConsistencyEvidence { return $this->databaseConsistency; }
    public function mutationAuthorized(): bool { return false; }
    public function toArray(): array
    {
        return [
            'binding_identity' => $this->crossReference->identity(),
            'operation_identity' => $this->crossReference->operationIdentity(),
            'recovery_identity' => $this->crossReference->recoveryIdentity()->value(),
            'manifest_identity' => $this->manifest->identity(),
            'cross_reference_identity' => $this->crossReference->identity(),
            'database_scope_identity' => $this->plan->databaseScope()->identity(),
            'mutation_authorized' => false,
            'wu6_required' => true,
        ];
    }

    private static function assertDomain(array $domains, string $identifier, string $scope): void
    {
        $domain = $domains[$identifier] ?? null;
        if (!$domain instanceof RecoveryDomainIdentity || $domain->scopeIdentity() !== $scope) {
            throw new RecoveryLifecycleException('Authority recovery manifest is missing a required domain binding.');
        }
    }
}
