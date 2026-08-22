<?php

namespace Copot\Core;

class InstallerFinalizer
{
    private const REQUIRED_SETTINGS = [
        ['site', 'name'],
        ['site', 'tagline'],
        ['localization', 'timezone'],
        ['localization', 'locale'],
    ];

    public function __construct(
        private Database $database,
        private InstallerSchemaState $schema,
        private SettingsService $settings,
        private SettingsRepository $settingsRepository,
        private ThemeDiscovery $themeDiscovery,
        private ThemeManager $themes,
        private ModuleManager $modules,
        private InstallationState $installationState,
        private CommittedLifecycleStateStore $committedLifecycleState,
        private InstallationMutex $mutex
    ) {
    }

    public function finalize(): array
    {
        $lock = $this->mutex->acquire();

        if (!$lock instanceof InstallationLock) {
            throw new InstallationException('Another installation process is already running.');
        }

        try {
            return $this->finalizePrepared();
        } finally {
            $lock->release();
        }
    }

    /** Execute finalization while the caller owns the installation lock. */
    public function finalizePrepared(): array
    {
        if ($this->installationState->isInstalled()) {
            throw new InstallationException('Installation has already been finalized.');
        }
        if (!$this->schema->isReady()) {
            throw new InstallationException('Database schema is not ready.');
        }
        $this->validateFirstAdministrator();
        $this->validateInitialSettings();
        $this->committedLifecycleState->commit(
            $this->installationState,
            new CommittedLifecycleState(
                Version::CURRENT,
                'copot-v' . Version::CURRENT,
                null,
                PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION,
                'canonical-current',
                CoreMigrationStateIdentity::fromRecords([]),
                new \DateTimeImmutable('now')
            )
        );
        return ['version' => Version::CURRENT, 'theme' => null, 'modules' => []];
    }

    private function validateFirstAdministrator(): void
    {
        $userCount = (int) $this->database->connection()
            ->query('SELECT COUNT(*) FROM ' . $this->database->table('users'))
            ->fetchColumn();

        if ($userCount !== 1) {
            throw new InstallationException('Exactly one first administrator is required.');
        }

        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(*)
            FROM ' . $this->database->table('users') . ' users
            INNER JOIN ' . $this->database->table('user_roles') . ' user_roles ON user_roles.user_id = users.id
            INNER JOIN ' . $this->database->table('roles') . ' roles ON roles.id = user_roles.role_id
            WHERE roles.slug = :role AND users.status = :status'
        );
        $statement->execute([
            'role' => 'admin',
            'status' => 'active',
        ]);

        if ((int) $statement->fetchColumn() !== 1) {
            throw new InstallationException('The first administrator is not ready.');
        }
    }

    private function validateInitialSettings(): void
    {
        foreach (self::REQUIRED_SETTINGS as [$namespace, $key]) {
            $override = $this->settingsRepository->findOverride($namespace, $key);

            if (
                !is_array($override)
                || ($override['value_type'] ?? null) !== 'string'
                || !isset($override['setting_value'])
                || !is_string($override['setting_value'])
            ) {
                throw new InstallationException('Initial settings are not ready.');
            }

            try {
                $this->settings->validate($namespace, $key, $override['setting_value'], 'string');
            } catch (SettingsException) {
                throw new InstallationException('Initial settings are not valid.');
            }
        }
    }

}
