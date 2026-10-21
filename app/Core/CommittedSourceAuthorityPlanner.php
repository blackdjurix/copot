<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\DatabaseScope;

/** Composes canonical planning identities without persisting or executing them. */
final class CommittedSourceAuthorityPlanner
{
    public function plan(
        CommittedLifecycleState $committed,
        InstallationIdentity $installation,
        PackageManifest $manifest,
        CanonicalTargetInventory $actualTarget,
        DatabaseScope $databaseScope,
        InstallerOwnershipProof $ownershipProof
    ): CommittedSourceAuthorityPlan {
        $eligibility = (new CommittedSourceAuthorityEligibility())->evaluate($committed, $installation, $manifest, $actualTarget, $databaseScope, $ownershipProof);
        if (!$eligibility->eligible()) {
            throw new \RuntimeException($eligibility->reason());
        }
        $sourcePlan = (new PackageV3SourceRelativePlanner())->plan($manifest);
        $applyPlan = WebcoreApplyPlan::fromPayload($manifest->payload());
        $operationId = 'source-authority-reconciliation-sha256:' . hash('sha256', json_encode([
            'operation_type' => 'COMMITTED_SOURCE_AUTHORITY_RECONCILIATION',
            'installation_identity' => $installation->value(),
            'manifest_identity' => $manifest->packageEvidenceIdentity(),
            'target_inventory_fingerprint' => $actualTarget->fingerprint(),
            'database_scope_identity' => $databaseScope->identity(),
            'apply_plan_identity' => $applyPlan->identity(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $now = gmdate(DATE_ATOM);
        $operation = new LifecycleOperationRecord(
            $operationId,
            'COMMITTED_SOURCE_AUTHORITY_RECONCILIATION',
            $committed->webcoreVersion(),
            $committed->releaseIdentity(),
            $manifest->payload()->archiveSha256(),
            $manifest->payload()->stagingPath(),
            $applyPlan->identity(),
            $applyPlan->identity(),
            LifecycleOperationRecord::PREPARING,
            0,
            null,
            null,
            null,
            $now,
            $now,
            'Authority-only source inventory reconciliation is planned; execution and WU6 are pending.'
        );
        $committedLifecycleIdentity = hash('sha256', json_encode($committed->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $identity = hash('sha256', json_encode([
            'plan_scheme' => 'committed-source-authority-reconciliation-v1',
            'operation_identity' => $operationId,
            'source_plan_identity' => $sourcePlan->identity(),
            'apply_plan_identity' => $applyPlan->identity(),
            'database_scope_identity' => $databaseScope->identity(),
            'target_inventory_fingerprint' => $actualTarget->fingerprint(),
            'authority_only' => true,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return new CommittedSourceAuthorityPlan($identity, $operation, $sourcePlan, $applyPlan, $databaseScope, true, $committedLifecycleIdentity, $committed->migrationStateIdentity());
    }
}
