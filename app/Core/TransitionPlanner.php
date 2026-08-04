<?php

namespace Copot\Core;

final class TransitionPlanner
{
    public function __construct(private string $canonicalCurrentVersion = Version::CURRENT)
    {
        PackageVersion::assertValid($this->canonicalCurrentVersion);
    }

    public function plan(
        InstalledStateInspection $installed,
        PackageContract $package,
        RuntimeCompatibilityContext $runtime
    ): TransitionPlan {
        if (!$runtime->supports($package->runtimeCompatibility())) {
            return TransitionPlan::rejected(TransitionPlan::REJECTED, 'Runtime requirements are not satisfied.', $package);
        }

        if ($installed->status() === InstalledStateStatus::FRESH) {
            if (PackageVersion::compare($package->targetWebcoreVersion(), $this->canonicalCurrentVersion) !== 0) {
                return TransitionPlan::rejected(TransitionPlan::REJECTED, 'Fresh installation requires the canonical current target.', $package);
            }

            return TransitionPlan::allow(TransitionPlan::INSTALL, $package);
        }

        if ($installed->status() === InstalledStateStatus::LEGACY) {
            return TransitionPlan::rejected(TransitionPlan::BOOTSTRAP_REQUIRED, 'Legacy installed state requires explicit lifecycle bootstrap.', $package);
        }

        if ($installed->status() !== InstalledStateStatus::COMMITTED || $installed->snapshot() === null) {
            return TransitionPlan::rejected(TransitionPlan::REJECTED, 'Installed state is not transitionable.', $package);
        }

        $snapshot = $installed->snapshot();

        if (!$snapshot->hasLifecycleIdentity()) {
            return TransitionPlan::rejected(TransitionPlan::REJECTED, 'Committed state lacks required lifecycle identity.', $package, $snapshot);
        }

        if (!$package->sourceCompatibility()->supports($snapshot->webcoreVersion())) {
            return TransitionPlan::rejected(TransitionPlan::REJECTED, 'Package does not support the installed Webcore version.', $package, $snapshot);
        }

        $comparison = PackageVersion::compare($package->targetWebcoreVersion(), $snapshot->webcoreVersion());

        if ($comparison === 0) {
            return TransitionPlan::allow(TransitionPlan::REPAIR, $package, $snapshot);
        }

        if ($comparison < 0) {
            return TransitionPlan::rejected(TransitionPlan::REJECTED, 'Downgrade and reverse transitions are unsupported.', $package, $snapshot);
        }

        $target = PackageVersion::coreComponents($package->targetWebcoreVersion());
        $current = PackageVersion::coreComponents($snapshot->webcoreVersion());

        if ($target['major'] === $current['major'] && $target['minor'] === $current['minor']) {
            return TransitionPlan::allow(TransitionPlan::PATCH, $package, $snapshot);
        }

        if ($target['major'] === $current['major'] && $target['minor'] > $current['minor']) {
            return TransitionPlan::allow(TransitionPlan::UPDATE, $package, $snapshot);
        }

        if ($target['major'] > $current['major']) {
            return TransitionPlan::allow(TransitionPlan::UPGRADE, $package, $snapshot);
        }

        return TransitionPlan::rejected(TransitionPlan::REJECTED, 'Forward version relation has no supported lifecycle classification.', $package);
    }
}
