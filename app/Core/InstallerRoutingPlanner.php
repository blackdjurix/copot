<?php

namespace Copot\Core;

final class InstallerRoutingPlanner
{
    public const FRESH = 'fresh';
    public const COEXIST = 'coexist';
    public const ADOPT = 'adopt';
    public const MIGRATE = 'migrate';

    public function __construct(private ?InstallerNamespaceAnalyzer $namespaces = null) { $this->namespaces ??= new InstallerNamespaceAnalyzer(); }

    /** @return list<string> */
    public function eligibleIntents(InstallerDatabaseOccupancyResult $occupancy): array
    {
        if ($occupancy->classification() === InstallerDatabaseOccupancy::EMPTY) {
            return [InstallerIntent::FRESH];
        }

        $eligible = [InstallerIntent::COEXIST];
        if (
            $occupancy->classification() === InstallerDatabaseOccupancy::COPOT
            && $occupancy->hasVerifiedOwnershipEvidence()
            && count($occupancy->copotNamespaces()) === 1
        ) {
            $eligible[] = InstallerIntent::ADOPT;
            $eligible[] = InstallerIntent::MIGRATE;
        }

        return $eligible;
    }

    public function plan(InstallerDatabaseOccupancyResult $occupancy, string $intent, ?string $requestedNamespace = null): InstallerRoutingPlan
    {
        if (!in_array($intent, InstallerIntent::all(), true)) throw new InstallationException('Installer intent is invalid.');
        $namespace = $requestedNamespace ?? ($occupancy->copotNamespaces()[0] ?? '');
        new DatabaseTableNames($namespace);
        if (!in_array($intent, $this->eligibleIntents($occupancy), true)) {
            throw new InstallationException('Selected installer intent is not eligible for the inspected Database.');
        }
        if (in_array($intent, [InstallerIntent::ADOPT, InstallerIntent::MIGRATE], true)) {
            if ($occupancy->classification() !== InstallerDatabaseOccupancy::COPOT || count($occupancy->copotNamespaces()) !== 1) {
                throw new InstallationException('Adoption or migration requires exactly one proven COPOT installation.');
            }
            if ($requestedNamespace !== null && $requestedNamespace !== $occupancy->copotNamespaces()[0]) {
                throw new InstallationException('Existing-installation routing must preserve the detected namespace.');
            }
            return new InstallerRoutingPlan($intent, $occupancy->copotNamespaces()[0], $intent === InstallerIntent::ADOPT ? self::ADOPT : self::MIGRATE);
        }
        if ($occupancy->classification() === InstallerDatabaseOccupancy::EMPTY) {
            $availability = $this->namespaces->analyze($occupancy->objects(), $namespace, $occupancy)->availability();
            if ($availability !== InstallerNamespaceAvailability::AVAILABLE) throw new InstallationException('Selected namespace is not available.');
            return new InstallerRoutingPlan($intent, $namespace, self::FRESH);
        }
        if ($intent !== InstallerIntent::COEXIST) throw new InstallationException('Non-empty database requires new independent installation intent.');
        $availability = $this->namespaces->analyze($occupancy->objects(), $namespace, $occupancy)->availability();
        if ($availability !== InstallerNamespaceAvailability::AVAILABLE) throw new InstallationException('Selected coexistence namespace is not available.');
        return new InstallerRoutingPlan($intent, $namespace, self::COEXIST);
    }
}
