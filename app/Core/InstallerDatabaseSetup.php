<?php

namespace Copot\Core;

class InstallerDatabaseSetup
{
    public function __construct(
        private InstallerDatabaseProbe $probe,
        private InstallerEnvironmentWriter $environment,
        private InstallerSchemaRunner $schema,
        private InstallationMutex $mutex
    ) {
    }

    /** @param list<InstallerOwnershipProof> $ownershipProofs */
    public function install(array $configuration, bool $requirementsPassed, string $intent = InstallerIntent::FRESH, array $ownershipProofs = []): array
    {
        if (!$requirementsPassed) {
            throw new InstallationException('Installer requirements are not satisfied.');
        }

        $lock = $this->mutex->acquire();

        if (!$lock instanceof InstallationLock) {
            throw new InstallationException('Another installation process is already running.');
        }

        try {
            $inspection = $this->probe->inspect($configuration, $ownershipProofs);
            $server = $inspection['server'];
            $requestedNamespace = array_key_exists('namespace', $configuration) ? (string) $configuration['namespace'] : null;
            $routing = (new InstallerRoutingPlanner())->plan($inspection['occupancy'], $intent, $requestedNamespace);
            $configuration['namespace'] = $routing->namespace();
            $this->environment->persist($configuration);
            $statementCount = $routing->route() === InstallerRoutingPlanner::ADOPT
                ? 0
                : $this->schema->install($configuration);

            return [
                'server' => $server,
                'statement_count' => $statementCount,
                'occupancy' => $inspection['occupancy']->classification(),
                'route' => $routing->route(),
                'intent' => $routing->intent(),
                'namespace' => $routing->namespace(),
                'warnings' => $routing->warnings(),
            ];
        } finally {
            $lock->release();
        }
    }
}
