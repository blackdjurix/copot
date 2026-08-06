<?php

namespace Copot\Core;

use PDO;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;
use Copot\Core\BackupRecovery\RecoveryManifest;

final class LegacyReconciliationFinalizer
{
    public function __construct(
        private RecoveryLifecycleStore $recoveryStore,
        private TargetPackageIntegrityVerifier $integrity,
        private DatabaseHealthVerifier $databaseHealth,
        private CoreMigrationHealthVerifier $migrationHealth,
        private RuntimeHealthVerifier $runtimeHealth
    ) {}

    /**
     * @param array<string, callable():bool|string> $runtimeChecks
     */
    public function finalize(
        LegacyReconciliationPlan $plan,
        RecoveryManifest $manifest,
        ReconciliationMutationEligibility $eligibility,
        WebcoreApplyResult $filesystemResult,
        LegacyReconciliationDatabaseResult $databaseResult,
        InstallationState $installation,
        CommittedLifecycleStateStore $committedStore,
        LiveTreePathGuard $liveGuard,
        PDO $connection,
        CoreMigrationRegistry $migrationRegistry,
        array $runtimeChecks,
        ?callable $beforeLifecycleWrite = null,
        ?callable $afterLifecycleWrite = null
    ): LegacyReconciliationFinalizationResult {
        try {
            $this->assertBinding($plan, $manifest, $eligibility, $filesystemResult, $databaseResult);
            $record = $this->recoveryStore->read($eligibility->recoveryIdentity());
            if ($record->state() !== RecoveryLifecycleState::READY || !$record->captureComplete()
                || !$record->confirmationMatches($eligibility->recoveryIdentity(), $manifest->identity(), $eligibility->confirmationIdentity())) {
                throw new \RuntimeException('Finalization recovery state is not READY with exact confirmation.');
            }

            $gates = $this->verificationGates($plan, $databaseResult, $installation, $committedStore, $liveGuard, $connection, $migrationRegistry, $runtimeChecks);
            if (!$gates->passed()) {
                $this->markRecoveryRequired($eligibility, $gates->failureReason());
                return new LegacyReconciliationFinalizationResult(LegacyReconciliationFinalizationResult::FAILED, $gates, $gates->failureReason());
            }

            if (!$eligibility->isValid()) {
                throw new \RuntimeException('Finalization requires an active quiescence lease.');
            }

            $existing = $committedStore->read();
            $marker = $installation->readMarker();
            if ($existing instanceof CommittedLifecycleState) {
                if (!$this->stateMatches($existing, $plan, $databaseResult)) {
                    throw new \RuntimeException('Existing committed lifecycle state has a different trusted identity.');
                }
                if (!is_array($marker)
                    || $marker['version'] !== $plan->target()->contract()->targetWebcoreVersion()
                    || $marker['installed_at'] !== $existing->committedAt()->format(DATE_ATOM)) {
                    throw new \RuntimeException('Existing committed lifecycle state does not match installed.lock.');
                }
                return new LegacyReconciliationFinalizationResult(LegacyReconciliationFinalizationResult::ALREADY_FINALIZED, $gates);
            }

            if (!$record->mutationStarted()) {
                $this->recoveryStore->markMutationStarting($eligibility->recoveryIdentity());
            }

            $committedAt = new \DateTimeImmutable('now');
            $installation->replaceMarker($plan->target()->contract()->targetWebcoreVersion(), $committedAt->format(DATE_ATOM));

            if (!$eligibility->isValid()) {
                throw new \RuntimeException('Quiescence expired after installed-state reconciliation.');
            }

            if ($beforeLifecycleWrite !== null) {
                $beforeLifecycleWrite();
            }

            $state = new CommittedLifecycleState(
                $plan->target()->contract()->targetWebcoreVersion(),
                $plan->target()->contract()->releaseIdentity(),
                $plan->target()->contract()->sourceTreeIdentity(),
                $plan->target()->contract()->manifestContractVersion(),
                $databaseResult->schemaIdentity(),
                $databaseResult->migrationStateIdentity(),
                $committedAt,
                $plan->target()->inventoryIdentity()
            );
            $committedStore->write($state);

            if ($afterLifecycleWrite !== null) {
                $afterLifecycleWrite();
            }

            $finalMarker = $installation->readMarker();
            $finalState = $committedStore->read();
            if (!is_array($finalMarker) || !$finalState instanceof CommittedLifecycleState
                || $finalMarker['version'] !== $state->webcoreVersion()
                || $finalMarker['installed_at'] !== $state->committedAt()->format(DATE_ATOM)
                || !$this->stateMatches($finalState, $plan, $databaseResult)) {
                throw new \RuntimeException('Final installed state and committed lifecycle state are inconsistent.');
            }

            return new LegacyReconciliationFinalizationResult(LegacyReconciliationFinalizationResult::COMPLETED, $gates);
        } catch (\Throwable $exception) {
            $this->markRecoveryRequired($eligibility, $exception->getMessage());
            return new LegacyReconciliationFinalizationResult(LegacyReconciliationFinalizationResult::FAILED, new HealthGateMatrix([HealthGateResult::fail('finalization', $exception->getMessage())]), $exception->getMessage());
        }
    }

    private function assertBinding(LegacyReconciliationPlan $plan, RecoveryManifest $manifest, ReconciliationMutationEligibility $eligibility, WebcoreApplyResult $filesystemResult, LegacyReconciliationDatabaseResult $databaseResult): void
    {
        $target = $plan->target();
        if (!$eligibility->isValid()
            || $filesystemResult->status() !== WebcoreApplyResult::COMPLETED
            || $databaseResult->status() !== LegacyReconciliationDatabaseResult::COMPLETED
            || !$eligibility->recoveryIdentity()->equals($manifest->recoveryIdentity())
            || $eligibility->operationIdentity() !== $plan->operationIdentity()
            || $eligibility->planIdentity() !== $plan->identity()
            || $eligibility->manifestIdentity() !== $manifest->identity()
            || $eligibility->targetIdentity() !== $target->packageIdentity()
            || $manifest->operationIdentity() !== $plan->operationIdentity()
            || $manifest->targetPackageIdentity() !== $target->packageIdentity()
            || $manifest->targetReleaseIdentity() !== $target->contract()->releaseIdentity()
            || $manifest->archiveIdentity() !== $target->archiveIdentity()
            || $manifest->applyPlanIdentity() !== $target->payloadIdentity()
            || $databaseResult->schemaIdentity() !== $plan->migrationPlan()->virtualFinalSchemaIdentity()
            || $databaseResult->migrationStateIdentity() !== $plan->expectedMigrationStateIdentity()) {
            throw new \RuntimeException('Finalization requires exact WU1/WU2/WU3/WU4 identity-bound evidence.');
        }
    }

    private function verificationGates(LegacyReconciliationPlan $plan, LegacyReconciliationDatabaseResult $databaseResult, InstallationState $installation, CommittedLifecycleStateStore $committedStore, LiveTreePathGuard $liveGuard, PDO $connection, CoreMigrationRegistry $migrationRegistry, array $runtimeChecks): HealthGateMatrix
    {
        $target = $plan->target();
        $gates = [
            $databaseResult->schemaIdentity() === $plan->migrationPlan()->virtualFinalSchemaIdentity() ? HealthGateResult::pass('expected-schema-identity') : HealthGateResult::fail('expected-schema-identity', 'Final schema identity does not match the plan.'),
            $databaseResult->migrationStateIdentity() === $plan->expectedMigrationStateIdentity() ? HealthGateResult::pass('expected-migration-identity') : HealthGateResult::fail('expected-migration-identity', 'Final migration identity does not match the plan.'),
            $target->contract()->targetWebcoreVersion() !== '' ? HealthGateResult::pass('target-version') : HealthGateResult::fail('target-version', 'Trusted target version is unavailable.'),
            $target->contract()->releaseIdentity() !== '' && $target->archiveIdentity() !== '' && $target->packageIdentity() !== '' ? HealthGateResult::pass('target-identities') : HealthGateResult::fail('target-identities', 'Trusted package identities are incomplete.'),
        ];
        $gates = array_merge($gates, $this->integrity->verify($target->contract(), $liveGuard)->gates(), $this->databaseHealth->verify($connection)->gates(), $this->migrationHealth->verify($connection, $migrationRegistry, $databaseResult->migrationStateIdentity())->gates(), $this->runtimeHealth->verify($runtimeChecks)->gates());
        return new HealthGateMatrix($gates);
    }

    private function stateMatches(CommittedLifecycleState $state, LegacyReconciliationPlan $plan, LegacyReconciliationDatabaseResult $databaseResult): bool
    {
        $contract = $plan->target()->contract();
        return $state->webcoreVersion() === $contract->targetWebcoreVersion()
            && $state->releaseIdentity() === $contract->releaseIdentity()
            && $state->sourceTreeIdentity() === $contract->sourceTreeIdentity()
            && $state->manifestContractVersion() === $contract->manifestContractVersion()
            && $state->schemaStateIdentity() === $databaseResult->schemaIdentity()
            && $state->migrationStateIdentity() === $databaseResult->migrationStateIdentity()
            && $state->packageIntegrityIdentity() === $plan->target()->inventoryIdentity();
    }

    private function markRecoveryRequired(ReconciliationMutationEligibility $eligibility, string $reason): void
    {
        try {
            $record = $this->recoveryStore->read($eligibility->recoveryIdentity());
            if ($record->mutationStarted() && $record->state() === RecoveryLifecycleState::READY) {
                $this->recoveryStore->transition($eligibility->recoveryIdentity(), RecoveryLifecycleState::RESTORE_REQUIRED, $reason);
            }
        } catch (\Throwable) {
            // Preserve the finalization failure; durable recovery remains fail-closed.
        }
    }
}
