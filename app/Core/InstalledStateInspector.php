<?php

namespace Copot\Core;

use DateTimeImmutable;

final class InstalledStateInspector
{
    public function inspect(InstallationState $state, ExistingInstallEvidence $evidence): InstalledStateInspection
    {
        try {
            $marker = $state->readMarker();
        } catch (InstallationException $exception) {
            return InstalledStateInspection::invalid($exception->getMessage());
        }

        if ($marker === null) {
            return $evidence->hasMaterialInstallationEvidence()
                ? InstalledStateInspection::inconsistent('Installation evidence exists without an installation marker.')
                : InstalledStateInspection::fresh();
        }

        return InstalledStateInspection::legacy(new InstalledStateSnapshot(
            $marker['version'],
            new DateTimeImmutable($marker['installed_at'])
        ));
    }
}
