<?php

namespace Copot\Core;

final class ModuleTransitionPlanner
{
    public function __construct(private CommittedLifecycleStateStore $webcoreStore, private RuntimeCompatibilityContext $runtime) {}

    public function plan(ModuleLifecycleInspection $installed, ModuleLifecycleTarget $target): ModuleTransitionPlan
    {
        $contract = $target->contract();
        try {
            $webcore = $this->webcoreStore->read();
            if ($webcore === null) return ModuleTransitionPlan::reject(ModuleTransitionPlan::REJECTED, 'Committed Webcore lifecycle state is unavailable.', $target);
            if (!$contract->supportsCommittedWebcoreVersion($webcore->webcoreVersion())) return ModuleTransitionPlan::reject(ModuleTransitionPlan::REJECTED, 'Module package does not support the committed Webcore version.', $target);
            if ($contract->runtimeRequirements() !== null && !$this->runtime->supports($contract->runtimeRequirements())) return ModuleTransitionPlan::reject(ModuleTransitionPlan::REJECTED, 'Module package runtime requirements are not satisfied.', $target);
        } catch (\Throwable $exception) { return ModuleTransitionPlan::reject(ModuleTransitionPlan::REJECTED, $exception->getMessage(), $target); }

        if ($installed->status() === InstalledStateStatus::FRESH) return ModuleTransitionPlan::allow(ModuleTransitionPlan::INSTALL, $target, null, false);
        if ($installed->status() === InstalledStateStatus::LEGACY) return ModuleTransitionPlan::reject(ModuleTransitionPlan::LEGACY_BLOCKED, 'Legacy Module installation requires explicit lifecycle bootstrap.', $target);
        if ($installed->status() !== InstalledStateStatus::COMMITTED || !$installed->state() instanceof ModuleLifecycleState) return ModuleTransitionPlan::reject(ModuleTransitionPlan::REJECTED, 'Module lifecycle state is not transitionable.', $target);
        $state = $installed->state();
        if ($state->moduleIdentity()->value() !== $contract->moduleIdentity()->value()) return ModuleTransitionPlan::reject(ModuleTransitionPlan::REJECTED, 'Technical Module identity rebinding is unsupported.', $target, $state);
        if (!$state->packageIdentity()->equals($contract->packageIdentity()->value())) return ModuleTransitionPlan::reject(ModuleTransitionPlan::REJECTED, 'Package identity rebinding is unsupported.', $target, $state);
        if ($state->manifestContractVersion() !== $contract->contractVersion()) return ModuleTransitionPlan::reject(ModuleTransitionPlan::REJECTED, 'Module package contract version does not match committed lifecycle state.', $target, $state);
        $comparison = PackageVersion::compare($contract->packageVersion(), $state->packageVersion());
        if ($comparison < 0) return ModuleTransitionPlan::reject(ModuleTransitionPlan::REJECTED, 'Downgrade and reverse transitions are unsupported.', $target, $state);
        if ($comparison === 0) return ModuleTransitionPlan::allow(ModuleTransitionPlan::REPAIR, $target, $state, $state->enabled());
        $targetVersion = PackageVersion::coreComponents($contract->packageVersion()); $currentVersion = PackageVersion::coreComponents($state->packageVersion());
        if ($targetVersion['major'] === $currentVersion['major'] && $targetVersion['minor'] === $currentVersion['minor']) $classification = ModuleTransitionPlan::PATCH;
        elseif ($targetVersion['major'] === $currentVersion['major'] && $targetVersion['minor'] > $currentVersion['minor']) $classification = ModuleTransitionPlan::UPDATE;
        elseif ($targetVersion['major'] > $currentVersion['major']) $classification = ModuleTransitionPlan::UPGRADE;
        else return ModuleTransitionPlan::reject(ModuleTransitionPlan::REJECTED, 'Forward version relation has no supported lifecycle classification.', $target, $state);
        return ModuleTransitionPlan::allow($classification, $target, $state, $state->enabled());
    }
}
