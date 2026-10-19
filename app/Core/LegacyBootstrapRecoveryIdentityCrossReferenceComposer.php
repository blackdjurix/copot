<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\ApplicationWriteProtectionEvidence;
use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureService;
use Copot\Core\BackupRecovery\RecoveryDomainIdentity;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryIdentityCrossReference;
use Copot\Core\BackupRecovery\RecoveryManifest;
use Copot\Core\BackupRecovery\ScopedDatabaseConsistencyEvidence;

/** Reconstructs operation/recovery identity linkage from canonical durable evidence. */
final class LegacyBootstrapRecoveryIdentityCrossReferenceComposer
{
    public function compose(
        LegacyBootstrapIdentityHandoff $handoff,
        LifecycleOperationRecord $operation,
        RecoveryManifest $manifest,
        DatabaseScope $databaseScope,
        ApplicationWriteProtectionEvidence $application,
        ScopedDatabaseConsistencyEvidence $database,
        string $deploymentIdentity
    ): LegacyBootstrapRecoveryIdentityCrossReferenceResult {
        $artifact = $handoff->artifact();
        $diagnostics = [];
        try {
            $recovery = NormalWebcoreRecoveryCaptureService::forOperation($artifact->operationIdentity(), $handoff->applyPlan()->identity());
            if ($operation->operationId() !== $artifact->operationIdentity()) $diagnostics[] = 'Lifecycle operation identity does not match bootstrap operation.';
            if ($operation->recoveryIdentity() !== $recovery->value()) $diagnostics[] = 'Lifecycle operation is not bound to the canonical derived recovery identity.';
            if ($operation->applyPlanIdentity() !== $handoff->applyPlan()->identity() || $operation->archiveSha256() !== $handoff->target()->archiveIdentity() || $operation->payloadIdentity() !== $handoff->target()->payloadIdentity()) $diagnostics[] = 'Lifecycle operation package or apply-plan identity drifted.';
            if ($operation->targetWebcoreVersion() !== $handoff->target()->contract()->targetWebcoreVersion() || $operation->releaseIdentity() !== $handoff->target()->contract()->releaseIdentity()) $diagnostics[] = 'Lifecycle operation target release identity drifted.';
            if (!$manifest->recoveryIdentity()->equals($recovery) || $manifest->operationIdentity() !== $artifact->operationIdentity() || $manifest->targetPackageIdentity() !== $handoff->target()->packageIdentity() || $manifest->targetReleaseIdentity() !== $handoff->target()->contract()->releaseIdentity() || $manifest->archiveIdentity() !== $handoff->target()->archiveIdentity() || $manifest->applyPlanIdentity() !== $handoff->applyPlan()->identity()) $diagnostics[] = 'Recovery manifest is not bound to the canonical bootstrap target.';
            if ($databaseScope->installationIdentity() !== $artifact->installationIdentity() || $application->scope()->identity() !== $databaseScope->identity() || $database->scope()->identity() !== $databaseScope->identity()) $diagnostics[] = 'Database scope or installation identity drifted.';
            if (!$application->operationIdentity()->equals($recovery) || !$database->operationIdentity()->equals($recovery)) $diagnostics[] = 'Scoped evidence is not bound to the derived recovery identity.';
            if (!$application->isValidAt() || !$database->isValidAt()) $diagnostics[] = 'Scoped protection evidence is expired.';
            $domains = $this->domains($manifest);
            $expectedDomains = ['database.webcore', 'filesystem.lifecycle.committed', 'filesystem.lifecycle.installed-lock', 'filesystem.package-owned'];
            $actualDomains = array_keys($domains);
            sort($actualDomains, SORT_STRING);
            if ($actualDomains !== $expectedDomains) $diagnostics[] = 'Recovery manifest domain set is incomplete or contains an unexpected owner.';
            if (($domains['database.webcore'] ?? null) !== $databaseScope->identity()) $diagnostics[] = 'Recovery database domain is not bound to the expected scope.';
            if (($domains['filesystem.package-owned'] ?? null) !== $handoff->applyPlan()->identity()) $diagnostics[] = 'Recovery filesystem domain is not bound to the apply plan.';
            if (($domains['filesystem.lifecycle.committed'] ?? null) !== $deploymentIdentity || ($domains['filesystem.lifecycle.installed-lock'] ?? null) !== $artifact->installationIdentity()) $diagnostics[] = 'Recovery lifecycle domains are not bound to the expected installation/deployment.';
            $crossReference = new RecoveryIdentityCrossReference($artifact->operationIdentity(), $recovery, $artifact->installationIdentity(), $handoff->target()->packageIdentity(), $handoff->applyPlan()->identity(), $databaseScope, $this->targetIdentity($handoff));
            if ($diagnostics !== []) return $this->result(LegacyBootstrapRecoveryIdentityCrossReferenceResult::INCOMPLETE, $handoff, $crossReference, $manifest, $application, $database, $diagnostics);
            return $this->result(LegacyBootstrapRecoveryIdentityCrossReferenceResult::VALID, $handoff, $crossReference, $manifest, $application, $database, []);
        } catch (\Throwable $exception) {
            return $this->result(LegacyBootstrapRecoveryIdentityCrossReferenceResult::UNVERIFIED, $handoff, null, $manifest, $application, $database, ['Canonical recovery cross-reference could not be reconstructed: ' . $exception->getMessage()]);
        }
    }

    /** @return array<string,string> */
    private function domains(RecoveryManifest $manifest): array
    {
        $domains = [];
        foreach ($manifest->domainIdentities() as $domain) {
            if (!$domain instanceof RecoveryDomainIdentity || isset($domains[$domain->identifier()])) throw new \RuntimeException('Recovery manifest domain identity is duplicate or invalid.');
            $domains[$domain->identifier()] = $domain->scopeIdentity();
        }
        return $domains;
    }

    private function targetIdentity(LegacyBootstrapIdentityHandoff $handoff): string
    {
        return hash('sha256', json_encode(['package_identity' => $handoff->target()->packageIdentity(), 'target_inventory_fingerprint' => $handoff->artifact()->targetInventoryFingerprint()], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function result(string $status, LegacyBootstrapIdentityHandoff $handoff, ?RecoveryIdentityCrossReference $crossReference, RecoveryManifest $manifest, ApplicationWriteProtectionEvidence $application, ScopedDatabaseConsistencyEvidence $database, array $diagnostics): LegacyBootstrapRecoveryIdentityCrossReferenceResult
    {
        return new LegacyBootstrapRecoveryIdentityCrossReferenceResult($status, $handoff->identity(), $handoff->artifact()->operationIdentity(), $crossReference, $manifest->identity(), $application->identity(), $database->identity(), $diagnostics);
    }
}
