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

    public function install(array $configuration, bool $requirementsPassed): array
    {
        if (!$requirementsPassed) {
            throw new InstallationException('Installer requirements are not satisfied.');
        }

        $lock = $this->mutex->acquire();

        if (!$lock instanceof InstallationLock) {
            throw new InstallationException('Another installation process is already running.');
        }

        try {
            $server = $this->probe->test($configuration);
            $this->environment->persist($configuration);
            $statementCount = $this->schema->install($configuration);

            return [
                'server' => $server,
                'statement_count' => $statementCount,
            ];
        } finally {
            $lock->release();
        }
    }
}
