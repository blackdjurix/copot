<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\RecoveryIdentity;

/**
 * Bootstrap-only adapter over the canonical planner. It deliberately permits
 * only full-coverage delivery, where no provisional source ownership is used.
 */
final class LegacyBootstrapV3ExecutionPlanner
{
    public function __construct(private PackageV3SourceRelativePlanner $planner) {}

    public function compose(PackageManifest $manifest, LegacyBootstrapIdentityHandoff $handoff, LifecycleOperationRecord $operation, RecoveryIdentity $recovery, LegacyBootstrapRecoveryBindingResult $binding): LegacyBootstrapV3ExecutionContext
    {
        $package = $manifest->contract();
        if ($package->manifestContractVersion() !== PackageContract::V3_MANIFEST_CONTRACT_VERSION || $package->coverage() !== 'full' || $package->removals() !== [] || $package->renames() !== []) {
            throw new \RuntimeException('Bootstrap execution requires a full package without source-dependent removal or rename declarations.');
        }
        if ($manifest->payload()->archiveSha256() !== $handoff->target()->archiveIdentity() || WebcoreApplyPlan::fromPayload($manifest->payload())->identity() !== $handoff->applyPlan()->identity()) {
            throw new \RuntimeException('Bootstrap package/apply-plan identity changed before execution.');
        }
        if ($operation->phase() !== LifecycleOperationRecord::PREPARING && $operation->phase() !== LifecycleOperationRecord::BLOCKED) {
            throw new \RuntimeException('Bootstrap operation is not at an executable pre-mutation phase.');
        }
        $plan = $this->planner->plan($manifest);
        if ($plan->targetFingerprint() !== $handoff->artifact()->targetInventoryFingerprint() || $plan->sourceReference() !== null) {
            throw new \RuntimeException('Bootstrap planner produced incompatible source or target evidence.');
        }
        return new LegacyBootstrapV3ExecutionContext($handoff->artifact()->installationIdentity(), $operation, $handoff, $plan, $recovery, $binding);
    }
}
