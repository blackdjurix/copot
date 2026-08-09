<?php

namespace Copot\Core;

final class InstallerRoutingPlanner
{
    public const FRESH = 'fresh';
    public const COEXIST = 'coexist';
    public const ADOPT = 'adopt';
    public const MIGRATE = 'migrate';

    public function __construct(private ?InstallerNamespaceAnalyzer $namespaces = null) { $this->namespaces ??= new InstallerNamespaceAnalyzer(); }

    public function plan(InstallerDatabaseOccupancyResult $occupancy, string $intent, ?string $requestedNamespace = null): InstallerRoutingPlan
    {
        if (!in_array($intent, InstallerIntent::all(), true)) throw new InstallationException('Installer intent is invalid.');
        $namespace = $requestedNamespace ?? ($occupancy->copotNamespaces()[0] ?? '');
        new DatabaseTableNames($namespace);
        if (in_array($occupancy->classification(), [InstallerDatabaseOccupancy::AMBIGUOUS], true)) {
            throw new InstallationException('Database ownership evidence is ambiguous; installation is blocked.');
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
            if ($intent === InstallerIntent::COEXIST && $namespace === '') throw new InstallationException('Coexistence requires an explicit non-empty namespace.');
            return new InstallerRoutingPlan($intent, $namespace, self::FRESH);
        }
        if ($intent !== InstallerIntent::COEXIST) throw new InstallationException('Non-empty database requires explicit coexistence intent.');
        if ($namespace === '') throw new InstallationException('Unsafe empty-namespace coexistence is blocked.');
        $availability = $this->namespaces->analyze($occupancy->objects(), $namespace, $occupancy)->availability();
        if ($availability !== InstallerNamespaceAvailability::AVAILABLE) throw new InstallationException('Selected coexistence namespace is not available.');
        return new InstallerRoutingPlan($intent, $namespace, self::COEXIST);
    }
}
