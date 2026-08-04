<?php

namespace Copot\Core;

use DateTimeImmutable;

final class InstalledStateInspector
{
    public function __construct(private ?CommittedLifecycleStateStore $committedStore = null)
    {
    }

    public function inspect(InstallationState $state, ExistingInstallEvidence $evidence): InstalledStateInspection
    {
        try {
            $marker = $state->readMarker();
        } catch (InstallationException $exception) {
            return InstalledStateInspection::invalid($exception->getMessage());
        }

        try {
            $committed = $this->committedStore?->read();
        } catch (\Throwable $exception) {
            return InstalledStateInspection::invalid($exception->getMessage());
        }

        if ($marker === null) {
            if ($committed !== null) {
                return InstalledStateInspection::inconsistent('Committed lifecycle state exists without an installation marker.');
            }
            return $evidence->hasMaterialInstallationEvidence()
                ? InstalledStateInspection::inconsistent('Installation evidence exists without an installation marker.')
                : InstalledStateInspection::fresh();
        }

        if ($committed !== null) {
            if ($committed->webcoreVersion() !== $marker['version']
                || $committed->committedAt()->format(DATE_ATOM) !== $marker['installed_at']) {
                return InstalledStateInspection::inconsistent('Committed lifecycle state contradicts the installation marker.');
            }

            return InstalledStateInspection::committed($committed->snapshot());
        }

        return InstalledStateInspection::legacy(new InstalledStateSnapshot(
            $marker['version'],
            new DateTimeImmutable($marker['installed_at'])
        ));
    }
}
