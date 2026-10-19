<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\ApplicationWriteProtectionEvidence;
use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\BackupRecovery\InstallationScopedMaintenanceGuard;
use Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureService;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\ScopedDatabaseConsistencyEvidence;

/** Validates already-established canonical scoped protection without activating it. */
final class LegacyBootstrapScopedProtectionComposer
{
    public function __construct(
        private LegacyBootstrapTargetFreshnessVerifier $freshnessVerifier,
        private InstallationScopedMaintenanceGuard $guard,
        private DatabaseScope $expectedDatabaseScope
    ) {}

    public function compose(
        LegacyBootstrapIdentityHandoff $handoff,
        ApplicationWriteProtectionEvidence $application,
        ScopedDatabaseConsistencyEvidence $database
    ): LegacyBootstrapProtectionCompositionResult {
        $at = time();
        $artifact = $handoff->artifact();
        $bootstrapOperation = new RecoveryIdentity($artifact->operationIdentity());
        $recoveryOperation = NormalWebcoreRecoveryCaptureService::forOperation($artifact->operationIdentity(), $handoff->applyPlan()->identity());
        $scopedOperation = $application->operationIdentity();
        $diagnostics = [];

        if (!hash_equals($this->expectedDatabaseScope->installationIdentity(), $artifact->installationIdentity())) {
            $diagnostics[] = 'Expected database scope is bound to a different installation.';
        }
        if ((!$application->operationIdentity()->equals($bootstrapOperation) && !$application->operationIdentity()->equals($recoveryOperation))
            || !$database->operationIdentity()->equals($scopedOperation)) {
            $diagnostics[] = 'Protection evidence operation identity does not match the bootstrap/recovery cross-reference.';
        }
        if (!hash_equals($this->expectedDatabaseScope->identity(), $application->scope()->identity())) {
            $diagnostics[] = 'Application protection database scope does not match the expected installation scope.';
        }
        if (!hash_equals($this->expectedDatabaseScope->identity(), $database->scope()->identity())) {
            $diagnostics[] = 'Database consistency namespace or tableset does not match the expected installation scope.';
        }
        if ($diagnostics !== []) {
            return $this->result(LegacyBootstrapProtectionCompositionResult::INCOMPLETE, $handoff, $application, $database, null, $diagnostics);
        }
        if (!$application->isValidAt($at)) {
            return $this->result(LegacyBootstrapProtectionCompositionResult::STALE, $handoff, $application, $database, null, ['Application write protection evidence is expired or not yet valid.']);
        }
        if (!$database->isValidAt($at)) {
            return $this->result(LegacyBootstrapProtectionCompositionResult::STALE, $handoff, $application, $database, null, ['Scoped database consistency evidence is expired or not yet valid.']);
        }
        if (!$this->guard->scopedActive($scopedOperation, $application)) {
            return $this->result(LegacyBootstrapProtectionCompositionResult::INCOMPLETE, $handoff, $application, $database, null, ['Installation-scoped application write protection is not durably active for this evidence.']);
        }

        $freshness = $this->freshnessVerifier->verify($handoff);
        if ($freshness->status() === LegacyBootstrapTargetFreshnessResult::STALE) {
            return $this->result(LegacyBootstrapProtectionCompositionResult::STALE, $handoff, $application, $database, $freshness, $freshness->diagnostics());
        }
        if ($freshness->status() !== LegacyBootstrapTargetFreshnessResult::MATCHED) {
            return $this->result(LegacyBootstrapProtectionCompositionResult::UNVERIFIED, $handoff, $application, $database, $freshness, $freshness->diagnostics());
        }
        return $this->result(LegacyBootstrapProtectionCompositionResult::VALID, $handoff, $application, $database, $freshness, []);
    }

    /** @param list<string> $diagnostics */
    private function result(string $status, LegacyBootstrapIdentityHandoff $handoff, ApplicationWriteProtectionEvidence $application, ScopedDatabaseConsistencyEvidence $database, ?LegacyBootstrapTargetFreshnessResult $freshness, array $diagnostics): LegacyBootstrapProtectionCompositionResult
    {
        $artifact = $handoff->artifact();
        return new LegacyBootstrapProtectionCompositionResult(
            $status, $artifact->installationIdentity(), $artifact->operationIdentity(), $artifact->planIdentity(),
            $artifact->targetInventoryFingerprint(), $handoff->identity(), $this->expectedDatabaseScope->identity(),
            $application->identity(), $database->identity(), $freshness?->identity(), $diagnostics
        );
    }
}
